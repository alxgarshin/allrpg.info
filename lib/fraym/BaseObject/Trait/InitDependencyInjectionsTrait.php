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

namespace Fraym\BaseObject\Trait;

use Fraym\BaseObject\DependencyInjection;
use Fraym\Helper\CMSVCHelper;
use ReflectionAttribute;
use ReflectionNamedType;
use ReflectionObject;

/** Функция инициализации зависимостей */
trait InitDependencyInjectionsTrait
{
    public function initDependencyInjections(): static
    {
        $reflection = new ReflectionObject($this);

        $properties = $reflection->getProperties();

        foreach ($properties as $propertyData) {
            $item = $propertyData->getAttributes(DependencyInjection::class, ReflectionAttribute::IS_INSTANCEOF);

            if (($item[0] ?? false) && $propertyData->getType() instanceof ReflectionNamedType) {
                $this->{$propertyData->name} = CMSVCHelper::get($propertyData->getType()->getName());
            }
        }

        return $this;
    }
}
