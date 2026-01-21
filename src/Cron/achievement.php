<?php

declare(strict_types=1);

use App\CMSVC\User\UserService;
use Fraym\Helper\CMSVCHelper;

require_once __DIR__ . '/../../public/fraym.php';

/** Выставляем счетчик количества пользователей */
$counter = 0;

/** Делаем выборку пользователей и проверяем у них все ачивки */
/** @var UserService $userService */
$userService = CMSVCHelper::getService('user');
$result = DB->select('user', []);

foreach ($result as $data) {
    $userService->checkForAchievements($data['id']);
    ++$counter;
}

/** Выводим результат работы скрипта */
echo 'done achievements check: ' . $counter . '<br>';
