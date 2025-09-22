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

namespace Fraym\BaseObject;

use Fraym\BaseObject\Trait\SqlTrait;
use Fraym\Helper\ObjectsHelper;

abstract class BaseMigration
{
    use SqlTrait;

    public string $migrationResult = 'success';

    abstract public function up(): bool;

    abstract public function down(): bool;

    public function getFixture(): ?BaseFixture
    {
        $fixtureClass = 'App\\Migrations\\Fixtures\\Fixture' . ObjectsHelper::getClassShortName($this::class, 'Migration');

        return new $fixtureClass();
    }
}
