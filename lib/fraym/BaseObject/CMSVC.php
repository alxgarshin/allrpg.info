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
    private ?string $objectName = null;

    public function __construct(
        /** Контроллер объекта */
        private BaseController|string|null $controller = null,

        /** Шаблон модели объекта */
        private BaseModel|string|null $model = null,

        /** Сервис объекта */
        private BaseService|string|null $service = null,

        /** Вьюшка объекта */
        private BaseView|string|null $view = null,

        /** Контекст объекта */
        private array $context = [],
    ) {
        $className = match (true) {
            is_string($this->controller) => $this->controller,
            is_string($this->service) => $this->service,
            is_string($this->view) => $this->view,
            is_string($this->model) => $this->model,
            default => null,
        };

        if ($className === null) {
            throw new Exception('No CMSVC classes found during CMSVC construct.');
        }

        $removeText = match (true) {
            is_string($this->controller) => 'Controller',
            is_string($this->service) => 'Service',
            is_string($this->view) => 'View',
            is_string($this->model) => 'Model',
            default => null,
        };

        $this->setObjectName(ObjectsHelper::getClassShortName($className, $removeText));

        CACHE->setToCache(
            '_CMSVC',
            0,
            $this,
            $this->getObjectName(),
        );
    }

    public function init(): void
    {
        $this->getController();
        $this->getService();
        $this->getView();
        $this->getModel();

        if (!$this->context) {
            $objectName = $this->getObjectName();
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

            $entity = $this->getView()?->getEntity();

            if ($entity && $entity instanceof CatalogEntity && TextHelper::camelCaseToSnakeCase($entity->getCatalogItemEntity()->getName()) === CMSVC) {
                $objectName = $entity->getCatalogItemEntity()->getName();

                $this->context['LIST'][] = $objectName . ':list';
                $this->context['VIEW'][] = $objectName . ':view';
                $this->context['VIEWIFNOTNULL'][] = $objectName . ':viewIfNotNull';
                $this->context['CREATE'][] = $objectName . ':create';
                $this->context['UPDATE'][] = $objectName . ':update';
                $this->context['EMBEDDED'][] = $objectName . ':embedded';
            }
        }
    }

    public function getObjectName(): ?string
    {
        return $this->objectName;
    }

    public function setObjectName(string $objectName): self
    {
        $this->objectName = TextHelper::mb_lcfirst($objectName);

        return $this;
    }

    public function getController(): ?BaseController
    {
        $controller = $this->controller;

        if (is_string($controller)) {
            $this->controller = new $controller();
            $this->controller
                ->construct($this)
                ->init();
        }

        return $this->controller;
    }

    public function setController(BaseController|string|null $controller): self
    {
        $this->controller = $controller;

        return $this;
    }

    public function getModel(): ?BaseModel
    {
        $model = $this->model;

        if (!$model instanceof BaseModel && is_string($model)) {
            $this->model = new $model();
            $this->model
                ->construct($this)
                ->init();
            $this->getService()?->postModelInit($this->model);
        }

        return $this->model;
    }

    public function setModel(BaseModel|string|null $model): self
    {
        if (!$this->model instanceof BaseModel) {
            $this->model = $model;
        }

        return $this;
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
            $modelInstance = (clone $model)->setModelData($data);

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

    public function getService(): ?BaseService
    {
        $service = $this->service;

        if (is_string($service)) {
            $this->service = new $service();
            $this->service
                ->construct($this)
                ->init();
        }

        return $this->service;
    }

    public function setService(BaseService|string|null $service): self
    {
        $this->service = $service;

        return $this;
    }

    public function getView(): ?BaseView
    {
        $view = $this->view;

        if (is_string($view)) {
            $this->view = new $view();
            $this->view
                ->construct($this)
                ->init();
        }

        return $this->view;
    }

    public function setView(BaseView|string|null $view): self
    {
        $this->view = $view;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }
}
