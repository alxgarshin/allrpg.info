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
use Fraym\Response\ArrayResponse;

abstract class ResponseHelper implements Helper
{
    /** Ответ 401: неавторизован */
    public static function response401(): void
    {
        header("HTTP/1.1 401 Unauthorized");
        exit;
    }

    /** Формирование ответа браузеру после динамического запроса */
    public static function response(
        array $messages,
        ?string $redirectPath = null,
        array $fields = [],
    ): ArrayResponse {
        $response = [];

        if (!is_null($redirectPath)) {
            foreach ($messages as $message) {
                if ($message[0] === 'success') {
                    self::success($message[1]);
                } elseif ($message[0] === 'error') {
                    self::error($message[1]);
                } elseif ($message[0] === 'information') {
                    self::info($message[1]);
                }
            }
            $response['redirect'] = $redirectPath;
            $response['executionTime'] = $GLOBALS['_GLOBALTIMER']->getTimerDiff();
            header('Access-Control-Allow-Origin: *');
            print DataHelper::jsonFixedEncode($response);
            exit;
        } else {
            foreach ($messages as $message) {
                $response['messages'][] = [$message[0], $message[1]];
            }

            foreach ($fields as $field) {
                $response['fields'][] = $field; //массив имен полей
            }
        }

        return new ArrayResponse($response);
    }

    /** Сокращенное формирование одного ответа браузеру после динамического запроса */
    public static function responseOneBlock(
        string $messageType,
        string $message,
        array $fields = [],
    ): void {
        $response = self::response([[$messageType, $message]], null, $fields);
        header('Access-Control-Allow-Origin: *');
        print DataHelper::jsonFixedEncode($response->getData());
        exit;
    }

    /** Создание пути для перенаправления браузера пользователя по результатам операции */
    public static function redirectConstruct(bool $checkOnlyReferer = false, bool $doNotIncludeId = false): ?string
    {
        $redirectPath = null;

        $refererPath = $_REQUEST['go_back_after_save_referer'][0] ?? null;
        $goBackAfterSave = ($_REQUEST['go_back_after_save'][0] ?? null) === 'on';

        if (!is_null($refererPath) && $refererPath !== '' && $goBackAfterSave) {
            /* если вдруг в $refererPath нет www, а в ABSOLUTE_PATH есть, делаем замену */
            if (preg_match('#www\.#', ABSOLUTE_PATH) && !preg_match('#www\.#', $refererPath)) {
                $refererPath = preg_replace(
                    '#' . preg_replace('#www\.#', '', ABSOLUTE_PATH) . '#',
                    ABSOLUTE_PATH,
                    $refererPath,
                );
            }

            /* если мы пришли сюда по прямой ссылке с внешнего сайта, то переходим просто в корневой раздел */
            if (!preg_match('#' . ABSOLUTE_PATH . '/#', $refererPath)) {
                $refererPath = ABSOLUTE_PATH . '/' . KIND . '/';
            }

            $redirectPath = $refererPath;
        } elseif (!$checkOnlyReferer) {
            $path = '/';
            $path .= KIND . '/';

            if (!$doNotIncludeId && DataHelper::getId() > 0) {
                $path .= DataHelper::getId() . '/';
            }

            $path2 = '';

            if (PAGE > 0) {
                $path2 .= 'page=' . PAGE;
            }

            if (SORTING > 0) {
                $path2 .= $path2 !== '' ? '&' : '';
                $path2 .= 'sorting=' . SORTING;
            }

            $redirectPath = $path . $path2;
        }

        return $redirectPath;
    }

    /** Перенаправление браузера пользователя */
    public static function redirect(string $link, ?array $cookieParams = null): void
    {
        if (!is_null($cookieParams)) {
            CookieHelper::batchSetCookie($cookieParams);
        }

        if (REQUEST_TYPE->isDynamicRequest() && (str_starts_with($link, '/') || preg_match('#^' . preg_quote(ABSOLUTE_PATH) . '#', $link))) {
            $response = self::response([], $link);
            print_r(DataHelper::jsonFixedEncode($response->getData()));
        } else {
            header('Location: ' . $link);
        }

        exit;
    }

    /** Создание пути для перенаправления из данных в cookie */
    public static function createRedirect(): ?string
    {
        $redirectPath = null;

        if (CookieHelper::getCookie('redirectToKind')) {
            $redirectPath = ABSOLUTE_PATH . '/';
            $redirectPath .= CookieHelper::getCookie('redirectToKind') . '/';

            if (CookieHelper::getCookie('redirectToObject')) {
                $redirectPath .= CookieHelper::getCookie('redirectToObject') . '/';
            }

            if (CookieHelper::getCookie('redirectToId')) {
                $redirectPath .= CookieHelper::getCookie('redirectToId') . '/';
            }

            if (CookieHelper::getCookie('redirectParams')) {
                $redirectPath .= CookieHelper::getCookie('redirectParams');
            }

            CookieHelper::batchDeleteCookie(['redirectToKind', 'redirectToId', 'redirectParams']);
        }

        return $redirectPath;
    }

    /** Добавление сообщения об успешном действии */
    public static function success(string $str): void
    {
        self::addMessage('success', $str);
    }

    /** Добавление сообщения о неуспешном действии / ошибке */
    public static function error(string $str): void
    {
        self::addMessage('error', $str);
    }

    /** Добавление информационного сообщения */
    public static function info(string $str): void
    {
        self::addMessage('information', $str);
    }

    /** Добавление сообщения в cookie-массив */
    private static function addMessage(string $type, string $str): void
    {
        $cookieMessages = CookieHelper::getCookie('messages', true);

        $cookieMessages[] = [$type, $str];

        CookieHelper::batchSetCookie(['messages' => $cookieMessages]);
    }
}
