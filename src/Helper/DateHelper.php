<?php

declare(strict_types=1);

namespace App\Helper;

use Fraym\Helper\LocaleHelper;

abstract class DateHelper extends \Fraym\Helper\DateHelper
{
    /** Перевод разницы между датами */
    /*public static function timeDiffToYearsAndMonths(array $datesSets, bool $filterData = false): string
    {
        $y = 0;
        $m = 0;
        foreach ($datesSets as $dateSet) {
            $datetime1 = new DateTime($dateSet[0]);
            $datetime2 = new DateTime($dateSet[1] ?? date("Y-m-d"));
            $interval = $datetime2->diff($datetime1);
            $y += $interval->y;
            $m += $interval->m;
        }
        $y += floor($m / 12);
        $m = $m % 12;

        if ($filterData) {
            $result = 0;

            if ($y < 1 && $m > 0) {
                $result = 1;
            } elseif ($y >= 1 && $y < 3) {
                $result = 2;
            } elseif ($y >= 3 && $y < 5) {
                $result = 3;
            } elseif ($y >= 5) {
                $result = 4;
            }
        } else {
            if ($y >= 5 && $y <= 20) {
                $yearText = 'лет';
            } elseif ($y == 1 || $y % 10 == 1) {
                $yearText = 'год';
            } elseif ($y % 10 >= 2 && $y % 10 <= 4) {
                $yearText = 'года';
            } else {
                $yearText = 'лет';
            }

            if ($m >= 5 && $m <= 20) {
                $monthText = 'месяцев';
            } elseif ($m == 1 || $m % 10 == 1) {
                $monthText = 'месяц';
            } elseif ($m % 10 >= 2 && $m % 10 <= 4) {
                $monthText = 'месяца';
            } else {
                $monthText = 'месяцев';
            }

            $result = $y.' '.$yearText.' '.$m.' '.$monthText;
        }

        return $result;
    }*/

    /** Вывод времени и даты в стандартизированном формате */
    public static function showDateTimeUsual(int|string $timestamp): string
    {
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        if (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        } else {
            $timestamp = (int) $timestamp;
        }
        $time = time();

        if ($time - $timestamp > 3600 * 48) {
            if (date('Y', $timestamp) < date('Y', $time)) {
                $str = date('d ', $timestamp) . DateHelper::monthname(date('n', $timestamp)) . ' ' . date('Y', $timestamp) . ' ' .
                    $LOCALE_FRAYM['datetime']['at'] . ' ' . date('H:i', $timestamp);
            } else {
                $str = date('d ', $timestamp) . DateHelper::monthname(date('n', $timestamp)) . ' ' .
                    $LOCALE_FRAYM['datetime']['at'] . ' ' . date('H:i', $timestamp);
            }
        } elseif ($time - $timestamp >= 3600 * 24 || date('d', $time) !== date('d', $timestamp)) {
            $str = $LOCALE_FRAYM['datetime']['yesterday'] . ' ' . $LOCALE_FRAYM['datetime']['at'] . ' ' . date('H:i', $timestamp);
        } else {
            $str = $LOCALE_FRAYM['datetime']['today'] . ' ' . $LOCALE_FRAYM['datetime']['at'] . ' ' . date('H:i', $timestamp);
        }

        return $str;
    }

    /** Вывод времени и даты в человекочитаемом формате */
    public static function showDateTime(string|int $timestamp, bool $shortestVersion = false): string
    {
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $str = '';

        if (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        }
        $time = time();

        if ($time - $timestamp > 3600 * 48 || date('d', $time) - date('d', $timestamp) > 1) {
            if (date('Y', $timestamp) < date('Y', $time)) {
                $str = date('d ', $timestamp) . DateHelper::monthname(date('n', $timestamp)) . ' ' . date('Y', $timestamp) . ' ' .
                    $LOCALE_FRAYM['datetime']['at'] . ' ' . date('H:i', $timestamp);

                if ($shortestVersion) {
                    $str = date('d ', $timestamp) . mb_substr(DateHelper::monthname(date('n', $timestamp)), 0, 3) . ' ' . date('Y', $timestamp);
                }
            } else {
                $str = date('d ', $timestamp) . DateHelper::monthname(date('n', $timestamp)) . ' ' .
                    $LOCALE_FRAYM['datetime']['at'] . ' ' . date('H:i', $timestamp);

                if ($shortestVersion) {
                    $str = date('d ', $timestamp) . mb_substr(DateHelper::monthname(date('n', $timestamp)), 0, 3);
                }
            }
        } elseif ($time - $timestamp >= 3600 * 24 || date('d', $time) - date('d', $timestamp) === 1) {
            $str = $LOCALE_FRAYM['datetime']['yesterday'] . ' ' . $LOCALE_FRAYM['datetime']['at'] . ' ' . date('H:i', $timestamp);

            if ($shortestVersion) {
                $str = $LOCALE_FRAYM['datetime']['yesterday'];
            }
        } elseif ($time - $timestamp >= 3600) {
            $str = $LOCALE_FRAYM['datetime']['today'] . ' ' . $LOCALE_FRAYM['datetime']['at'] . ' ' . date('H:i', $timestamp);

            if ($shortestVersion) {
                $str = date('H:i', $timestamp);
            }
        } elseif ($time - $timestamp > 60) {
            $str = round(($time - $timestamp) / 60) . ' ' . $LOCALE_FRAYM['datetime']['minutes'] .
                LocaleHelper::declineFemale((int) round(($time - $timestamp) / 60)) . ' ' . $LOCALE_FRAYM['datetime']['before'];

            if ($shortestVersion) {
                $str = date('H:i', $timestamp);
            }
        } elseif ($time - $timestamp === 0) {
            $str = $LOCALE_FRAYM['datetime']['few_seconds'];

            if ($shortestVersion) {
                $str = date('H:i', $timestamp);
            }
        } elseif ($time - $timestamp <= 60) {
            $str = ($time - $timestamp) . ' ' . $LOCALE_FRAYM['datetime']['seconds'] .
                LocaleHelper::declineFemale($time - $timestamp, 2) . ' ' . $LOCALE_FRAYM['datetime']['before'];

            if ($shortestVersion) {
                $str = date('H:i', $timestamp);
            }
        }

        return $str;
    }

    /** Написание даты в формате "от-до" */
    /*public static function dateFromToText(int $dateFrom, int $dateTo): string
    {
        $LOCALE_FRAYM = Locale::getLocale(['fraym']);

        if (date("d.m.Y", $dateFrom) == date("d.m.Y", $dateTo)) {
            //если день совпадает
            $eventDate = date("d.m.Y", $dateFrom).' '.$LOCALE_FRAYM['datetime']['from'].' '.date('H:i', $dateFrom).' '.
                $LOCALE_FRAYM['datetime']['to'].' '.date('H:i', $dateTo);
        } else {
            $eventDate = $LOCALE_FRAYM['datetime']['from'].' '.date('d.m.Y H:i', $dateFrom).' '.
                $LOCALE_FRAYM['datetime']['to'].' '.date('d.m.Y H:i', $dateTo);
        }

        return $eventDate;
    }*/

    /** Вывод даты события в календаре */
    public static function dateFromToCalendar(int $dateFrom, int $dateTo): string
    {
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_EVENTLIST = LocaleHelper::getLocale(['eventlist', 'global']);

        $eventDate = '';

        $today = strtotime('today');
        $tomorrow = strtotime('tomorrow');

        if ($dateTo < $today) { // если событие прошло, т.е. дата окончания меньше сегодня
            $eventDate = date('d.m.Y', $dateFrom) . ' ' . $LOCALE_FRAYM['datetime']['at'] . ' ' . date('H:i', $dateFrom);
        } elseif ($dateFrom >= $tomorrow) { // если событие в будущем, т.е. дата начала больше или равно завтра
            $eventDate = date('d.m.Y', $dateFrom) . ' ' . $LOCALE_FRAYM['datetime']['at'] . ' ' . date('H:i', $dateFrom);
        } elseif ($dateFrom >= $today && $dateTo < $tomorrow) { // если дата начала и окончания сегодня
            $eventDate = $LOCALE_FRAYM['datetime']['today'] . ' ' . $LOCALE_FRAYM['datetime']['from'] . ' ' . date('H:i', $dateFrom) . ' ' .
                $LOCALE_FRAYM['datetime']['to'] . ' ' . date('H:i', $dateTo);
        } elseif ($dateFrom < $today && $dateTo >= $tomorrow) { // если дата начала ранее сегодня, но дата окончания более
            $eventDate = $LOCALE_EVENTLIST['during_day'];
        } elseif ($dateFrom >= $today) { // если дата начала сегодня
            $eventDate = $LOCALE_FRAYM['datetime']['today'] . ' ' . $LOCALE_FRAYM['datetime']['from'] . ' ' . date('H:i', $dateFrom);
        } else { // если дата окончания сегодня
            $eventDate = $LOCALE_FRAYM['datetime']['today'] . ' ' . $LOCALE_FRAYM['datetime']['to'] . ' ' . date('H:i', $dateTo);
        }

        return $eventDate;
    }

    /** Вывод даты события */
    public static function dateFromToEvent(string $dateFrom, string $dateTo, bool $hideYear = false): string
    {
        $eventDate = '';

        if ($dateFrom !== '' && $dateTo !== '') {
            if (date('Y', strtotime($dateFrom)) !== date('Y', strtotime($dateTo))) {
                $eventDate = date('j', strtotime($dateFrom)) . ' ' . DateHelper::monthname(date('m', strtotime($dateFrom))) .
                    ($hideYear ? '' : ' ' . date('Y', strtotime($dateFrom))) .
                    ' – ' . date('j', strtotime($dateTo)) . ' ' . DateHelper::monthname(date('m', strtotime($dateTo))) .
                    ($hideYear ? '' : ' ' . date('Y', strtotime($dateTo)));
            } elseif (date('m', strtotime($dateFrom)) !== date('m', strtotime($dateTo))) {
                $eventDate = date('j', strtotime($dateFrom)) . ' ' . DateHelper::monthname(date('m', strtotime($dateFrom))) .
                    ' – ' . date('j', strtotime($dateTo)) . ' ' . DateHelper::monthname(date('m', strtotime($dateTo))) .
                    ($hideYear ? '' : ' ' . date('Y', strtotime($dateTo)));
            } elseif ($dateFrom === $dateTo) {
                $eventDate = date('j', strtotime($dateFrom)) . ' ' . DateHelper::monthname(date('m', strtotime($dateFrom))) .
                    ($hideYear ? '' : ' ' . date('Y', strtotime($dateFrom)));
            } else {
                $eventDate = date('j', strtotime($dateFrom)) . '-' . date('j', strtotime($dateTo)) . ' ' .
                    DateHelper::monthname(date('m', strtotime($dateTo))) .
                    ($hideYear ? '' : ' ' . date('Y', strtotime($dateTo)));
            }
        }

        return $eventDate;
    }

    /** Вывод даты новости */
    public static function dateFromTo(array $newsItem): array
    {
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $newsDate = '';

        if (!empty($newsItem['from_date']) || !empty($newsItem['to_date'])) {
            if ($newsItem['from_date'] === $newsItem['to_date']) {
                $newsDateBase = strtotime($newsItem['from_date']);
                $newsDate = date('d', $newsDateBase) . ' ' . DateHelper::monthname(date('m', $newsDateBase), true) . ' ' .
                    date('Y', $newsDateBase);
            } else {
                if ($newsItem['from_date'] !== '') {
                    $newsDateBase = strtotime($newsItem['from_date']);
                    $newsDate .= $LOCALE_FRAYM['datetime']['from'] . ' ' . date('d', $newsDateBase) . ' ' .
                        DateHelper::monthname(date('m', $newsDateBase), true) . ' ';

                    if ($newsItem['to_date'] === '') {
                        $newsDate .= date('Y', $newsDateBase);
                    }
                }

                if ($newsItem['to_date'] !== '') {
                    if ($newsDate !== '') {
                        $newsDate .= ' ';
                    }
                    $newsDateBase = strtotime($newsItem['to_date']);
                    $newsDate .= $LOCALE_FRAYM['datetime']['to'] . ' ' . date('d', $newsDateBase) . ' ' .
                        DateHelper::monthname(date('m', $newsDateBase), true) . ' ' . date('Y', $newsDateBase);
                }
            }
            $result['range'] = true;
        } else {
            $newsDateBase = strtotime($newsItem['show_date']);
            $newsDate = date('d', $newsDateBase) . ' ' . DateHelper::monthname(date('m', $newsDateBase), true) . ' ';

            if (date('Y', $newsDateBase) !== date('Y')) {
                $newsDate .= date('Y', $newsDateBase) . ' ';
            }
            $newsDate .= date('H:m', $newsDateBase);
            $result['range'] = false;
        }
        $result['date'] = $newsDate;

        return $result;
    }

    /** Получение названия месяца на основе порядкового номера */
    public static function monthname(string|int $num, bool $short = false, bool $base = false): string
    {
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $num = (int) $num;

        if ($num < 1) {
            $num = 12 - $num;
        }

        if ($num > 12) {
            $num = $num - 12;
        }

        $monthname = $LOCALE_FRAYM['months'][$num];

        if ($base) {
            $monthname = $LOCALE_FRAYM['months_base'][$num];
        }

        if ($short) {
            $monthname = mb_substr($monthname, 0, 3, 'UTF-8');
        }

        return $monthname;
    }
}
