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

namespace Fraym\Interface;

interface MinMaxChar
{
    public function getMinChar(): ?int;

    public function setMinChar(?int $minChar): static;

    public function getMaxChar(): ?int;

    public function setMaxchar(?int $maxChar): static;
}
