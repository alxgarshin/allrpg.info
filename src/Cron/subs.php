<?php

declare(strict_types=1);

use Fraym\Enum\{EscapeModeEnum, OperandEnum};
use Fraym\Helper\{DataHelper, EmailHelper};

require_once __DIR__ . '/../../public/fraym.php';

/** Выставляем счетчик количества писем */
$counter = 0;

/** Делаем выборку из очереди писем и добавляем к ней настройки пользователя */
$result = DB->query('SELECT s.*, u.subs_type, u.subs_objects, u.em, u.em_verified FROM subscription s LEFT JOIN user u ON u.id=s.user_id', []);

foreach ($result as $data) {
    /** Проверяем не выставлена ли у пользователя подписка = "никогда" */
    if ($data['subs_type'] !== 10 && $data['em'] !== '' && $data['em_verified'] === '1') {
        /** Проверяем, хочет ли пользователь получать оповещения сразу */
        if ((date('G') === '2' && $data['subs_type'] === 2) || $data['subs_type'] === 1) {
            /** Проверяем, хочет ли пользователь получать оповещения данного типа */
            if (
                preg_match('#-' . $data['obj_type'] . '-#', $data['subs_objects'])
                || in_array($data['obj_type'], ['{project_application}', '{ruling_item_wall}'])
                || ($data['obj_type'] === '{project_application_conversation}' && $data['user_id'] !== 1)
            ) {
                if (EmailHelper::sendMail(
                    DataHelper::escapeOutput($data['author_name']),
                    DataHelper::escapeOutput($data['author_email']),
                    $data['em'],
                    DataHelper::escapeOutput($data['name']),
                    DataHelper::escapeOutput($data['content'], EscapeModeEnum::plainHTMLforceNewLines),
                    true,
                )) {
                    ++$counter;
                }
            }

            DB->delete(
                tableName: 'subscription',
                criteria: [
                    'id' => $data['id'],
                ],
            );
        }
    } else {
        DB->delete(
            tableName: 'subscription',
            criteria: [
                'id' => $data['id'],
            ],
        );
    }
}

/** Выводим результат работы скрипта */
echo 'done email subs: ' . $counter . '<br>';

/** Чистим лог активности */
if (date('G:i') === '02:00') {
    $admins = [];
    $adminsData = DB->select(
        tableName: 'user',
        criteria: [
            ['rights', '%-1-%', [OperandEnum::LIKE]],
        ],
    );

    foreach ($adminsData as $adminData) {
        $admins[] = $adminData['id'];
    }
    DB->query(
        'DELETE FROM activity_log WHERE created_at < :created_at' .
            (count($admins) > 0 ? ' AND user_id NOT IN (:admins)' : ''),
        [
            ['created_at', time() - 24 * 3600 * 7],
            ['admins', $admins],
        ],
    );

    if (count($admins) > 0) {
        DB->delete(
            tableName: 'activity_log',
            criteria: [
                ['created_at', time() - 24 * 3600 * 30, [OperandEnum::LESS]],
                'user_id' => $admins,
            ],
        );
    }
}

/** Чистим лог геопозиции */
if (date('G:i') === '02:00') {
    DB->delete(
        tableName: 'project_application_geoposition',
        criteria: [
            ['created_at', time() - 3600 * 24 * 10, [OperandEnum::LESS_OR_EQUAL]],
        ],
    );

    echo 'done geoposition cleaning<br>';
}

/** Подгружаем дальнейшие формы уведомлений: пуши и браузерные пуши */
include_once 'browser_push.php';
// include_once('push.php');
