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

namespace Fraym\Element\Attribute\Trait;

trait MinMaxChar
{
    /** Минимальное количество символов */
    private ?int $minChar;

    /** Максимальное количество символов */
    private ?int $maxChar;

    public function getMinChar(): ?int
    {
        return $this->minChar;
    }

    public function setMinChar(?int $minChar): static
    {
        $this->minChar = $minChar;

        return $this;
    }

    public function getMaxChar(): ?int
    {
        return $this->maxChar;
    }

    public function setMaxchar(?int $maxChar): static
    {
        $this->maxChar = $maxChar;

        return $this;
    }
}
