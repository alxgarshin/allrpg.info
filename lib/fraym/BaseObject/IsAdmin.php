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

/** Атрибут для методов, указывающий недоступность не администраторам */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class IsAdmin
{
    public function __construct(
        protected readonly ?string $redirectPath = null,
        protected readonly ?array $redirectData = null,
    ) {
    }

    public function getRedirectPath(): ?string
    {
        return $this->redirectPath;
    }

    public function getRedirectData(): ?array
    {
        return $this->redirectData;
    }
}
