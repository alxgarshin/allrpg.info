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

enum OperandEnum
{
    case JSON;
    case LOWER;
    case UPPER;
    case LIKE;
    case NOT_LIKE;
    case IS_NULL;
    case NOT_NULL;
    case LESS;
    case MORE;
    case LESS_OR_EQUAL;
    case MORE_OR_EQUAL;
    case NOT_EQUAL;
}
