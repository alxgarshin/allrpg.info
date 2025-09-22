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

use Fraym\Helper\LocaleHelper;
use Fraym\Interface\{ElementItem, Validator};

abstract class BaseValidator implements Validator
{
    public static function getName(): string
    {
        return static::class;
    }

    protected static function getLocale(): array
    {
        return LocaleHelper::getLocale(['fraym', 'fraymActions']);
    }

    protected static function getSequenceMessagePart(array $messageData, bool $withInString = true): array
    {
        $LOC = self::getLocale();
        $multiple = 0;

        if ($withInString) {
            $message = '';

            foreach ($messageData as $stringId => $groupData) {
                foreach ($groupData as $elementsArray) {
                    foreach ($elementsArray as $element) {
                        $multiple++;

                        /** @var ElementItem $element */
                        $message .= '«' . $element->getShownName() . '»';

                        if ($stringId > 0) {
                            $message .= ' ' . $LOC['in_string'] . $stringId;
                        }
                        $message .= ', ';
                    }
                }
            }

            $message = mb_substr($message, 0, mb_strlen($message) - 2) . '.';
        } else {
            $sequenceStarted = false;
            $message = '';
            $i = 0;

            foreach ($messageData as $stringId => $groupData) {
                $nextStringId = key(next($messageData));

                if ($i === 0) {
                    $message = $stringId;

                    if ($nextStringId === $stringId + 1) {
                        $message .= '-';
                        $sequenceStarted = true;
                    } elseif (isset($nextStringId)) {
                        $message .= ', ';
                        $sequenceStarted = false;
                    }
                } elseif ($i === count($messageData) - 1) {
                    $message .= $stringId;
                } elseif ($nextStringId > $stringId + 1) {
                    $message .= $stringId . ', ';
                    $sequenceStarted = false;
                } elseif ($nextStringId === $stringId + 1) {
                    if (!$sequenceStarted) {
                        $message .= $stringId . '-';
                        $sequenceStarted = true;
                    }
                }

                $i++;
            }

            $multiple = $i;
        }

        return [$message, $multiple > 1];
    }
}
