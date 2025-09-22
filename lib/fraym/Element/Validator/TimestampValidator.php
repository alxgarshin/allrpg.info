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

use Fraym\Interface\ElementItem;

final class TimestampValidator extends BaseValidator
{
    public static function validate(ElementItem $element, mixed $value, array $options): bool
    {
        if (($options['table'] ?? false) && ($options['id'] ?? false)) {
            $result = DB->select($options['table'], [
                ['id', $options['id']],
            ], true);

            return $result && $result[$element->getName()] <= $value;
        }

        return true;
    }

    public static function getMessage(array $messageData): string
    {
        $LOC = self::getLocale();
        [$message, $multiple] = self::getSequenceMessagePart($messageData);

        return ($multiple ? sprintf($LOC['old_data_in_fields'], $message) : $LOC['old_data_in_field']);
    }
}
