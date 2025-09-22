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

namespace Fraym\BaseObject\Trait;

use Fraym\BaseObject\BaseMigration;
use Fraym\Helper\ObjectsHelper;
use PDOException;

trait SqlTrait
{
    public function getSql(): ?string
    {
        $sqlPath = INNER_PATH . 'src/Migrations/Sql/Sql' .
            ObjectsHelper::getClassShortName($this::class, ($this instanceof BaseMigration ? 'Migration' : 'Fixture')) . '.sql';

        if (file_exists($sqlPath)) {
            return trim(file_get_contents($sqlPath));
        }

        return null;
    }

    public function executeSql(): bool
    {
        $SQL = $this->getSql();

        if (!is_null($SQL) && $SQL !== '') {
            try {
                return MIGRATE_DB->exec($SQL);
            } catch (PDOException $e) {
                error_log(
                    'Migration SQL exec error: ' . $e->getMessage() .
                        ' | Backtrace: ' . $e->getTraceAsString(),
                );
                exit;
            }
        }

        return false;
    }
}
