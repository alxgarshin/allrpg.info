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

abstract class BaseFixture
{
    use SqlTrait;

    public string $fixtureResult = 'success';

    abstract public function init(BaseMigration $migration): bool;
}
