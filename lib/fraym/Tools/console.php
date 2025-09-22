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

/** Файл основных команд Fraym из командной строки.
 *
 * Основной синтаксис:
 * [php console.php|bin/console] make:cmsvc --cmsvc=TestObject
 * [php console.php|bin/console] make:migration
 */

use Fraym\Helper\TextHelper;
use Fraym\Service\EnvService;

require_once __DIR__ . '/../BaseVar.php';
require_once __DIR__ . '/echoResult.php';

/** Возможный набор действий в командах вида make:действие */
$allowedProjectActions = [
    'make:cmsvc',
    'make:migration',
];

/** Парсим основной .env-файл */
(new EnvService(INNER_PATH . '.env'))->load();

/** Парсим дополнительные .env-файлы */
if (file_exists(INNER_PATH . '.env.dev')) {
    (new EnvService(INNER_PATH . '.env.dev'))->load();
} elseif (file_exists(INNER_PATH . '.env.stage')) {
    (new EnvService(INNER_PATH . '.env.stage'))->load();
} elseif (file_exists(INNER_PATH . '.env.prod')) {
    (new EnvService(INNER_PATH . '.env.prod'))->load();
}

/** Разбираем параметры запуска скрипта */
$CMSVCName = null;
$action = '';

for ($i = 1; $i < $argc; $i++) {
    $arg = trim($argv[$i]);

    if (str_contains($arg, '-cmsvc=')) {
        unset($match);
        preg_match('#-cmsvc=(.*)$#', $arg, $match);
        $CMSVCName = TextHelper::snakeCaseToCamelCase(trim($match[1]));
    }

    if (str_starts_with($arg, 'make:')) {
        $action = $arg;

        if (!in_array($action, $allowedProjectActions)) {
            echoResult("Project action (" . $action . ") provided is not correct.", 'red');
            exit;
        }
    }
}

if ($_ENV['APP_ENV'] === 'DEV') {
    if ($action === 'make:cmsvc' && !is_null($CMSVCName)) {
        $LOCALES_LIST = [
            'EN',
            'RU',
        ];

        $pathToCMSVC = INNER_PATH . 'src/CMSVC/' . $CMSVCName . '/';

        /** Контроллер */
        $controllerCode = "<?php

namespace App\CMSVC\\" . $CMSVCName . ";

use Fraym\BaseObject\{BaseController, CMSVC};

/** @extends BaseController<" . $CMSVCName . "Service> */
#[CMSVC(
    model: " . $CMSVCName . "Model::class,
    service: " . $CMSVCName . "Service::class,
    view: " . $CMSVCName . "View::class
)]
class " . $CMSVCName . "Controller extends BaseController
{
}";
        createFile($pathToCMSVC, $CMSVCName . 'Controller', 'php', $controllerCode);

        /** Модель */
        $modelCode = "<?php

namespace App\CMSVC\\" . $CMSVCName . ";

use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\Attribute as Attribute;
use Fraym\Element\Item as Item;

#[Controller(" . $CMSVCName . "Controller::class)]
class " . $CMSVCName . "Model extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
}";
        createFile($pathToCMSVC, $CMSVCName . 'Model', 'php', $modelCode);

        /** Сервис */
        $serviceCode = "<?php

namespace App\CMSVC\\" . $CMSVCName . ";

use Fraym\BaseObject\{BaseService, Controller};

/** @extends BaseService<" . $CMSVCName . "Model> */
#[Controller(" . $CMSVCName . "Controller::class)]
class " . $CMSVCName . "Service extends BaseService
{
}";
        createFile($pathToCMSVC, $CMSVCName . 'Service', 'php', $serviceCode);

        /** Вьюшка */
        $viewCode = "<?php

namespace App\CMSVC\\" . $CMSVCName . ";

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Interface\Response;

/** @extends BaseView<" . $CMSVCName . "Service> */
#[TableEntity(
    name: '" . TextHelper::camelCaseToSnakeCase($CMSVCName) . "',
    table: '" . TextHelper::camelCaseToSnakeCase($CMSVCName) . "',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
    ]
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: true,
    deleteRight: true,
    viewRestrict: null,
    changeRestrict: null,
    deleteRestrict: null
)]
#[Controller(" . $CMSVCName . "Controller::class)]
class " . $CMSVCName . "View extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}";
        createFile($pathToCMSVC, $CMSVCName . 'View', 'php', $viewCode);

        /** Json-файлы локалей */
        $localeCode = '{
  "global": {
    "title": ""
  },
  "fraym_model": {
    "object_name": "",
    "object_messages": [
      "",
      "",
      ""
    ],
    "elements": {
      "name": {
        "shownName": ""
      }
    }
  }
}';
        createFile($pathToCMSVC, 'RU', 'json', $localeCode);
        createFile($pathToCMSVC, 'EN', 'json', $localeCode);

        /** Javascript-файл */
        $jsCode = "if (withDocumentEvents) {
    _arSuccess('some_action_name', function (jsonData, params, target) {
        showMessageFromJsonData(jsonData);
    })
        
    _arError('some_action_name', function (jsonData, params, target, error) {

    })
}";
        createFile($pathToCMSVC, 'js', 'js', $jsCode);

        /** Css-файл */
        $cssCode = 'div.kind_' . TextHelper::camelCaseToSnakeCase($CMSVCName) . ' {
    opacity: 1;
}';

        createFile($pathToCMSVC, 'css', 'css', $cssCode);
    }

    if ($action === 'make:migration') {
        $pathToMigrations = INNER_PATH . 'src/Migrations/';

        /** Название для миграции-фикстуры-sql */
        $migrationDate = date("YmdHis");

        /** Миграция */
        $migrationCode = "<?php

namespace App\Migrations;

use Fraym\BaseObject\BaseMigration;

class Migration" . date("YmdHis") . " extends BaseMigration
{
    public function up(): bool
    {
        return true;
    }

    public function down(): bool
    {
        return true;
    }
}";
        createFile($pathToMigrations, "Migration" . $migrationDate, 'php', $migrationCode);

        /** Фикстура */
        $fixtureCode = "<?php

namespace App\Migrations\Fixtures;

use Fraym\BaseObject\BaseFixture;
use Fraym\BaseObject\BaseMigration;

class Fixture" . $migrationDate . " extends BaseFixture
{
    public function init(BaseMigration \$migration): bool
    {
        return true;
    }
}";
        createFile($pathToMigrations . 'Fixtures/', "Fixture" . $migrationDate, 'php', $fixtureCode);

        /** SQL-файл */
        $sqlCode = "";
        createFile($pathToMigrations . 'Sql/', "Sql" . $migrationDate, 'sql', $sqlCode);
    }
}

function createFile(
    string $directory,
    string $fileName,
    string $fileExtension = 'php',
    string $contents = '',
): void {
    if (!is_dir($directory)) {
        try {
            mkdir($directory);
        } catch (Exception) {
            echoResult("Error on creating a directory " . $directory . ".", 'red');
        }
    }

    $filePath = $directory . $fileName . '.' . $fileExtension;

    if (!is_file($filePath)) {
        file_put_contents($filePath, $contents);
        echoResult("A " . $filePath . " created successfully.");
    } else {
        errorOnCreate($fileName, $filePath);
    }
}

function errorOnCreate(string $fileName, string $filePath): void
{
    echoResult("Error on creating a " . $fileName . ". Probably " . $filePath . " already exists.", 'red');
}
