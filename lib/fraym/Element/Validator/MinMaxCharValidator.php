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

use Fraym\Element\Item\Password;
use Fraym\Interface\{ElementItem, MinMaxChar};

final class MinMaxCharValidator extends BaseValidator
{
    public static function validate(ElementItem $element, mixed $value, array $options): bool
    {
        $attribute = $element->getAttribute();

        if ($attribute instanceof MinMaxChar) {
            $length = mb_strlen($value ?? '');

            return !($length < ($attribute->getMinChar() ?? 0) || (!is_null($attribute->getMaxchar()) && $length > $attribute->getMaxchar())) || (($element instanceof Password || !$element->getObligatory()) && $length === 0);
        }

        return true;
    }

    public static function getMessage(array $messageData): string
    {
        $LOC = self::getLocale();
        [$message, $multiple] = self::getSequenceMessagePart($messageData);

        return $LOC[($multiple ? 'maxminchar_in_fields' : 'maxminchar_in_field')] . ' ' . $message;
    }
}
