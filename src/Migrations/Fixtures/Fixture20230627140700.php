<?php

declare(strict_types=1);

namespace App\Migrations\Fixtures;

use Fraym\BaseObject\{BaseFixture, BaseMigration};

class Fixture20230627140700 extends BaseFixture
{
    public function init(BaseMigration $migration): bool
    {
        return $this->executeSql();
    }
}
