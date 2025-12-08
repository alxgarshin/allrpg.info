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
use ReflectionClass;

/** Атрибут универсального хранения ссылки на controller */
#[Attribute(Attribute::TARGET_CLASS)]
final class Controller
{
    public CMSVC $CMSVC {
        get {
            $CMSVC = (new ReflectionClass($this->controllerClassName))->getAttributes(CMSVC::class)[0]->newInstance();
            $CMSVC->init();

            return $CMSVC;
        }
    }

    public function __construct(
        protected readonly string $controllerClassName,
    ) {
    }
}
