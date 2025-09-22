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

namespace Fraym\BaseObject;

use Attribute;
use Exception;
use Fraym\Interface\Helper;
use ReflectionClass;

/** Атрибут для методов, указывающий недоступность незалогиненному пользователю */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class IsAccessible
{
    private ?string $additionalCheckAccessHelper = null;
    private ?string $additionalCheckAccessMethod = null;

    public function __construct(
        private readonly string $redirectPath = '/login/',
        private readonly ?array $redirectData = null,
        ?string $additionalCheckAccessHelper = null,
        ?string $additionalCheckAccessMethod = null,
    ) {
        $helper = $additionalCheckAccessHelper;
        $method = $additionalCheckAccessMethod;

        if (!is_null($helper)) {
            $refClass = new ReflectionClass($helper);

            if (!($refClass->implementsInterface(Helper::class))) {
                throw new Exception('Class in IsAccessible attribute must implement ' . Helper::class);
            }

            if (!($refClass->hasMethod($method))) {
                throw new Exception('Class in IsAccessible attribute must have method ' . $method);
            }
            unset($refClass);
        }

        $this->additionalCheckAccessHelper = $helper;
        $this->additionalCheckAccessMethod = $method;
    }

    public function getRedirectPath(): ?string
    {
        return $this->redirectPath;
    }

    public function getRedirectData(): ?array
    {
        return $this->redirectData;
    }

    public function getAdditionalCheckAccessHelper(): ?string
    {
        return $this->additionalCheckAccessHelper;
    }

    public function getAdditionalCheckAccessMethod(): ?string
    {
        return $this->additionalCheckAccessMethod;
    }
}
