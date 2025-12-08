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

use Fraym\BaseObject\Trait\InitDependencyInjectionsTrait;
use Fraym\Entity\{BaseEntity, CatalogEntity, CatalogItemEntity, MultiObjectsEntity, Rights};
use Fraym\Helper\{LocaleHelper, TextHelper};
use Fraym\Interface\Response;
use Fraym\Response\{ArrayResponse, HtmlResponse};
use ReflectionAttribute;
use ReflectionObject;

/** @template T of BaseService */
abstract class BaseView
{
    use InitDependencyInjectionsTrait;

    public ?array $LOCALE = null {
        get => $this->LOCALE;
        set => $this->LOCALE = LocaleHelper::getLocale($value);
    }

    public ?BaseEntity $entity = null {
        get => $this->entity;
        set {
            $this->entity = $value;
            $value->view = $this;
        }
    }

    public ?Rights $viewRights = null;

    public array $propertiesWithListContext = [];

    public ?CMSVC $CMSVC = null;

    public ?BaseModel $model {
        get => $this->CMSVC?->model;
    }

    public ?BaseController $controller {
        get => $this->CMSVC?->controller;
    }

    /** @var T|null */
    public ?BaseService $service {
        get => $this->CMSVC?->service;
    }

    abstract public function Response(): ?Response;

    public function construct(?CMSVC $CMSVC = null): static
    {
        $reflection = new ReflectionObject($this);

        if (is_null($CMSVC)) {
            $controllerRef = $reflection->getAttributes(Controller::class);

            if ($controllerRef[0] ?? false) {
                /** @var Controller $controller */
                $controller = $controllerRef[0]->newInstance();
                $this->CMSVC = $controller->CMSVC;
            } else {
                $CMSVC = $reflection->getAttributes(CMSVC::class);

                if ($CMSVC[0] ?? false) {
                    $this->CMSVC = $CMSVC[0]->newInstance();
                    $this->CMSVC->view = $this;
                    $this->CMSVC->init();
                }
            }
        } else {
            $this->CMSVC = $CMSVC;
        }

        $this->CMSVC->view = $this;

        $entity = $reflection->getAttributes(BaseEntity::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($entity[0] ?? false) {
            $this->entity = $entity[0]->newInstance();
            $entity = $this->entity;

            $this->LOCALE = [$entity->name, 'global'];

            $entity->view = $this;
            $entity->LOCALE = [$entity->name, 'fraymModel'];

            $viewRights = $reflection->getAttributes(Rights::class);

            if ($viewRights[0] ?? false) {
                $this->viewRights = $viewRights[0]->newInstance();
                $this->viewRights->entity = $entity;
            }

            if ($entity instanceof CatalogEntity) {
                $catalogItemEntityRef = $reflection->getAttributes(CatalogItemEntity::class, ReflectionAttribute::IS_INSTANCEOF);

                if ($catalogItemEntityRef[0] ?? false) {
                    /** @var CatalogItemEntity $catalogItemEntity */
                    $catalogItemEntity = $catalogItemEntityRef[0]->newInstance();
                    $catalogItemEntity->view = $this;
                    $catalogItemEntity->catalogEntity = $entity;
                    $catalogItemEntity->LOCALE = [$entity->name . '/' . $catalogItemEntity->name, 'fraymModel'];

                    $entity->catalogItemEntity = $catalogItemEntity;
                }
            }

            $propertiesWithListContext = [];

            /** В мультиобъектных сущностях в контекст :list выводятся все поля, потому что кроме list у них и нет других определяющих контекстов */
            if (!($entity instanceof MultiObjectsEntity)) {
                $entitySortingItems = $entity->sortingData;

                foreach ($entitySortingItems as $entitySortingItem) {
                    $propertiesWithListContext[] = $entitySortingItem->tableFieldName;
                }

                /** В каталогах и наследующих объектах нам также нужны технические поля, определяющие родителя и является поле каталогом или наследником */
                if ($entity instanceof CatalogEntity) {
                    /** @var CatalogItemEntity $itemEntity */
                    $itemEntity = $entity->catalogItemEntity;
                    $propertiesWithListContext[] = $itemEntity->tableFieldWithParentId;
                    $propertiesWithListContext[] = $itemEntity->tableFieldToDetectType;

                    $itemEntitySortingItems = $itemEntity->sortingData;

                    foreach ($itemEntitySortingItems as $itemEntitySortingItem) {
                        $propertiesWithListContext[] = $itemEntitySortingItem->tableFieldName;
                    }
                }
            }
            $this->propertiesWithListContext = $propertiesWithListContext;
        } else {
            $this->LOCALE = [TextHelper::camelCaseToSnakeCase(KIND), 'global'];
        }

        $this->initDependencyInjections();

        return $this;
    }

    public function init(): static
    {
        return $this;
    }

    public function asHtml(?string $html, ?string $pagetitle): ?HtmlResponse
    {
        return !is_null($html) ? new HtmlResponse($html, $pagetitle) : null;
    }

    public function asArray(?array $data): ?ArrayResponse
    {
        return !is_null($data) ? new ArrayResponse($data) : null;
    }

    public function preViewHandler(): void
    {
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        return $response;
    }
}
