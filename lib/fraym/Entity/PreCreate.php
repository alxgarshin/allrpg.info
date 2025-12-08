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

namespace Fraym\Entity;

use Attribute;

/** Функция перед OnCreate. Добавляется в Service */
#[Attribute(Attribute::TARGET_CLASS)]
class PreCreate
{
    public function __construct(
        /** Имя функции  */
        public string $callback = 'preCreate',
    ) {
    }
}
