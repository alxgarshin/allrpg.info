<?php

declare(strict_types=1);

use App\CMSVC\Transaction\TransactionService;
use App\CMSVC\User\UserService;
use Fraym\Helper\{CMSVCHelper, DataHelper};

$content = 'FAIL';

/* данные от PayAnyWay */
$orderData = [
    'MNT_ID' => $_REQUEST['MNT_ID'],
    'MNT_TRANSACTION_ID' => $_REQUEST['MNT_TRANSACTION_ID'],
    'MNT_OPERATION_ID' => $_REQUEST['MNT_OPERATION_ID'],
    'MNT_AMOUNT' => $_REQUEST['MNT_AMOUNT'],
    'MNT_CURRENCY_CODE' => $_REQUEST['MNT_CURRENCY_CODE'],
    'MNT_SUBSCRIBER_ID' => $_REQUEST['MNT_SUBSCRIBER_ID'],
];

/* проверяем данные allrpg.info */
$transactionData = DB->findObjectById($orderData['MNT_TRANSACTION_ID'], 'project_transaction');

if ($transactionData['id'] > 0) {
    $projectData = DB->findObjectById($transactionData['project_id'], 'project');
    $paymentTypeData = DB->findObjectById($transactionData['project_payment_type_id'], 'project_payment_type');

    /** @var UserService $userService */
    $userService = CMSVCHelper::getService('user');
    $userData = $userService->get($transactionData['creator_id']);
    $conversationMessageData = DB->findObjectById($transactionData['conversation_message_id'], 'conversation_message');

    $checkSignature = md5(
        $orderData['MNT_ID'] . $orderData['MNT_TRANSACTION_ID'] . $orderData['MNT_OPERATION_ID'] . $orderData['MNT_AMOUNT'] . $orderData['MNT_CURRENCY_CODE'] . $orderData['MNT_SUBSCRIBER_ID'] . '0' . $projectData['paw_code'],
    );

    if (
        $userData->sid->get() === $orderData['MNT_SUBSCRIBER_ID'] && $checkSignature ===
        $_REQUEST['MNT_SIGNATURE']
        && $paymentTypeData['paw_type'] === '1' && $projectData['paw_mnt_id'] === $orderData['MNT_ID'] && $transactionData['verified'] === '0'
    ) {
        CURRENT_USER->setId($userData->id->getAsInt());
        CURRENT_USER->setSid($userData->sid->get());
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
        $transactionService->transactionToPaymentType($transactionData['id']);

        if ($conversationMessageData['id'] > 0) {
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
            $messageActionData = json_decode($conversationMessageData['message_action_data'], true);
            $messageActionData['resolved'] = $resolvedData['id'];
            DB->update(
                tableName: 'conversation_message',
                data: [
                    'message_action_data' => DataHelper::jsonFixedEncode($messageActionData),
                ],
                criteria: [
                    'id' => $conversationMessageData['id'],
                ],
            );
        }

        $content = 'SUCCESS';
    }
}

// Выводим результат работы скрипта
echo $content;
