<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use Fraym\BaseObject\CurrentUser;
use Fraym\Enum\{ActEnum, ActionEnum, RequestTypeEnum};
use Fraym\Helper\{CookieHelper, LocaleHelper};
use Fraym\Service\{CacheService, EnvService, GlobalTimerService, SQLDatabaseService};

require_once __DIR__ . '/BaseVar.php';

/** Запускаем глобальный таймер */
$_GLOBALTIMER = new GlobalTimerService();

/** Парсим основной .env-файл Fraym'а */
(new EnvService(INNER_PATH . '.env.fraym'))->load();

/** Парсим основной .env-файл проекта */
(new EnvService(INNER_PATH . '.env'))->load();

/** Парсим дополнительные .env-файлы */
if (file_exists(INNER_PATH . '.env.dev')) {
    (new EnvService(INNER_PATH . '.env.dev'))->load();
} elseif (file_exists(INNER_PATH . '.env.stage')) {
    (new EnvService(INNER_PATH . '.env.stage'))->load();
} elseif (file_exists(INNER_PATH . '.env.prod')) {
    (new EnvService(INNER_PATH . '.env.prod'))->load();
}

/** Устанавливаем глобальные переменные */
define('REQUEST_TYPE', RequestTypeEnum::getRequestType());

if (REQUEST_TYPE->isApiRequest()) {
    $_ENV['GLOBALTIMERDRAWREPORT'] = false;
}
define('PRE_REQUEST_CHECK', ($_REQUEST['preRequestCheck'] ?? '') === 'true');

define('ABSOLUTE_PATH', $_ENV['ABSOLUTE_PATH']);
define('ACTION', ActionEnum::init());
define('ACT', !is_null($_REQUEST['act'] ?? null) ? ActEnum::tryFrom($_REQUEST['act'] ?? false) : null);
define('KIND', $_REQUEST['kind'] ?? $_ENV['STARTING_KIND']);
define('CMSVC', $_REQUEST['cmsvc'] ?? KIND);
define('ID', ($_REQUEST['id'] ?? false) ? (is_array($_REQUEST['id']) ? $_REQUEST['id'] : [(int) $_REQUEST['id']]) : []);
define('PAGE', (int) ($_REQUEST['page'] ?? 0));
define('SORTING', (int) ($_REQUEST['sorting'] ?? 0));

/** Выставляем рекомендуемые базовые настройки */
mb_internal_encoding('UTF-8');
date_default_timezone_set($_ENV['TIMEZONE']);

ini_set("log_errors", 1);
ini_set("display_errors", false);
error_reporting(E_ALL);

ini_set("memory_limit", "500M");
set_time_limit(60);

/** Инициализируем кэш */
/** @const CacheService CACHE */
define('CACHE', CacheService::getInstance());

/** Подключаемся к базе данных */
/** @const SQLDatabaseService DB */
define('DB', SQLDatabaseService::getInstance());

/** Инициализируем пользователя */
/** @const CurrentUser CURRENT_USER */
define('CURRENT_USER', CurrentUser::getInstance());
CURRENT_USER->auth();

/** Перепроверяем настройки локали и меняем их, если нужно */
$LOCALES_LIST = LocaleHelper::getLocalesList();

if ($_REQUEST['locale'] ?? false) {
    if ($_REQUEST['locale'] === 'default') {
        CookieHelper::batchSetCookie(['locale' => 'RU']);
    } else {
        $fixedLocaleName = mb_strtoupper(htmlspecialchars($_REQUEST['locale']));

        if (in_array($fixedLocaleName, $LOCALES_LIST, true)) {
            CookieHelper::batchSetCookie(['locale' => $fixedLocaleName]);
        }
    }
}

if (!CookieHelper::getCookie('locale')) {
    CookieHelper::batchSetCookie(['locale' => 'RU']);
}
