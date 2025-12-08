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

namespace Fraym\Element\Attribute;

use Attribute;
use Fraym\Element\Validator\{LoginValidator, MinMaxCharValidator, ObligatoryValidator};

/** Поле логина */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Login extends Text
{
    public array $basicElementValidators = [
        ObligatoryValidator::class,
        MinMaxCharValidator::class,
        LoginValidator::class,
    ];
}
