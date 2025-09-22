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

namespace Fraym\Enum;

use Fraym\Helper\{DataHelper, TextHelper};

enum ActionEnum: string
{
    case create = 'create';
    case change = 'change';
    case delete = 'delete';

    case setFilters = 'setFilters';
    case clearFilters = 'clearFilters';

    public static function getBaseValues(): array
    {
        return [
            self::create,
            self::change,
            self::delete,
        ];
    }

    public static function getFilterValues(): array
    {
        return [
            self::setFilters,
            self::clearFilters,
        ];
    }

    public static function init(): null|string|self
    {
        $requestAction = $_REQUEST['action'] ?? null;

        if (!is_null($requestAction)) {
            return self::tryFrom($requestAction) ?? TextHelper::snakeCaseToCamelCase($requestAction, true);
        } else {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $input = file_get_contents('php://input');
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if ($method === 'POST') {
                return self::create;
            } elseif ($method === 'GET') {
                return null;
            }

            $parsed = null;

            if (stripos($contentType, 'application/json') !== false) {
                $parsed = DataHelper::jsonFixedDecode($input, true);
            } else {
                parse_str($input, $parsed);
            }

            $GLOBALS['_JSON'] = (stripos($contentType, 'application/json') !== false)
                ? ($parsed ?? [])
                : [];

            if (in_array($method, ['PUT', 'DELETE'])) {
                $_REQUEST = array_merge($_REQUEST, $parsed ?: []);

                return match ($method) {
                    'DELETE' => self::delete,
                    default => self::change,
                };
            }
        }

        return null;
    }

    public static function getAsString(self|string|null $action): string
    {
        return $action instanceof ActionEnum
            ? $action->value
            : (string) $action;
    }
}
