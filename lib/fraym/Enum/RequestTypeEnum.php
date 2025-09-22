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

enum RequestTypeEnum: string
{
    case FRAYM_REQUEST = 'FraymRequest';
    case FRAYM_API_REQUEST = 'ApiRequest';
    case HTMX_REQUEST = 'HtmxRequest';

    case NOT_A_DYNAMIC_REQUEST = 'NotADynamicRequest';

    public function isDynamicRequest(): bool
    {
        return $this !== self::NOT_A_DYNAMIC_REQUEST;
    }

    public function isApiRequest(): bool
    {
        return $this === self::FRAYM_API_REQUEST;
    }

    public static function getRequestType(): static
    {
        return match (true) {
            ($_SERVER['HTTP_FRAYM_REQUEST'] ?? false) === 'true' => self::FRAYM_REQUEST,
            ($_SERVER['HTTP_FRAYM_API_REQUEST'] ?? false) === 'true' => self::FRAYM_API_REQUEST,
            ($_SERVER['HTTP_HX_REQUEST'] ?? false) === 'true' => self::HTMX_REQUEST,
            default => self::NOT_A_DYNAMIC_REQUEST,
        };
    }
}
