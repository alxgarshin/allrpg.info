<?php

declare(strict_types=1);

use App\CMSVC\Transaction\TransactionService;
use App\CMSVC\User\UserService;
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};

$result = false;
$content = 'FAIL';

/*file_put_contents(INNER_PATH . 'var/log/paymaster.txt', '
' . file_get_contents('php://input'), FILE_APPEND);*/

/* данные от PayMaster */
$transactionId = (int) $_REQUEST['LMI_PAYMENT_NO'];

/* проверяем данные allrpg.info */
if ($transactionId > 0) {
    $conversationMessageData = DB->select(
        tableName: 'conversation_message',
        criteria: [
            'message_action_data' => '{project_transaction_id: ' . $transactionId . '}',
        ],
        oneResult: true,
    );

    if (($conversationMessageData['id'] ?? false) > 0) {
        $transactionData = DB->findObjectById($transactionId, 'project_transaction');

        if ($transactionData['id'] > 0 && $transactionData['conversation_message_id'] === $conversationMessageData['id']) {
            $projectData = DB->findObjectById($transactionData['project_id'], 'project');
            $paymentTypeData = DB->findObjectById($transactionData['project_payment_type_id'], 'project_payment_type');

            /** @var UserService $userService */
            $userService = CMSVCHelper::getService('user');
            $userData = $userService->get($transactionData['creator_id']);

            if ($paymentTypeData['pm_type'] === '1') {
                // проверяем хэш запроса'
                $hashStr = '';

                $fieldsToHash = [
                    'LMI_MERCHANT_ID',
                    'LMI_PAYMENT_NO',
                    'LMI_SYS_PAYMENT_ID',
                    'LMI_SYS_PAYMENT_DATE',
                    'LMI_PAYMENT_AMOUNT',
                    'LMI_CURRENCY',
                    'LMI_PAID_AMOUNT',
                    'LMI_PAID_CURRENCY',
                    'LMI_PAYMENT_SYSTEM',
                    'LMI_SIM_MODE',
                ];

                foreach ($fieldsToHash as $fieldToHash) {
                    $hashStr .= $_REQUEST[$fieldToHash] . ';';
                }
                $hashStr .= DataHelper::escapeOutput($projectData['paymaster_code']);

                $hashStrHashed = base64_encode(md5($hashStr, true));

                if ($hashStrHashed === $_REQUEST['LMI_HASH']) {
                    CURRENT_USER->setId(1);
                    CURRENT_USER->setSid(2);

                    $transactionDone = false;

                    if ($transactionData['verified'] === '0') {
                        // платеж прошел
                        DB->update(
                            tableName: 'project_transaction',
                            data: [
                                'verified' => '1',
                            ],
                            criteria: [
                                'id' => $transactionData['id'],
                            ],
                        );

                        /** @var TransactionService */
                        $transactionService = CMSVCHelper::getService('transaction');
                        $transactionDone = $transactionService->transactionToPaymentType($transactionData['id']);

                        // считаем комиссию в зависимости от платежного метода
                        $comissionPercent = 2.95 + 4;

                        if ($_REQUEST['LMI_PAYMENT_METHOD'] === 'SBP') {
                            // если СБП, то 1% за СБП + 4% за самозанятого
                            $comissionPercent = 1 + 4;
                        }
                        $comissionValue = (float) $_REQUEST['LMI_PAID_AMOUNT'] / 100 * $comissionPercent;

                        DB->update(
                            tableName: 'project_transaction',
                            data: [
                                'comission_percent' => $comissionPercent,
                                'comission_value' => $comissionValue,
                            ],
                            criteria: [
                                'id' => $transactionData['id'],
                            ],
                        );
                    }

                    if ($transactionDone) {
                        $resolvedData = DB->select(
                            tableName: 'conversation_message',
                            criteria: [
                                'parent' => $conversationMessageData['id'] ?? false,
                            ],
                            oneResult: true,
                            order: [
                                'created_at DESC',
                            ],
                            limit: 1,
                        );
                        $messageActionData = '{project_transaction_id: ' . $transactionId . ', resolved: ' . $resolvedData['id'] . '}';
                        DB->update(
                            tableName: 'conversation_message',
                            data: [
                                'message_action_data' => $messageActionData,
                            ],
                            criteria: [
                                'id' => $conversationMessageData['id'],
                            ],
                        );

                        $LOCALE = LocaleHelper::getLocale(['application', 'messages']);

                        DB->update(
                            tableName: 'conversation_message',
                            data: [
                                'content' => $resolvedData['content'] . '&br;' . $LOCALE['payment_provided_accepted_need_check'],
                            ],
                            criteria: [
                                'id' => $resolvedData['id'],
                            ],
                        );

                        $result = true;
                        $content = 'SUCCESS';
                    } else {
                        $content = 'PayMaster прислала непонятный тип события по транзакции.';
                    }
                } else {
                    $content = 'Неверная подпись запроса.';
                }
            } else {
                $content = 'У данной транзакции установлен тип оплаты отличный от PayMaster.';
            }
        } else {
            $content = 'Не найдена транзакция.';
        }
    } else {
        $content = 'Не найдена операция с таким id.';
    }
} else {
    $content = 'Неверный формат запроса.';
}

if (!$result) {
    header('HTTP/1.0 404 Not Found');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// Выводим результат работы скрипта
echo $content;
