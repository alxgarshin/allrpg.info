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

use Fraym\BaseObject\{BaseController, BaseHelper, BaseModel, BaseService, BaseView};
use Fraym\Interface\Helper;
use ReflectionClass;

abstract class ObjectsHelper implements Helper
{
    /** Получение короткого имени класса из одного из объектов CMSVC */
    public static function getClassShortNameFromCMSVCObject(BaseHelper|BaseController|BaseModel|BaseService|BaseView $object): string
    {
        $cachedName = CACHE->getFromCache('_CLASSNAMES', 0, $object::class);

        if (!is_null($cachedName)) {
            return $cachedName;
        }

        $removeText = '';

        if ($object instanceof BaseHelper || $object instanceof BaseController) {
            $removeText = 'Controller';
        } elseif ($object instanceof BaseModel) {
            $removeText = 'Model';
        } elseif ($object instanceof BaseService) {
            $removeText = 'Service';
        } elseif ($object instanceof BaseView) {
            $removeText = 'View';
        }

        return self::getClassShortName($object::class, $removeText);
    }

    /** Получение короткого имени класса */
    public static function getClassShortName(string $className, string $removeTextFromClassName = ''): string
    {
        $reflection = self::getReflection($className);
        $name = lcfirst($reflection->getShortName());

        if ($removeTextFromClassName !== '') {
            $name = str_ireplace($removeTextFromClassName, '', $name);
        }
        CACHE->setToCache('_CLASSNAMES', 0, $name, $className);

        return $name;
    }

    /** Получение Reflection класса */
    public static function getReflection(string|object $class): ReflectionClass
    {
        return new ReflectionClass($class);
    }
}
