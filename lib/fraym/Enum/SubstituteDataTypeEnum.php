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

enum SubstituteDataTypeEnum: string
{
    /** Поиск по таблице и сортировка по ней же, а не по основной */
    case TABLE = 'table';

    /** Выбор из забитого массива */
    case ARRAY = 'array';
}
