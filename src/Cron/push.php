<?php

declare(strict_types=1);

use App\CMSVC\User\UserService;
use Fraym\Helper\{CMSVCHelper, DataHelper};

require_once __DIR__ . '/../../lib/fraym/AppStart.php';

/** Выставляем счетчик количества push-уведомлений */
$counter = 0;

/** Делаем выборку из очереди писем и добавляем к ней настройки пользователя */
$result = DB->query(
    'SELECT s.*, u.subs_type, u.subs_objects, aup.google_token, aup.apple_token, aup.windows_token, a.google_api_key, a.apple_api_key, a.apple_api_cert, a.id as application_id FROM subscription_push s LEFT JOIN user u ON u.id=s.user_id LEFT JOIN api_application_user_push aup ON aup.user_id=s.user_id LEFT JOIN api_application a ON a.id=aup.app_id',
    [],
);

foreach ($result as $data) {
    /** Проверяем не выставлена ли у пользователя подписка = "никогда" */
    if ($data['subs_type'] !== 10) {
        /** Проверяем, хочет ли пользователь получать оповещения сразу */
        // if((date('G')=='12' && $data["subs_type"]==2) || $data["subs_type"]==1) {
        /** Проверяем, хочет ли пользователь получать оповещения данного типа */
        if (
            preg_match('#-' . $data['obj_type'] . '-#', $data['subs_objects'])
            || in_array($data['obj_type'], ['{project_application}', '{ruling_item_wall}'])
            || ($data['obj_type'] === '{project_application_conversation}' && $data['user_id'] !== 1)
        ) {
            /** Проверяем, есть ли у приложения ключ подключения к API Google и есть ли у пользователя регистрационный id, полученный там же */
            if ($data['google_token'] !== '' && $data['google_api_key'] !== '') {
                $url = 'https://android.googleapis.com/gcm/send';
                $fields = [
                    'to' => $data['google_token'],
                    'data' => [
                        'header' => DataHelper::escapeOutput($data['header']),
                        'message' => DataHelper::escapeOutput($data['content']),
                        'obj_type' => $data['obj_type'],
                        'obj_id' => $data['obj_id'],
                        'message_img' => $data['message_img'],
                        // 'server'   =>  ABSOLUTE_PATH,
                    ],
                ];
                $headers = [
                    'Authorization: key=' . $data['google_api_key'],
                    'Content-Type: application/json',
                ];

                if (function_exists('curl_version')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, DataHelper::jsonFixedEncode($fields));

                    $requestResult = curl_exec($ch);

                    if ($requestResult === false) {
                        echo 'Curl failed: ' . curl_error($ch);
                    }
                    curl_close($ch);
                } else {
                    echo 'No curl detected<br>';
                }

                ++$counter;
            }

            /** Проверяем, есть ли у приложения ключ подключения к API Apple и есть ли у пользователя регистрационный id, полученный там же */
            if ($data['apple_token'] !== '' && $data['apple_api_key'] !== '') {
                $deviceToken = $data['apple_token'];

                /** Настройки */
                $apnsHost = 'gateway.sandbox.push.apple.com';
                $apnsPort = 2195;

                if (file_exists(INNER_PATH . 'public/apple_certs/' . $data['application_id'] . '/' . $data['apple_api_cert'])) {
                    $apnsCert = INNER_PATH . 'public/apple_certs/' . $data['application_id'] . '/' . $data['apple_api_cert'];
                } else {
                    $apnsCert = '';
                    echo 'No apple certificate found.<br>';
                }
                $apnsPass = $data['apple_api_key'];

                /** Сообщение не должно быть длиннее 70 символов */
                $message = trim(DataHelper::escapeOutput($data['content']));

                if (mb_strlen($message) > 70) {
                    $message = mb_substr($message, 0, 69) . '…';
                }

                /** Открытие подключения к APNS */
                $ctx = stream_context_create();
                stream_context_set_option($ctx, 'ssl', 'local_cert', $apnsCert);
                stream_context_set_option($ctx, 'ssl', 'passphrase', $apnsPass);

                $apns = stream_socket_client(
                    'ssl://' . $apnsHost . ':' . $apnsPort,
                    $error,
                    $errorString,
                    2,
                    STREAM_CLIENT_CONNECT,
                    $ctx,
                );

                if ($apns) {
                    /** Настройки содержимого для отправки */
                    $payload = [
                        'aps' => [
                            'alert' => $message,
                        ],
                        // 'header' => DataHelper::escapeOutput($data['header']),
                        'obj_type' => $data['obj_type'],
                        'obj_id' => $data['obj_id'],
                        'message_img' => $data['message_img'],
                        // 'server' => ABSOLUTE_PATH,
                    ];

                    /** Данные в json */
                    $output = DataHelper::jsonFixedEncode($payload);

                    // echo($output.'-'.strlen($output).'<br>');

                    /** Циклом отправляем на все токены */
                    $token = pack('H*', str_replace(' ', '', $deviceToken));
                    /** Строим бинарное уведомление */
                    $apnsMessage = chr(0) . chr(0) . chr(32) . $token . chr(0) . chr(strlen($output)) . $output;

                    /** Отправляем на сервер */
                    $requestResult = fwrite($apns, $apnsMessage);
                    // echo 'Apple success: '.$requestResult.'<br>';

                    /** Закрываем соединение */
                    fclose($apns);
                } else {
                    /** Ошибка соединения с apple.com */
                    echo 'Apple connect fail<br>';

                    if ($errorString || $error) {
                        echo $errorString . ' (№' . $error . ')<br>';
                    }
                }

                ++$counter;
            }

            /** Проверяем, есть ли у приложения ключ подключения к API Windows и есть ли у пользователя регистрационный id, полученный там же */
            if ($data['windows_token'] !== '') {
                $url = urldecode($data['windows_token']);

                /** @var UserService $userService */
                $userService = CMSVCHelper::getService('user');
                $msg = '<?xml version="1.0" encoding="utf-8"?>' .
                    '<wp:Notification xmlns:wp="WPNotification">' .
                    '<wp:Toast>' .
                    '<wp:Text1>' . htmlspecialchars(
                        $userService->getUserName($userService->get($data['creator_id'])),
                    ) . '</wp:Text1>' .
                    '<wp:Text2>' . htmlspecialchars(DataHelper::escapeOutput($data['content'])) . '</wp:Text2>' .
                    '<wp:Param>/MainPage.xaml?ObjType=' . $data['obj_type'] . '&amp;ObjId=' . $data['obj_id'] . '&amp;Name=' . DataHelper::escapeOutput($data['header']) .
                    '</wp:Param>' .
                    '</wp:Toast>' .
                    '</wp:Notification>';

                $sendedheaders = [
                    'Content-Type: text/xml',
                    'Accept: application/*',
                    'X-WindowsPhone-Target: toast',
                    'X-NotificationClass: 2',
                ];

                if (function_exists('curl_version')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $sendedheaders);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $msg);

                    $requestResult = curl_exec($ch);

                    if ($requestResult === false) {
                        echo 'Curl failed: ' . curl_error($ch);
                    }
                    curl_close($ch);
                } else {
                    echo 'No curl detected<br>';
                }

                ++$counter;
            }
        }
        // }
    }

    DB->delete(
        tableName: 'subscription_push',
        criteria: [
            'id' => $data['id'],
        ],
    );
}

/** Выводим результат работы скрипта */
echo 'done push subs: ' . $counter . '<br>';
