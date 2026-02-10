<?php

declare(strict_types=1);

namespace App\CMSVC\Myapplication;

use App\CMSVC\Application\ApplicationModel;
use App\CMSVC\Conversation\ConversationService;
use App\CMSVC\Group\GroupService;
use App\CMSVC\Message\MessageService;
use App\CMSVC\Trait\ApplicationServiceTrait;
use App\CMSVC\Transaction\{TransactionModel, TransactionService};
use App\Helper\{MessageHelper, RightsHelper, TextHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Element\{Item};
use Fraym\Entity\{PostChange, PostCreate, PostDelete, PreChange, PreCreate, PreDelete};
use Fraym\Enum\{ActEnum, ActionEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, EmailHelper, LocaleHelper, ResponseHelper};
use Fraym\Interface\Response;

/** @extends BaseService<ApplicationModel> */
#[Controller(MyapplicationController::class)]
#[PostChange]
#[PostCreate]
#[PostDelete]
#[PreChange]
#[PreCreate]
#[PreDelete]
class MyapplicationService extends BaseService
{
    use ApplicationServiceTrait;

    private ?bool $letToApply = null;
    private ?array $gamemastersList = null;
    /** @var string[] */
    private array $gamemastersAllinfoData = [];
    private ?array $oldApplicationData = null;

    public function getAddApplicationProjectsListData(): false|array
    {
        return DB->query(
            "SELECT p.id, p.name, p.oneorderfromplayer, p.date_from, p.date_to, p.external_link, p.attachments, MAX(paf.id) AS individual_field_id, MAX(paf2.id) AS team_field_id, MAX(pg.id) AS group_id FROM project p LEFT JOIN project_application_field paf ON paf.project_id=p.id AND paf.application_type='0' LEFT JOIN project_application_field paf2 ON paf2.project_id=p.id AND paf2.application_type='1' LEFT JOIN project_group pg ON pg.project_id=p.id AND (pg.rights=0 OR pg.rights=1) WHERE p.status='1' AND p.date_to>=:date_to AND (paf.id IS NOT NULL OR paf2.id IS NOT NULL) GROUP BY p.id ORDER BY p.name ASC",
            [
                ['date_to', date("Y-m-d")],
            ],
        );
    }

    public function createTransaction(): ?Response
    {
        $LOCALE = $this->LOCALE;

        $applicationData = $this->getApplicationData((int) $_REQUEST['project_application_id_hidden'][0]);
        $projectData = $this->getProjectData($applicationData['project_id']);

        $paymentDatetime = $_REQUEST['payment_datetime'][0] ?? null;
        $paymentAmount = (int) ($_REQUEST['amount'][0] ?? 0);
        $projectPaymentTypeId = (int) ($_REQUEST['project_payment_type_id'][0] ?? 0);
        $paymentContent = $_REQUEST['content'][0] ?? false;

        $errorFields = [];

        if ($projectPaymentTypeId === 0) {
            $errorFields[] = 'project_payment_type_id[0]';
        }

        if ($paymentAmount === 0) {
            $errorFields[] = 'amount[0]';
        }

        if ($projectData->show_datetime_in_transaction->get() && !$paymentDatetime) {
            $errorFields[] = 'payment_datetime[0]';
        }

        if ($errorFields) {
            return ResponseHelper::response([], null, $errorFields);
        } else {
            /** @var TransactionService */
            $transactionService = CMSVCHelper::getService('transaction');

            $result = $transactionService->createTransaction(
                $projectData->id->getAsInt(),
                $applicationData['id'],
                $projectPaymentTypeId,
                $LOCALE['fee_payment'],
                $paymentAmount,
                false,
                $paymentContent,
                $paymentDatetime,
            );

            if ($result) {
                DB->update(
                    tableName: 'project_application',
                    data: [
                        'money_need_approve' => '1',
                    ],
                    criteria: [
                        'id' => $applicationData['id'],
                    ],
                );

                $projectPaymentTypeData = DB->findObjectById($projectPaymentTypeId, 'project_payment_type');

                /** @var TransactionModel */
                $transactionModel = $transactionService->model;

                $message = "**" . $LOCALE['fee_payment'] . "**
                    
                    " . "__" . $transactionModel->amount->shownName . "__: " . $paymentAmount . "
                    " .
                    "__" . $transactionModel->project_payment_type_id->shownName . "__: " . DataHelper::escapeOutput($projectPaymentTypeData['name']) .
                    ($projectData->show_datetime_in_transaction->get() ? "
                    __" . $transactionModel->payment_datetime->shownName . "__: " . date('d.m.Y H:i', strtotime($paymentDatetime)) : "") .
                    ($paymentContent ? "
                    
                    " . $paymentContent : "");

                /** @var MessageService */
                $messageService = CMSVCHelper::getService('message');

                $cmId = $messageService->newMessage(
                    null,
                    $message,
                    '',
                    [],
                    [],
                    [
                        'obj_type' => '{project_application_conversation}',
                        'obj_id' => $applicationData['id'],
                        'sub_obj_type' => '{from_player}',
                    ],
                    '{fee_payment}',
                    '{project_transaction_id: ' . $result . '}',
                );

                if ($cmId > 0) {
                    DB->update(
                        tableName: 'project_transaction',
                        data: [
                            'conversation_message_id' => $cmId,
                        ],
                        criteria: [
                            'id' => $result,
                        ],
                    );
                }

                $checkPmPaymentType = DB->select(
                    tableName: 'project_payment_type',
                    criteria: [
                        'pm_type' => '1',
                        'project_id' => $projectData->id->get(),
                    ],
                    oneResult: true,
                );

                $checkYkPaymentType = DB->select(
                    tableName: 'project_payment_type',
                    criteria: [
                        'yk_type' => '1',
                        'project_id' => $projectData->id->get(),
                    ],
                    oneResult: true,
                );

                $checkPawPaymentType = DB->select(
                    tableName: 'project_payment_type',
                    criteria: [
                        'paw_type' => '1',
                        'project_id' => $projectData->id->get(),
                    ],
                    oneResult: true,
                );

                $checkPkPaymentType = DB->select(
                    tableName: 'project_payment_type',
                    criteria: [
                        'pk_type' => '1',
                        'project_id' => $projectData->id->get(),
                    ],
                    oneResult: true,
                );

                $redirectTo = null;

                if (($checkPkPaymentType['id'] ?? null) === $projectPaymentTypeData['id']) {
                    //оплата PayKeeper
                    $serverPaykeeper = $projectData['paykeeper_server'];

                    /* получение токена */
                    $pkToken = false;
                    $pkHeaders = [];
                    $pkHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
                    $pkHeaders[] = 'Authorization: Basic ' . base64_encode(
                        $projectData['paykeeper_login'] . ':' . $projectData['paykeeper_pass'],
                    );

                    $ch = curl_init();

                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_URL, $serverPaykeeper . $_ENV['PK_TOKEN_API_URL']);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $pkHeaders);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    $requestResult = json_decode(curl_exec($ch), true);

                    if (isset($requestResult['token'])) {
                        $pkToken = $requestResult['token'];

                        $orderData = [
                            'pay_amount' => $paymentAmount . '.00',
                            'orderid' => $result,
                            //'orderid' => sprintf($LOCALE['pm_description'], $applicationData['id']),
                            'token' => $pkToken,
                        ];

                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_URL, $serverPaykeeper . $_ENV['PK_PAYMENT_API_URL']);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $pkHeaders);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($orderData));
                        $requestResult = json_decode(curl_exec($ch), true);

                        curl_close($ch);

                        if ($requestResult['invoice_id'] ?? false) {
                            $redirectTo = $serverPaykeeper . sprintf(
                                $_ENV['PK_BILL_API_URL'],
                                $requestResult['invoice_id'],
                            );
                        } else {
                            ResponseHelper::response([['error', $LOCALE['messages']['paykeeper']['invoice_id_error']]], 'stayhere');
                        }
                    } else {
                        curl_close($ch);
                        ResponseHelper::response([['error', $LOCALE['messages']['paykeeper']['token_error']]], 'stayhere');
                    }
                } elseif (($checkPmPaymentType['id'] ?? null) === $projectPaymentTypeData['id']) {
                    //оплата PayMaster
                    $userData = $this->getUserService()->get(CURRENT_USER->id());
                    $orderData = [
                        'LMI_MERCHANT_ID' => $projectData->paymaster_merchant_id->get(),
                        'LMI_PAYMENT_AMOUNT' => $paymentAmount . '.00',
                        'LMI_CURRENCY' => 'RUB',
                        'LMI_PAYMENT_NO' => $result,
                        'LMI_PAYMENT_DESC' => sprintf(
                            $LOCALE['pm_description'],
                            $applicationData['id'],
                        ),
                        'LMI_PAYMENT_NOTIFICATION_URL' => ABSOLUTE_PATH . '/scripts/paymaster/',
                        'LMI_SUCCESS_URL' => ABSOLUTE_PATH . '/myapplication/' . $applicationData['id'] . '/',
                        'LMI_SUCCESS_METHOD' => 'POST',
                        'LMI_FAIL_URL' => ABSOLUTE_PATH . '/myapplication/' . $applicationData['id'] . '/',
                        'LMI_FAIL_METHOD' => 'POST',
                        'LMI_PAYER_PHONE_NUMBER' => ($userData->phone->get() ? str_replace(
                            ['+', '-'],
                            '',
                            $userData->phone->get(),
                        ) : null),
                        'LMI_PAYER_EMAIL' => $userData->em->get(),
                        'LMI_PAYMENT_METHOD' => ($_REQUEST['test_payment'][0] ?? false) === 'on' ?
                            'Test' : (($_REQUEST['pay_by_card'][0] ?? false) === 'on' ? 'BankCard' : 'SBP'),
                    ];

                    $ch = curl_init();
                    curl_setopt(
                        $ch,
                        CURLOPT_URL,
                        $_ENV['PM_PAYMENT_API_URL'] . '?' . http_build_query($orderData),
                    );
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                    $requestResult = curl_exec($ch);
                    $goodRedirect = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

                    if ($goodRedirect) {
                        $redirectTo = $goodRedirect;
                    }

                    curl_close($ch);
                } elseif (($checkYkPaymentType['id'] ?? null) === $projectPaymentTypeData['id']) {
                    //оплата Юkassа
                    $appId = $projectData['yk_acc_id'];
                    $appSecret = $projectData['yk_code'];

                    $fields = [
                        'amount' => [
                            'value' => $paymentAmount . '.00',
                            'currency' => 'RUB',
                        ],
                        'capture' => true,
                        'confirmation' => [
                            'type' => 'redirect',
                            'return_url' => ABSOLUTE_PATH . '/myapplication/' . $applicationData['id'] . '/',
                        ],
                        'description' => sprintf($LOCALE['yk_description'], $applicationData['id']),
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $_ENV['YK_PAYMENT_API_URL']);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_USERPWD, $appId . ':' . $appSecret);
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Idempotence-Key: ' . uniqid('', true),
                    ]);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, DataHelper::jsonFixedEncode($fields));
                    $requestResult = json_decode(curl_exec($ch), true);
                    curl_close($ch);

                    if ($requestResult['id'] ?? false) {
                        //запоминаем id операции от Юkassы
                        $cmData = DB->findObjectById($cmId, 'conversation_message');
                        $messageActionData = mb_substr(
                            DataHelper::escapeOutput($cmData['message_action_data']),
                            0,
                            mb_strlen(DataHelper::escapeOutput($cmData['message_action_data'])) - 1,
                        ) . ', yk_operation_id: ' . $requestResult['id'] . '}';

                        DB->update(
                            tableName: 'conversation_message',
                            data: [
                                'message_action_data' => $messageActionData,
                            ],
                            criteria: [
                                'id' => $cmId,
                            ],
                        );
                    }

                    if ($requestResult['confirmation']['confirmation_url'] ?? false) {
                        //переводим на Юkassу для оплаты
                        $redirectTo = $requestResult['confirmation']['confirmation_url'];
                    }
                } elseif (($checkPawPaymentType['id'] ?? null) === $projectPaymentTypeData['id']) {
                    //оплата PayAnyWay
                    $orderData = [
                        'MNT_ID' => $projectData->paw_mnt_id->get(),
                        'MNT_AMOUNT' => $paymentAmount . '.00',
                        'MNT_TRANSACTION_ID' => $result,
                        'MNT_DESCRIPTION' => sprintf(
                            $LOCALE['paw_description'],
                            $applicationData['id'],
                        ),
                        'MNT_SUBSCRIBER_ID' => CURRENT_USER->sid(),
                        'MNT_SUCCESS_URL' => ABSOLUTE_PATH . '/myapplication/' . $applicationData['id'] . '/',
                        'paymentSystem.unitId' => (int) $_ENV['PAW_UNIT_ID'],
                        'paymentSystem.limitIds' => (string) $_ENV['PAW_UNIT_ID'],
                    ];
                    $orderData['MNT_SIGNATURE'] = md5(
                        $orderData['MNT_ID'] . $orderData['MNT_TRANSACTION_ID'] . $orderData['MNT_AMOUNT'] . ($projectData->currency->get() === 'RUR' ? 'RUB' : $projectData->currency->get()) . $orderData['MNT_SUBSCRIBER_ID'] . '0' . $projectData['paw_code'],
                    );
                    $redirectTo = $_ENV['PAW_PAYMENT_FORM'] . '?' . http_build_query($orderData);
                } else {
                    $redirectTo = 'stayhere';
                }

                ResponseHelper::response([['success', $LOCALE['messages']['payment_provided_successfully']]], $redirectTo);
            } else {
                return ResponseHelper::response([]);
            }
        }

        return null;
    }

    public function acceptApplication(int $objId): void
    {
        $LOCALE = $this->LOCALE;

        $applicationData = $this->getApplicationData($objId);

        if ($applicationData['offer_to_user_id'] === CURRENT_USER->id() && !$applicationData['offer_denied']) {
            DB->update(
                tableName: 'project_application',
                data: [
                    'creator_id' => CURRENT_USER->id(),
                    'offer_to_user_id' => '0',
                    'offer_denied' => '0',
                    'deleted_by_player' => '0',
                ],
                criteria: [
                    'id' => $objId,
                ],
            );

            ResponseHelper::success($LOCALE['application_accepted']);

            ResponseHelper::redirect('/myapplication/' . $objId . '/');
        }
    }

    public function declineApplication(int $objId): void
    {
        $LOCALE = $this->LOCALE;

        $applicationData = $this->getApplicationData($objId);

        if ($applicationData['offer_to_user_id'] === CURRENT_USER->id() && !$applicationData['offer_denied']) {
            DB->update(
                tableName: 'project_application',
                data: [
                    'offer_denied' => '1',
                ],
                criteria: [
                    'id' => $objId,
                ],
            );

            ResponseHelper::success($LOCALE['application_declined']);

            ResponseHelper::redirect('/myapplication/');
        }
    }

    /** Приглашение другого игрока к проживанию в одной комнате на проекте */
    public function addNeighboorRequest(int $applicationId, int $userId, int $roomId): array
    {
        $LOCALE_MYAPPLICATION = LocaleHelper::getLocale(['myapplication', 'global']);

        $returnArr = [];

        if ($applicationId > 0 && $userId > 0 && $roomId > 0) {
            // прежде всего проверяем: заявка ли это текущего пользователя?
            $checkApplicationExists = $this->get(
                id: $applicationId,
                criteria: [
                    'creator_id' => CURRENT_USER->id(),
                ],
            );

            if ($checkApplicationExists) {
                // проверяем: выставлена ли указанная комната у данного игрока в его заявке?
                if (!RightsHelper::checkRights('{member}', '{room}', $roomId, '{application}', $applicationId)) {
                    $returnArr = [
                        'response' => 'error',
                        'response_text' => $LOCALE_MYAPPLICATION['messages']['add_neighboor_request']['not_your_room'],
                    ];
                } else {
                    // проверяем: есть ли свободные места в указанной комнате?
                    $roomData = DB->findObjectById($roomId, 'project_room');
                    $membersCount = RightsHelper::findByRights(
                        '{member}',
                        '{room}',
                        $roomId,
                        '{application}',
                        false,
                    );

                    if ($roomData['places_count'] <= count($membersCount)) {
                        $returnArr = [
                            'response' => 'error',
                            'response_text' => $LOCALE_MYAPPLICATION['messages']['add_neighboor_request']['not_enough_places'],
                        ];
                    } else {
                        // проверяем: может ли приглашаемый игрок выбрать эту комнату в рамках дополнительной настройки взносов, если они есть?
                        $checkApplicationExistsInvited = $this->get(
                            criteria: [
                                'project_id' => $checkApplicationExists->project_id->get(),
                                'creator_id' => $userId,
                                'deleted_by_player' => '0',
                                'deleted_by_gamemaster' => '0',
                            ],
                            order: [
                                'updated_at DESC',
                            ],
                        );

                        if ($checkApplicationExistsInvited) {
                            $hasLimitations = false;
                            $allowRoom = false;
                            $playerFees = $checkApplicationExistsInvited->project_fee_ids->get();

                            foreach ($playerFees as $playerFeeId) {
                                $feeDateData = DB->findObjectById($playerFeeId, 'project_fee');
                                $feeDataParent = DB->findObjectById($feeDateData['parent'], 'project_fee');
                                $playerRoomsTypes = DataHelper::multiselectToArray($feeDataParent['project_room_ids']);

                                if (count($playerRoomsTypes) > 0) {
                                    $hasLimitations = true;

                                    if (in_array($roomId, $playerRoomsTypes)) {
                                        $allowRoom = true;
                                    }
                                }
                            }

                            if (!$hasLimitations || $allowRoom) {
                                /** @var ConversationService $conversationService */
                                $conversationService = CMSVCHelper::getService('conversation');

                                $returnArr = $conversationService->sendInvitation('project_room', $roomId, $userId);
                            } else {
                                $returnArr = [
                                    'response' => 'error',
                                    'response_text' => $LOCALE_MYAPPLICATION['messages']['add_neighboor_request']['cannot_be_invited_to_room_type'],
                                ];
                            }
                        }
                    }
                }
            }
        } else {
            $returnArr = [
                'response' => 'error',
                'response_text' => $LOCALE_MYAPPLICATION['messages']['add_neighboor_request']['not_your_room'],
            ];
        }

        return $returnArr;
    }

    /** Получение списка групп персонажа */
    public function getListOfGroups(int $objId, int $prevObjId): array
    {
        $addGroupsList = [];
        $removeGroupsList = [];

        if ($objId > 0) {
            $projectCharacterData = DB->findObjectById($objId, 'project_character');
            $addGroupsList = DataHelper::multiselectToArray($projectCharacterData['project_group_ids']);
        }

        if ($prevObjId > 0) {
            $prevCharacterId = $prevObjId;
            $projectCharacterData = DB->findObjectById($prevCharacterId, 'project_character');
            $removeGroupsList = DataHelper::multiselectToArray($projectCharacterData['project_group_ids']);
        }

        return [
            'response' => 'success',
            'response_data' => [
                'add' => $addGroupsList,
                'remove' => $removeGroupsList,
            ],
        ];
    }

    public function responseIfForProjectIdIsSet(): void
    {
        $forProjectId = $_REQUEST['for_project_id'] ?? null;

        if ($forProjectId) {
            $checkApplicationPresent = $this->get(
                criteria: [
                    'creator_id' => CURRENT_USER->id(),
                    'project_id' => $forProjectId,
                    'deleted_by_player' => '0',
                ],
            );

            $projectData = DB->findObjectById($forProjectId, 'project');
            $characterId = (int) ($_REQUEST['character_id'] ?? false);

            if ($checkApplicationPresent && !($projectData['oneorderfromplayer'] !== '1' && $characterId > 0)) {
                ResponseHelper::redirect(ABSOLUTE_PATH . '/' . KIND . '/' . $checkApplicationPresent->id->get() . '/');
            } else {
                if ($characterId > 0) {
                    $characterData = DB->findObjectById($characterId, 'project_character');
                }

                ResponseHelper::redirect(
                    ABSOLUTE_PATH . '/' . KIND . '/act=add&project_id=' . $forProjectId . '&' . ($characterId > 0 ? 'character_id=' . $characterId . '&application_type=' . (int) $characterData['team_character'] : 'application_type=0'),
                );
            }
        }
    }

    public function preCreate(): void
    {
        $this->getCharacterData((int) ($_REQUEST['project_character_id'][0] ?? null));
    }

    public function postCreate(array $successfulResultsIds): void
    {
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_PROFILE = LocaleHelper::getLocale(['profile']);

        $projectData = $this->getProjectData();
        $projectCharacterData = $this->getCharacterData();
        $projectGroupsData = $this->getProjectGroupsData();
        $projectGroupsDataById = $this->getProjectGroupsDataById();

        foreach ($successfulResultsIds as $successfulResultsId) {
            DB->update(
                tableName: 'project_application',
                data: [
                    'status' => 1,
                    'creator_id' => CURRENT_USER->id(),
                ],
                criteria: [
                    'id' => $successfulResultsId,
                ],
            );

            /** Приводим в норму группы; определяем и устанавливаем ответственного мастера */
            $projectGroupIds = [];

            if ($projectCharacterData) {
                DB->update(
                    tableName: 'project_application',
                    data: [
                        'project_group_ids' => DataHelper::arrayToMultiselect($projectCharacterData->project_group_ids->get()),
                    ],
                    criteria: [
                        'id' => $successfulResultsId,
                    ],
                );
                $projectGroupIds = $projectCharacterData->project_group_ids->get();
            } elseif (count($projectGroupsData)) {
                /** Если у нас не выставлен персонаж, то принудительно выставляем все родительские группы */
                /** @var GroupService */
                $groupService = CMSVCHelper::getService('group');

                $projectGroupIds = $groupService->getGroupsListWithParents($projectGroupsData, $_REQUEST['project_group_ids'][0]);

                DB->update(
                    tableName: 'project_application',
                    data: [
                        'project_group_ids' => DataHelper::arrayToMultiselect($projectGroupIds),
                    ],
                    criteria: [
                        'id' => $successfulResultsId,
                    ],
                );
            }

            $responsibleGamemasterId = 0;

            if ($projectGroupIds) {
                /** Ищем самую глубоко вложенную группу в списке группов в поисках ответственного мастера */
                $foundGroupLevel = -1;

                foreach ($projectGroupIds as $projectGroupId) {
                    $groupInfo = $projectGroupsDataById[$projectGroupId] ?? null;

                    if ($groupInfo && $groupInfo[2]['responsible_gamemaster_id'] > 0 && $foundGroupLevel < $groupInfo[1]) {
                        $responsibleGamemasterId = $groupInfo[2]['responsible_gamemaster_id'];
                        $foundGroupLevel = $groupInfo[1];
                    }
                }
            }

            if ($responsibleGamemasterId === 0) {
                $responsibleGamemasterId = RightsHelper::findOneByRights(
                    ['{admin}', '{gamemaster}'],
                    '{project}',
                    $projectData->id->getAsInt(),
                    '{user}',
                    false,
                );
            }
            DB->update(
                tableName: 'project_application',
                data: [
                    'responsible_gamemaster_id' => $responsibleGamemasterId,
                ],
                criteria: [
                    'id' => $successfulResultsId,
                ],
            );

            $this->updateApplication($successfulResultsId);

            RightsHelper::getAccess('{project}', $projectData->id->getAsInt(), true);

            /** Обновляем данные профиля пользователя, если требуется */
            $userData = $this->getUserService()->get(CURRENT_USER->id());

            foreach ($this->profileFieldsList as $profileField) {
                $fieldData = $_REQUEST[$profileField][0] ?? null;

                if ($fieldData !== $userData->{$profileField}->get()) {
                    if ($fieldData || !$userData->getElement($profileField)->getObligatory()) {
                        if ($profileField === 'em') {
                            $emDoubleVerify = DB->select(
                                tableName: 'user',
                                criteria: [
                                    'em' => $fieldData,
                                    ['id', $userData->id->get(), OperandEnum::NOT_EQUAL],
                                ],
                                oneResult: true,
                            );

                            if (!$emDoubleVerify) {
                                DB->update(
                                    tableName: 'user',
                                    data: [
                                        'em' => $fieldData,
                                        'login' => $fieldData,
                                        'em_verified' => 0,
                                    ],
                                    criteria: [
                                        'id' => $userData->id->get(),
                                    ],
                                );

                                $idToReverify = md5(
                                    $userData->id->get() .
                                        $userData->created_at->getAsTimeStamp() .
                                        $fieldData .
                                        $_ENV['PROJECT_HASH_WORD'],
                                );
                                $text = sprintf(
                                    $LOCALE_PROFILE['global']['verify_em']['base_text'],
                                    $LOCALE_GLOBAL['sitename'],
                                    ABSOLUTE_PATH . '/profile/action=verify_em&verify_id=' . $idToReverify,
                                    ABSOLUTE_PATH . '/profile/action=verify_em&verify_id=' . $idToReverify,
                                );

                                EmailHelper::sendMail(
                                    $LOCALE_GLOBAL['sitename'],
                                    $LOCALE_GLOBAL['admin_mail'],
                                    $fieldData,
                                    sprintf($LOCALE_PROFILE['global']['verify_em']['name'], $LOCALE_GLOBAL['sitename']),
                                    $text,
                                    true,
                                );
                            } else {
                                ResponseHelper::error($LOCALE_PROFILE['messages']['already_registered_email']);
                            }
                        } else {
                            DB->update(
                                tableName: 'user',
                                data: [
                                    $profileField => $this->getUserService()->social($fieldData),
                                ],
                                criteria: [
                                    'id' => CURRENT_USER->id(),
                                ],
                            );
                        }
                    }
                }
            }
        }
    }

    public function preChange(): void
    {
        $applicationData = $this->getApplicationData();

        $allinfo = DataHelper::unmakeVirtual($applicationData['allinfo']);
        unset($applicationData['allinfo']);
        $this->oldApplicationData[$applicationData['id']] = array_merge($applicationData, $allinfo);

        $gamemastersAllinfoData = '';
        $gamemastersAllinfo = $allinfo;

        foreach ($this->getApplicationFields() as $elem) {
            if (!in_array('myapplication:update', $elem->getAttribute()->context) && !$elem instanceof Item\H1) {
                $reencodeData = $gamemastersAllinfo[$elem->name] ?? '';
                $reencodeData = str_replace('[', '&open;', $reencodeData);
                $reencodeData = str_replace(']', '&close;', $reencodeData);
                $gamemastersAllinfoData .= '[' . $elem->name . '][' . $reencodeData . ']' . chr(13) . chr(10);
            }
        }

        $this->gamemastersAllinfoData[$applicationData['id']] = $gamemastersAllinfoData;

        $this->preChangeHelper();
    }

    public function preDelete(): void
    {
        $LOCALE = $this->LOCALE['messages'];

        $applicationData = $this->getApplicationData(DataHelper::getId());

        if ($applicationData['deleted_by_gamemaster'] === '1') {
            $this->deletedApplicationsData[$applicationData['id']] = $applicationData;

            DB->delete(
                tableName: 'project_application_history',
                criteria: [
                    'project_application_id' => DataHelper::getId(),
                ],
            );
        } else {
            DB->update(
                tableName: 'project_application',
                data: [
                    'deleted_by_player' => '1',
                ],
                criteria: [
                    'id' => DataHelper::getId(),
                    'project_id' => $this->getActivatedProjectId(),
                ],
            );

            $this->postDelete([DataHelper::getId()]);

            ResponseHelper::response([['success', $LOCALE['application_deleted_success']]], '/myapplication/');
        }
    }

    public function getSortLastUpdateUserId(): array
    {
        if (is_null($this->usersDataTableViewShort)) {
            $this->getUsersDataTableViewShort();
        }

        return $this->usersDataTableViewShort;
    }

    public function checkRightsAdd(): bool
    {
        if (is_null($this->letToApply)) {
            $letToApply = true;

            if (!DataHelper::getId() && $this->act === ActEnum::add && $this->getActivatedProjectId()) {
                $LOCALE = $this->LOCALE;

                $projectData = $this->getProjectData();

                if ($this->getHistoryView()) {
                    $letToApply = false;
                }

                if ($projectData->status->getAsInt() !== 1) {
                    $letToApply = false;
                }

                $projectRights = RightsHelper::checkProjectRights();

                if (RightsHelper::checkAllowProjectActions($projectRights, ['{gamemaster}', '{application}'])) {
                    $letToApply = true;
                }

                if ($projectData->oneorderfromplayer->get()) {
                    $checkApplicationPresent = DB->select(
                        tableName: 'project_application',
                        criteria: [
                            'creator_id' => CURRENT_USER->id(),
                            'deleted_by_player' => '0',
                            'project_id' => $projectData->id->get(),
                        ],
                        oneResult: true,
                    );

                    if ($checkApplicationPresent) {
                        $letToApply = false;
                        ResponseHelper::error($LOCALE['messages']['oneorderfromplayer']);
                        ResponseHelper::redirect(ABSOLUTE_PATH . '/' . KIND . '/' . $checkApplicationPresent['id'] . '/');
                    }
                }

                $profileCompletion = (CURRENT_USER->isLogged() ? $this->getUserService()->calculateProfileCompletion(CURRENT_USER->id()) : 0);

                if (!$profileCompletion) {
                    $letToApply = false;
                }
            }

            $this->letToApply = $letToApply;
        }

        return $this->letToApply;
    }

    public function checkRightsChangeDelete(): bool
    {
        return !$this->getHistoryView();
    }

    public function checkRightsViewRestrict(): string
    {
        return '(creator_id=' . CURRENT_USER->id() . ' OR (offer_to_user_id=' . CURRENT_USER->id() . ' AND offer_denied!="1"))' . ($this->getDeletedView() ? ' AND deleted_by_player="1"' : (((!DataHelper::getId() || ACTION === ActionEnum::delete) && $this->act !== ActEnum::add) ? ' AND deleted_by_player="0"' : ''));
    }

    public function checkRightsRestrict(): string
    {
        return 'creator_id=' . CURRENT_USER->id() . ' AND deleted_by_player="0"';
    }

    /** Заглушка */
    public function getExcelType(): int
    {
        return 0;
    }

    public function getTeamApplicationContext(): array
    {
        return [];
    }

    public function getProjectIdValues(): array
    {
        $projectIdValues = [];
        $myProjectsData = DB->query(
            "SELECT DISTINCT p.id, p.name FROM project AS p LEFT JOIN project_application AS pa ON p.id=pa.project_id WHERE pa.creator_id=:creator_id",
            [
                ['creator_id', CURRENT_USER->id()],
            ],
        );

        foreach ($myProjectsData as $myProjectData) {
            $projectIdValues[] = [$myProjectData['id'], DataHelper::escapeOutput($myProjectData['name'])];
        }

        return $projectIdValues;
    }

    public function getCreatorIdValues(): array
    {
        return [];
    }

    public function getDistributedItemIdsValues(): array
    {
        return [];
    }

    public function getResponsibleGamemasterIdDefault(): null
    {
        return null;
    }

    public function getResponsibleGamemasterIdValues(): array
    {
        if (is_null($this->gamemastersList)) {
            $gamemastersList = [];

            if ($this->getProjectData()?->id->get() > 0) {
                $allusersDataSort = [];
                $gamemasters = RightsHelper::findByRights(
                    ['{admin}', '{gamemaster}'],
                    '{project}',
                    $this->getProjectData()->id->get(),
                    '{user}',
                    false,
                );

                foreach ($gamemasters as $gamemaster) {
                    $gamemastersList[] = [
                        $gamemaster,
                        $this->getUserService()->showNameWithId($this->getUserService()->get($gamemaster), true),
                    ];
                    $allusersDataSort[$gamemaster] = mb_strtolower(
                        $this->getUserService()->showNameWithId($this->getUserService()->get($gamemaster)),
                    );
                }
                array_multisort($allusersDataSort, SORT_ASC, $gamemastersList);
            }

            $this->gamemastersList = $gamemastersList;
        }

        return $this->gamemastersList;
    }

    public function getTeamApplicationMyapplicationDefault(): int
    {
        return $this->applicationType;
    }

    /** Обрабатываем все динамические поля, которые игрок может читать, но не может менять, на предмет ссылок и формата */
    public function postProcessTextsOfReadOnlyFields(string $RESPONSE_DATA): string
    {
        foreach ($this->getApplicationFields() as $applicationField) {
            if ($applicationField->getAttribute()->context[0] <= 10 && $applicationField->getAttribute()->context[1] > 10) {
                $name = $applicationField->name;

                unset($match);
                preg_match('#id="div_' . $name . '\[(\d+)]">(.*?)</div>#ms', $RESPONSE_DATA, $match);

                if ($match[2] ?? false) {
                    $RESPONSE_DATA = preg_replace('#id="div_' . $name . '\[' . $match[1] . ']">(.*?)</div>#ms', 'id="div_' . $name . '[' . $match[1] . ']">' . TextHelper::makeURLsActive(TextHelper::bbCodesInDescription($match[2])) . '</div>', $RESPONSE_DATA, 1);
                }
            }
        }

        return $RESPONSE_DATA;
    }

    private function getUsersDataTableViewShort(): void
    {
        if (is_null($this->usersDataTableView) || is_null($this->usersDataTableViewShort)) {
            $usersDataTableView = [];
            $usersDataTableViewShort = [];

            if (!DataHelper::getId() && $this->act !== ActEnum::add) {
                $creatorsData = DB->query(
                    "SELECT DISTINCT u.* FROM user u WHERE u.id in (SELECT id FROM user WHERE id=:id) OR u.id in (SELECT last_update_user_id FROM project_application WHERE creator_id=:creator_id)",
                    [
                        ['id', CURRENT_USER->id()],
                        ['creator_id', CURRENT_USER->id()],
                    ],
                );

                foreach ($creatorsData as $userData) {
                    $userData = $this->getUserService()->arrayToModel($userData);

                    $usersDataTableView[] = [
                        $userData->id->getAsInt(),
                        $this->getUserService()->showNameWithId($userData),
                    ];
                    $usersDataTableViewShort[] = [
                        $userData->id->getAsInt(),
                        $this->getUserService()->showNameExtended($userData, true, false, '', true, false, true),
                    ];
                }

                $usersDataTableViewSort = [];

                foreach ($usersDataTableView as $key => $row) {
                    $usersDataTableViewSort[$key] = mb_strtolower($row[1]);
                }
                array_multisort($usersDataTableViewSort, SORT_ASC, $usersDataTableView);
            }

            $this->usersDataTableView = $usersDataTableView;
            $this->usersDataTableViewShort = $usersDataTableViewShort;
        }
    }

    private function updateApplication(string|int $successfulResultsId): void
    {
        $LOCALE_MYAPPLICATION = $this->LOCALE;
        $LOCALE = $LOCALE_MYAPPLICATION['messages'];
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym', 'basefunc']);

        $newApplicationData = DB->findObjectById($successfulResultsId, 'project_application', true);
        $newApplicationModel = $this->arrayToModel($newApplicationData);
        $gamemastersAllinfoData = $this->gamemastersAllinfoData[$successfulResultsId] ?? '';
        $projectCharacterData = $this->getCharacterData((int) $newApplicationModel->project_character_id->get());
        $projectData = $this->getProjectData();
        $feeOptions = $this->getFeeOptions();

        /** Корректируем sorter */
        $newCharacterSorter = $_REQUEST['virtual' . ($newApplicationModel->team_application->get() ? $projectData->sorter2->get() : $projectData->sorter->get())][0] ?? null;

        if (!$newCharacterSorter && $projectCharacterData?->name->get()) {
            $newCharacterSorter = $projectCharacterData->name->get();
        }

        /** Добавляем данные доступные только мастерам в allinfo */
        DB->update(
            tableName: 'project_application',
            data: [
                'allinfo' => $newApplicationData['allinfo'] . $gamemastersAllinfoData,
                'sorter' => ($newCharacterSorter ? $newCharacterSorter : $LOCALE_FRAYM['not_set']),
            ],
            criteria: [
                'id' => $successfulResultsId,
            ],
        );

        $updatingUserData = $this->getUserService()->get(CURRENT_USER->id());

        if ($newApplicationModel->creator_id->get()) {
            $creatorUserData = $this->getUserService()->get($newApplicationModel->creator_id->get());
        } else {
            $creatorUserData = $updatingUserData;
        }

        /** Проверяем требуемый и выплаченный взнос, выставляем или убираем "взнос сдан", если есть выбор опций */
        $money = 0;
        $feeOptionDateData = [];

        if (count($feeOptions) > 1) {
            $projectFeeIdsData = $_REQUEST['project_fee_ids'][DataHelper::findDataKeyInRequestById($successfulResultsId)] ?? false;

            if (is_array($projectFeeIdsData)) {
                foreach ($projectFeeIdsData as $projectFeeId => $value) {
                    foreach ($feeOptions as $feeOption) {
                        if ($feeOption[0] === $projectFeeId) {
                            $feeOptionData = DB->findObjectById($projectFeeId, 'project_fee');
                            $money += $feeOptionData['cost'];
                            break;
                        }
                    }
                }
            }
        } else {
            //у нас только одна позиция взноса, ставим ее
            $feeOptionDateData = DB->query(
                "SELECT * FROM project_fee WHERE project_id=:project_id AND content IS NULL AND date_from <= CURDATE() ORDER BY date_from DESC LIMIT 1",
                [
                    ['project_id', $projectData->id->getAsInt()],
                ],
                true,
            );
            $money = $feeOptionDateData['cost'] ?? 0;
        }

        if (!$newApplicationModel->money_paid->get()) {
            $feeUpdate = $feeOptionDateData ? ['project_fee_ids' => DataHelper::arrayToMultiselect([$feeOptionDateData['id']])] : [];

            DB->update(
                tableName: 'project_application',
                data: array_merge(
                    [
                        'money' => $money,
                        'money_paid' => (int) $money > 0 && $newApplicationModel->money_provided->get() >= (int) $money ? '1' : '0',
                    ],
                    $feeUpdate,
                ),
                criteria: [
                    'id' => $successfulResultsId,
                ],
            );
        }

        /** Проставляем / обновляем связку с проживанием */
        $checkRoom = RightsHelper::findOneByRights('{member}', '{room}', null, '{application}', $successfulResultsId);
        $roomsSelectorData = $_REQUEST['rooms_selector'][DataHelper::findDataKeyInRequestById($successfulResultsId)] ?? false;

        if ($roomsSelectorData !== $checkRoom) {
            RightsHelper::deleteRights('{member}', '{room}', null, '{application}', $successfulResultsId);

            if ($roomsSelectorData) {
                RightsHelper::addRights(
                    '{member}',
                    '{room}',
                    $roomsSelectorData,
                    '{application}',
                    $successfulResultsId,
                );
            }
        }

        /** Сравниваем старую информацию в заявке с новой и рассылаем оповещения, если есть изменения */
        $oldApplicationData = $this->oldApplicationData[$successfulResultsId] ?? [];

        $newApplicationData = DB->findObjectById($successfulResultsId, 'project_application', true);
        $newApplicationModel = $this->arrayToModel($newApplicationData);

        $newApplicationData = array_merge($newApplicationData, DataHelper::unmakeVirtual($newApplicationData['allinfo']));
        unset($newApplicationData['allinfo']);

        $sendChangeToGamemaster = ACTION === ActionEnum::create;
        $doNotIncludeFields = [
            'updated_at',
        ];

        $fieldsData = '';

        foreach ($this->model->elementsList as $elem) {
            if (!in_array($elem->name, $doNotIncludeFields)) {
                $elem->set($newApplicationData[$elem->name] ?? null);

                if ($elem instanceof Item\H1) {
                    $fieldsData .= mb_strtoupper($elem->shownName) . '<br><br>';
                } elseif (!$elem instanceof Item\Hidden && $elem->get()) {
                    if (
                        in_array('myapplication:update', $elem->getAttribute()->context) &&
                        (
                            ACTION === ActionEnum::create ||
                            ($oldApplicationData[$elem->name] ?? false) !== ($newApplicationData[$elem->name] ?? false)
                        )
                    ) {
                        $fieldsData .= $elem->shownName;

                        if (ACTION === ActionEnum::change) {
                            $sendChangeToGamemaster = true;
                            $fieldsData .= $LOCALE['field_changed'];
                        }
                        $fieldsData .= ':<br>';
                        $fieldsData .= $elem->asHTML(false);

                        if (!$elem instanceof Item\Timestamp) {
                            $fieldsData .= '<br><br>';
                        }
                    }
                }
            }
        }

        if ($fieldsData !== '') {
            $fieldsData = '<br><br>' . mb_substr($fieldsData, 0, mb_strlen($fieldsData) - 8);
        }

        if ($sendChangeToGamemaster && !$newApplicationModel->deleted_by_gamemaster->get()) {
            $userIds = [];

            if ($newApplicationModel->responsible_gamemaster_id->get()) {
                $userIds[] = $newApplicationModel->responsible_gamemaster_id->get();
            } else {
                $userIds = RightsHelper::findByRights(
                    ['{admin}', '{gamemaster}'],
                    '{project}',
                    $this->getActivatedProjectId(),
                    '{user}',
                    false,
                );
            }

            $subscriptionUsers = RightsHelper::findByRights(
                '{subscribe}',
                '{project_application}',
                $successfulResultsId,
                '{user}',
                false,
            );

            if (is_array($subscriptionUsers)) {
                $userIds = array_merge($userIds, $subscriptionUsers);
            }
            $userIds = array_unique($userIds);

            if (ACTION === ActionEnum::create) {
                $subject = sprintf(
                    $LOCALE['application_create_to_gamemasters_subject'],
                    $this->getUserService()->showName($updatingUserData),
                    DataHelper::escapeOutput($newApplicationModel->sorter->get()),
                    DataHelper::escapeOutput($projectData->name->get()),
                );
            } else {
                $subject = sprintf(
                    $LOCALE['application_change_to_gamemasters_subject'],
                    DataHelper::escapeOutput($newApplicationModel->sorter->get()),
                    DataHelper::escapeOutput($projectData->name->get()),
                );
            }

            $message = sprintf(
                $LOCALE['application_' . ActionEnum::getAsString(ACTION) . '_to_gamemasters_message'],
                ABSOLUTE_PATH,
                $successfulResultsId,
                $projectData->id->get(),
                DataHelper::escapeOutput($newApplicationModel->sorter->get()),
                ABSOLUTE_PATH,
                $creatorUserData->sid->get(),
                $this->getUserService()->showName($creatorUserData),
            ) . $fieldsData . '<br><br><a href="' . ABSOLUTE_PATH . '/application/' . $successfulResultsId . '/act=edit&project_id=' . $projectData->id->get() . '">' . ABSOLUTE_PATH . '/application/' . $successfulResultsId . '/act=edit&project_id=' . $projectData->id->get() . '</a>' . $LOCALE_CONVERSATION['subscription']['base_text2'];

            if ($userIds) {
                MessageHelper::prepareEmails($userIds, [
                    'author_name' => $this->getUserService()->showName($updatingUserData),
                    'author_email' => DataHelper::escapeOutput($updatingUserData->em->get()),
                    'name' => $subject,
                    'content' => $message,
                    'obj_type' => 'project_application',
                    'obj_id' => $successfulResultsId,
                ]);

                MessageHelper::preparePushs($userIds, [
                    'user_id_from' => $updatingUserData->id->get(),
                    'message_img' => $this->getUserService()->photoUrl($updatingUserData),
                    'header' => DataHelper::escapeOutput($newApplicationModel->sorter->get()) . ' (' . DataHelper::escapeOutput($projectData->name->get()) . ')',
                    'content' => trim(strip_tags(str_replace("<br>", "\n", $subject))),
                    'obj_type' => 'application',
                    'obj_id' => $successfulResultsId,
                ]);
            }
        }

        $this->updateGroupsInRelation($successfulResultsId);

        /** Добавляем сообщение-запрос на добавление той или иной группы к заявке, если его еще не было */
        if (is_array($_REQUEST['user_requested_project_group_ids'][0] ?? null)) {
            foreach ($_REQUEST['user_requested_project_group_ids'][0] as $requestedGroupId => $uselessData) {
                if ($uselessData === 'on') {
                    /** Проверяем, нет ли уже такого запроса в БД */
                    $checkMessage = DB->query(
                        "SELECT c.id, cm.updated_at FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id WHERE c.obj_type='{project_application_conversation}' AND c.obj_id=:obj_id AND cm.message_action='{request_group}' AND cm.message_action_data LIKE :project_group_id AND cm.creator_id=:creator_id ORDER BY cm.updated_at DESC",
                        [
                            ['obj_id', $newApplicationModel->id->get()],
                            ['project_group_id', '{project_group_id: ' . $requestedGroupId . '%'],
                            ['creator_id', CURRENT_USER->id()],
                        ],
                        true,
                    );

                    if (!$checkMessage) {
                        /** Отправляем сообщение с запросом к профильному мастеру по группе **/
                        $projectGroupData = DB->findObjectById($requestedGroupId, 'project_group');
                        $responsibleGamemasterData = $this->getUserService()->get($projectGroupData['responsible_gamemaster_id']);

                        if ($responsibleGamemasterData?->id->get()) {
                            $message = "@" . $this->getUserService()->showName($responsibleGamemasterData) .
                                '[' . $responsibleGamemasterData->sid->get() . ']
                            
                            ' . sprintf(
                                    $LOCALE_MYAPPLICATION['group_request'],
                                    DataHelper::escapeOutput($projectGroupData['name']),
                                );

                            /** @var MessageService */
                            $messageService = CMSVCHelper::getService('message');

                            $messageService->newMessage(
                                null,
                                $message,
                                '',
                                [],
                                [],
                                [
                                    'obj_type' => '{project_application_conversation}',
                                    'obj_id' => $successfulResultsId,
                                    'sub_obj_type' => '{from_player}',
                                ],
                                '{request_group}',
                                '{project_group_id: ' . $requestedGroupId . '}',
                            );
                        }
                    }
                }
            }
        }
    }
}
