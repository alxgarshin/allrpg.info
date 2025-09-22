<?php

declare(strict_types=1);

namespace App\CMSVC\Ingame;

use App\CMSVC\Application\ApplicationModel;
use App\CMSVC\BankTransaction\BankTransactionService;
use App\CMSVC\IngameBankTransaction\{IngameBankTransactionModel, IngameBankTransactionService};
use App\CMSVC\Myapplication\MyapplicationService;
use App\CMSVC\Plot\PlotService;
use App\CMSVC\Trait\{ProjectDataTrait, UserServiceTrait};
use App\Helper\{DateHelper, DesignHelper, MessageHelper, RightsHelper, TextHelper};
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Entity\MultiObjectsEntity;
use Fraym\Enum\{ActEnum, EscapeModeEnum, MultiObjectsEntitySubTypeEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper, ResponseHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

#[Controller(IngameController::class)]
class IngameService extends BaseService
{
    use ProjectDataTrait;
    use UserServiceTrait;

    public array $moreThanOneApplicationOnAProject = [];
    public array $applicationsFullData = [];

    #[DependencyInjection]
    public MyapplicationService $myapplicationService;

    private ?int $applicationId = null;
    private ?ApplicationModel $applicationData = null;

    private ?IngameBankTransactionService $ingameBankTransactionService = null;

    public function init(): static
    {
        $applicationData = $this->getApplicationData($this->getApplicationId());

        if (!$this->getApplicationId()) {
            $alreadyFoundProject = [];
            $moreThanOneApplicationOnAProject = [];
            $applicationsFullData = [];
            $applicationsData = DB->query(
                "SELECT pa.creator_id, pa.id, pa.sorter, p.name, p.id as project_id, p.name as project_name, p.attachments FROM project_application pa LEFT JOIN project p ON p.id=pa.project_id WHERE pa.creator_id=:creator_id AND p.date_to >= :date_to AND pa.deleted_by_player='0' ORDER BY p.name, pa.sorter",
                [
                    ['creator_id', CURRENT_USER->id()],
                    ['date_to', date("Y-m-d")],
                ],
            );

            foreach ($applicationsData as $applicationData) {
                $applicationsFullData[] = $applicationData;

                if (in_array($applicationData['project_id'], $alreadyFoundProject)) {
                    $moreThanOneApplicationOnAProject[] = $applicationData['project_id'];
                }
                $alreadyFoundProject[] = $applicationData['project_id'];
            }
            unset($alreadyFoundProject);

            $this->moreThanOneApplicationOnAProject = $moreThanOneApplicationOnAProject;
            $this->applicationsFullData = $applicationsFullData;
        } else {
            $this->getProjectData();
        }

        return $this;
    }

    public function getApplicationId(): ?int
    {
        if (is_null($this->applicationId)) {
            $applicationId = null;
            $requestId = $_REQUEST['id'] ?? false;

            if ($requestId === 'exit') {
                CookieHelper::batchDeleteCookie(['ingame_application_id']);
            } elseif ($requestId) {
                $applicationId = (int) $requestId;
                CookieHelper::batchSetCookie(['ingame_application_id' => (string) $applicationId]);
            } elseif (CookieHelper::getCookie('ingame_application_id')) {
                $applicationId = (int) CookieHelper::getCookie('ingame_application_id');
            }

            $this->applicationId = $applicationId;
        }

        return $this->applicationId;
    }

    public function getApplicationData(?int $applicationId = null): ?ApplicationModel
    {
        if (is_null($this->applicationData) && $applicationId) {
            $this->applicationData = $this->myapplicationService->get($applicationId);

            if ($this->applicationData->creator_id->getAsInt() !== CURRENT_USER->id() || $this->applicationData->deleted_by_player->get()) {
                $LOCALE = $this->getLOCALE()['messages'];

                $this->applicationData = null;
                $this->applicationId = null;
                CookieHelper::batchDeleteCookie(['ingame_application_id']);
                ResponseHelper::error($LOCALE['no_access']);
            }
        }

        return $this->applicationData;
    }

    /** Подсчет банковского баланса */
    public function getBankBalance(): array
    {
        return BankTransactionService::getApplicationBalances($this->getApplicationId());
    }

    /** Добавление банковской транзакции */
    public function createBankTransaction(): ?Response
    {
        $LOCALE = $this->getLOCALE()['messages'];
        $LOCALE_INGAME_BANK_TRANSACTION = LocaleHelper::getLocale(['ingame_bank_transaction']);

        $ingameBankTransactionService = $this->getIngameBankTransactionService();

        $bankBalance = $this->getBankBalance();
        $projectData = $this->getProjectData();

        $name = $_REQUEST['name'][0] ?? null;
        $fromBankCurrencyId = (int) ($_REQUEST['from_bank_currency_id'][0] ?? 0);
        $recipientNum = (int) ($_REQUEST['to_project_application_id'][0] ?? 0);
        $amountFrom = (int) ($_REQUEST['amount_from'][0] ?? 0);
        $bankCurrencyId = (int) ($_REQUEST['bank_currency_id'][0] ?? 0);
        $currenciesLocked = $ingameBankTransactionService->getFromBankCurrencyIdLocked();

        if ($amountFrom > 0) {
            if ((int) $bankBalance[$fromBankCurrencyId] - $amountFrom >= 0 && !in_array($fromBankCurrencyId, $currenciesLocked)) {
                $recipientApplication = $this->myapplicationService->get($recipientNum);

                if ($recipientApplication->project_id->getAsInt() === $projectData->id->getAsInt()) {
                    /** Проверяем установленные правила переводов */
                    $amountTo = $this->checkAndChangeAmountByBankRules(
                        $this->getApplicationData(),
                        $fromBankCurrencyId,
                        $amountFrom,
                        $recipientApplication,
                        $bankCurrencyId,
                    );

                    if ($amountTo > 0) {
                        DB->insert(
                            tableName: 'bank_transaction',
                            data: [
                                'name' => $name,
                                'from_project_application_id' => $this->getApplicationId(),
                                'from_bank_currency_id' => $fromBankCurrencyId,
                                'amount_from' => $amountFrom,
                                'to_project_application_id' => $recipientApplication->id->getAsInt(),
                                'bank_currency_id' => $bankCurrencyId,
                                'amount' => $amountTo,
                                'project_id' => $projectData->id->getAsInt(),
                                'created_at' => time(),
                                'creator_id' => CURRENT_USER->id(),
                            ],
                        );

                        $redirectTo = '/' . KIND . '/#bank';

                        ResponseHelper::response([['success', $LOCALE_INGAME_BANK_TRANSACTION['fraym_model']['object_messages'][0]]], $redirectTo);
                    } elseif (!$amountTo) {
                        ResponseHelper::responseOneBlock('error', $LOCALE['rules_prohibit'], [0]);
                    } else {
                        ResponseHelper::responseOneBlock(
                            'error',
                            sprintf($LOCALE['value_should_be_multiple'], abs($amountTo)),
                            [0],
                        );
                    }
                } else {
                    ResponseHelper::responseOneBlock('error', $LOCALE['bad_recipient'], [0]);
                }
            } else {
                ResponseHelper::responseOneBlock('error', $LOCALE['not_enough_balance'], [0]);
            }
        } else {
            ResponseHelper::responseOneBlock('error', $LOCALE_INGAME_BANK_TRANSACTION['global']['messages']['too_small_amount'], [0]);
        }

        return null;
    }

    /** Поля заявки: внутриигровой паспорт */
    public function renderGameFieldsHtml(): string
    {
        return $this->renderFieldsHtml('game');
    }

    /** Поля заявки: информация о персонаже */
    public function renderOutOfGameFieldsHtml(): string
    {
        return $this->renderFieldsHtml('out_of_game');
    }

    public function getPlotsDataDefault(): ?string
    {
        /** @var PlotService */
        $plotService = CMSVCHelper::getService('plot');

        return $plotService->generateAllPlots($this->getActivatedProjectId(), '{application}', $this->getApplicationId(), true);
    }

    public function renderCommentContent(): string
    {
        $LOCALE = $this->getLOCALE();

        $applicationId = $this->getApplicationId();
        $applicationData = $this->getApplicationData();

        $RESPONSE_DATA = '<div class="application_conversations player"> ';

        $RESPONSE_DATA .= MessageHelper::conversationForm(
            null,
            '{project_application_conversation}',
            $applicationId,
            $LOCALE['conversation_text'],
            '',
            true,
            false,
            '{from_player}',
        );

        $unreadConversationsIds = [];
        $conversationsUnreadData = DB->query(
            "SELECT c.id as c_id, c.name as c_name, c.sub_obj_type as c_sub_obj_type FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND cms.user_id=:user_id WHERE c.obj_type='{project_application_conversation}' AND c.obj_id=:obj_id AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{to_player}' OR c.sub_obj_type IS NULL) AND (cms.message_read='0' OR cms.message_read IS NULL) GROUP BY c.id ORDER BY MAX(cm.updated_at) DESC",
            [
                ['user_id', CURRENT_USER->id()],
                ['obj_id', $applicationId],
            ],
        );

        foreach ($conversationsUnreadData as $conversationData) {
            $subObjType = DataHelper::clearBraces($conversationData['c_sub_obj_type']);
            $unreadConversationsIds[] = $conversationData['c_id'];
            $RESPONSE_DATA .= MessageHelper::conversationTree($conversationData['c_id'], 0, 1, '{project_application}', $applicationData, $subObjType, $LOCALE['titles_conversation_sub_obj_types'][$subObjType]);
        }

        $conversationsReadData = DB->query(
            "SELECT c.id as c_id, c.name as c_name, c.sub_obj_type as c_sub_obj_type FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND cms.user_id=:user_id WHERE c.obj_type='{project_application_conversation}' AND c.obj_id=:obj_id AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{to_player}' OR c.sub_obj_type IS NULL) AND cms.message_read='1' GROUP BY c.id ORDER BY MAX(cm.updated_at) DESC",
            [
                ['user_id', CURRENT_USER->id()],
                ['obj_id', $applicationId],
            ],
        );

        foreach ($conversationsReadData as $conversationData) {
            if (!in_array($conversationData['c_id'], $unreadConversationsIds)) {
                $subObjType = DataHelper::clearBraces($conversationData['c_sub_obj_type']);

                $RESPONSE_DATA .= MessageHelper::conversationTree($conversationData['c_id'], 0, 1, '{project_application}', $applicationData, $subObjType, $LOCALE['titles_conversation_sub_obj_types'][$subObjType]);
            }
        }

        $RESPONSE_DATA .= '</div>';

        return $RESPONSE_DATA;
    }

    public function renderBankContent(): string
    {
        $LOCALE = $this->getLOCALE();

        $ingameBankTransactionService = $this->getIngameBankTransactionService();
        /** @var IngameBankTransactionModel */
        $ingameBankTransactionModel = $ingameBankTransactionService->getModel();

        $RESPONSE_DATA = '
        <h2>' . $LOCALE['generate_qrpg_pay'] . '</h2>
        <div class="bank_header">
            <div class="bank_generate_qrpg">
                <div class="amount">
                    ' . ($ingameBankTransactionModel->bank_currency_id->getValues() ? '<span>' . $ingameBankTransactionModel->from_bank_currency_id->getShownName() . '</span>' . $ingameBankTransactionModel->bank_currency_id->asHTML(true) : '') . '
                    <span>' . $ingameBankTransactionModel->amount_from->getShownName() . '</span>
                    <input type="text" name="bank_generate_qrpg_amount" autocomplete="off">
                    <span>' . $ingameBankTransactionModel->name->getShownName() . '</span>
                    <input type="text" name="bank_generate_qrpg_name" autocomplete="off">
                    
                </div>
                <div class="result"><button class="main" id="bank_generate_qrpg_button">' . $LOCALE['generate'] . '</button></div>
            </div>';

        $RESPONSE_DATA .= '
            <div class="bank_balance">';

        $RESPONSE_DATA .= $this->myapplicationService->getPlayersBankValuesDefault();

        $RESPONSE_DATA .= '
                <div class="small">' . $LOCALE['account_num'] . '<span>' . CookieHelper::getCookie('ingame_application_id') . '</span></div>
            </div>
        </div>
        
        <h2>' . $LOCALE['pay_manually'] . '</h2><br>';

        $RESPONSE_DATA .= $this->renderBankTransactionsHtml();

        $RESPONSE_DATA .= $this->renderDefaultCurrency();

        return $RESPONSE_DATA;
    }

    /** Старт QRpg-хакинга */
    public function qrpgHackingStart(int $qhaId): array
    {
        $result = [];

        $LOCALE = $this->getLOCALE();

        if ($qhaId > 0) {
            $qhaData = DB->findObjectById($qhaId, 'qrpg_hacking');

            if ($qhaData['id'] > 0 && (int) $qhaData['started_at'] === 0) {
                $applicationId = $this->getApplicationId();

                if ($applicationId > 0 && $applicationId === $qhaData['project_application_id']) {
                    $applicationData = $this->getApplicationData();

                    if ($applicationData->creator_id->getAsInt() === CURRENT_USER->id() && !$applicationData->deleted_by_player->get()) {
                        $html = '';
                        $inputLength = $qhaData['input_length']; // количество ячеек ввода
                        $matrix = json_decode($qhaData['matrix'], true);

                        $sequencesLengthSum = 0;
                        $sequences = json_decode($qhaData['sequences'], true);

                        foreach ($sequences as $sequence) {
                            $sequencesLengthSum += count($sequence);
                        }

                        $html .= '<input type="hidden" name="hacking_sequence" value="{}">';
                        $html .= '<input type="hidden" name="sequences_length_sum" value="' . $sequencesLengthSum . '">';

                        $html .= '<div class="qrpg_hacking_header">';

                        $html .= '<div class="qrpg_hacking_timer"><div class="name">' . $LOCALE['timer'] . '</div><span>' . $qhaData['timer'] . '</span></div>';

                        $html .= '<div class="qrpg_hacking_input_container"><div class="name">' . $LOCALE['inputed_sequence'] . '</div><div class="qrpg_hacking_input">';

                        $html .= str_repeat('<div class="qrpg_hacking_input_elem"></div>', $inputLength);

                        $html .= '</div></div>';

                        $html .= '</div>';

                        $html .= '<div class="qrpg_hacking_body">';

                        $html .= '<div class="qrpg_hacking_sequences"><div class="name">' . $LOCALE['sequences'] . '</div><img src="' . ABSOLUTE_PATH . '/scripts/qha_sequences.php?qha_id=' . $qhaData['id'] . '" /></div>';

                        $html .= '<div class="qrpg_hacking_matrix_container"><div class="finish">' . $LOCALE['finish'] . '</div><div class="name">' . $LOCALE['matrix'] . '</div><div class="qrpg_hacking_matrix">';

                        foreach ($matrix as $matrixRowKey => $matrixRow) {
                            $html .= '<div class="qrpg_hacking_matrix_row">';

                            foreach ($matrixRow as $matrixColKey => $matrixCol) {
                                $html .= '<div class="qrpg_hacking_matrix_col" row="' . $matrixRowKey . '" col="' . $matrixColKey . '">' . $matrixCol . '</div>';
                            }

                            $html .= '</div>';
                        }

                        $html .= '</div></div>';

                        $html .= '</div>';

                        $jsonText = [
                            'html' => $html,
                            'timer' => $qhaData['timer'],
                        ];

                        $result = [
                            'response' => 'success',
                            'response_data' => $jsonText,
                        ];

                        DB->update(
                            tableName: 'qrpg_hacking',
                            data: [
                                'started_at' => DateHelper::getNow(),
                            ],
                            criteria: [
                                'id' => $qhaId,
                            ],
                        );
                    }
                }
            }
        }

        return $result;
    }

    /** Оплата по QRpg-коду */
    public function qrpgBankPay(int $accountNumTo, int $bankCurrencyId, int $amount, string $name): array
    {
        $LOCALE = $this->getLOCALE();
        $LOCALE_TRANSACTION = LocaleHelper::getLocale(['bank_transaction', 'global']);

        $returnArr = [];

        $applicationId = $this->getApplicationId();

        if ($applicationId > 0) {
            $applicationData = $this->getApplicationData();

            if ($applicationData->creator_id->getAsInt() === CURRENT_USER->id() && !$applicationData->deleted_by_player->get()) {
                $recipientData = null;

                if ($accountNumTo > 0) {
                    $recipientData = $this->myapplicationService->get($accountNumTo);
                }

                $amount = (int) round($amount);

                $bankBalance = BankTransactionService::getApplicationBalances($this->getApplicationId());

                $bankCurrencyData = [];

                if ($bankCurrencyId > 0) {
                    $bankCurrencyData = DB->findObjectById($bankCurrencyId, 'bank_currency');
                }

                if ($amount > 0) {
                    if ((int) $bankBalance[$bankCurrencyId] - $amount > 0) {
                        if ($recipientData->project_id->getAsInt() === $applicationData->project_id->get()) {
                            /* проверяем установленные правила переводов */
                            $toAmount = $this->checkAndChangeAmountByBankRules(
                                $applicationData,
                                $bankCurrencyId,
                                $amount,
                                $recipientData,
                                $bankCurrencyId,
                            );

                            if ($toAmount !== false && $toAmount === $amount) {
                                DB->insert(
                                    tableName: 'bank_transaction',
                                    data: [
                                        'project_id' => $recipientData->project_id->get(),
                                        'name' => $name,
                                        'from_project_application_id' => $applicationId,
                                        'from_bank_currency_id' => $bankCurrencyId,
                                        'amount_from' => $toAmount,
                                        'to_project_application_id' => $recipientData->id->getAsInt(),
                                        'bank_currency_id' => $bankCurrencyId,
                                        'amount' => $amount,
                                        'creator_id' => CURRENT_USER->id(),
                                        'created_at' => DateHelper::getNow(),
                                        'updated_at' => DateHelper::getNow(),
                                    ],
                                );

                                $returnArr = [
                                    'response' => 'success',
                                    'response_data' => [
                                        'header' => $LOCALE['payment_success'],
                                        'description' => sprintf(
                                            $LOCALE['payment_success_details'],
                                            $amount,
                                            $bankCurrencyData['name'] !== '' ? ' (' . mb_strtolower(DataHelper::escapeOutput($bankCurrencyData['name'])) . ')' : '',
                                            $applicationId,
                                            $recipientData->id->getAsInt(),
                                            $name,
                                        ),
                                    ],
                                ];
                            } else {
                                $returnArr = [
                                    'response' => 'error',
                                    'response_text' => $LOCALE['messages']['rules_prohibit'],
                                ];
                            }
                        }
                    } else {
                        $returnArr = [
                            'response' => 'error',
                            'response_text' => $LOCALE['messages']['not_enough_balance'],
                        ];
                    }
                } else {
                    $returnArr = [
                        'response' => 'error',
                        'response_text' => $LOCALE_TRANSACTION['messages']['too_small_amount'],
                    ];
                }
            }
        }

        return $returnArr;
    }

    /** Создание данных QRpg-кода для оплаты */
    public function prepareQRpgBankCode(int $bankCurrencyId, int $amount, string $name): array
    {
        $LOCALE_TRANSACTION = LocaleHelper::getLocale(['ingame_bank_transaction', 'global']);

        $result = [];

        $applicationId = $this->getApplicationId();

        if ($applicationId > 0) {
            $applicationData = $this->getApplicationData($applicationId);

            if ($applicationData->creator_id->getAsInt() === CURRENT_USER->id() && !$applicationData->deleted_by_player->get()) {
                if (round($amount) > 0) {
                    $jsonText = [
                        'r' => $this->getApplicationId(),
                        'cu' => $bankCurrencyId,
                        'a' => $amount,
                        'n' => $name,
                        'h' => mb_substr(
                            md5(
                                'qrpg' . $this->getApplicationId() . 'bank' . $bankCurrencyId . 'qrpg' . $amount . 'qrpg',
                            ),
                            0,
                            8,
                        ),
                    ];

                    $result = [
                        'response' => 'success',
                        'response_data' => $jsonText,
                    ];
                } else {
                    $result = [
                        'response' => 'error',
                        'response_text' => $LOCALE_TRANSACTION['messages']['too_small_amount'],
                    ];
                }
            }
        }

        return $result;
    }

    /** Запись геопозиции пользователя в базу */
    public function setGeoposition(string $latitude, string $longitude, string $accuracy): array
    {
        if ($this->getApplicationId() > 0) {
            $applicationData = $this->myapplicationService->get($this->getApplicationId());

            if ($applicationData && $applicationData->creator_id->getAsInt() === CURRENT_USER->id() && $applicationData->project_id->get()) {
                DB->insert(
                    tableName: 'project_application_geoposition',
                    data: [
                        'creator_id' => CURRENT_USER->id(),
                        'project_id' => $applicationData->project_id->get(),
                        'project_application_id' => $this->getApplicationId(),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'accuracy' => $accuracy,
                        'created_at' => DateHelper::getNow(),
                        'updated_at' => DateHelper::getNow(),
                    ],
                );
            }
        }

        return [
            'response' => 'success',
        ];
    }

    /** Случайная выборка пары символов из набора для QRpg-хакинга */
    public function getQRpgHackingMatrixPair(): string
    {
        $matrixSymbols = '1234567890ABCDEF';
        $rand1 = rand(0, mb_strlen($matrixSymbols) - 1);
        $rand2 = rand(0, mb_strlen($matrixSymbols) - 1);

        return mb_substr($matrixSymbols, $rand1, 1) . mb_substr($matrixSymbols, $rand2, 1);
    }

    /** Проверка возможности транзакции в игровом банке с учетом правил переводов, и перевода суммы ресурса в сумму ресурса при возможности */
    public function checkAndChangeAmountByBankRules(
        ApplicationModel $senderApplicationData,
        int $fromBankCurrencyId,
        int $fromAmount,
        ?ApplicationModel $recipientApplicationData,
        int $toBankCurrencyId,
    ): bool|int {
        $toAmount = false;

        if ($toBankCurrencyId === 0) {
            $toBankCurrencyId = $fromBankCurrencyId;
        }

        if ($fromAmount > 0) {
            $qrpgKeysSender = $this->qrpgGetKeysAndProperties($senderApplicationData->id->getAsInt());
            $qrpgKeysSender = $qrpgKeysSender['response_data']['my_qrpg_keys'];

            $qrpgKeysRecipient = $this->qrpgGetKeysAndProperties($recipientApplicationData->id->getAsInt());
            $qrpgKeysRecipient = $qrpgKeysRecipient['response_data']['my_qrpg_keys'];

            /* выбираем все правила по переводу из указанной валюты в указанную */
            $globalBankRulesData = DB->select(
                tableName: 'bank_rule',
                criteria: [
                    'project_id' => $senderApplicationData->project_id->get(),
                    'currency_from_id' => $fromBankCurrencyId,
                    'currency_to_id' => $toBankCurrencyId,
                ],
            );
            $globalBankRulesCount = count($globalBankRulesData);

            /* если не найдено ни одного правила и при этом валюта одна и та же, просто отдаем сумму перевода от */
            if ($globalBankRulesCount === 0 && $toBankCurrencyId === $fromBankCurrencyId) {
                $toAmount = $fromAmount;
            } elseif ($globalBankRulesCount > 0) {
                /* правила перевода установлены, проверяем наших пользователей на соответствие им и ищем максимально подходящее правило при этом */
                $rulesVariant = [];

                foreach ($globalBankRulesData as $globalBankRuleData) {
                    $blockCurrencyBySender = false;
                    $blockCurrencyByRecipient = false;
                    $hasSpecificSenderKey = false;
                    $hasSpecificRecipientKey = false;

                    $qrpgKeysFromIds = DataHelper::multiselectToArray($globalBankRuleData['qrpg_keys_from_ids']);
                    $qrpgKeysToIds = DataHelper::multiselectToArray($globalBankRuleData['qrpg_keys_to_ids']);

                    if (in_array('none', $qrpgKeysFromIds)) {
                        $blockCurrencyBySender = true;
                    } elseif (count($qrpgKeysFromIds) > 0) {
                        /* проверяем, есть ли у игрока хотя бы один из перечисленных qrpg-ключей. Если нет ни одного блокируем. */
                        $blockCurrencyBySender = true;

                        foreach ($qrpgKeysFromIds as $qrpgKeysId) {
                            if (in_array($qrpgKeysId, $qrpgKeysSender)) {
                                $blockCurrencyBySender = false;
                                $hasSpecificSenderKey = true;
                            }
                        }
                    }

                    if (in_array('none', $qrpgKeysToIds)) {
                        $blockCurrencyByRecipient = true;
                    } elseif (count($qrpgKeysToIds) > 0) {
                        /* проверяем, есть ли у игрока хотя бы один из перечисленных qrpg-ключей. Если нет ни одного блокируем. */
                        $blockCurrencyByRecipient = true;

                        foreach ($qrpgKeysToIds as $qrpgKeysId) {
                            if (in_array($qrpgKeysId, $qrpgKeysRecipient)) {
                                $blockCurrencyByRecipient = false;
                                $hasSpecificRecipientKey = true;
                            }
                        }
                    }

                    /* если по ключам всё ок, у правила не выставлены нули в полях "сколько" и переводимая сумма кратна полю "сколько", то считаем */
                    if (!$blockCurrencyBySender && !$blockCurrencyByRecipient && $globalBankRuleData['amount_from'] > 0 && $globalBankRuleData['amount_to'] > 0) {
                        if ($fromAmount % $globalBankRuleData['amount_from'] === 0) {
                            /* если мы попали точно в ключи, то используем этот вариант */
                            if ((!$rulesVariant['has_specific_sender_key'] && $hasSpecificSenderKey) || (!$rulesVariant['has_specific_recipient_key'] && $hasSpecificRecipientKey)) {
                                $rulesVariant = [
                                    'has_specific_sender_key' => $hasSpecificSenderKey,
                                    'has_specific_recipient_key' => $hasSpecificRecipientKey,
                                    'amount_from' => $globalBankRuleData['amount_from'],
                                    'amount_to' => $globalBankRuleData['amount_to'],
                                ];
                            }
                        } else {
                            /* если переводимое число не кратно выставленному в правиле, то выдаем минусовое значение */
                            $toAmount = 0 - $globalBankRuleData['amount_from'];
                        }
                    }
                }

                if ($rulesVariant['amount_from'] > 0 && $rulesVariant['amount_to'] > 0) {
                    $toAmount = $fromAmount / $rulesVariant['amount_from'] * $rulesVariant['amount_to'];
                }
            }
        }

        return $toAmount;
    }

    /** Расчет имеющихся в текущий момент ключей и свойств */
    public function qrpgGetKeysAndProperties(?int $applicationPresetId = null): array
    {
        $qrpgPropertiesList = '';
        $qrpgKeysList = '';
        $myQrpgKeys = [];

        $applicationId = is_null($applicationPresetId) ? $this->getApplicationId() : $applicationPresetId;

        if ($applicationId > 0) {
            $applicationData = $this->getApplicationData($applicationId);

            if (($applicationData->creator_id->getAsInt() === CURRENT_USER->id() || !is_null($applicationPresetId)) && !$applicationData->deleted_by_player->get()) {
                $projectData = $this->getProjectData($applicationData->project_id->getAsInt());

                $keysTimers = [];

                /* QRpg-ключи */
                $qrpgAllKeys = [];
                $myQrpgKeys = $applicationData->qrpg_key->get();

                /* добавляем к списку ключей пользователя ключи, которые получаются временно */
                $temporaryKeysMaxTime = [];
                $temporaryKeysData = DB->query(
                    "SELECT qh.*, qc.gives_qrpg_keys, qc.gives_qrpg_keys_for_minutes, qc.gives_bad_qrpg_keys, qc.gives_bad_qrpg_keys_for_minutes FROM qrpg_history AS qh LEFT JOIN qrpg_code AS qc ON qc.id=qh.qrpg_code_id WHERE qh.project_id=:project_id AND qh.creator_id=:creator_id AND ((qc.gives_qrpg_keys IS NOT NULL AND qc.gives_qrpg_keys != '[]') OR (qc.gives_bad_qrpg_keys IS NOT NULL AND qc.gives_bad_qrpg_keys_for_minutes != '[]'))",
                    [
                        ['project_id', $projectData->id->getAsInt()],
                        ['creator_id', $applicationData->creator_id->getAsInt()],
                    ],
                );

                foreach ($temporaryKeysData as $temporaryKeyData) {
                    $successesData = json_decode($temporaryKeyData['success'] ?? '', true);
                    $givesQrpgKeys = json_decode($temporaryKeyData['gives_qrpg_keys'] ?? '', true);
                    $givesQrpgKeysForMinutes = json_decode($temporaryKeyData['gives_qrpg_keys_for_minutes'] ?? '', true);
                    $givesBadQrpgKeys = json_decode($temporaryKeyData['gives_bad_qrpg_keys'] ?? '', true);
                    $givesBadQrpgKeysForMinutes = json_decode($temporaryKeyData['gives_bad_qrpg_keys_for_minutes'] ?? '', true);

                    foreach ($successesData as $key => $successData) {
                        if ($successData === '1') {
                            if (
                                (int) $givesQrpgKeysForMinutes[$key] > 0 && $temporaryKeyData['created_at'] >= time() - (int) $givesQrpgKeysForMinutes[$key] * 60
                            ) {
                                $myQrpgKeys = array_merge($myQrpgKeys, array_keys($givesQrpgKeys[$key]));

                                foreach (array_keys($givesQrpgKeys[$key]) as $timeredKeyId) {
                                    if (!isset($temporaryKeysMaxTime[$timeredKeyId]) || $temporaryKeyData['created_at'] > $temporaryKeysMaxTime[$timeredKeyId]) {
                                        $temporaryKeysMaxTime[$timeredKeyId] = $temporaryKeyData['created_at'];
                                    }
                                    $keysTimers[$timeredKeyId] = $temporaryKeyData['created_at'] + (int) $givesQrpgKeysForMinutes[$key] * 60;
                                }
                            }
                        } elseif ($successData === '0') {
                            if (
                                $givesBadQrpgKeysForMinutes &&
                                (int) $givesBadQrpgKeysForMinutes[$key] > 0 &&
                                $temporaryKeyData['created_at'] >= time() - (int) $givesBadQrpgKeysForMinutes[$key] * 60
                            ) {
                                $myQrpgKeys = array_merge($myQrpgKeys, array_keys($givesBadQrpgKeys[$key]));

                                foreach (array_keys($givesBadQrpgKeys[$key]) as $timeredKeyId) {
                                    if (!isset($temporaryKeysMaxTime[$timeredKeyId]) || $temporaryKeyData['created_at'] > $temporaryKeysMaxTime[$timeredKeyId]) {
                                        $temporaryKeysMaxTime[$timeredKeyId] = $temporaryKeyData['created_at'];
                                    }
                                    $keysTimers[$timeredKeyId] = $temporaryKeyData['created_at'] + (int) $givesBadQrpgKeys[$key] * 60;
                                }
                            }
                        }
                    }
                }
                $myQrpgKeys = array_unique($myQrpgKeys);

                /* убираем из списка ключей пользователя ключи, которые получались временно, а ПОСЛЕ ЭТОГО были забраны по скану какого-то кода */
                $temporaryKeysData = DB->query(
                    "SELECT qh.*, qc.removes_qrpg_keys_user FROM qrpg_history AS qh LEFT JOIN qrpg_code AS qc ON qc.id=qh.qrpg_code_id WHERE qh.project_id=:project_id AND qh.creator_id=:creator_id AND (qc.removes_qrpg_keys_user IS NOT NULL AND qc.removes_qrpg_keys_user != '[]')",
                    [
                        ['project_id', $projectData->id->getAsInt()],
                        ['creator_id', $applicationData->creator_id->getAsInt()],
                    ],
                );

                foreach ($temporaryKeysData as $temporaryKeyData) {
                    $successesData = json_decode($temporaryKeyData['success'], true);
                    $removesQrpgKeysUser = json_decode($temporaryKeyData['removes_qrpg_keys_user'], true);

                    foreach ($successesData as $key => $successData) {
                        if ($successData === '1') {
                            if (is_array($removesQrpgKeysUser[$key]) && count($removesQrpgKeysUser[$key]) > 0) {
                                foreach (array_keys($removesQrpgKeysUser[$key]) as $tkeyid) {
                                    if (
                                        isset($temporaryKeysMaxTime[$tkeyid]) && $temporaryKeyData['created_at'] > $temporaryKeysMaxTime[$tkeyid] && in_array(
                                            $tkeyid,
                                            $myQrpgKeys,
                                            true,
                                        )
                                    ) {
                                        unset($myQrpgKeys[array_search($tkeyid, $myQrpgKeys, true)]);
                                        unset($keysTimers[$tkeyid]);
                                    }
                                }
                            }
                        }
                    }
                }
                $myQrpgKeys = array_unique($myQrpgKeys);

                /* добавляем к списку ключей пользователя сборные ключи (перепроверяем их до тех пор, пока ни одного не добавим, а то может быть вложенность большая */
                $consistingKeysData = [];
                $qrpgKeysConsistsOf = DB->query(
                    "SELECT * FROM qrpg_key WHERE project_id=:project_id AND consists_of IS NOT NULL AND consists_of NOT IN ('', '-', '--')",
                    [
                        ['project_id', $projectData->id->getAsInt()],
                    ],
                );

                foreach ($qrpgKeysConsistsOf as $qrpgKeyConsistsOf) {
                    $consistingKeysData[$qrpgKeyConsistsOf['id']] = DataHelper::multiselectToArray(
                        $qrpgKeyConsistsOf['consists_of'],
                    );
                }

                if (count($consistingKeysData) > 0) {
                    $foundNewConsistingKey = true;

                    while ($foundNewConsistingKey) {
                        $foundNewConsistingKey = false;

                        foreach ($consistingKeysData as $keyId => $keyConsistsOf) {
                            if (!in_array($keyId, $myQrpgKeys)) {
                                $includeKey = true;

                                foreach ($keyConsistsOf as $checkKey) {
                                    if (!in_array($checkKey, $myQrpgKeys)) {
                                        $includeKey = false;
                                    }
                                }

                                if ($includeKey) {
                                    $myQrpgKeys[] = $keyId;
                                    $foundNewConsistingKey = true;
                                }
                            }
                        }
                    }
                }
                $myQrpgKeys = array_unique($myQrpgKeys);

                if ($myQrpgKeys) {
                    $qrpgKeysData = DB->select(
                        tableName: 'qrpg_key',
                        criteria: [
                            'project_id' => $projectData->id->getAsInt(),
                            'id' => $myQrpgKeys,
                        ],
                    );

                    foreach ($qrpgKeysData as $qrpgKeyData) {
                        $imgPath = '';

                        if (DataHelper::escapeOutput($qrpgKeyData['img']) > 0) {
                            $imgPath = ABSOLUTE_PATH . '/design/qrpg/' . DataHelper::escapeOutput($qrpgKeyData['img']) . '.svg';
                        }
                        $qrpgAllKeys[] = [
                            'id' => $qrpgKeyData['id'],
                            'timer' => $keysTimers[$qrpgKeyData['id']] ?? '',
                            'name' => DataHelper::escapeOutput($qrpgKeyData[$qrpgKeyData['property_name'] ? 'property_name' : 'name']),
                            'img' => $imgPath,
                        ];
                    }
                }

                /* QRpg-свойства */
                $qrpgProperties = [];

                if (count($myQrpgKeys) > 0) {
                    $qrpgKeysWithProperties = DB->select(
                        tableName: 'qrpg_key',
                        criteria: [
                            'project_id' => $projectData->id->getAsInt(),
                            ['property_name', '', [OperandEnum::NOT_EQUAL]],
                            'id' => $myQrpgKeys,
                        ],
                    );

                    foreach ($qrpgKeysWithProperties as $qrpgKeyWithProperties) {
                        $qrpgProperties[] = [
                            'id' => $qrpgKeyWithProperties['id'],
                            'timer' => $keysTimers[$qrpgKeyWithProperties['id']] ?? '',
                            'name' => DataHelper::escapeOutput($qrpgKeyWithProperties['property_name']),
                            'description' => TextHelper::basePrepareText(
                                TextHelper::mp3ToPlayer(
                                    $this->qrpgDrawCodeInText(
                                        $projectData->id->getAsInt(),
                                        $qrpgKeyWithProperties['id'],
                                        'key',
                                        DataHelper::escapeOutput($qrpgKeyWithProperties['property_description'], EscapeModeEnum::forHTMLforceNewLines),
                                    ),
                                ),
                            ),
                        ];
                    }
                }

                foreach ($qrpgProperties as $qrpgProperty) {
                    $qrpgPropertiesList .= '<div class="qrpg_property_description">' . $qrpgProperty['description'] . '</div><div class="qrpg_property_name"' . ($qrpgProperty['timer'] !== '' ? ' id="qrpg_property_name_' . $qrpgProperty['id'] . '" timer="' . (
                        $qrpgProperty['timer'] - time()
                    ) . '"' : '') . '>' . $qrpgProperty['name'] . '</div>';
                }

                foreach ($qrpgAllKeys as $qrpgAllKey) {
                    $qrpgKeysList .= '<div class="qrpg_key"' . ($qrpgAllKey['timer'] !== '' ? ' id="qrpg_key_' . $qrpgAllKey['id'] . '" timer="' .
                        ($qrpgAllKey['timer'] - time()) . '"' : '') . '>' .
                        ($qrpgAllKey['img'] !== '' ? '<div class="qrpg_key_icon" style="' . DesignHelper::getCssBackgroundImage($qrpgAllKey['img']) . '"></div>' : '') .
                        '<div class="qrpg_key_name">' . $qrpgAllKey['name'] . '</div></div>';
                }
            }
        }

        return [
            'response' => 'success',
            'response_data' => [
                'qrpg_properties_list' => $qrpgPropertiesList,
                'qrpg_keys_list' => $qrpgKeysList,
                'my_qrpg_keys' => $myQrpgKeys,
            ],
        ];
    }

    /** Расшифровка кода в QRpg */
    public function qrpgDecode(
        ?array $qrpgData,
        ?array $hackingSequence,
        int $qhaId,
        ?string $checkTextToAccess,
        int $qhiId,
    ): array {
        $LOCALE = LocaleHelper::getLocale(['ingame', 'global']);

        $returnArr = [];

        $projectId = $qrpgData['p'];
        $qrpgCodeId = $qrpgData['c'];
        $qrpgHash = $qrpgData['h'];

        $recipientId = (int) ($qrpgData['r'] ?? 0);
        $recipientData = null;

        if ($recipientId > 0) {
            $recipientData = $this->myapplicationService->get($recipientId);
        }
        $bankCurrencyId = (int) ($qrpgData['cu'] ?? 0);
        $amount = (int) ($qrpgData['a'] ?? 0);
        $name =
            mb_convert_encoding(
                $qrpgData['n'] ?? '',
                'cp1252',
                'utf-8',
            ); // по какой-то причине распознается не в той кодировке

        $qrpgHistoryData = [];

        /* если нам в рамках хакинга пришли данные по уже созданному qrpg_history */
        $qrpgHackingData = [];

        if (!is_null($hackingSequence) && $qhaId > 0 && $this->getApplicationId()) {
            $qrpgHackingData = DB->findObjectById($qhaId, 'qrpg_hacking');
            $qrpgHistoryData = DB->findObjectById($qrpgHackingData['qrpg_history_id'], 'qrpg_history');

            if ($qrpgHistoryData['creator_id'] === CURRENT_USER->id() && $qrpgHistoryData['application_id'] === $this->getApplicationId()) {
                $qrpgCodeId = $qrpgHistoryData['qrpg_code_id'];
                $projectId = $qrpgHistoryData['project_id'];
                $qrpgHash = mb_substr(md5('qrpg' . $projectId . 'qrpg' . $qrpgCodeId . 'qrpg'), 0, 8);
            }
        }

        /* если нам в рамках ввода текста пришли данные с текстом */
        if (!is_null($checkTextToAccess) && $qhiId > 0 && $this->getApplicationId()) {
            $qrpgHistoryData = DB->findObjectById($qhiId, 'qrpg_history');

            if ($qrpgHistoryData['creator_id'] === CURRENT_USER->id() && $qrpgHistoryData['application_id'] === $this->getApplicationId()) {
                $qrpgCodeId = $qrpgHistoryData['qrpg_code_id'];
                $projectId = $qrpgHistoryData['project_id'];
                $qrpgHash = mb_substr(md5('qrpg' . $projectId . 'qrpg' . $qrpgCodeId . 'qrpg'), 0, 8);
            }
        }

        $applicationData = null;

        if ($this->getApplicationId()) {
            $applicationData = $this->myapplicationService->get($this->getApplicationId());
        }

        if (!$this->getApplicationId() || ($applicationData?->project_id->getAsInt() !== $projectId && $applicationData?->project_id->getAsInt() !== $recipientData?->project_id->getAsInt())) {
            $returnArr = [
                'response' => 'success',
                'response_data' => [
                    'header' => $LOCALE['messages']['wrong_project_header'],
                    'description' => $LOCALE['messages']['wrong_project'],
                ],
            ];
        } elseif ($projectId > 0 && $qrpgCodeId > 0 && $qrpgHash !== '') {
            $qrpgCodeData = DB->findObjectById($qrpgCodeId, 'qrpg_code');

            if (
                $qrpgCodeData['project_id'] === $projectId && $qrpgHash === mb_substr(
                    md5('qrpg' . $projectId . 'qrpg' . $qrpgCodeId . 'qrpg'),
                    0,
                    8,
                )
            ) {
                // $projectAdmin = RightsHelper::checkRights(array('{admin}', '{gamemaster}'), '{project}', $projectId);

                $userQrpgKeysData = $this->myapplicationService->get($this->getApplicationId());
                $applicationQrpgKeys = $userQrpgKeysData->qrpg_key->get();

                foreach ($applicationQrpgKeys as $key => $value) {
                    $applicationQrpgKeys[$key] = (int) $value;
                }
                $userQrpgKeys = $applicationQrpgKeys;

                /* добавляем к списку ключей пользователя ключи, которые получаются временно */
                $temporaryKeysMaxTime = [];
                $temporaryKeysData = DB->query(
                    "SELECT qh.*, qc.gives_qrpg_keys, qc.gives_qrpg_keys_for_minutes, qc.gives_bad_qrpg_keys, qc.gives_bad_qrpg_keys_for_minutes FROM qrpg_history AS qh LEFT JOIN qrpg_code AS qc ON qc.id=qh.qrpg_code_id WHERE qh.project_id=:project_id AND qh.creator_id=:creator_id AND ((qc.gives_qrpg_keys IS NOT NULL AND qc.gives_qrpg_keys != '[]') OR (qc.gives_bad_qrpg_keys IS NOT NULL AND qc.gives_bad_qrpg_keys_for_minutes != '[]'))",
                    [
                        ['project_id', $projectId],
                        ['creator_id', CURRENT_USER->id()],
                    ],
                );

                foreach ($temporaryKeysData as $temporaryKeyData) {
                    $successesData = json_decode($temporaryKeyData['success'], true);
                    $givesQrpgKeys = json_decode($temporaryKeyData['gives_qrpg_keys'], true);
                    $givesQrpgKeysForMinutes = json_decode(
                        $temporaryKeyData['gives_qrpg_keys_for_minutes'],
                        true,
                    );
                    $givesBadQrpgKeys = json_decode($temporaryKeyData['gives_bad_qrpg_keys'], true);
                    $givesBadQrpgKeysForMinutes = json_decode(
                        $temporaryKeyData['gives_bad_qrpg_keys_for_minutes'],
                        true,
                    );

                    foreach ($successesData as $key => $successData) {
                        if ($successData === '1') {
                            if (
                                (int) $givesQrpgKeysForMinutes[$key] > 0 && $temporaryKeyData['created_at'] >= time() - (int) $givesQrpgKeysForMinutes[$key] * 60
                            ) {
                                $userQrpgKeys = array_merge($userQrpgKeys, array_keys($givesQrpgKeys[$key]));

                                foreach (array_keys($givesQrpgKeys[$key]) as $tkeyid) {
                                    if (!isset($temporaryKeysMaxTime[$tkeyid]) || $temporaryKeyData['created_at'] > $temporaryKeysMaxTime[$tkeyid]) {
                                        $temporaryKeysMaxTime[$tkeyid] = $temporaryKeyData['created_at'];
                                    }
                                }
                            }
                        } elseif ($successData === '0') {
                            if (
                                (int) $givesBadQrpgKeysForMinutes[$key] > 0 && $temporaryKeyData['created_at'] >= time() - (int) $givesBadQrpgKeysForMinutes[$key] * 60
                            ) {
                                $userQrpgKeys = array_merge($userQrpgKeys, array_keys($givesBadQrpgKeys[$key]));

                                foreach (array_keys($givesBadQrpgKeys[$key]) as $tkeyid) {
                                    if (!isset($temporaryKeysMaxTime[$tkeyid]) || $temporaryKeyData['created_at'] > $temporaryKeysMaxTime[$tkeyid]) {
                                        $temporaryKeysMaxTime[$tkeyid] = $temporaryKeyData['created_at'];
                                    }
                                }
                            }
                        }
                    }
                }
                $userQrpgKeys = array_unique($userQrpgKeys);

                /* убираем из списка ключей пользователя ключи, которые получались временно, а ПОСЛЕ ЭТОГО были забраны по скану какого-то кода */
                $temporaryKeysData = DB->query(
                    "SELECT qh.*, qc.removes_qrpg_keys_user FROM qrpg_history AS qh LEFT JOIN qrpg_code AS qc ON qc.id=qh.qrpg_code_id WHERE qh.project_id=:project_id AND qh.creator_id=:creator_id AND (qc.removes_qrpg_keys_user IS NOT NULL AND qc.removes_qrpg_keys_user != '[]')",
                    [
                        ['project_id', $projectId],
                        ['creator_id', CURRENT_USER->id()],
                    ],
                );

                foreach ($temporaryKeysData as $temporaryKeyData) {
                    $successesData = json_decode($temporaryKeyData['success'], true);
                    $removesQrpgKeysUser = json_decode($temporaryKeyData['removes_qrpg_keys_user'], true);

                    foreach ($successesData as $key => $successData) {
                        if ($successData === '1') {
                            if ($removesQrpgKeysUser[$key]) {
                                foreach (array_keys($removesQrpgKeysUser[$key]) as $tkeyid) {
                                    if (
                                        isset($temporaryKeysMaxTime[$tkeyid]) && $temporaryKeyData['created_at'] > $temporaryKeysMaxTime[$tkeyid] && in_array(
                                            $tkeyid,
                                            $userQrpgKeys,
                                            true,
                                        )
                                    ) {
                                        unset($userQrpgKeys[array_search($tkeyid, $userQrpgKeys, true)]);
                                    }
                                }
                            }
                        }
                    }
                }
                $userQrpgKeys = array_unique($userQrpgKeys);

                /* добавляем к списку ключей пользователя сборные ключи (перепроверяем их до тех пор, пока ни одного не добавим, а то может быть вложенность большая */
                $consistingKeysData = [];
                $qrpgKeysConsistsOf = DB->query(
                    "SELECT * FROM qrpg_key WHERE project_id=:project_id AND consists_of IS NOT NULL AND consists_of NOT IN ('', '-', '--')",
                    [
                        ['project_id', $projectId],
                    ],
                );

                foreach ($qrpgKeysConsistsOf as $qrpgKeyConsistsOf) {
                    $consistingKeysData[$qrpgKeyConsistsOf['id']] = DataHelper::multiselectToArray(
                        $qrpgKeyConsistsOf['consists_of'],
                    );
                }

                if (count($consistingKeysData) > 0) {
                    $foundNewConsistingKey = true;

                    while ($foundNewConsistingKey) {
                        $foundNewConsistingKey = false;

                        foreach ($consistingKeysData as $keyId => $keyConsistsOf) {
                            if (!in_array($keyId, $userQrpgKeys)) {
                                $includeKey = true;

                                foreach ($keyConsistsOf as $checkKey) {
                                    if (!in_array($checkKey, $userQrpgKeys)) {
                                        $includeKey = false;
                                    }
                                }

                                if ($includeKey) {
                                    $userQrpgKeys[] = $keyId;
                                    $foundNewConsistingKey = true;
                                }
                            }
                        }
                    }
                }
                $userQrpgKeys = array_unique($userQrpgKeys);

                // if (count($userQrpgKeys) > 0 || $projectAdmin) {
                if (count($userQrpgKeys) > 0) {
                    $headers = [];
                    $descriptions = [];
                    $successes = [];
                    $removeCopiesSuccesses = [];
                    $currenciesSuccesses = [];
                    $checkedSection = [];

                    $qrpgKeys = json_decode($qrpgCodeData['qrpg_keys'], true);
                    $notQrpgKeys = json_decode($qrpgCodeData['not_qrpg_keys'], true);
                    $qrpgDescriptions = json_decode($qrpgCodeData['description'], true);
                    $removesQrpgKeys = json_decode($qrpgCodeData['removes_qrpg_keys'], true);
                    $removesQrpgKeysUser = json_decode($qrpgCodeData['removes_qrpg_keys_user'], true);
                    $removesCopiesOfQrpgCodes = json_decode($qrpgCodeData['removes_copies_of_qrpg_codes'], true);
                    $givesQrpgKeys = json_decode($qrpgCodeData['gives_qrpg_keys'], true);
                    $givesQrpgKeysForMinutes = json_decode($qrpgCodeData['gives_qrpg_keys_for_minutes'], true);

                    $givesBankCurrencyAmount = json_decode($qrpgCodeData['gives_bank_currency_amount'], true);
                    $givesBankCurrency = json_decode($qrpgCodeData['gives_bank_currency'], true);
                    $givesBankCurrencyTotalTimes = json_decode($qrpgCodeData['gives_bank_currency_total_times'], true);
                    $givesBankCurrencyOnceInMinutes = json_decode($qrpgCodeData['gives_bank_currency_once_in_minutes'], true);
                    $givesBankCurrencyTotalTimesUser = json_decode($qrpgCodeData['gives_bank_currency_total_times_user'], true);
                    $givesBankCurrencyOnceInMinutesUser = json_decode($qrpgCodeData['gives_bank_currency_once_in_minutes_user'], true);

                    $hackingSettings = json_decode($qrpgCodeData['hacking_settings'], true);

                    $textToAccess = json_decode($qrpgCodeData['text_to_access'], true);
                    $givesBadQrpgKeys = json_decode($qrpgCodeData['gives_bad_qrpg_keys'], true);
                    $givesBadQrpgKeysForMinutes = json_decode($qrpgCodeData['gives_bad_qrpg_keys_for_minutes'], true);
                    $qrpgBadDescriptions = json_decode($qrpgCodeData['description_bad'], true);

                    $previousSuccesses = [];

                    if (($qrpgHistoryData['id'] ?? 0) > 0) {
                        $previousSuccesses = json_decode($qrpgHistoryData['success'], true);
                    }
                    $newQrpgHackingIds = [];
                    $newTextAccessIds = [];

                    $foundNewCodes = true;

                    while ($foundNewCodes) {
                        $foundNewCodes = false;

                        if ($qrpgKeys) {
                            foreach ($qrpgKeys as $groupKey => $qrpgKeysData) {
                                if (!isset($successes[$groupKey])) {
                                    $successes[$groupKey] = '0';
                                }

                                if (!isset($currenciesSuccesses[$groupKey])) {
                                    $currenciesSuccesses[$groupKey] = '';
                                }

                                if (!isset($checkedSection[$groupKey])) {
                                    $checkedSection[$groupKey] = '0';
                                }

                                $hasKeys = [];

                                foreach ($qrpgKeysData as $qrpgCodeKey => $uselessValue) {
                                    if (in_array($qrpgCodeKey, $userQrpgKeys)) {
                                        $keyData = DB->findObjectById($qrpgCodeKey, 'qrpg_key');
                                        $hasKeys[] = DataHelper::escapeOutput($keyData['name']);
                                    }
                                }

                                $notHasKeys = [];

                                if (($notQrpgKeys[$groupKey] ?? '') === '') {
                                    $notQrpgKeys[$groupKey] = [];
                                }

                                foreach ($notQrpgKeys[$groupKey] as $qrpgCodeKey => $uselessValue) {
                                    if (!in_array($qrpgCodeKey, $userQrpgKeys)) {
                                        $notHasKeys[] = $qrpgCodeKey;
                                    }
                                }

                                /*if ($checkedSection[$groupKey] != '1' && ((count($hasKeys) == count(
                                            $qrpgKeysData
                                        ) && count($notHasKeys) == count(
                                            $notQrpgKeys[$groupKey]
                                        )) || $projectAdmin)) {*/
                                if (
                                    $checkedSection[$groupKey] !== '1' && (
                                        count($hasKeys) === count(
                                            $qrpgKeysData,
                                        ) && count($notHasKeys) === count(
                                            $notQrpgKeys[$groupKey],
                                        )
                                    )
                                ) {
                                    $foundNewCodes = true;
                                    $checkedSection[$groupKey] = '1';

                                    $allowAction = true;

                                    if ($hackingSettings[$groupKey] !== '' && $previousSuccesses[$groupKey] !== '1' && $previousSuccesses[$groupKey] !== '?') { // если есть настройка хакинга и при этом нет подтвержденного успешного взлома в предыдущей попытке скана и это не передача текстового кода для входа
                                        $allowAction = false;

                                        DB->delete(
                                            tableName: 'qrpg_hacking',
                                            criteria: [
                                                ['created_at', time() - 24 * 3600, [OperandEnum::LESS_OR_EQUAL]],
                                            ],
                                        );

                                        if (
                                            !is_null(
                                                $hackingSequence,
                                            ) && $qrpgHistoryData['id'] > 0 && $previousSuccesses[$groupKey] === '-'
                                        ) { // если нам пришли из фронтенда данные, при этом взлом был запущен
                                            $qhaMatrix = json_decode($qrpgHackingData['matrix'], true);

                                            if (
                                                time() <= $qrpgHackingData['started_at'] + $qrpgHackingData['timer'] + 20 && count(
                                                    $hackingSequence,
                                                ) <= $qrpgHackingData['input_length']
                                            ) { // проверяем время начала + таймер + 20 секунд на издержки коннекта, а также проверяем, что количество присланных символов не больше разрешенного количества ввода
                                                $qhaSequences = json_decode($qrpgHackingData['sequences'], true);

                                                foreach ($hackingSequence as $hackingSequencePositions) { // проверяем введенные данные на соответствие последовательностям
                                                    $hackingSequencePiece = $qhaMatrix[$hackingSequencePositions[0]][$hackingSequencePositions[1]];

                                                    foreach ($qhaSequences as $qhaSequenceKey => $qhaSequence) {
                                                        foreach ($qhaSequence as $qhaElementKey => $qhaElement) {
                                                            if ($qhaElement === $hackingSequencePiece && ($qhaElementKey === 0 || $qhaSequence[$qhaElementKey - 1])) { // если это первый элемент в последовательности или же предыдущий элемент уже успешно пройден
                                                                $qhaSequences[$qhaSequenceKey][$qhaElementKey] = true;
                                                            }
                                                        }
                                                    }
                                                }

                                                $goodSequences = true;

                                                foreach ($qhaSequences as $qhaSequence) {
                                                    foreach ($qhaSequence as $qhaElement) {
                                                        if (!($qhaElement === true)) { // если хотя бы один элемент последовательностей не был найден
                                                            $goodSequences = false;
                                                            break;
                                                        }
                                                    }
                                                }

                                                if ($goodSequences) {
                                                    $allowAction = true; // этого достаточно, т.к. дальше будет выставлен успех и выдан текст
                                                } else { // в ином случае сообщаем браузере о провале, а в базу "0" проставится и так
                                                    $headers[] = 'xk1kljd9cjsa3';

                                                    if ($qrpgBadDescriptions[$groupKey] !== '') {
                                                        $descriptions[] = TextHelper::basePrepareText(
                                                            TextHelper::mp3ToPlayer(
                                                                $this->qrpgDrawCodeInText(
                                                                    $projectId,
                                                                    $qrpgCodeId,
                                                                    'code',
                                                                    DataHelper::escapeOutput($qrpgBadDescriptions[$groupKey], EscapeModeEnum::forHTMLforceNewLines),
                                                                ),
                                                            ),
                                                        );
                                                    } else {
                                                        $descriptions[] = -1;
                                                    }
                                                }
                                            }
                                        } elseif (!isset($previousSuccesses[$groupKey])) { // если взлом не был запущен
                                            $successes[$groupKey] = '-';

                                            $headers[] = 'xk1kljd9cjsa3';

                                            /* создаем qrpg_hacking запись */
                                            $matrix = [];
                                            $sequences = [];
                                            $timer = 60;

                                            $matrixHeight = 5; // пять строчек
                                            $matrixWidth = 5; // пять колонок

                                            /* генерим матрицу */
                                            $possibleMatrixPairs = [];

                                            for ($i = 0; $i < 10; ++$i) { // десять разных потенциальных вариантов символов в матрице
                                                $possibleMatrixPairs[] = $this->getQRpgHackingMatrixPair();
                                            }

                                            for ($i = 0; $i < $matrixHeight; ++$i) {
                                                for ($j = 0; $j < $matrixWidth; ++$j) {
                                                    $randomPair = rand(0, count($possibleMatrixPairs) - 1);
                                                    $matrix[$i][$j] = $possibleMatrixPairs[$randomPair];
                                                }
                                            }

                                            /* генерим последовательности и устанавливаем длину ввода в зависимости от уровня сложности */
                                            if ($hackingSettings[$groupKey] === 'max') {
                                                $sequencesLength = [4, 3, 4];
                                                $inputLength = 11; // +0 к сумме длин последовательностей
                                            } elseif ($hackingSettings[$groupKey] === 'avg') {
                                                $sequencesLength = [3, 4, 2];
                                                $inputLength = 10; // +1 к сумме длин последовательностей
                                            } else {
                                                $sequencesLength = [2, 3, 2];
                                                $inputLength = 9; // +2 к сумме длин последовательностей
                                            }

                                            $alreadyChosen = [];
                                            $row = 0; // начинаем с верхнего ряда всегда
                                            $col = rand(0, $matrixWidth - 1); // но со случайной колонки
                                            $nextIsRowOrCol = 'row';

                                            foreach ($sequencesLength as $sequenceLength) { // формируем последовательности
                                                $sequenceArray = [];

                                                for ($i = 0; $i < $sequenceLength; ++$i) { // проходим по матрице, каждый раз смещаясь последовательно то на ряд то на колонку
                                                    $alreadyChosen[$row][$col] = true;
                                                    $sequenceArray[] = $matrix[$row][$col];

                                                    if ($nextIsRowOrCol === 'row') {
                                                        $oldRow = $row;

                                                        /** @phpstan-ignore-next-line */
                                                        while ($row === $oldRow || $alreadyChosen[$row][$col]) {
                                                            $row = rand(0, $matrixHeight - 1);
                                                        }
                                                    } else {
                                                        $oldCol = $col;

                                                        /** @phpstan-ignore-next-line */
                                                        while ($col === $oldCol || $alreadyChosen[$row][$col]) {
                                                            $col = rand(0, $matrixWidth - 1);
                                                        }
                                                    }

                                                    /** @phpstan-ignore-next-line */
                                                    $nextIsRowOrCol = $nextIsRowOrCol === 'col' ? 'row' : 'col';
                                                }
                                                $sequences[] = $sequenceArray;
                                            }

                                            /* произвольно перемешиваем порядок последовательностей */
                                            shuffle($sequences);

                                            DB->insert(
                                                tableName: 'qrpg_hacking',
                                                data: [
                                                    'creator_id' => CURRENT_USER->id(),
                                                    'project_application_id' => $this->getApplicationId(),
                                                    'qrpg_code_id' => $qrpgCodeId,
                                                    'qrpg_code_group' => $groupKey,
                                                    'matrix' => DataHelper::jsonFixedEncode($matrix),
                                                    'sequences' => DataHelper::jsonFixedEncode($sequences),
                                                    'input_length' => $inputLength,
                                                    'timer' => $timer,
                                                    'created_at' => DateHelper::getNow(),
                                                    'updated_at' => DateHelper::getNow(),
                                                ],
                                            );
                                            $qrpgHackingId = DB->lastInsertId();
                                            $descriptions[] = $qrpgHackingId;

                                            $newQrpgHackingIds[] = $qrpgHackingId;
                                        } elseif ($previousSuccesses[$groupKey] === '-') { // если взлом был запущен, но еще не пройден, для целостности пишем это сразу
                                            $successes[$groupKey] = '-';
                                        }
                                    }

                                    if ($allowAction && $textToAccess[$groupKey] !== '' && $previousSuccesses[$groupKey] !== '1') { // если есть настройка текстового ввода и при этом нет подтвержденного успешного ввода в предыдущей попытке скана
                                        $allowAction = false;

                                        if (!is_null($checkTextToAccess) && $qrpgHistoryData['id'] > 0 && $previousSuccesses[$groupKey] === '?') {
                                            // если нам пришли из фронтенда данные, при этом поле для ввода было выдано
                                            if (mb_strtolower($checkTextToAccess) === mb_strtolower(DataHelper::escapeOutput($textToAccess[$groupKey]))) {
                                                $allowAction = true; // этого достаточно, т.к. дальше будет выставлен успех и выдан текст
                                            } else { // в ином случае сообщаем браузере о провале, а в базу "0" проставится и так
                                                $headers[] = 'j3jkcnsmmxu82';

                                                if ($qrpgBadDescriptions[$groupKey] !== '') {
                                                    $descriptions[] = TextHelper::basePrepareText(
                                                        TextHelper::mp3ToPlayer(
                                                            $this->qrpgDrawCodeInText(
                                                                $projectId,
                                                                $qrpgCodeId,
                                                                'code',
                                                                DataHelper::escapeOutput($qrpgBadDescriptions[$groupKey], EscapeModeEnum::forHTMLforceNewLines),
                                                            ),
                                                        ),
                                                    );
                                                } else {
                                                    $descriptions[] = -1;
                                                }

                                                // выдаем ключи при провале
                                                /* если данная группа действий кода передает ключи навсегда, а не на время, то добавляем в заявку пользователя */
                                                if ((int) $givesBadQrpgKeysForMinutes[$groupKey] === 0) {
                                                    $userApplication = $this->myapplicationService->get($this->getApplicationId());
                                                    $shiftedQrpgKeys = $userApplication->qrpg_key->get();

                                                    foreach ($givesBadQrpgKeys[$groupKey] as $textGivesBadQrpgKeyId => $anotherUselessValue) {
                                                        $shiftedQrpgKeys[] = $textGivesBadQrpgKeyId;
                                                        $userQrpgKeys[] = $textGivesBadQrpgKeyId;
                                                    }
                                                    $shiftedQrpgKeys = array_unique($shiftedQrpgKeys);
                                                    DB->update(
                                                        tableName: 'project_application',
                                                        data: [
                                                            'qrpg_key' => DataHelper::arrayToMultiselect($shiftedQrpgKeys),
                                                        ],
                                                        criteria: [
                                                            'id' => $this->getApplicationId(),
                                                        ],
                                                    );

                                                    $userQrpgKeys = array_unique($userQrpgKeys);
                                                }
                                            }
                                        } elseif (!isset($previousSuccesses[$groupKey])) { // если поле для ввода не было выдано
                                            $successes[$groupKey] = '?';

                                            $headers[] = 'j3jkcnsmmxu82';

                                            $descriptions[] = 0;

                                            $newTextAccessIds[] = count($descriptions) - 1;
                                        } elseif ($previousSuccesses[$groupKey] === '?') { // если ввод кода был запущен, но еще не пройден, для целостности пишем это сразу
                                            $successes[$groupKey] = '?';
                                        }
                                    }

                                    if ($allowAction) {
                                        /* проверка достаточности копий для уменьшения */
                                        $removesCopiesOfQrpgCodesCopiesCounts = [];

                                        if ($removesCopiesOfQrpgCodes[$groupKey] ?? []) {
                                            /* проверяем, нет ли в прошлом успешного вскрытия данной группы данного кода. Если есть, то не надо списывать ничего повторно. */
                                            $checkHistoryData = DB->query(
                                                'SELECT qh.* FROM qrpg_history AS qh WHERE qh.project_id=:project_id AND qh.creator_id=:creator_id AND qh.application_id=:application_id AND qh.qrpg_code_id=:qrpg_code_id ORDER BY created_at DESC',
                                                [
                                                    ['project_id', $projectId],
                                                    ['creator_id', CURRENT_USER->id()],
                                                    ['application_id', $this->getApplicationId()],
                                                    ['qrpg_code_id', $qrpgCodeId],
                                                ],
                                                true,
                                            );
                                            $successesData = json_decode(
                                                $checkHistoryData['remove_copies_success'],
                                                true,
                                            );

                                            if ($successesData[$groupKey] !== '1' && $successesData[$groupKey] !== '-') {
                                                foreach ($removesCopiesOfQrpgCodes[$groupKey] as $removesQrpgCodeCopyId => $anotherUselessValue) {
                                                    $removeQrpgCodeData = DB->findObjectById(
                                                        $removesQrpgCodeCopyId,
                                                        'qrpg_code',
                                                    );

                                                    if ($removeQrpgCodeData['copies'] > 0) {
                                                        $removesCopiesOfQrpgCodesCopiesCounts[$removesQrpgCodeCopyId] = [
                                                            $removeQrpgCodeData['copies'],
                                                            DataHelper::escapeOutput($removeQrpgCodeData['sid']),
                                                        ];
                                                    } else {
                                                        $allowAction = false;
                                                        $removesCopiesOfQrpgCodesCopiesCounts = [];
                                                        $headers[] = $LOCALE['messages']['no_access_header'];
                                                        $descriptions[] = $LOCALE['messages']['not_enough_copies'];
                                                        $successes[$groupKey] = '0';
                                                        $removeCopiesSuccesses[$groupKey] = '0';
                                                        break;
                                                    }
                                                }
                                            } else {
                                                $removeCopiesSuccesses[$groupKey] = '-';
                                            }
                                        }

                                        if ($allowAction) {
                                            $successes[$groupKey] = '1';

                                            /*$headers[] = $projectAdmin ? $LOCALE['admin'] : implode(
                                            ' + ',
                                            $hasKeys
                                        );*/
                                            $headers[] = implode(
                                                ' + ',
                                                $hasKeys,
                                            );
                                            $descriptions[] = TextHelper::basePrepareText(
                                                TextHelper::mp3ToPlayer(
                                                    $this->qrpgDrawCodeInText(
                                                        $projectId,
                                                        $qrpgCodeId,
                                                        'code',
                                                        DataHelper::escapeOutput($qrpgDescriptions[$groupKey], EscapeModeEnum::forHTMLforceNewLines),
                                                    ),
                                                ),
                                            );

                                            /* забираем ключи, если что-то указано, у пользователя */
                                            $changedUserKeys = false;

                                            foreach (($removesQrpgKeysUser[$groupKey] ?? []) as $removesQrpgKeyId => $anotherUselessValue) {
                                                if (
                                                    in_array(
                                                        (int) $removesQrpgKeyId,
                                                        $applicationQrpgKeys,
                                                        true,
                                                    )
                                                ) {
                                                    unset(
                                                        $applicationQrpgKeys[array_search(
                                                            (int) $removesQrpgKeyId,
                                                            $applicationQrpgKeys,
                                                            true,
                                                        )],
                                                    );
                                                    $changedUserKeys = true;
                                                    unset(
                                                        $userQrpgKeys[array_search(
                                                            (int) $removesQrpgKeyId,
                                                            $userQrpgKeys,
                                                            true,
                                                        )],
                                                    );
                                                }
                                            }

                                            if ($changedUserKeys) {
                                                DB->update(
                                                    tableName: 'project_application',
                                                    data: [
                                                        'qrpg_key' => DataHelper::arrayToMultiselect($applicationQrpgKeys),
                                                    ],
                                                    criteria: [
                                                        'id' => $this->getApplicationId(),
                                                    ],
                                                );
                                            }

                                            /* забираем ключи, если что-то указано, из других заявок */
                                            foreach (($removesQrpgKeys[$groupKey] ?? []) as $removesQrpgKeyId => $anotherUselessValue) {
                                                $otherApplications = $this->myapplicationService->getAll(
                                                    criteria: [
                                                        ['qrpg_key', '%-' . $removesQrpgKeyId . '-%', [OperandEnum::LIKE]],
                                                    ],
                                                );

                                                foreach ($otherApplications as $otherApplication) {
                                                    $shiftedQrpgKeys = $otherApplication->qrpg_key->get();

                                                    foreach ($shiftedQrpgKeys as $key => $value) {
                                                        $shiftedQrpgKeys[$key] = (int) $value;
                                                    }

                                                    if (
                                                        in_array(
                                                            (int) $removesQrpgKeyId,
                                                            $shiftedQrpgKeys,
                                                            true,
                                                        )
                                                    ) {
                                                        unset(
                                                            $shiftedQrpgKeys[array_search(
                                                                (int) $removesQrpgKeyId,
                                                                $shiftedQrpgKeys,
                                                                true,
                                                            )],
                                                        );
                                                        DB->update(
                                                            tableName: 'project_application',
                                                            data: [
                                                                'qrpg_key' => DataHelper::arrayToMultiselect($shiftedQrpgKeys),
                                                            ],
                                                            criteria: [
                                                                'id' => $otherApplication->id->getAsInt(),
                                                            ],
                                                        );
                                                    }
                                                }
                                            }

                                            /* забираем копии кодов, если этот пользователь еще не делал этого */
                                            if (count($removesCopiesOfQrpgCodesCopiesCounts) > 0) {
                                                $removeCopiesSuccesses[$groupKey] = '1';
                                                $headers[] = $LOCALE['messages']['please_destroy'];
                                                $removesQrpgCodeCopySids = [];

                                                foreach ($removesCopiesOfQrpgCodesCopiesCounts as $removesQrpgCodeCopyId => $removesQrpgCodeCopyData) {
                                                    DB->update(
                                                        tableName: 'qrpg_code',
                                                        data: [
                                                            'copies' => $removesQrpgCodeCopyData[0] - 1,
                                                        ],
                                                        criteria: [
                                                            'id' => $removesQrpgCodeCopyId,
                                                        ],
                                                    );
                                                    $removesQrpgCodeCopySids[] = $removesQrpgCodeCopyData[1];
                                                }
                                                $descriptions[] = sprintf(
                                                    $LOCALE['messages']['destroy_following_codes'],
                                                    implode(',', $removesQrpgCodeCopySids),
                                                );
                                            }

                                            if ((int) $givesQrpgKeysForMinutes[$groupKey] === 0) {
                                                /* если данная группа действий кода передает ключи навсегда, а не на время, то добавляем в заявку пользователя */
                                                $userApplication = $this->myapplicationService->get($this->getApplicationId());
                                                $shiftedQrpgKeys = $userApplication->qrpg_key->get();

                                                foreach (($givesQrpgKeys[$groupKey] ?? []) as $givesQrpgKeyId => $anotherUselessValue) {
                                                    $shiftedQrpgKeys[] = $givesQrpgKeyId;
                                                    $userQrpgKeys[] = $givesQrpgKeyId;
                                                }
                                                $shiftedQrpgKeys = array_unique($shiftedQrpgKeys);
                                                DB->update(
                                                    tableName: 'project_application',
                                                    data: [
                                                        'qrpg_key' => DataHelper::arrayToMultiselect($shiftedQrpgKeys),
                                                    ],
                                                    criteria: [
                                                        'id' => $this->getApplicationId(),
                                                    ],
                                                );
                                            } else {
                                                /* если данная группа действий кода выдает ключи на время, то добавляем в список ключей, чтобы следующие условия могли правильно отработать */
                                                foreach ($givesQrpgKeys[$groupKey] as $givesQrpgKeyId => $anotherUselessValue) {
                                                    $userQrpgKeys[] = $givesQrpgKeyId;
                                                }
                                            }

                                            $userQrpgKeys = array_unique($userQrpgKeys);

                                            /* выдаем ресурс, если указана выдача ресурса с группы действий */
                                            if ((int) $givesBankCurrencyAmount[$groupKey] !== 0) {
                                                $amount = (int) $givesBankCurrencyAmount[$groupKey];
                                                $totalMaxTimes = (int) $givesBankCurrencyTotalTimes[$groupKey];
                                                $totalMaxTimesUser = (int) $givesBankCurrencyTotalTimesUser[$groupKey];

                                                if ($totalMaxTimesUser > $totalMaxTimes && $totalMaxTimes > 0) {
                                                    $totalMaxTimesUser = $totalMaxTimes;
                                                }

                                                $bankCurrencyId = 0;
                                                $bankCurrencyData = [];

                                                if ((int) $givesBankCurrency[$groupKey] > 0) {
                                                    $bankCurrencyId = (int) $givesBankCurrency[$groupKey];
                                                    $bankCurrencyData = DB->findObjectById(
                                                        $bankCurrencyId,
                                                        'bank_currency',
                                                    );
                                                }

                                                // выдавать ресурс, только если time_limit = 0 и данный код еще никем не открывался, или же если он больше 0, но прошло достаточно времени с предыдущего вскрытия кода
                                                $timeLimit = (int) $givesBankCurrencyOnceInMinutes[$groupKey];
                                                $timeLimitUser = (int) $givesBankCurrencyOnceInMinutesUser[$groupKey];
                                                $lastUserReceivedTimestamp = 0;
                                                $lastThisUserReceivedTimestamp = 0;
                                                $totalGained = 0;
                                                $totalGainedUser = 0;

                                                $keysCurrenciesData = DB->select(
                                                    tableName: 'qrpg_history',
                                                    criteria: [
                                                        'qrpg_code_id' => $qrpgCodeId,
                                                    ],
                                                );

                                                foreach ($keysCurrenciesData as $keysCurrencyData) {
                                                    $currenciesSuccessData = json_decode(
                                                        $keysCurrencyData['currencies_success'],
                                                        true,
                                                    );

                                                    if ($currenciesSuccessData[$groupKey] === '1') {
                                                        ++$totalGained;

                                                        if ($keysCurrencyData['creator_id'] === CURRENT_USER->id()) {
                                                            ++$totalGainedUser;
                                                        }
                                                    }

                                                    if ($currenciesSuccessData[$groupKey] === '1' && $keysCurrencyData['created_at'] > $lastUserReceivedTimestamp) {
                                                        $lastUserReceivedTimestamp = $keysCurrencyData['created_at'];
                                                    }

                                                    if (
                                                        $currenciesSuccessData[$groupKey] === '1' && $keysCurrencyData['created_at'] > $lastThisUserReceivedTimestamp && $keysCurrencyData['creator_id'] === CURRENT_USER->id()
                                                    ) {
                                                        $lastThisUserReceivedTimestamp = $keysCurrencyData['created_at'];
                                                    }
                                                }

                                                $currencyActionDescription = '';

                                                if (time() >= $lastUserReceivedTimestamp + ($timeLimit * 60) && time() >= $lastThisUserReceivedTimestamp + ($timeLimitUser * 60) && ($totalMaxTimes === 0 || $totalGained < $totalMaxTimes) && ($totalMaxTimesUser === 0 || $totalGainedUser < $totalMaxTimesUser)) {
                                                    $currenciesSuccesses[$groupKey] = '1';

                                                    DB->insert(
                                                        tableName: 'bank_transaction',
                                                        data: [
                                                            'project_id' => $projectId,
                                                            'name' => 'QRpg #' . $qrpgCodeData['sid'],
                                                            'to_project_application_id' => $this->getApplicationId(),
                                                            'from_project_application_id' => null,
                                                            'amount' => $amount,
                                                            'bank_currency_id' => $bankCurrencyId,
                                                            'creator_id' => CURRENT_USER->id(),
                                                            'created_at' => DateHelper::getNow(),
                                                            'updated_at' => DateHelper::getNow(),
                                                        ],
                                                    );

                                                    $currencyActionDescription = sprintf(
                                                        $amount >= 0 ? $LOCALE['messages']['received_resource'] : $LOCALE['messages']['lost_resource'],
                                                        $amount,
                                                        $bankCurrencyData['name'] !== '' ? ' (' . DataHelper::escapeOutput(
                                                            $bankCurrencyData['name'],
                                                        ) . ')' : '',
                                                    );
                                                } elseif (!(($totalMaxTimes === 0 || $totalGained < $totalMaxTimes) && ($totalMaxTimesUser === 0 || $totalGainedUser < $totalMaxTimesUser))) {
                                                    $currencyActionDescription = $LOCALE['messages']['not_received_resource_due_to_once'];
                                                    $currenciesSuccesses[$groupKey] = '0';
                                                } elseif (
                                                    !(time() >= $lastUserReceivedTimestamp + ($timeLimit * 60) && time() >= $lastThisUserReceivedTimestamp + ($timeLimitUser * 60))
                                                ) {
                                                    $currencyActionDescription = $LOCALE['messages']['not_received_resource_due_to_timeout'];
                                                    $currenciesSuccesses[$groupKey] = '0';
                                                }

                                                $descriptions[count(
                                                    $descriptions,
                                                ) - 1] .= '<br><br>' . $currencyActionDescription;
                                            }
                                        }
                                    }
                                } elseif ($qrpgBadDescriptions[$groupKey] !== '' && $checkedSection[$groupKey] !== '1') {
                                    $checkedSection[$groupKey] = '1';
                                    $successes[$groupKey] = '0';
                                    $headers[] = $LOCALE['messages']['no_access_header'];
                                    $descriptions[] = TextHelper::basePrepareText(
                                        TextHelper::mp3ToPlayer(
                                            $this->qrpgDrawCodeInText(
                                                $projectId,
                                                $qrpgCodeId,
                                                'code',
                                                DataHelper::escapeOutput($qrpgBadDescriptions[$groupKey], EscapeModeEnum::forHTMLforceNewLines),
                                            ),
                                        ),
                                    );
                                }
                            }
                        }
                    }

                    if (count($headers) > 0) {
                        if (!isset($qrpgHistoryData['id'])) { // если мы первый раз зашли в сканирование данных, а не возвращаемся для хакинга или ввода кода, например
                            DB->insert(
                                tableName: 'qrpg_history',
                                data: [
                                    'creator_id' => CURRENT_USER->id(),
                                    'project_id' => $projectId,
                                    'application_id' => $this->getApplicationId(),
                                    'qrpg_code_id' => $qrpgCodeId,
                                    'success' => DataHelper::jsonFixedEncode($successes),
                                    'remove_copies_success' => DataHelper::jsonFixedEncode($removeCopiesSuccesses),
                                    'currencies_success' => DataHelper::jsonFixedEncode($currenciesSuccesses),
                                    'created_at' => DateHelper::getNow(),
                                    'updated_at' => DateHelper::getNow(),
                                ],
                            );
                            $qrpgHistoryId = DB->lastInsertId();

                            foreach ($newQrpgHackingIds as $newQrpgHackingId) {
                                DB->update(
                                    tableName: 'qrpg_hacking',
                                    data: [
                                        'qrpg_history_id' => $qrpgHistoryId,
                                    ],
                                    criteria: [
                                        'id' => $newQrpgHackingId,
                                    ],
                                );
                            }

                            foreach ($newTextAccessIds as $newTextAccessId) {
                                $descriptions[$newTextAccessId] = $qrpgHistoryId;
                            }
                        } else { // в ином случае корректируем данные по успешности открытия кодов на основе текущих результатов
                            DB->update(
                                tableName: 'qrpg_history',
                                data: [
                                    'success' => DataHelper::jsonFixedEncode($successes),
                                    'remove_copies_success' => DataHelper::jsonFixedEncode($removeCopiesSuccesses),
                                    'currencies_success' => DataHelper::jsonFixedEncode($currenciesSuccesses),
                                    'updated_at' => DateHelper::getNow(),
                                ],
                                criteria: [
                                    'id' => $qrpgHistoryData['id'],
                                ],
                            );
                        }

                        $returnArr = [
                            'response' => 'success',
                            'response_data' => [
                                'headers' => $headers,
                                'descriptions' => $descriptions,
                            ],
                        ];
                    }
                }
            }
        } elseif (
            $recipientData->id->getAsInt() > 0 && $qrpgHash === mb_substr(
                md5('qrpg' . $recipientData->id->getAsInt() . 'bank' . $bankCurrencyId . 'qrpg' . $amount . 'qrpg'),
                0,
                8,
            )
        ) { // код на оплату
            $bankBalance = BankTransactionService::getApplicationBalances($this->getApplicationId());

            $bankCurrencyData = [];

            if ($bankCurrencyId > 0) {
                $bankCurrencyData = DB->findObjectById($bankCurrencyId, 'bank_currency');
            }

            $paymentHtml = '
            <div class="bank_balance">
                <div>' . $LOCALE['balance'] . '<span>' . (int) $bankBalance[$bankCurrencyId] . ($bankCurrencyData['name'] !== '' ? ' (' .
                mb_strtolower(DataHelper::escapeOutput($bankCurrencyData['name'])) . ')' : '') .
                '</span></div> <div class="small">' . $LOCALE['account_num_to'] . '<span>' . $recipientData->id->getAsInt() . '</span>' . ($name !== '' ? '. ' . $LOCALE['name'] . ':<span>' . $name . '</span>' : '') . '</div>
                <div>' . $LOCALE['amount'] . ':<span>' . $amount . '</span></div>
            </div>
            <input type="hidden" name="account_num_to" value="' . $recipientData->id->getAsInt() . '">
            <input type="hidden" name="amount" value="' . $amount . '">
            <input type="hidden" name="bank_currency_id" value="' . $bankCurrencyId . '">
            ' . ($name !== '' ? '<input type="hidden" name="name" value="' . $name . '">' : '') . '
            <center><button class="main" id="approve_payment">' . $LOCALE['approve'] . '</button></center>';

            $returnArr = [
                'response' => 'success',
                'response_data' => [
                    'header' => $LOCALE['payment'],
                    'description' => $paymentHtml,
                ],
            ];
        }

        if (count($returnArr) === 0) {
            if (
                !preg_match('/^[a-zA-Z0-9]+$/', $qrpgData['h']) || (!is_numeric($qrpgCodeId) && (!is_numeric(
                    $recipientId,
                ) || !isset($qrpgData['a'])))
            ) {
                $returnArr = [
                    'response' => 'success',
                    'response_data' => [
                        'header' => $LOCALE['messages']['errouneous_data_header'],
                        'description' => $LOCALE['messages']['errouneous_data'],
                    ],
                ];
            } else {
                $returnArr = [
                    'response' => 'success',
                    'response_data' => [
                        'header' => $LOCALE['messages']['no_access_header'],
                        'description' => $LOCALE['messages']['no_access'],
                    ],
                ];
            }

            if ((int) $projectId === 0) {
                $projectId = $applicationData->project_id->get();
            }

            if ((int) $qrpgCodeId === 0) {
                $qrpgCodeId = 0;
            }

            DB->insert(
                tableName: 'qrpg_history',
                data: [
                    'creator_id' => CURRENT_USER->id(),
                    'project_id' => $projectId,
                    'application_id' => $this->getApplicationId(),
                    'qrpg_code_id' => $qrpgCodeId,
                    'success' => '["-"]',
                    'created_at' => DateHelper::getNow(),
                ],
            );
        }

        return $returnArr;
    }

    /** Вывод кликабельного QR-кода внутри описания, а также вывода формы отправки сообщения в диалог */
    public function qrpgDrawCodeInText(int $projectId, int $objId, string $objType, string $content): string
    {
        if ($projectId > 0) {
            preg_match_all('#\[\#([^]]+)]#', $content, $matches);

            foreach ($matches[1] as $codeSid) {
                $qrpgCodeData = DB->select(
                    tableName: 'qrpg_code',
                    criteria: [
                        'project_id' => $projectId,
                        'sid' => $codeSid,
                    ],
                    oneResult: true,
                    order: [
                        'updated_at DESC',
                    ],
                    limit: 1,
                );

                if (($qrpgCodeData['id'] ?? 0) > 0) {
                    $content = preg_replace(
                        '#\[\#' . $codeSid . ']#',
                        '<img class="qrpg_code_data" src="' . ABSOLUTE_PATH . '/qrpg_generator/project_id=' . $projectId . '&qrpg_code_id=' . $qrpgCodeData['id'] . '&obj_type=' . $objType . '&obj_id=' . $objId . '" obj_data=\'' . DataHelper::jsonFixedEncode(
                            [
                                'p' => $projectId,
                                'c' => $qrpgCodeData['id'],
                                'h' => mb_substr(md5('qrpg' . $projectId . 'qrpg' . $qrpgCodeData['id'] . 'qrpg'), 0, 8),
                            ],
                        ) . '\'>',
                        $content,
                    );
                }
            }

            /* форма отправки сообщения в диалог с указанным пользователем */
            preg_match_all('#\[conversation\|([^]]+)]#', $content, $matches);

            foreach ($matches[1] as $userSid) {
                if (is_numeric($userSid)) {
                    $userData = $this->getUserService()->get(null, ['sid' => $userSid]);

                    if ($userData->id->getAsInt() > 0) {
                        $checkFriends = CURRENT_USER->isLogged() && RightsHelper::checkRights(
                            '{friend}',
                            '{user}',
                            CURRENT_USER->id(),
                            '{user}',
                            $userData->id->getAsInt(),
                        );

                        if (!$checkFriends) {
                            $this->getUserService()->becomeFriends($userData->id->getAsInt());
                        }

                        $rData = DB->query(
                            'SELECT r.obj_id_to FROM relation r LEFT JOIN conversation c ON c.id=r.obj_id_to WHERE r.obj_type_to="{conversation}" AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_id_from IN (:obj_id_froms) AND (c.name IS NULL OR c.name="") AND EXISTS (SELECT 1 FROM relation r2 WHERE r.obj_id_to=r2.obj_id_to AND r2.obj_type_to="{conversation}" AND r2.type="{member}" AND r2.obj_type_from="{user}" GROUP BY r2.obj_id_to HAVING COUNT(r2.obj_id_from)=2) GROUP BY r.obj_id_to HAVING COUNT(r.obj_id_from)=2 ORDER BY r.obj_id_to LIMIT 1',
                            [
                                ['obj_id_froms', [$userData->id->getAsInt(), CURRENT_USER->id()]],
                            ],
                            true,
                        );

                        if (($rData['obj_id_to'] ?? 0) > 0) {
                            $content = preg_replace(
                                '#\[conversation\|' . $userSid . ']#',
                                preg_replace(
                                    '#<div class="conversation_form_photo">(.*)<div class="conversation_form_data">#msu',
                                    '<div class="conversation_form_data">',
                                    '<div class="message_conversation_form">' . MessageHelper::conversationForm(
                                        $rData['obj_id_to'],
                                        '{conversation_message}',
                                        $rData['obj_id_to'],
                                        '',
                                        0,
                                        true,
                                        false,
                                        '',
                                        true,
                                    ) . '</div>',
                                ),
                                $content,
                            );
                        }
                    }
                }
            }
        }

        return $content;
    }

    private function renderFieldsHtml(string $fieldType): string
    {
        $myapplicationService = $this->myapplicationService;

        $restrict = 'id=' . $this->getApplicationId();

        $myapplicationService->getCMSVC()
            ->setObjectName('ingame:' . $fieldType)
            ->setContext([
                'VIEW' => [
                    'ingame:' . $fieldType . ':view',
                ],
            ]);

        $myapplicationService->getView()->setEntity(
            (new MultiObjectsEntity(
                name: 'project_application',
                table: 'project_application',
                sortingData: [],
                subType: MultiObjectsEntitySubTypeEnum::Cards,
            )),
        )->getViewRights()
            ->setViewRight(true)
            ->setAddRight(false)
            ->setChangeRight(false)
            ->setDeleteRight(false)
            ->setViewRestrict($restrict)
            ->setChangeRestrict($restrict)
            ->setDeleteRestrict($restrict);

        $modelData = $this->getApplicationData()->getModelData();
        $allinfo = DataHelper::unmakeVirtual($modelData['allinfo']);
        $allinfo['id'] = $modelData['id'];

        $RESPONSE_DATA = $myapplicationService->getEntity()->viewActList([$allinfo]);

        $RESPONSE_DATA = preg_replace('#<div class="cardtable_card_num"(.*?)</div>#', '', $RESPONSE_DATA);

        $RESPONSE_DATA = $myapplicationService->postProcessTextsOfReadOnlyFields($RESPONSE_DATA);

        return $RESPONSE_DATA;
    }

    private function getIngameBankTransactionService(): IngameBankTransactionService
    {
        if ($this->ingameBankTransactionService === null) {
            /** @var IngameBankTransactionService */
            $ingameBankTransactionService = CMSVCHelper::getService('ingameBankTransaction');
            $this->ingameBankTransactionService = $ingameBankTransactionService;
        }

        return $this->ingameBankTransactionService;
    }

    private function renderBankTransactionsHtml(): string
    {
        $ingameBankTransactionService = $this->getIngameBankTransactionService();

        $restrict = 'from_project_application_id=' . CookieHelper::getCookie('ingame_application_id') . ' OR to_project_application_id=' . CookieHelper::getCookie('ingame_application_id');

        $ingameBankTransactionService->getView()->getViewRights()
            ->setAddRight(true)
            ->setChangeRight(false)
            ->setDeleteRight(false)
            ->setViewRestrict($restrict)
            ->setChangeRestrict($restrict)
            ->setDeleteRestrict($restrict);

        /** @var HtmlResponse */
        $RESPONSE_DATA = $ingameBankTransactionService->getEntity()->view(ActEnum::list);

        $RESPONSE_DATA = $RESPONSE_DATA->getHtml();

        $RESPONSE_DATA = str_replace('<input type="hidden" name="kind" value="ingame" />', '<input type="hidden" name="kind" value="ingame" /><input type="hidden" name="action" value="create_transaction" />', $RESPONSE_DATA);

        $RESPONSE_DATA = preg_replace(
            '#<h1 class="form_header"(.*)</h1>#',
            '',
            $RESPONSE_DATA,
        );

        $RESPONSE_DATA = preg_replace(
            '#name="name#',
            'autocomplete="off" name="name',
            $RESPONSE_DATA,
        );
        $RESPONSE_DATA = preg_replace(
            '#name="amount_from#',
            'autocomplete="off" name="amount_from',
            $RESPONSE_DATA,
        );
        $RESPONSE_DATA = preg_replace(
            '#name="to_project_application_id#',
            'autocomplete="off" name="to_project_application_id',
            $RESPONSE_DATA,
        );

        return $RESPONSE_DATA;
    }

    private function renderDefaultCurrency(): string
    {
        $projectData = $this->getProjectData();

        $defaultCurrency = DB->select(
            tableName: 'bank_currency',
            criteria: [
                'project_id' => $projectData->id->getAsInt(),
                'default_one' => '1',
            ],
            order: [
                'created_at',
            ],
            limit: 1,
            oneResult: true,
        );

        if ($defaultCurrency) {
            return '<script>
defaultCurrencyId = ' . $defaultCurrency['id'] . ';
</script>';
        }

        return '';
    }
}
