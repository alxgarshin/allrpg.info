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

final class RepeatPasswordValidator extends BaseValidator
{
    public static function validate(ElementItem $element, mixed $value, array $options): bool
    {
        return $value === ($options['compareValue'] ?? null);
    }

    public static function getMessage(array $messageData): string
    {
        $LOC = self::getLocale();
        [$message, $multiple] = self::getSequenceMessagePart($messageData);

        return $LOC[($multiple ? 'unmatched_passwords_in_fields' . $message : 'unmatched_passwords_in_field')];
    }
}
