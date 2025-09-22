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

/** Функция после OnCreate. Добавляется в Service */
#[Attribute(Attribute::TARGET_CLASS)]
class PostCreate
{
    public function __construct(
        /** Имя функции  */
        private string $callback = 'postCreate',
    ) {
    }

    public function getCallback(): ?string
    {
        return $this->callback;
    }

    public function setCallback(?string $callback): static
    {
        $this->callback = $callback;

        return $this;
    }
}
