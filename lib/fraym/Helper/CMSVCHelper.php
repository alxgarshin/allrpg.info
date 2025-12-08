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

use Fraym\BaseObject\{BaseController, BaseModel, BaseService, BaseView, CMSVC};
use Fraym\Interface\Helper;
use ReflectionClass;
use RuntimeException;

abstract class CMSVCHelper implements Helper
{
    /** Подгрузка контроллера из кэша или создание */
    public static function getController(string $cmsvcName): ?BaseController
    {
        return self::get($cmsvcName, 'Controller');
    }

    /** Подгрузка модели из кэша или создание */
    public static function getModel(string $cmsvcName): ?BaseModel
    {
        return self::get($cmsvcName, 'Model');
    }

    /** Подгрузка сервиса из кэша или создание */
    public static function getService(string $cmsvcName): ?BaseService
    {
        return self::get($cmsvcName, 'Service');
    }

    /** Подгрузка вьюшки из кэша или создание */
    public static function getView(string $cmsvcName): ?BaseView
    {
        return self::get($cmsvcName, 'View');
    }

    /** Подгрузка любого из объектов CMSVC / класса, добавленного через атрибут DependencyInjection, из кэша или же попытка его инициализации */
    public static function get(string $cmsvcNameOrObjectClass, ?string $objectType = null): BaseController|BaseModel|BaseService|BaseView|null
    {
        if ($objectType === null) {
            $objectClass = $cmsvcNameOrObjectClass;
            $cmsvcName = null;

            if (class_exists($objectClass)) {
                $reflection = new ReflectionClass($objectClass);

                $objectType = match (true) {
                    $reflection->isSubclassOf(BaseController::class) => 'Controller',
                    $reflection->isSubclassOf(BaseModel::class) => 'Model',
                    $reflection->isSubclassOf(BaseService::class) => 'Service',
                    $reflection->isSubclassOf(BaseView::class) => 'View',
                    default => null,
                };

                preg_match('#\\\([^\\\]+)' . $objectType . '$#', $objectClass, $match);

                if ($match[1] ?? false) {
                    $cmsvcName = $match[1];
                } else {
                    preg_match('#\\\([^\\\]+)$#', $objectClass, $match);
                    $cmsvcName = $match[1];
                }
            }
        } else {
            $cmsvcName = TextHelper::mb_ucfirst(TextHelper::snakeCaseToCamelCase($cmsvcNameOrObjectClass));

            $allowedObjectTypes = [
                'Controller',
                'Model',
                'Service',
                'View',
            ];
            $objectType = TextHelper::mb_ucfirst($objectType);

            if (!in_array($objectType, $allowedObjectTypes)) {
                throw new RuntimeException("Object type " . $objectType . " is not allowed in CMVSCHelper::get function.");
            } else {
                $objectClass = 'App\CMSVC\\' . $cmsvcName . '\\' . $cmsvcName . $objectType;
            }
        }

        $object = null;
        $CMSVC = CACHE->getFromCache('_CMSVC', 0, $cmsvcName);

        if ($CMSVC !== null) {
            /** @var CMSVC $CMSVC */
            $object = match ($objectType) {
                'Controller' => $CMSVC->controller,
                'Model' => $CMSVC->model,
                'Service' => $CMSVC->service,
                'View' => $CMSVC->view,
                default => null,
            };
        }

        if ($object === null && class_exists($objectClass)) {
            $dependencyInjectedClass = CACHE->getFromCache('_DependencyInjectedClasses', 0, $cmsvcName);

            if ($dependencyInjectedClass !== null) {
                $object = $dependencyInjectedClass;
            } else {
                $object = new $objectClass();

                if (method_exists($object, 'construct')) {
                    $object->construct();
                }

                if (method_exists($object, 'getCMSVC') && method_exists($object->CMSVC, 'initDependencyInjections')) {
                    $object->CMSVC->initDependencyInjections($object);
                }

                if (method_exists($object, 'init')) {
                    $object->init();
                }

                if (method_exists($object, 'getCMSVC')) {
                    CACHE->setToCache('_CMSVC', 0, $object->CMSVC, $cmsvcName);
                } else {
                    CACHE->setToCache('_DependencyInjectedClasses', 0, $object, $cmsvcName);
                }
            }
        }

        return $object;
    }

    /** Подгрузка CMSVC-объекта из кэша */
    public static function getCMSVC(string $cmsvcName): ?CMSVC
    {
        return CACHE->getFromCache('_CMSVC', 0, TextHelper::mb_lcfirst($cmsvcName));
    }
}
