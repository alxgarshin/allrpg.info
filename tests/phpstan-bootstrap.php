<?php

declare(strict_types=1);

use Fraym\BaseObject\CurrentUser;
use Fraym\Enum\{ActEnum, ActionEnum, RequestTypeEnum};
use Fraym\Service\{CacheService, GlobalTimerService, SQLDatabaseService};

define('INNER_PATH', '');
define('PRE_REQUEST_CHECK', ($_REQUEST['preRequestCheck'] ?? '') === 'true');
define('ABSOLUTE_PATH', '');
define('ACTION', ActionEnum::init());
define('ACT', !is_null($_REQUEST['act'] ?? null) ? ActEnum::tryFrom($_REQUEST['act']) : null);
define('KIND', $_REQUEST['kind'] ?? '');
define('CMSVC', $_REQUEST['cmsvc'] ?? KIND);
define('ID', ($_REQUEST['id'] ?? false) ? (is_array($_REQUEST['id']) ? $_REQUEST['id'] : [(int) $_REQUEST['id']]) : []);
define('PAGE', (int) ($_REQUEST['page'] ?? 0));
define('SORTING', (int) ($_REQUEST['sorting'] ?? 0));
define('OBJ_TYPE', $_REQUEST['obj_type'] ?? null);
$objId = ($_REQUEST['obj_id'] ?? false) ? (is_array($_REQUEST['obj_id']) ? $_REQUEST['obj_id'][0] : $_REQUEST['obj_id']) : null;
define('OBJ_ID', is_numeric($objId) ? (int) $objId : $objId);

define('GLOBALTIMER', new GlobalTimerService());
define('CACHE', CacheService::getInstance());
define('CURRENT_USER', CurrentUser::getInstance());
define('REQUEST_TYPE', RequestTypeEnum::getRequestType());

$reflection = new ReflectionClass(SQLDatabaseService::class);
$fakeInstance = $reflection->newInstanceWithoutConstructor();
define('DB', $fakeInstance);

$reflection = new ReflectionClass(SQLDatabaseService::class);
$fakeInstance = $reflection->newInstanceWithoutConstructor();
define('MIGRATE_DB', $fakeInstance);

define('BID', 1);
define('MODAL', false);
define('TEST_MODE', '');
define('REQUEST_PROJECT_ID', 1);
