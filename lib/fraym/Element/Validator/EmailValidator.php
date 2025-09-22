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

namespace Fraym\Element\Validator;

use Fraym\Element\Item\Email;
use Fraym\Interface\ElementItem;

final class EmailValidator extends BaseValidator
{
    public static function validate(ElementItem $element, mixed $value, array $options): bool
    {
        if (method_exists($element, 'validateEmail')) {
            /** @var Email $element */
            return (bool) ($element->validateEmail($value));
        }

        return true;
    }

    public static function getMessage(array $messageData): string
    {
        $LOC = self::getLocale();
        [$message, $multiple] = self::getSequenceMessagePart($messageData);

        return $LOC[($multiple ? 'wrong_format_in_fields' : 'wrong_format_in_field')] . ' ' . $message;
    }
}
