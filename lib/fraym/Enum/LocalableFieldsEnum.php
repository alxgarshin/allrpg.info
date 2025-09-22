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

namespace Fraym\Enum;

enum LocalableFieldsEnum: string
{
    case name = 'name';
    case shownName = 'shownName';
    case helpText = 'helpText';
    case values = 'values';
}
