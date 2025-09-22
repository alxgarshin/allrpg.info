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

use Fiber;
use Fraym\Interface\Helper;

abstract class CurlHelper implements Helper
{
    /** Асинхронный curl-запрос */
    public static function curlPostAsync(string $url, array $params): void
    {
        if ($_ENV['APP_ENV'] === 'prod') {
            $fiber = new Fiber(static function (string $url, array $params): void {
                $post_params = [];

                foreach ($params as $key => &$val) {
                    if (is_array($val)) {
                        $val = implode(',', $val);
                    }
                    $post_params[] = $key . '=' . urlencode($val);
                }
                $post_string = implode('&', $post_params);

                $parts = parse_url($url);

                $fp = fsockopen(
                    $parts['host'],
                    $parts['port'] ?? 80,
                    $errno,
                    $errstr,
                    30,
                );

                $out = "POST " . $parts['path'] . " HTTP/1.1\r\n";
                $out .= "Host: " . $parts['host'] . "\r\n";
                $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
                $out .= "Content-Length: " . strlen($post_string) . "\r\n";
                $out .= "Connection: Close\r\n\r\n";
                $out .= $post_string;

                fwrite($fp, $out);
                fclose($fp);
            });
            $fiber->start(...[$url, $params]);
        }
    }
}
