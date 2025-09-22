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

namespace Fraym\Element\Item;

/** Строка с email */
class Email extends Text
{
    public static function validateEmail($email): bool|string
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
