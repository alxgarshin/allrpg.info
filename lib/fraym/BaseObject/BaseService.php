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

    protected ?array $LOCALE = null;

    protected ?CMSVC $CMSVC = null;

    /** Callback-функция перед create */
    protected ?string $preCreate = null;

    /** Callback-функция после create */
    protected ?string $postCreate = null;

    /** Callback-функция перед change */
    protected ?string $preChange = null;

    /** Callback-функция после change */
    protected ?string $postChange = null;

    /** Callback-функция перед delete */
    protected ?string $preDelete = null;

    /** Callback-функция после delete */
    protected ?string $postDelete = null;

    /** Уточненный ACT (часто нужен в сервисах для проверки прав) */
    protected ActEnum $act;

    /** Массив переменных, которые можно добавлять во время обработки сервиса. Помогает в postModelInit понять, нужно ли совершить какие-либо действия после того, как модель была собрана с участием сервиса (и избежать зацикливания таким образом). */
    protected array $postModelInitVars = [];

    public function construct(?CMSVC $CMSVC = null): static
    {
        if (is_null($CMSVC)) {
            $reflection = new ReflectionObject($this);

            $controllerRef = $reflection->getAttributes(Controller::class);

            if ($controllerRef[0] ?? false) {
                /** @var Controller $controller */
                $controller = $controllerRef[0]->newInstance();
                $this->CMSVC = $controller->getCMSVC();
            } else {
                $CMSVC = $reflection->getAttributes(CMSVC::class);

                if ($CMSVC[0] ?? false) {
                    $this->CMSVC = $CMSVC[0]->newInstance();
                    $this->CMSVC->setService($this);
                    $this->CMSVC->init();
                }
            }
        } else {
            $this->CMSVC = $CMSVC;
        }

        $this->CMSVC->setService($this);

        $this->LOCALE = $this->setLOCALE([ObjectsHelper::getClassShortNameFromCMSVCObject($this), 'global']);

        $entityService = new ReflectionObject($this);

        $preCreateRef = $entityService->getAttributes(PreCreate::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($preCreateRef[0] ?? false) {
            /** @var PreCreate $preCreate */
            $preCreate = $preCreateRef[0]->newInstance();
            $this->preCreate = $preCreate->getCallback();
        }

        $postCreateRef = $entityService->getAttributes(PostCreate::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($postCreateRef[0] ?? false) {
            /** @var PostCreate $postCreate */
            $postCreate = $postCreateRef[0]->newInstance();
            $this->postCreate = $postCreate->getCallback();
        }

        $preChangeRef = $entityService->getAttributes(PreChange::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($preChangeRef[0] ?? false) {
            /** @var PreChange $preChange */
            $preChange = $preChangeRef[0]->newInstance();
            $this->preChange = $preChange->getCallback();
        }

        $postChangeRef = $entityService->getAttributes(PostChange::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($postChangeRef[0] ?? false) {
            /** @var PostChange $postChange */
            $postChange = $postChangeRef[0]->newInstance();
            $this->postChange = $postChange->getCallback();
        }

        $preDeleteRef = $entityService->getAttributes(PreDelete::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($preDeleteRef[0] ?? false) {
            /** @var PreDelete $preDelete */
            $preDelete = $preDeleteRef[0]->newInstance();
            $this->preDelete = $preDelete->getCallback();
        }

        $postDeleteRef = $entityService->getAttributes(PostDelete::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($postDeleteRef[0] ?? false) {
            /** @var PostDelete $postDelete */
            $postDelete = $postDeleteRef[0]->newInstance();
            $this->postDelete = $postDelete->getCallback();
        }

        $this->act = DataHelper::getActDefault($this->getEntity());

        $this->initDependencyInjections();

        return $this;
    }

    public function init(): static
    {
        return $this;
    }

    public function getLOCALE(): ?array
    {
        return $this->LOCALE;
    }

    public function setLOCALE(array $entityPathInLocale): ?array
    {
        $this->LOCALE = LocaleHelper::getLocale($entityPathInLocale);

        return $this->LOCALE;
    }

    public function getMessages(): array
    {
        return $this->LOCALE['messages'];
    }

    public function getCMSVC(): ?CMSVC
    {
        return $this->CMSVC;
    }

    /** Опциональная функция, позволяющая подменить модель в entity еще до исполнения действий в контроллере. */
    public function preLoadModel(): void
    {
    }

    public function getModel(): ?BaseModel
    {
        return $this->getCMSVC()?->getModel();
    }

    public function getView(): ?BaseView
    {
        return $this->getCMSVC()->getView();
    }

    public function getEntity(): ?BaseEntity
    {
        return $this->getView()?->getEntity();
    }

    public function getTable(): ?string
    {
        return $this->getEntity()?->getTable();
    }

    public function getPreCreate(): ?string
    {
        return $this->preCreate;
    }

    public function setPreCreate(?string $preCreate): static
    {
        $this->preCreate = $preCreate;

        return $this;
    }

    public function getPostCreate(): ?string
    {
        return $this->postCreate;
    }

    public function setPostCreate(?string $postCreate): static
    {
        $this->postCreate = $postCreate;

        return $this;
    }

    public function getPreChange(): ?string
    {
        return $this->preChange;
    }

    public function setPreChange(?string $preChange): static
    {
        $this->preChange = $preChange;

        return $this;
    }

    public function getPostChange(): ?string
    {
        return $this->postChange;
    }

    public function setPostChange(?string $postChange): static
    {
        $this->postChange = $postChange;

        return $this;
    }

    public function getPreDelete(): ?string
    {
        return $this->preDelete;
    }

    public function setPreDelete(?string $preDelete): static
    {
        $this->preDelete = $preDelete;

        return $this;
    }

    public function getPostDelete(): ?string
    {
        return $this->postDelete;
    }

    public function setPostDelete(?string $postDelete): static
    {
        $this->postDelete = $postDelete;

        return $this;
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

    public function getAct(): ActEnum
    {
        return $this->act;
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
                $checkData = $this->CMSVC->getModelInstance($this->CMSVC->getModel(), $id, false);

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
        $table = $this->getTable();

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
        $entity = $this->getEntity();

        if ($entity instanceof CatalogEntity || $entity instanceof CatalogItemEntity) {
            return $entity->detectEntityType($data)->getModel();
        } else {
            return $entity->getModel();
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
        return $this->getCMSVC()?->getModelInstance($model, $id, $createIfNotExists, $data, $refresh);
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
