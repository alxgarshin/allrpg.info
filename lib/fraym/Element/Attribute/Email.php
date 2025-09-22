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
use Fraym\Element\Validator\{EmailValidator, ObligatoryValidator};

/** Строка с email */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Email extends Text
{
    protected array $basicElementValidators = [
        ObligatoryValidator::class,
        EmailValidator::class,
    ];
}
