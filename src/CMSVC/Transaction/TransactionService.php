<?php

declare(strict_types=1);

namespace App\CMSVC\Transaction;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Message\MessageService;
use App\CMSVC\PaymentType\PaymentTypeService;
use App\CMSVC\Trait\{GetUpdatedAtCustomAsHTMLRendererTrait, ProjectDataTrait, UserServiceTrait};
use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Entity\{PostCreate, PostDelete, PreDelete};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};

/** @extends BaseService<TransactionModel> */
#[Controller(TransactionController::class)]
#[PostCreate]
#[PreDelete]
#[PostDelete]
class TransactionService extends BaseService
{
    use GetUpdatedAtCustomAsHTMLRendererTrait;
    use ProjectDataTrait;
    use UserServiceTrait;

    #[DependencyInjection]
    public ApplicationService $applicationService;

    private ?array $projectPaymentTypes = null;
    private ?int $projectPaymentTypeDefaultId = null;
    private ?array $transactionData = null;

    /** Изменение значений комиссии */
    public function changeComission(int $objId, string $objType, int $value): array
    {
        $transactionData = DB->select(
            tableName: 'project_transaction',
            criteria: [
                'id' => $objId,
            ],
            oneResult: true,
        );

        if ($transactionData['project_id'] === $this->getActivatedProjectId()) {
            if ($objType === 'comission_percent') {
                $comissionPercent = $value;
                $comissionValue = $transactionData['amount'] / 100 * $comissionPercent;
            } else {
                $comissionValue = $value;
                $comissionPercent = $comissionValue / ($transactionData['amount'] / 100);
            }

            DB->update(
                tableName: 'project_transaction',
                data: [
                    'comission_percent' => $comissionPercent,
                    'comission_value' => $comissionValue,
                ],
                criteria: [
                    'id' => $objId,
                ],
            );
        }

        return ['response' => 'success'];
    }

    /** Подтверждение платежа в заявке */
    public function confirmPayment(int $objId): array
    {
        $LOCALE = LocaleHelper::getLocale(['application', 'global', 'messages']);
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $returnArr = [];

        $conversationMessageData = DB->select(
            tableName: 'conversation_message',
            criteria: [
                'id' => $objId,
            ],
            oneResult: true,
        );

        if ($conversationMessageData) {
            $transactionData = DB->select(
                tableName: 'project_transaction',
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                    'conversation_message_id' => $objId,
                    'verified' => '0',
                ],
                oneResult: true,
            );

            if ($transactionData) {
                DB->update(
                    tableName: 'project_transaction',
                    data: [
                        'verified' => '1',
                    ],
                    criteria: [
                        'id' => $transactionData['id'],
                    ],
                );

                $this->transactionToPaymentType($transactionData['id']);

                $resolvedData = DB->select(
                    tableName: 'conversation_message',
                    criteria: [
                        'parent' => $conversationMessageData['id'],
                    ],
                    oneResult: true,
                    order: [
                        'created_at DESC',
                    ],
                    limit: 1,
                );

                DB->update(
                    tableName: 'conversation_message',
                    data: [
                        'message_action_data' => mb_substr($conversationMessageData['message_action_data'], 0, mb_strlen($conversationMessageData['message_action_data']) - 1) . ', resolved:' . $resolvedData['id'] . '}',
                    ],
                    criteria: [
                        'id' => $conversationMessageData['id'],
                    ],
                );

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['payment_provided_accepted'],
                    'response_data' => '<div class="done">' . $LOCALE_CONVERSATION['actions']['request_done'] . '</div>',
                    'response_amount' => $transactionData['amount'],
                ];
            }
        }

        return $returnArr;
    }

    /** Неподтверждение платежа в заявке */
    public function declinePayment(int $objId): array
    {
        $LOCALE = LocaleHelper::getLocale(['application', 'global', 'messages']);
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $returnArr = [];

        $conversationMessageData = DB->select(
            tableName: 'conversation_message',
            criteria: [
                'id' => $objId,
            ],
            oneResult: true,
        );

        if ($conversationMessageData) {
            $transactionData = DB->select(
                tableName: 'project_transaction',
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                    'conversation_message_id' => $objId,
                    'verified' => '0',
                ],
                oneResult: true,
            );

            if ($transactionData) {
                DB->delete(
                    tableName: 'project_transaction',
                    criteria: [
                        'id' => $transactionData['id'],
                    ],
                );

                $this->deleteTransaction($transactionData);

                $resolvedData = DB->select(
                    tableName: 'conversation_message',
                    criteria: [
                        'parent' => $conversationMessageData['id'],
                    ],
                    oneResult: true,
                    order: [
                        'created_at DESC',
                    ],
                    limit: 1,
                );

                DB->update(
                    tableName: 'conversation_message',
                    data: [
                        'message_action_data' => mb_substr($conversationMessageData['message_action_data'], 0, -1) . ', resolved:' . $resolvedData['id'] . '}',
                    ],
                    criteria: [
                        'id' => $conversationMessageData['id'],
                    ],
                );

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['payment_provided_declined'],
                    'response_data' => '<div class="done">' . $LOCALE_CONVERSATION['actions']['request_done'] . '</div>',
                ];
            }
        }

        return $returnArr;
    }

    /** Выставление транзакции неверифицированности*/
    public function unVerifyTransaction(int $objId): array
    {
        if ($objId > 0) {
            $projectTransactionData = DB->findObjectById($objId, 'project_transaction');

            if ($projectTransactionData['verified'] === 1) {
                DB->update(
                    tableName: 'project_transaction',
                    data: [
                        'verified' => null,
                    ],
                    criteria: [
                        'project_id' => $this->getActivatedProjectId(),
                        'id' => $objId,
                    ],
                );
            }
        }

        return [
            'response' => 'success',
            'response_data' => '<span class="sbi sbi-check"></span>',
        ];
    }

    /** Выставление транзакции верифицированности */
    public function verifyTransaction(int $objId): array
    {
        if ($objId > 0) {
            $projectTransactionData = DB->findObjectById($objId, 'project_transaction');

            if ($projectTransactionData['verified'] === '0') {
                DB->update(
                    tableName: 'project_transaction',
                    data: [
                        'verified' => '1',
                    ],
                    criteria: [
                        'project_id' => $this->getActivatedProjectId(),
                        'id' => $objId,
                    ],
                );
                $this->transactionToPaymentType($objId);
            }
        }

        return [
            'response' => 'success',
            'response_data' => '<span class="sbi sbi-check"></span>',
        ];
    }

    /** Обнуление взносов проекта */
    public function nullifyFees(): array
    {
        $LOCALE = LocaleHelper::getLocale(['fee', 'global']);

        // обнуляем выплаченные деньги, статус оплаты и статус требования подтверждения у всех заявок
        DB->update(
            tableName: 'project_application',
            data: [
                'money_provided' => null,
                'money_paid' => '0',
                'money_need_approve' => '0',
            ],
            criteria: [
                'project_id' => $this->getActivatedProjectId(),
            ],
        );

        // скидываем бюджет всех методов оплаты в ноль
        $paymentTypesData = DB->select(
            tableName: 'project_payment_type',
            criteria: [
                'project_id' => $this->getActivatedProjectId(),
            ],
        );

        foreach ($paymentTypesData as $paymentTypeData) {
            $transactionId = $this->createTransaction(
                $this->getActivatedProjectId(),
                null,
                $paymentTypeData['id'],
                $LOCALE['nullify_fee'],
                0 - (int) $paymentTypeData['amount'],
                true,
            );
            $this->transactionToPaymentType($transactionId);
        }

        // у всех заявок возвращаем опцию взноса к неизмененной насильно
        $changedFeesApplicationsData = $this->applicationService->getAll(
            criteria: [
                'project_id' => $this->getActivatedProjectId(),
            ],
        );

        foreach ($changedFeesApplicationsData as $changedFeesApplicationData) {
            $projectFeeIds = $changedFeesApplicationData->project_fee_ids->get();
            $money = 0;

            foreach ($projectFeeIds as $key => $value) {
                if (preg_match('#\.1-#', $value)) {
                    $projectFeeIds[$key] = $value = str_replace('.1', '', $value);
                }
                $projectFeeData = DB->findObjectById($value, 'project_fee');

                if ($projectFeeData['id'] > 0) {
                    $money += $projectFeeData['cost'];
                } else {
                    unset($projectFeeIds[$key]);
                }
            }

            DB->update(
                tableName: 'project_application',
                data: [
                    'project_fee_ids' => DataHelper::arrayToMultiselect($projectFeeIds),
                    'money' => $money,
                ],
                criteria: [
                    'id' => $changedFeesApplicationData->id->getAsInt(),
                ],
            );
        }

        return [
            'response' => 'success',
            'response_text' => $LOCALE['messages']['nullify_fees'],
        ];
    }

    /** Удаление транзакции */
    public function deleteTransaction(array $transactionData): bool
    {
        $LOCALE = LocaleHelper::getLocale(['application', 'global']);

        if ($transactionData['conversation_message_id'] > 0) {
            /* если к транзакции был привязан комментарий в заявке, пишем про отклонение */
            $conversationMessage = DB->findObjectById($transactionData['conversation_message_id'], 'conversation_message');

            $message = $LOCALE['messages']['payment_provided_declined'];

            /** @var MessageService $messageService */
            $messageService = CMSVCHelper::getService('message');

            $messageService->newMessage(
                $conversationMessage['conversation_id'],
                $message,
                '',
                [],
                [],
                [
                    'obj_type' => '{project_application_conversation}',
                    'obj_id' => $transactionData['project_application_id'],
                    'sub_obj_type' => '{to_player}',
                ],
                '',
                '',
                $transactionData['conversation_message_id'],
            );
        }

        if ($transactionData['project_application_id'] > 0) {
            /* если к транзакции была привязана заявка, проверяем есть ли еще транзакции с ней. Если нет, то ставим признак, что их нет, в заявке */
            $moreTransactionsToApprove = DB->select(
                tableName: 'project_transaction',
                criteria: [
                    'project_id' => $transactionData['project_id'],
                    'project_application_id' => $transactionData['project_application_id'],
                    ['id', $transactionData['id'], [OperandEnum::NOT_EQUAL]],
                    'verified' => '0',
                ],
            );

            if (empty($moreTransactionsToApprove)) {
                DB->update(
                    tableName: 'project_application',
                    data: [
                        'money_need_approve' => '0',
                    ],
                    criteria: [
                        'id' => $transactionData['project_application_id'],
                    ],
                );
            }

            /* если транзакция была верифицирована, откатываем сумму взноса */
            if ($transactionData['verified'] === '1') {
                $applicationData = $this->applicationService->get($transactionData['project_application_id']);

                DB->update(
                    tableName: 'project_application',
                    data: [
                        'money_provided' => ((int) $applicationData->money_provided->get() - (int) $transactionData['amount']),
                        'money_paid' => ((int) $applicationData->money_provided->get() - (int) $transactionData['amount'] >= (int) $applicationData->money->get() ? '1' : '0'),
                    ],
                    criteria: [
                        'id' => $transactionData['project_application_id'],
                    ],
                );
            }
        }

        if ($transactionData['verified'] === '1') {
            /* если транзакция была верифицирована, откатываем нужную сумму со счета */
            $paymentTypeData = DB->findObjectById($transactionData['project_payment_type_id'], 'project_payment_type');

            DB->update(
                tableName: 'project_payment_type',
                data: [
                    'amount' => ((int) $paymentTypeData['amount'] - (int) $transactionData['amount']),
                ],
                criteria: [
                    'id' => $paymentTypeData['id'],
                ],
            );
        }

        return true;
    }

    /** Создание транзакции */
    public function createTransaction(
        int $projectId,
        ?int $projectApplicationId,
        int $projectPaymentTypeId,
        string $name,
        int $amount,
        bool $verified = false,
        ?string $content = null,
        ?string $paymentDatetime = null,
    ): false|int|string {
        $result = false;

        if (!is_null($paymentDatetime)) {
            if (is_numeric($paymentDatetime)) {
                // данные уже в Unix timestamp формате
            } else {
                $paymentDatetime = strtotime($paymentDatetime);
            }

            $paymentDatetime = date('Y-m-d H:i:s', $paymentDatetime);
        }

        if ($projectId > 0 && $projectPaymentTypeId > 0 && $name !== '') {
            $projectPaymentTypeData = DB->findObjectById($projectPaymentTypeId, 'project_payment_type');

            if ($projectId === $projectPaymentTypeData['project_id']) {
                $dataQuery = DB->insert(
                    tableName: 'project_transaction',
                    data: [
                        'creator_id' => CURRENT_USER->id(),
                        'project_id' => $projectId,
                        'project_application_id' => $projectApplicationId,
                        'project_payment_type_id' => $projectPaymentTypeId,
                        'name' => $name,
                        'content' => $content,
                        'amount' => $amount,
                        'verified' => ($verified ? '1' : '0'),
                        'last_update_user_id' => CURRENT_USER->id(),
                        'payment_datetime' => $paymentDatetime,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ],
                );

                if ($dataQuery !== false) {
                    $result = DB->lastInsertId();

                    if ($verified && $result > 0) {
                        $this->transactionToPaymentType($result);
                    }
                }
            }
        }

        return $result;
    }

    /** Перевод транзакции в изменение суммы на счету метода оплаты */
    public function transactionToPaymentType(int|string $transactionId): bool
    {
        $LOCALE = LocaleHelper::getLocale(['application', 'global']);

        $result = false;

        $transactionData = DB->findObjectById($transactionId, 'project_transaction', true);

        if (isset($transactionData['amount']) && isset($transactionData['project_payment_type_id']) && isset($transactionData['project_id']) && $transactionData['verified']) {
            // принимаем только верные по всем данным и проверенные транзакции к изменениям
            $paymentTypeData = DB->findObjectById($transactionData['project_payment_type_id'], 'project_payment_type');

            if (isset($paymentTypeData['amount']) && $paymentTypeData['project_id'] === $transactionData['project_id']) {
                if (
                    DB->update(
                        tableName: 'project_payment_type',
                        data: [
                            'amount' => ((int) $paymentTypeData['amount'] + (int) $transactionData['amount']),
                        ],
                        criteria: [
                            'id' => $paymentTypeData['id'],
                        ],
                    ) !== false
                ) {
                    $result = true;

                    if ((int) $transactionData['project_application_id'] > 0) {
                        $applicationData = $this->applicationService->get((int) $transactionData['project_application_id']);

                        if ($applicationData->project_id->getAsInt() === $transactionData['project_id']) {
                            $moreTransactionsToApprove = DB->select(
                                tableName: 'project_transaction',
                                criteria: [
                                    'project_id' => $paymentTypeData['project_id'],
                                    'project_application_id' => $applicationData->id->getAsInt(),
                                    ['id', $transactionId, [OperandEnum::NOT_EQUAL]],
                                    'verified' => '0',
                                ],
                            );
                            $moreTransactionsToApprove = count($moreTransactionsToApprove);

                            DB->update(
                                tableName: 'project_application',
                                data: [
                                    'money_provided' => ($applicationData->money_provided->get() + $transactionData['amount']),
                                    'money_paid' => (($applicationData->money_provided->get() + $transactionData['amount']) >= $applicationData->money->get() ? 1 : 0),
                                    'money_need_approve' => ($moreTransactionsToApprove > 0 ? 1 : 0),
                                ],
                                criteria: [
                                    'id' => $applicationData->id->getAsInt(),
                                ],
                            );

                            if ($transactionData['conversation_message_id'] > 0) {
                                /* есть комментарий по данной транзакции в заявке */
                                $conversationMessage = DB->findObjectById($transactionData['conversation_message_id'], 'conversation_message');

                                $message = $LOCALE['messages']['payment_provided_accepted'];

                                /** @var MessageService $messageService */
                                $messageService = CMSVCHelper::getService('message');

                                $messageService->newMessage(
                                    $conversationMessage['conversation_id'],
                                    $message,
                                    '',
                                    [],
                                    [],
                                    [
                                        'obj_type' => '{project_application_conversation}',
                                        'obj_id' => $applicationData->id->getAsInt(),
                                        'sub_obj_type' => '{to_player}',
                                    ],
                                    '',
                                    '',
                                    $transactionData['conversation_message_id'],
                                );
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            DB->update(
                tableName: 'project_transaction',
                data: [
                    'verified' => '1',
                ],
                criteria: [
                    'id' => $successfulResultsId,
                ],
            );
        }
    }

    public function preDelete(): void
    {
        foreach (ID as $id) {
            $this->transactionData[$id] = DB->findObjectById($id, 'project_transaction');
        }
    }

    public function postDelete(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            $this->deleteTransaction($this->transactionData[$successfulResultsId]);
        }
    }

    public function checkRights(): bool
    {
        return RightsHelper::checkAllowProjectActions(PROJECT_RIGHTS, ['{budget}']);
    }

    public function getProjectApplicationIdDefault(): ?int
    {
        return DataHelper::getId();
    }

    public function getProjectApplicationIdValues(): array
    {
        $userService = $this->getUserService();

        $LOCALE_APPLICATION = LocaleHelper::getLocale(['application']);

        $projectData = $this->getProjectData();

        $projectApplications = [];
        $statusesList = $LOCALE_APPLICATION['fraym_model']['elements']['status']['values'];
        $rehashedStatusesList = [];

        foreach ($statusesList as $statusData) {
            $rehashedStatusesList[$statusData[0]] = $statusData[1];
        }

        $applicationData = DB->query('SELECT u.*, pa.id AS application_id, pa.sorter, pa.status AS application_status FROM project_application AS pa LEFT JOIN user AS u ON u.id=pa.creator_id WHERE project_id=' . $projectData->id->getAsInt() . " AND deleted_by_gamemaster='0'", []);

        foreach ($applicationData as $applicationItem) {
            $projectApplications[] = [$applicationItem['application_id'], '<a href="' . ABSOLUTE_PATH . '/application/' . $applicationItem['application_id'] . '/">' . DataHelper::escapeOutput($applicationItem['sorter']) . '</a><span class="separator"> | </span><span class="gray">' . $rehashedStatusesList[$applicationItem['application_status']] . '</span><span class="separator"> | </span><span class="gray">' . $userService->showNameWithId($userService->arrayToModel($applicationItem), true) . '<div class="sbi sbi-info" obj_id="' . $applicationItem['id'] . '" obj_type="roleslist" value="' . $this->getActivatedProjectId() . '"></div></span>'];
        }

        return $projectApplications;
    }

    public function getHelperBeforeTransactionAdd(): ?string
    {
        $projectData = $this->getProjectData();

        return DataHelper::escapeOutput($projectData->helper_before_transaction_add->get());
    }

    public function getPayByCardContext(): array
    {
        $projectData = $this->getProjectData();

        if ($projectData?->paymaster_merchant_id->get() || $projectData?->paykeeper_login->get()) {
            return [
                'myapplication:view',
                'myapplication:create',
            ];
        }

        return [];
    }

    public function getTestPaymentContext(): array
    {
        $projectData = $this->getProjectData();

        if ($projectData?->paymaster_merchant_id->get() || $projectData?->paykeeper_login->get()) {
            if (RightsHelper::checkRights('{admin}', '{project}', $projectData->id->getAsInt())) {
                return [
                    'myapplication:view',
                    'myapplication:create',
                ];
            }
        }

        return [];
    }

    /** Проверка наличия чека с nalog.ru в ответном сообщении на транзакцию */
    public function getHasCheckValues(): array
    {
        $hasCheckValues = [];

        $transactionData = DB->query(
            "SELECT cm2.id AS message_id, pt.id FROM project_transaction AS pt LEFT JOIN conversation_message AS cm ON cm.id=pt.conversation_message_id LEFT JOIN conversation_message AS cm2 ON cm2.parent=cm.id AND cm2.content LIKE '%nalog.ru%' WHERE pt.project_id=:project_id GROUP BY pt.id, message_id",
            [
                ['project_id', $this->getActivatedProjectId()],
            ],
        );

        foreach ($transactionData as $transactionItem) {
            $hasCheckValues[] = [
                $transactionItem['id'],
                $transactionItem['message_id'] > 0 ? '<span class="sbi sbi-check"></span>' : '<span class="sbi sbi-times"></span>',
            ];
        }

        return $hasCheckValues;
    }

    public function initProjectPaymentTypesData(): void
    {
        if (is_null($this->projectPaymentTypes)) {
            /** @var PaymentTypeService */
            $paymentTypeService = CMSVCHelper::getService('payment_type');

            $projectData = $this->getProjectData();

            $projectPaymentTypes = [];
            $projectPaymentTypeDefaultId = null;

            $checkPaymentTypes = [
                'payanyway',
                'yandex',
                'paymaster',
                'paykeeper',
            ];

            foreach ($checkPaymentTypes as $paymentTypeName) {
                if (in_array($paymentTypeName, $_ENV['USE_PAYMENT_SYSTEMS']) && $paymentTypeService->checkProjectFieldsFilled($paymentTypeName)) {
                    $projectPaymentTypeDefaultId = $paymentTypeService->checkPaymentTypeId($paymentTypeName);
                }
            }

            $projectPaymentTypesDependentQuery = '';

            foreach ($checkPaymentTypes as $paymentTypeName) {
                if (in_array($paymentTypeName, $_ENV['USE_PAYMENT_SYSTEMS'])) {
                    $projectPaymentTypesDependentQuery .= ' AND ' . $paymentTypeService->getPaymentTypeFieldName($paymentTypeName) . " != '1'";
                }
            }

            $projectPaymentTypes = DB->getArrayOfItemsAsArray('project_payment_type WHERE project_id=' . $projectData->id->getAsInt() . (KIND === 'myapplication' ? " AND registration_type='0'" . $projectPaymentTypesDependentQuery : ''), 'id', 'name');

            if (is_null($projectPaymentTypeDefaultId) && $projectPaymentTypes !== null) {
                $projectPaymentTypeDefaultId = $projectPaymentTypes[array_key_first($projectPaymentTypes)][0];
            }

            $this->projectPaymentTypes = $projectPaymentTypes;
            $this->projectPaymentTypeDefaultId = $projectPaymentTypeDefaultId;
        }
    }

    public function getProjectPaymentTypeIdValues(): ?array
    {
        $this->initProjectPaymentTypesData();

        return $this->projectPaymentTypes;
    }

    public function getProjectPaymentTypeIdDefault(): ?int
    {
        if (KIND === 'myapplication') {
            $this->initProjectPaymentTypesData();

            return $this->projectPaymentTypeDefaultId;
        }

        return null;
    }

    public function getPaymentDatetimeContext(): array
    {
        $projectData = $this->getProjectData();

        if ($projectData?->show_datetime_in_transaction->get()) {
            return [
                'transaction:list',
                'transaction:view',
                'transaction:create',
                'transaction:update',
                'transaction:embedded',
                'myapplication:view',
                'myapplication:create',
            ];
        }

        return [];
    }
}
