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

    protected ?array $LOCALE = null;

    protected ?BaseEntity $entity = null;

    protected ?Rights $viewRights = null;

    protected array $propertiesWithListContext = [];

    protected ?CMSVC $CMSVC = null;

    abstract public function Response(): ?Response;

    public function construct(?CMSVC $CMSVC = null): static
    {
        $reflection = new ReflectionObject($this);

        if (is_null($CMSVC)) {
            $controllerRef = $reflection->getAttributes(Controller::class);

            if ($controllerRef[0] ?? false) {
                /** @var Controller $controller */
                $controller = $controllerRef[0]->newInstance();
                $this->CMSVC = $controller->getCMSVC();
            } else {
                $CMSVC = $reflection->getAttributes(CMSVC::class);

                if ($CMSVC[0] ?? false) {
                    $this->CMSVC = $CMSVC[0]->newInstance();
                    $this->CMSVC->setView($this);
                    $this->CMSVC->init();
                }
            }
        } else {
            $this->CMSVC = $CMSVC;
        }

        $this->CMSVC->setView($this);

        $entity = $reflection->getAttributes(BaseEntity::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($entity[0] ?? false) {
            $this->entity = $entity[0]->newInstance();
            $entity = $this->entity;

            $this->LOCALE = $this->setLOCALE([$entity->getName(), 'global']);

            $entity->setView($this);
            $entity->setLOCALE([$entity->getName(), 'fraymModel']);

            $viewRights = $reflection->getAttributes(Rights::class);

            if ($viewRights[0] ?? false) {
                $this->viewRights = $viewRights[0]->newInstance();
                $this->viewRights->setEntity($entity);
            }

            if ($entity instanceof CatalogEntity) {
                $catalogItemEntityRef = $reflection->getAttributes(CatalogItemEntity::class, ReflectionAttribute::IS_INSTANCEOF);

                if ($catalogItemEntityRef[0] ?? false) {
                    /** @var CatalogItemEntity $catalogItemEntity */
                    $catalogItemEntity = $catalogItemEntityRef[0]->newInstance();
                    $catalogItemEntity->setView($this);
                    $catalogItemEntity->setCatalogEntity($entity);
                    $catalogItemEntity->setLOCALE([$entity->getName() . '/' . $catalogItemEntity->getName(), 'fraymModel']);

                    $entity->setCatalogItemEntity($catalogItemEntity);
                }
            }

            $propertiesWithListContext = [];

            /** В мультиобъектных сущностях в контекст :list выводятся все поля, потому что кроме list у них и нет других определяющих контекстов */
            if (!($entity instanceof MultiObjectsEntity)) {
                $entitySortingItems = $entity->getSortingData();

                foreach ($entitySortingItems as $entitySortingItem) {
                    $propertiesWithListContext[] = $entitySortingItem->getTableFieldName();
                }

                /** В каталогах и наследующих объектах нам также нужны технические поля, определяющие родителя и является поле каталогом или наследником */
                if ($entity instanceof CatalogEntity) {
                    /** @var CatalogItemEntity $itemEntity */
                    $itemEntity = $entity->getCatalogItemEntity();
                    $propertiesWithListContext[] = $itemEntity->getTableFieldWithParentId();
                    $propertiesWithListContext[] = $itemEntity->getTableFieldToDetectType();

                    $itemEntitySortingItems = $itemEntity->getSortingData();

                    foreach ($itemEntitySortingItems as $itemEntitySortingItem) {
                        $propertiesWithListContext[] = $itemEntitySortingItem->getTableFieldName();
                    }
                }
            }
            $this->propertiesWithListContext = $propertiesWithListContext;
        } else {
            $this->LOCALE = $this->setLOCALE([TextHelper::camelCaseToSnakeCase(KIND), 'global']);
        }

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

    public function getCMSVC(): ?CMSVC
    {
        return $this->CMSVC;
    }

    public function getEntity(): ?BaseEntity
    {
        return $this->entity;
    }

    public function setEntity(?BaseEntity $entity): static
    {
        $this->entity = $entity;
        $entity->setView($this);

        return $this;
    }

    public function getModel(): ?BaseModel
    {
        return $this->getCMSVC()?->getModel();
    }

    public function getController(): ?BaseController
    {
        return $this->getCMSVC()?->getController();
    }

    /** @return T|null */
    public function getService(): ?BaseService
    {
        return $this->getCMSVC()?->getService();
    }

    public function getMessages(): array
    {
        return $this->getController()->getMessages();
    }

    public function getViewRights(): ?Rights
    {
        return $this->viewRights;
    }

    public function getPropertiesWithListContext(): array
    {
        return $this->propertiesWithListContext;
    }

    public function setPropertiesWithListContext(array $propertiesWithListContext): static
    {
        $this->propertiesWithListContext = $propertiesWithListContext;

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
