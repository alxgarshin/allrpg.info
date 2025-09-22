<?php

declare(strict_types=1);

use App\CMSVC\Transaction\TransactionService;
use App\CMSVC\User\UserService;
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{CMSVCHelper, LocaleHelper};

$result = false;
$content = 'FAIL';

/* данные от Юkassы */
$source = file_get_contents('php://input');
$requestBody = json_decode($source, true);

/* проверяем данные allrpg.info */
if ($requestBody['object']['id'] !== '') {
    // ищем транзакцию с таким id
    $conversationMessageData = DB->select(
        tableName: 'conversation_message',
        criteria: [
            ['message_action_data', '%yk_operation_id: ' . $requestBody['object']['id'] . '%', [OperandEnum::LIKE]],
        ],
        oneResult: true,
    );

    if ($conversationMessageData) {
        preg_match(
            '#{([^:]+):([^,]+), yk_operation_id:(.*)}#',
            $conversationMessageData['message_action_data'],
            $actionData,
        );

        if ((int) $actionData[2] > 0) {
            $transactionData = DB->findObjectById((int) $actionData[2], 'project_transaction');

            if ($transactionData['id'] > 0 && $transactionData['conversation_message_id'] === $conversationMessageData['id']) {
                $projectData = DB->findObjectById($transactionData['project_id'], 'project');
                $paymentTypeData = DB->findObjectById(
                    $transactionData['project_payment_type_id'],
                    'project_payment_type',
                );
                /** @var UserService $userService */
                $userService = CMSVCHelper::getService('user');
                $userData = $userService->get($transactionData['creator_id']);

                if ($paymentTypeData['yk_type'] === '1') {
                    CURRENT_USER->setId(1);
                    CURRENT_USER->setSid(2);

                    $transactionDone = false;

                    /** @var TransactionService */
                    $transactionService = CMSVCHelper::getService('transaction');

                    if ($requestBody['event'] === 'payment.succeeded' && $transactionData['verified'] === '0') {
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

                        $transactionDone = $transactionService->transactionToPaymentType($transactionData['id']);
                    } elseif ($requestBody['event'] === 'payment.canceled') {
                        // платеж отменен
                        DB->delete(
                            tableName: 'project_transaction',
                            criteria: [
                                'id' => $transactionData['id'],
                            ],
                        );

                        $transactionDone = $transactionService->deleteTransaction($transactionData);
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
                        $messageActionData = '{project_transaction_id: ' . (int) $actionData[2] . ', resolved: ' . $resolvedData['id'] . '}';
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
                        $content = 'Юkassа прислала непонятный тип события по транзакции.';
                    }
                } else {
                    $content = 'У данной транзакции установлен тип оплаты отличный от Юkassы.';
                }
            } else {
                $content = 'Не найдена транзакция.';
            }
        } else {
            $content = 'Не найдено указание на транзакцию.';
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
