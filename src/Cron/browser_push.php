<?php

declare(strict_types=1);

use Fraym\Helper\DataHelper;
use Minishlink\WebPush\{MessageSentReport, Subscription, WebPush};

require_once __DIR__ . '/../../lib/fraym/AppStart.php';

$auth = [
    'VAPID' => [
        'subject' => 'mailto:' . $_ENV['NOTIFY_EMAIL'],
        'publicKey' => $_ENV['PUBLIC_VAPID_KEY'],
        'privateKey' => $_ENV['PRIVATE_VAPID_KEY'],
    ],
];

$webPush = new WebPush($auth);

/** Выставляем счетчик количества browser push-уведомлений */
$counter = 0;

/** Делаем выборку из очереди писем и добавляем к ней настройки пользователя */
$result = DB->query('SELECT s.*, u.subs_type, u.subs_objects, ups.endpoint, ups.p256dh, ups.auth, ups.content_encoding FROM subscription_push s LEFT JOIN user u ON u.id=s.user_id LEFT JOIN user__push_subscriptions ups ON ups.user_id=s.user_id', []);

foreach ($result as $data) {
    if (DataHelper::clearBraces($data['obj_type']) === 'application/application') {
        $data['obj_type'] = 'application';
    }

    $title = DataHelper::escapeOutput($data['header']);
    $body = str_replace("\r\n", "\n", DataHelper::escapeOutput($data['content']));

    $icon = $data['message_img'] ?? ABSOLUTE_PATH . '/files/favicons/google-touch-icon-512x512.png';

    $clickAction = ABSOLUTE_PATH . ($data['obj_type'] !== '' && $data['obj_id'] > 0 ? '/' . DataHelper::clearBraces($data['obj_type']) . '/' . $data['obj_id'] . '/' : '');

    if (DataHelper::clearBraces($data['obj_type']) === 'application') {
        $parentObjData = DB->select('project_application', ['id' => $data['obj_id']], true);
        $clickAction = ABSOLUTE_PATH . '/application/application/' . $data['obj_id'] . '/act=edit&project_id=' . $parentObjData['project_id'];
    }

    /** Проверяем, хочет ли пользователь получать оповещения данного типа */
    if (
        preg_match('#-' . $data['obj_type'] . '-#', $data['subs_objects'])
        || in_array(DataHelper::clearBraces($data['obj_type']), ['application', 'myapplication'])
    ) {
        /** Отправляем уведомление в браузере, если есть токен у пользователя */
        $subscription = Subscription::create([
            'endpoint' => $data['endpoint'],
            'keys' => [
                'p256dh' => $data['p256dh'],
                'auth'   => $data['auth'],
            ],
            'contentEncoding' => $data['content_encoding'] ?? 'aesgcm',
        ]);

        $payload = DataHelper::jsonFixedEncode([
            'title' => $title,
            'body'  => $body,
            'icon' => $icon,
            'url'   => $clickAction,
        ]);

        $webPush->queueNotification($subscription, $payload, ['TTL' => 60, 'urgency' => 'normal']);
    }

    DB->delete(
        tableName: 'subscription_push',
        criteria: [
            'id' => $data['id'],
        ],
    );
}

/** Рассылаем и обрабатываем репорты */
foreach ($webPush->flush() as $report) {
    /** @var MessageSentReport $report */

    $endpoint = (string) $report->getRequest()->getUri();

    if (!$report->isSuccess()) {
        if ($report->isSubscriptionExpired()) {
            DB->delete(
                tableName: 'user__push_subscriptions',
                criteria: [
                    'endpoint' => $report->getEndpoint(),
                ],
            );
        }
    } else {
        $counter++;
    }
}

/** Выводим результат работы скрипта */
echo 'done browser push subs: ' . $counter . '<br>';
