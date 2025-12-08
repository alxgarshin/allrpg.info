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

use AllowDynamicProperties;
use Fraym\BaseObject\Trait\InitDependencyInjectionsTrait;
use Fraym\Entity\{BaseEntity, CatalogEntity, CatalogItemEntity, PostChange, PostCreate, PostDelete, PreChange, PreCreate, PreDelete};
use Fraym\Enum\ActEnum;
use Fraym\Helper\{DataHelper, LocaleHelper, ObjectsHelper};
use Generator;
use ReflectionAttribute;
use ReflectionObject;
use RuntimeException;

/** @template T of BaseModel */
#[AllowDynamicProperties]
abstract class BaseService
{
    use InitDependencyInjectionsTrait;

    public ?array $LOCALE = null {
        get => $this->LOCALE;
        set => $this->LOCALE = LocaleHelper::getLocale($value);
    }

    public ?CMSVC $CMSVC = null;

    /** Callback-функция перед create */
    public ?string $preCreate = null;

    /** Callback-функция после create */
    public ?string $postCreate = null;

    /** Callback-функция перед change */
    public ?string $preChange = null;

    /** Callback-функция после change */
    public ?string $postChange = null;

    /** Callback-функция перед delete */
    public ?string $preDelete = null;

    /** Callback-функция после delete */
    public ?string $postDelete = null;

    /** Уточненный ACT (часто нужен в сервисах для проверки прав) */
    public ActEnum $act;

    /** Массив переменных, которые можно добавлять во время обработки сервиса. Помогает в postModelInit понять, нужно ли совершить какие-либо действия после того, как модель была собрана с участием сервиса (и избежать зацикливания таким образом). */
    public array $postModelInitVars = [];

    public ?BaseModel $model {
        get => $this->CMSVC?->model;
    }

    public ?BaseView $view {
        get => $this->CMSVC?->view;
    }

    public ?BaseEntity $entity {
        get => $this->CMSVC?->view?->entity;
    }

    public ?string $table {
        get => $this->entity?->table;
    }

    public function construct(?CMSVC $CMSVC = null): static
    {
        if (is_null($CMSVC)) {
            $reflection = new ReflectionObject($this);

            $controllerRef = $reflection->getAttributes(Controller::class);

            if ($controllerRef[0] ?? false) {
                /** @var Controller $controller */
                $controller = $controllerRef[0]->newInstance();
                $this->CMSVC = $controller->CMSVC;
            } else {
                $CMSVC = $reflection->getAttributes(CMSVC::class);

                if ($CMSVC[0] ?? false) {
                    $this->CMSVC = $CMSVC[0]->newInstance();
                    $this->CMSVC->service = $this;
                    $this->CMSVC->init();
                }
            }
        } else {
            $this->CMSVC = $CMSVC;
        }

        $this->CMSVC->service = $this;

        $this->LOCALE = $this->LOCALE = [ObjectsHelper::getClassShortNameFromCMSVCObject($this), 'global'];

        $entityService = new ReflectionObject($this);

        $preCreateRef = $entityService->getAttributes(PreCreate::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($preCreateRef[0] ?? false) {
            /** @var PreCreate $preCreate */
            $preCreate = $preCreateRef[0]->newInstance();
            $this->preCreate = $preCreate->callback;
        }

        $postCreateRef = $entityService->getAttributes(PostCreate::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($postCreateRef[0] ?? false) {
            /** @var PostCreate $postCreate */
            $postCreate = $postCreateRef[0]->newInstance();
            $this->postCreate = $postCreate->callback;
        }

        $preChangeRef = $entityService->getAttributes(PreChange::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($preChangeRef[0] ?? false) {
            /** @var PreChange $preChange */
            $preChange = $preChangeRef[0]->newInstance();
            $this->preChange = $preChange->callback;
        }

        $postChangeRef = $entityService->getAttributes(PostChange::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($postChangeRef[0] ?? false) {
            /** @var PostChange $postChange */
            $postChange = $postChangeRef[0]->newInstance();
            $this->postChange = $postChange->callback;
        }

        $preDeleteRef = $entityService->getAttributes(PreDelete::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($preDeleteRef[0] ?? false) {
            /** @var PreDelete $preDelete */
            $preDelete = $preDeleteRef[0]->newInstance();
            $this->preDelete = $preDelete->callback;
        }

        $postDeleteRef = $entityService->getAttributes(PostDelete::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($postDeleteRef[0] ?? false) {
            /** @var PostDelete $postDelete */
            $postDelete = $postDeleteRef[0]->newInstance();
            $this->postDelete = $postDelete->callback;
        }

        $this->act = DataHelper::getActDefault($this->entity);

        $this->initDependencyInjections();

        return $this;
    }

    public function init(): static
    {
        return $this;
    }

    /** Опциональная функция, позволяющая подменить модель в entity еще до исполнения действий в контроллере. */
    public function preLoadModel(): void
    {
    }

    public function preCreate(): void
    {
    }

    public function postCreate(array $successfulResultsIds): void
    {
    }

    public function preChange(): void
    {
    }

    public function postChange(array $successfulResultsIds): void
    {
    }

    public function preDelete(): void
    {
    }

    public function postDelete(array $successfulResultsIds): void
    {
    }

    /** @return T|null */
    public function get(
        int|string|null $id = null,
        ?array $criteria = null,
        ?array $order = null,
        bool $refresh = false,
        bool $strict = false,
    ): ?BaseModel {
        if ($id !== null || $criteria !== null) {
            if (!$refresh && $id !== null) {
                $checkData = $this->CMSVC->getModelInstance($this->CMSVC->model, $id, false);

                if ($checkData instanceof BaseModel) {
                    return $checkData;
                }
            }

            if ($id !== null) {
                $criteria['id'] = $id;
            }

            $result = iterator_to_array($this->getAll($criteria, $refresh, $order, 1));

            if (count($result) === 1) {
                return $result[key($result)];
            } elseif ($strict) {
                throw new RuntimeException(sprintf('BaseService get method failed with not one result with id = %s and criteria: %s', $id, print_r($criteria, true)));
            }
        }

        return null;
    }

    /** @return Generator<int|string, T> */
    public function getAll(
        ?array $criteria = null,
        bool $refresh = false,
        ?array $order = null,
        ?int $limit = null,
        ?int $offset = null,
    ): Generator {
        $table = $this->table;

        $objData = DB->select(
            $table,
            $criteria,
            false,
            $order,
            $limit,
            $offset,
        );

        return $this->arraysToModels($objData, $refresh);
    }

    public function detectModelTemplateBasedOnData(?array $data): BaseModel
    {
        $entity = $this->entity;

        if ($entity instanceof CatalogEntity || $entity instanceof CatalogItemEntity) {
            return $entity->detectEntityType($data)->model;
        } else {
            return $entity->model;
        }
    }

    /** @return T|null */
    public function arrayToModel(?array $data, bool $refresh = false): ?BaseModel
    {
        return $this->getModelInstance($this->detectModelTemplateBasedOnData($data), $data['id'] ?? null, true, $data, $refresh);
    }

    /** @return Generator<int|string, T> */
    public function arraysToModels(array $objData, bool $refresh = false): Generator
    {
        foreach ($objData as $objItem) {
            $checkData = null;

            if (!$refresh) {
                $checkData = $this->CMSVC->getModelInstance($this->detectModelTemplateBasedOnData($objItem), $objItem['id'], false);

                if ($checkData instanceof BaseModel) {
                    yield $objItem['id'] => $checkData;
                }
            }

            if (is_null($checkData)) {
                $objModel = $this->arrayToModel($objItem, $refresh);
                yield $objItem['id'] => $objModel;
            }
        }
    }

    /** @return T|null */
    public function getModelInstance(
        BaseModel $model,
        int|string|null $id = null,
        bool $createIfNotExists = true,
        ?array $data = null,
        bool $refresh = false,
    ): ?BaseModel {
        return $this->CMSVC?->getModelInstance($model, $id, $createIfNotExists, $data, $refresh);
    }

    /** Осуществление дополнительных операций с моделью после ее полной инициализации (позволяет избежать зацикливания) */
    public function postModelInit(BaseModel $model): BaseModel
    {
        return $model;
    }

    /** Дополнительная очистка параметров после сброса фильтров */
    public function postClearFilters(): void
    {
    }
}
