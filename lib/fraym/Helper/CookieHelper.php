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

abstract class CookieHelper implements Helper
{
    /** Массовое создание cookie
     * @param array<string|int, string|int|array> $cookies
     */
    public static function batchSetCookie(array $cookies, ?int $time = null): void
    {
        foreach ($cookies as $cookieKey => $cookie) {
            if (self::getCookie($cookieKey) !== (is_array($cookie) ? DataHelper::jsonFixedEncode($cookie) : $cookie)) {
                if (self::getCookie($cookieKey) !== null) {
                    self::deleteCookieFromHeaders($cookieKey);
                }

                setcookie($cookieKey, is_array($cookie) ? DataHelper::jsonFixedEncode($cookie) : (string) $cookie, CookieHelper::getOptions($time));
            }
        }
    }

    /** Получение cookie (в том числе выставленного только что) */
    public static function getCookie(string $cookieName, bool $isArray = false): string|array|null
    {
        $cookies = self::getCookiesFromHeaders();

        if (!is_null($cookies[$cookieName] ?? null)) {
            return $isArray ? DataHelper::jsonFixedDecode($cookies[$cookieName]) : $cookies[$cookieName];
        }

        if (!is_null($_COOKIE[$cookieName] ?? null)) {
            return $isArray ? DataHelper::jsonFixedDecode($_COOKIE[$cookieName]) : $_COOKIE[$cookieName];
        }

        return $isArray ? [] : null;
    }

    /** Массовое удаление cookie
     * @param string[] $cookiesNames
     */
    public static function batchDeleteCookie(array $cookiesNames): void
    {
        foreach ($cookiesNames as $cookieKey) {
            self::deleteCookieFromHeaders($cookieKey);

            setcookie($cookieKey, '', CookieHelper::getOptions(time() - 20));
        }
    }

    /** Удаление всех cookie сайта */
    public static function deleteAllCookies(): void
    {
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $cookies = explode(';', $_SERVER['HTTP_COOKIE']);

            foreach ($cookies as $cookie) {
                $parts = explode('=', $cookie);
                $name = trim($parts[0]);
                setcookie($name, '', CookieHelper::getOptions(time() - 20));
            }
        }
    }

    /** Получение стандартного набора свойств для всех cookie проекта */
    public static function getOptions(?int $time = null): array
    {
        return [
            'expires' => $time ?? (time() + 60 * 60 * 24 * 30),
            'path' => '/',
            'domain' => $_ENV['COOKIE_PATH'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }

    /** Получение текущих cookie из header'а */
    private static function getCookiesFromHeaders(): array
    {
        $cookies = [];
        $headers = headers_list();

        foreach ($headers as $header) {
            if (str_starts_with($header, 'Set-Cookie: ')) {
                $value = str_replace('&', urlencode('&'), substr($header, 12));
                parse_str(current(explode(';', $value, 2)), $pair);

                if (!in_array('deleted', $pair)) {
                    $cookies = array_merge_recursive($cookies, $pair);
                }
            }
        }

        return $cookies;
    }

    /** Удаление определенной cookie из headers */
    private static function deleteCookieFromHeaders(string $cookieKey): void
    {
        $cookies = self::getCookiesFromHeaders();

        if ($cookies[$cookieKey] ?? false) {
            header_remove('Set-Cookie');

            foreach ($cookies as $cookieName => $cookieValue) {
                if ($cookieName !== $cookieKey) {
                    setcookie($cookieName, $cookieValue, CookieHelper::getOptions());
                }
            }
        }
    }
}
