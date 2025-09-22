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

use Fraym\Interface\Helper;

abstract class TextHelper implements Helper
{
    private static $cache = [];

    /** Проверка текстового поля на спам */
    public static function checkForSpam(string $text): bool
    {
        $is_spam = false;

        if (str_contains($text, 'url=')) {
            $is_spam = true;
        }

        return $is_spam;
    }

    /** Проверка текстового поля на заполненность */
    public static function checkForFullfill(string $text): bool
    {
        $text = trim($text);

        return preg_match('#[a-zа-я]+#i', trim($text)) && mb_strlen(trim($text)) >= 10;
    }

    /** Преобразование первой буквы текста в заглавную */
    public static function mb_ucfirst(string $str): string
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1, mb_strlen($str));
    }

    /** Преобразование первой буквы текста в строчную */
    public static function mb_lcfirst(string $str): string
    {
        return mb_strtolower(mb_substr($str, 0, 1)) . mb_substr($str, 1, mb_strlen($str));
    }

    /** Обрезка строки до указанного лимита */
    public static function cutStringToLimit(string $string, int $limit = 255, bool $striptags = false): string
    {
        if ($striptags) {
            $string = strip_tags(DataHelper::escapeOutput($string));
        } else {
            $string = DataHelper::escapeOutput($string);
        }

        if (mb_strlen($string) > $limit) {
            $string = mb_substr($string, 0, $limit) . '&#8230;';
        }

        return $string;
    }

    /** Превращение всех ссылок в тексте в активные */
    public static function basePrepareText(?string $content): ?string
    {
        return self::makeQuotesActive(self::makeATsActive(self::makeURLsActive(self::bbCodesInDescription($content))));
    }

    /** Превращение всех ссылок в тексте в активные */
    public static function makeURLsActive(?string $content): ?string
    {
        $content = $content ?? '';

        $url_pattern = '/(?<!")(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))/u';

        $content = preg_replace($url_pattern, '<a href="$1" target="_blank">$1</a>', $content);

        /** Замена зря переведенных url'ов в тегах типа img */
        $content = preg_replace('#="(https://|http://)<a href=[^>]*>([^<]*)</a>#', '="$1$2', $content);

        $content = preg_replace('#href="(?!http://)#', 'href="http://', $content);

        return preg_replace('#http://https://#', 'https://', $content);
    }

    /** Превращение кодов в форматирование */
    public static function bbCodesInDescription(?string $content): ?string
    {
        $content = $content ?? '';

        $content = preg_replace('#\*\*(.*?)\*\*#', '<b>$1</b>', $content);
        $content = preg_replace('#__(.*?)__#', '<i>$1</i>', $content);
        $content = preg_replace('#\[(.*?) (\d+)]#', '<img src="$1" width="$2%">', $content);

        return preg_replace('#\[(http|https|www)(.*?)]#', '<img src="$1$2">', $content);
    }

    /** Превращение > в начале строки в цитату */
    public static function makeQuotesActive(?string $content): ?string
    {
        $content = preg_replace('#(<br />|<br/>)#', '<br>', $content);
        $content = preg_replace('#^(&gt;)#', '>', $content);
        $content = preg_replace('#<br>&gt;#', '<br>>', $content);

        $content = preg_replace('#(^>|<br>>) (.*?)(<br>|$)#', '<blockquote>$2</blockquote>', $content);
        $content = preg_replace('#</blockquote>&gt;#', '</blockquote>>', $content);
        $content = preg_replace(
            '#</blockquote>> (.*?)<blockquote>#',
            '</blockquote><blockquote>$1</blockquote><blockquote>',
            $content,
        );

        return preg_replace(
            '#</blockquote>> (.*?)(<br>|$)#',
            '</blockquote><blockquote>$1</blockquote>',
            $content,
        );
    }

    /** Превращение @ обращений в ссылку */
    public static function makeATsActive(?string $content): ?string
    {
        return preg_replace('#@([^\[]+)\[(\d+)]#', '<a href="' . ABSOLUTE_PATH . '/people/$2/">$1</a>', $content);
    }

    /** Превращение ссылок на пользователя в @ обращение */
    public static function makeATsInactive(?string $content): ?string
    {
        return preg_replace(
            '#' . preg_quote('<a href="' . ABSOLUTE_PATH . '/people/', '/') . '(\d+)' . preg_quote(
                '/">',
                '/',
            ) . '([^<]+)' . preg_quote('</a>', '/') . '#',
            '@$2[$1]',
            $content,
        );
    }

    /** CamelCase в snake_case */
    public static function camelCaseToSnakeCase(string $name): string
    {
        if (self::$cache[$name] ?? false) {
            $result = self::$cache[$name];
        } else {
            $result = self::$cache[$name] = mb_strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        }

        return $result;
    }

    /** snake_case в CamelCase */
    public static function snakeCaseToCamelCase(string $name, bool $firstWordLowcase = false): string
    {
        $text = str_replace(' ', '', ucwords(str_replace('_', ' ', str_replace('/', '/ ', $name))));

        return $firstWordLowcase ? self::mb_lcfirst($text) : $text;
    }
}
