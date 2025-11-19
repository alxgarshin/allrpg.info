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

use DateTime;
use Fraym\Interface\Helper;

abstract class AuthHelper implements Helper
{
    /** Создание JWT-токена текущего пользователя */
    public static function generateAuthToken(): string
    {
        $tokenData = [
            "exp" => time() + 3600,
            "id" => CURRENT_USER->id(),
            "sid" => CURRENT_USER->sid(),
            "rights" => CURRENT_USER->getAllRights(),
            "bazecount" => CURRENT_USER->getBazeCount(),
            "block_save_referer" => CURRENT_USER->getBlockSaveReferer(),
            "block_auto_redirect" => CURRENT_USER->getBlockAutoRedirect(),
        ];

        return self::generateJWTAuthToken(["alg" => "HS256", "typ" => "JWT"], $tokenData);
    }

    /** Проверка валидности токена авторизации */
    public static function getAuthTokenPayload(): ?array
    {
        $headers = getallheaders();

        if ($headers['Authorization'] ?? false) {
            if (str_starts_with($headers['Authorization'], 'Bearer ')) {
                $authToken = trim(substr($headers['Authorization'], 7));
                $tokenParts = explode('.', $authToken);
                $headers = DataHelper::jsonFixedDecode(base64_decode($tokenParts[0]));
                $payload = DataHelper::jsonFixedDecode(base64_decode($tokenParts[1]));

                if ($authToken === self::generateJWTAuthToken($headers, $payload)) {
                    return $payload;
                }
            }
        }

        return null;
    }

    /** Генерация криптографичного уникального токена для обновления токена авторизации */
    public static function generateAndSaveRefreshToken(): void
    {
        $refreshToken = DataHelper::getRandomStringBin2hex();
        DB->update('user', ['refresh_token' => $refreshToken, 'refresh_token_exp' => new DateTime('+30 days')], ['id' => CURRENT_USER->id()]);
        CookieHelper::batchSetCookie(['refreshToken' => $refreshToken]);
    }

    /** Получение cookie refreshToken */
    public static function getRefreshTokenCookie(): ?string
    {
        return CookieHelper::getCookie('refreshToken');
    }

    /** Сброс cookie refreshToken */
    public static function removeRefreshTokenCookie(): void
    {
        CookieHelper::batchDeleteCookie(['refreshToken']);
    }

    /** Добавление проеектного хэша к строке */
    public static function addProjectHashWord(string $string): string
    {
        return $string . $_ENV['PROJECT_HASH_WORD'];
    }

    /** Хэширование паролей */
    public static function hashPassword(string $password, bool $usePepper = true): string
    {
        if ($usePepper) {
            $password = self::addProjectHashWord($password);
        }

        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 1 << 17,
            'time_cost'   => 3,
            'threads'     => 1,
        ]);
    }

    /** Создание токена авторизации */
    private static function generateJWTAuthToken(array $headers, array $payload): string
    {
        $headersEncoded = DataHelper::base64UrlEncode(DataHelper::jsonFixedEncode($headers));
        $payloadEncoded = DataHelper::base64UrlEncode(DataHelper::jsonFixedEncode($payload));
        $signature = hash_hmac('SHA256', $headersEncoded . $payloadEncoded, $_ENV['PROJECT_HASH_WORD'], true);
        $signatureEncoded = DataHelper::base64UrlEncode($signature);

        return $headersEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
}
