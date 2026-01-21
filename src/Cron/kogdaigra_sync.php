<?php

declare(strict_types=1);

require_once __DIR__ . '/../../public/fraym.php';

$requestResult = '';

/** Делаем выборку всех изменившихся за последние сутки записей */
$url = 'https://kogda-igra.ru/api/changed/' . (time() - 3600 * 24 * 2);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=1');

$requestResult = curl_exec($ch);

if ($requestResult === false) {
    echo 'Curl failed: ' . curl_error($ch);
}

if (substr($requestResult, 0, 3) === pack('CCC', 0xEF, 0xBB, 0xBF)) {
    $requestResult = substr($requestResult, 3);
}

$updatedEventsData = json_decode($requestResult, true);

if (is_array($updatedEventsData)) {
    foreach ($updatedEventsData as $updatedEventData) {
        $url = ABSOLUTE_PATH . '/kogdaigra/from=' . $updatedEventData['id'] . '&to=' . ($updatedEventData['id'] + 1);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $requestResult = curl_exec($ch);

        if ($requestResult === false) {
            echo 'Curl failed: ' . curl_error($ch);
        }
    }
} else {
    $requestResult = 'No data found.';
}

curl_close($ch);

/** Выводим результат работы скрипта */
echo $requestResult;
