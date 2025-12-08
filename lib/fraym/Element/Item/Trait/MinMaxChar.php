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

namespace Fraym\Element\Item\Trait;

trait MinMaxChar
{
    public function asArrayMinMaxChar(): array
    {
        return [
            'minChar' => $this->getMinChar(),
            'maxChar' => $this->getMaxChar(),
        ];
    }

    public function getMinChar(): ?int
    {
        return $this->getAttribute()->minChar;
    }

    public function getMaxChar(): ?int
    {
        return $this->getAttribute()->maxChar;
    }
}
