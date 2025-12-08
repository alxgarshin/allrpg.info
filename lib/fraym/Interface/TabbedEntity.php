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

use Fraym\Element\Item\Tab;

interface TabbedEntity
{
    public ?array $tabs { get; set; }

    public function addTab(Tab $baseTab): static;
}
