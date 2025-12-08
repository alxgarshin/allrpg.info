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
use Fraym\Entity\CatalogEntity;
use Fraym\Helper\{ObjectsHelper, TextHelper};

/** Атрибут универсального хранения ссылок на: controller, model, service, view, context */
#[Attribute(Attribute::TARGET_CLASS)]
final class CMSVC
{
    /** Название объекта / сущности */
    public ?string $objectName = null {
        get => $this->objectName;
        set => $this->objectName = TextHelper::mb_lcfirst($value);
    }

    /** Контроллер объекта */
    public BaseController|string|null $controller = null {
        get {
            $controller = $this->controller;

            if (is_string($controller)) {
                $this->controller = new $controller();
                $this->controller
                    ->construct($this)
                    ->init();
            }

            return $this->controller;
        }
        set => $this->controller = $value;
    }

    /** Шаблон модели объекта */
    public BaseModel|string|null $model = null {
        get {
            $model = $this->model;

            if (!$model instanceof BaseModel && is_string($model)) {
                $this->model = new $model();
                $this->model
                    ->construct($this)
                    ->init();
                $this->service?->postModelInit($this->model);
            }

            return $this->model;
        }
        set {
            if (!isset($this->model) || !$this->model instanceof BaseModel) {
                $this->model = $value;
            } else {
                $this->model = $this->model;
            }
        }
    }

    /** Сервис объекта */
    public BaseService|string|null $service = null {
        get {
            $service = $this->service;

            if (is_string($service)) {
                $this->service = new $service();
                $this->service
                    ->construct($this)
                    ->init();
            }

            return $this->service;
        }
        set => $this->service = $value;
    }

    /** Вьюшка объекта */
    public BaseView|string|null $view = null {
        get {
            $view = $this->view;

            if (is_string($view)) {
                $this->view = new $view();
                $this->view
                    ->construct($this)
                    ->init();
            }

            return $this->view;
        }
        set => $this->view = $value;
    }

    public function __construct(
        BaseController|string|null $controller = null,
        BaseModel|string|null $model = null,
        BaseService|string|null $service = null,
        BaseView|string|null $view = null,

        /** Контекст объекта */
        public array $context = [],
    ) {
        $this->controller = $controller;
        $this->model = $model;
        $this->service = $service;
        $this->view = $view;

        $className = match (true) {
            is_string($controller) => $controller,
            is_string($service) => $service,
            is_string($view) => $view,
            is_string($model) => $model,
            default => null,
        };

        if ($className === null) {
            throw new Exception('No CMSVC classes found during CMSVC construct.');
        }

        $removeText = match (true) {
            is_string($controller) => 'Controller',
            is_string($service) => 'Service',
            is_string($view) => 'View',
            is_string($model) => 'Model',
            default => null,
        };

        $this->objectName = ObjectsHelper::getClassShortName($className, $removeText);

        CACHE->setToCache(
            '_CMSVC',
            0,
            $this,
            $this->objectName,
        );
    }

    public function init(): void
    {
        $t = $this->controller;
        $t = $this->service;
        $t = $this->view;
        $t = $this->model;

        if (!$this->context) {
            $objectName = $this->objectName;

            $this->context = [
                'LIST' => [
                    ':list',
                    $objectName . ':list',
                ],
                'VIEW' => [
                    ':view',
                    $objectName . ':view',
                ],
                'VIEWONACTADD' => [
                    ':viewOnActAdd',
                    $objectName . ':viewOnActAdd',
                ],
                'VIEWIFNOTNULL' => [
                    ':viewIfNotNull',
                    $objectName . ':viewIfNotNull',
                ],
                'CREATE' => [
                    ':create',
                    $objectName . ':create',
                ],
                'UPDATE' => [
                    ':update',
                    $objectName . ':update',
                ],
                'EMBEDDED' => [
                    ':embedded',
                    $objectName . ':embedded',
                ],
            ];

            $entity = $this->view?->entity;

            if ($entity && $entity instanceof CatalogEntity && TextHelper::camelCaseToSnakeCase($entity->catalogItemEntity->name) === CMSVC) {
                $objectName = $entity->catalogItemEntity->name;

                $this->context['LIST'][] = $objectName . ':list';
                $this->context['VIEW'][] = $objectName . ':view';
                $this->context['VIEWIFNOTNULL'][] = $objectName . ':viewIfNotNull';
                $this->context['CREATE'][] = $objectName . ':create';
                $this->context['UPDATE'][] = $objectName . ':update';
                $this->context['EMBEDDED'][] = $objectName . ':embedded';
            }
        }
    }

    public function getModelInstance(
        BaseModel $model,
        int|string|null $id = null,
        bool $createIfNotExists = true,
        ?array $data = null,
        bool $refresh = false,
    ): ?BaseModel {
        $modelInstance = null;

        $modelClass = ObjectsHelper::getClassShortNameFromCMSVCObject($model);

        if (!$refresh && !is_null($id)) {
            $modelInstance = CACHE->getFromCache('_MODELINSTANCES', $id, $modelClass);
        }

        if ($refresh || is_null($id) || (is_null($modelInstance) && $createIfNotExists)) {
            $modelInstance = clone $model;
            $modelInstance->modelData = $data;

            if (is_null($id)) {
                /** Находим самый большой ключ с частью "mockModel_" */
                $modelInstancesForModel = CACHE->getFromCache('_MODELINSTANCES', null, $modelClass);
                $modelInstancesKeys = array_keys($modelInstancesForModel ?? []);
                $highestKey = 0;

                foreach ($modelInstancesKeys as $modelInstancesKey) {
                    unset($match);

                    if (preg_match('#mockModel_(\d+)#', (string) $modelInstancesKey, $match)) {
                        if ((int) $match[1] > $highestKey) {
                            $highestKey = (int) $match[1];
                        }
                    }
                }
                $id = 'mockModel_' . $highestKey;
            }
            CACHE->setToCache('_MODELINSTANCES', $id, $modelInstance, $modelClass);
        }

        return $modelInstance;
    }
}
