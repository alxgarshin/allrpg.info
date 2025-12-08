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

namespace Fraym\Element\Attribute;

use Attribute;

/** Данные используемые во время OnChange в качестве замены данных элемента */
#[Attribute(Attribute::TARGET_PROPERTY)]
class OnChange
{
    public function __construct(
        /** Четкие данные элемента */
        public mixed $data = null,

        /** Имя функции, предоставляющей данные элемента */
        public ?string $callback = null,
    ) {
    }
}
