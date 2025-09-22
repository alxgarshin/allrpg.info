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

use Attribute;

/** Атрибут универсального указания устаревания метода или свойства класса */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final class Deprecated
{
    public function __construct(
        protected readonly string $deprecatedMessage,
    ) {
    }

    public function getMessage(): string
    {
        return $this->deprecatedMessage;
    }
}
