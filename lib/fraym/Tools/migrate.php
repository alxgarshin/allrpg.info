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

/** Файл управления миграциями из командной строки. Пример использования см. в bin/recreateDb
 *
 * Основной синтаксис:
 * migrate.php --env=[dev|test|stage|prod] database:[drop|migrate|migrate:[up|down]] [--migration=20230627140700]
 */

use Fraym\BaseObject\{BaseFixture, BaseMigration};
use Fraym\Enum\OperandEnum;
use Fraym\Service\{CacheService, EnvService, SQLDatabaseService};

require_once __DIR__ . '/../BaseVar.php';
require_once __DIR__ . '/echoResult.php';

/** Возможный набор действий в командах вида database:действие */
$allowedDatabaseActions = [
    'drop',
    'migrate',
    'migrate:up',
    'migrate:down',
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
$migrationFile = null;
$action = 'migrate';
$migrationDirection = 'up';

for ($i = 1; $i < $argc; $i++) {
    $arg = trim($argv[$i]);

    if (str_contains($arg, '-env=test')) {
        define('TEST', true);
    }

    if (str_contains($arg, '-migration=')) {
        unset($match);
        preg_match('#-migration=(.*)$#', $arg, $match);
        $migrationFile = $match[1];

        if (!str_ends_with($migrationFile, '.php')) {
            $migrationFile .= '.php';
        }

        if (!str_starts_with($migrationFile, 'Migration')) {
            $migrationFile = 'Migration' . $migrationFile;
        }

        if (!file_exists(INNER_PATH . 'src/Migrations/' . $migrationFile)) {
            echoResult("Migration " . $migrationFile . " was not found in src/Migrations folder.", 'red');
            exit;
        }
    }

    if (str_starts_with($arg, 'database:')) {
        unset($match);
        preg_match('#database:(.*)$#', $arg, $match);
        $action = $match[1];

        if (!in_array($action, $allowedDatabaseActions)) {
            echoResult("Database action (" . $action . ") provided is not correct.", 'red');
            exit;
        } elseif (str_starts_with($action, 'migrate:')) {
            unset($match);
            preg_match('#migrate:(.*)$#', $action, $match);
            $direction = $match[1];

            if ($direction === 'down') {
                $migrationDirection = $direction;
            }
        }
    }
}

if (!defined('TEST')) {
    define('TEST', false);
}

/** @phpstan-ignore-next-line */
if (TEST && file_exists(INNER_PATH . '.env.test')) {
    (new EnvService(INNER_PATH . '.env.test'))->load();
}

/** Соединение с БД должно предполагать, что БД еще не существует (например, она удалена в результате drop до этого) */
$databaseNameCache = $_ENV['DATABASE_NAME'];
$_ENV['DATABASE_NAME'] = '';

define('MIGRATE_DB', SQLDatabaseService::getInstance());

if (in_array($_ENV['APP_ENV'], ['DEV', 'TEST'])) {
    $databaseUser = $_ENV['DATABASE_USER'];
    $databasePassword = $_ENV['DATABASE_PASSWORD'];
    $_ENV['DATABASE_USER'] = 'root';
    $_ENV['DATABASE_PASSWORD'] = 'secret';
    define("ROOT_DB", SQLDatabaseService::forceCreate());
    $_ENV['DATABASE_NAME'] = $databaseNameCache;
    $_ENV['DATABASE_USER'] = $databaseUser;
    unset($databaseUser);
    $_ENV['DATABASE_PASSWORD'] = $databasePassword;
    unset($databasePassword);

    if ($action === 'drop') {
        /** Полный сброс базы данных в dev и test окружениях */
        if (ROOT_DB->query("DROP DATABASE IF EXISTS `" . $_ENV['DATABASE_NAME'] . "`;", []) !== false) {
            echoResult("Database `" . $_ENV['DATABASE_NAME'] . "` dropped.");
        } else {
            echoResult("Database `" . $_ENV['DATABASE_NAME'] . "` not dropped.", 'red');
        }
        exit;
    } elseif ($action === 'migrate') {
        /** Проверка на наличие и создание в случае необходимости базы данных */
        $checkForDB = MIGRATE_DB->query("SHOW DATABASES LIKE '" . $_ENV['DATABASE_NAME'] . "';", [], true);

        if (!$checkForDB) {
            ROOT_DB->query(
                "CREATE DATABASE IF NOT EXISTS `" . $_ENV['DATABASE_NAME'] . "`;",
                [],
            );

            ROOT_DB->query(
                "GRANT ALL PRIVILEGES ON `" . $_ENV['DATABASE_NAME'] . "`.* TO '" . $_ENV['DATABASE_USER'] . "'@'%' IDENTIFIED BY '" . $_ENV['DATABASE_PASSWORD'] . "';",
                [],
            );
        }
    }
}

if ($action === 'drop') {
    echoResult("Cannot drop a non-dev and non-test database.", 'red');
} elseif ($action === 'migrate') {
    define('CACHE', CacheService::getInstance());

    $_ENV['DATABASE_NAME'] = $databaseNameCache;
    unset($databaseNameCache);

    MIGRATE_DB->query("USE `" . $_ENV['DATABASE_NAME'] . "`;", []);

    MIGRATE_DB->query(
        "CREATE TABLE IF NOT EXISTS `migration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration_id` varchar(100) NOT NULL,
  `migrated_at` timestamp NOT NULL,
  `migration_result` json,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
        [],
    );

    $appendedMigrations = [];
    $appendedMigrationsData = MIGRATE_DB->select('migration', null, false, ['migration_id']);

    foreach ($appendedMigrationsData as $appendedMigrationsItem) {
        $appendedMigrations[] = $appendedMigrationsItem['migration_id'] . '.php';
    }

    if (!is_null($migrationFile)) {
        if (!in_array($migrationFile, $appendedMigrations) || $migrationDirection === 'down') {
            executeMigration($migrationFile, $migrationDirection);
        } else {
            echoResult("Migration " . $migrationFile . " has already been appended to the database `" . $_ENV['DATABASE_NAME'] . "`.", 'red');
        }
    } else {
        $migrationDirection = 'up';
        $files = array_diff(scandir(INNER_PATH . 'src/Migrations/'), ['.', '..', 'Sql', 'Fixtures']);
        sort($files);

        $foundMigration = false;
        $executedMigration = false;

        foreach ($files as $migrationFile) {
            if (preg_match('#^Migration\d+\.php$#', $migrationFile)) {
                $foundMigration = true;

                if (!in_array($migrationFile, $appendedMigrations)) {
                    $executedMigration = true;
                    executeMigration($migrationFile, $migrationDirection);
                }
            }
        }

        if ($foundMigration && !$executedMigration) {
            echoResult("All migrations have been already applied to the database `" . $_ENV['DATABASE_NAME'] . "`.");
        }
    }
}

function executeMigration(string $migrationFile, string $migrationDirection): void
{
    /** Отрабатываем класс миграции */
    $migrationClassName = 'App\\Migrations\\' . str_replace('.php', '', $migrationFile);
    $migration = new $migrationClassName();

    if ($migration instanceof BaseMigration) {
        if ($migration->$migrationDirection()) {
            echoResult(
                "Migration " . $migrationFile . " " .
                    ($migrationDirection === 'up' ? "done" : "reversed") .
                    " on database `" . $_ENV['DATABASE_NAME'] . "`.",
            );

            MIGRATE_DB->query("SET time_zone='+03:00';", []);

            $migrationResultFullData = [
                'direction' => $migrationDirection,
                'status' => ($migrationDirection === 'up' ? "done" : "reversed"),
                'result' => $migration->migrationResult,
            ];
            MIGRATE_DB->insert('migration', [
                'migration_id' => str_replace('.php', '', $migrationFile),
                'migrated_at' => new DateTime('now'),
                'migration_result' => ['migration_result', $migrationResultFullData, [OperandEnum::JSON]],
            ]);

            if ($migrationDirection === 'up') {
                /** Отрабатываем класс фикстуры, только если мы в dev или test окружении */
                if (in_array($_ENV['APP_ENV'], ['DEV', 'TEST'])) {
                    $migrationRecordId = MIGRATE_DB->lastInsertId();
                    $fixtureResult = uploadFixture($migration);

                    if (!is_null($fixtureResult)) {
                        $migrationResultFullData['fixtureResult'] = $fixtureResult;
                        MIGRATE_DB->update(
                            'migration',
                            ['migration_result' => ['migration_result', $migrationResultFullData, [OperandEnum::JSON]]],
                            ['id' => $migrationRecordId],
                        );
                    }
                }
            }
        } else {
            echoResult(
                "Migration " . $migrationFile . " was not " .
                    ($migrationDirection === 'up' ? "done" : "reversed") .
                    " on database `" . $_ENV['DATABASE_NAME'] . "`.",
                'red',
            );
        }
    }
}

function uploadFixture(BaseMigration $migration): ?string
{
    $fixture = $migration->getFixture();

    if ($fixture instanceof BaseFixture) {
        if ($fixture->init($migration)) {
            echoResult("Fixture " . $fixture::class . " loaded into database `" . $_ENV['DATABASE_NAME'] . "`.");

            return $fixture->fixtureResult;
        } else {
            echoResult("Fixture " . $fixture::class . " not loaded into database `" . $_ENV['DATABASE_NAME'] . "`.", 'red');
        }
    }

    return null;
}
