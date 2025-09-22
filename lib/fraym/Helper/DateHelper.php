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

namespace Fraym\Helper;

use DateTimeImmutable;
use Fraym\Interface\Helper;

abstract class DateHelper implements Helper
{
    /** Получение текущей даты в нужной форме: сейчас это timestamp, но будет DateTime */
    public static function getNow(): int
    {
        return time();
    }

    /** Преобразование даты из строки в DateTimeImmutable */
    public static function setDateToUTC(DateTimeImmutable|int|string|null $dateTime): ?DateTimeImmutable
    {
        if (is_numeric($dateTime)) {
            $dateTimeFixed = new DateTimeImmutable();
            $dateTimeFixed = $dateTimeFixed->setTimestamp((int) $dateTime);
            $dateTime = $dateTimeFixed;
        } elseif (is_string($dateTime)) {
            $dateTime = trim($dateTime) ? new DateTimeImmutable($dateTime) : null;
        }

        //$dateTime?->setTimeZone(new DateTimeZone('UTC'));

        return $dateTime;
    }

    /** Самый простой из возможных выводов даты и времени */
    public static function basicShowDateTime(int|string|null $timestamp): ?string
    {
        if (is_null($timestamp) || $timestamp === '') {
            return null;
        }

        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $timestamp = is_string($timestamp) ? (int) trim($timestamp) : $timestamp;

        return self::setDateToUTC($timestamp)?->format('d.m.Y ' . $LOCALE_FRAYM['datetime']['at'] . ' H:i');
    }
}
