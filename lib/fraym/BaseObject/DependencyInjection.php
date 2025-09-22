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

/** Атрибут для Dependency Injection любого класса (рекомендуются только наследники BaseService) к ПУБЛИЧНОМУ свойству (с ReflectionNamedType) любого из объектов CMSVC */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class DependencyInjection
{
}
