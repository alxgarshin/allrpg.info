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

namespace Fraym\Entity;

use Attribute;
use Exception;
use Fraym\BaseObject\BaseModel;
use Fraym\Entity\Trait\{BaseEntityItem, Tabs};
use Fraym\Enum\ActEnum;
use Fraym\Helper\{TextHelper};
use Fraym\Interface\TabbedEntity;

/** Наследующая сущность каталога "родительская сущность + наследующая", например: разделы сайта и текстовые страницы */
#[Attribute(Attribute::TARGET_CLASS)]
final class CatalogItemEntity extends BaseEntity implements CatalogInterface, TabbedEntity
{
    use BaseEntityItem;
    use Tabs;

    /** Модель сущности */
    protected ?BaseModel $catalogItemModel = null;

    /** Родительская сущность */
    protected CatalogEntity $catalogEntity;

    private array $ITEMS = [];

    public function __construct(
        string $name,
        string $table,

        /** Класс модели сущности */
        protected string $catalogItemModelClass,

        /** В каком столбце хранится id родителя наследующего объекта? */
        protected string $tableFieldWithParentId,

        /** В каком столбце хранится содержимое объекта, позволяющее отличить родителя от наследника? Например: у страниц = текст, а у разделов = null. */
        protected string $tableFieldToDetectType,

        /** @var EntitySortingItem[] $entitySortingData */
        array $sortingData,
        ?string $virtualField = null,
        ?int $elementsPerPage = 50,
        bool $useCustomView = false,
        bool $useCustomList = false,
        ActEnum $defaultItemActType = ActEnum::edit,
        ActEnum $defaultListActType = ActEnum::list,
    ) {
        parent::__construct(
            name: $name,
            table: $table,
            sortingData: $sortingData,
            virtualField: $virtualField,
            elementsPerPage: $elementsPerPage,
            useCustomView: $useCustomView,
            useCustomList: $useCustomList,
            defaultItemActType: $defaultItemActType,
            defaultListActType: $defaultListActType,
        );
    }

    public function getCatalogItemModelClass(): string
    {
        return $this->catalogItemModelClass;
    }

    public function setCatalogItemModelClass(string $catalogItemModelClass): self
    {
        $this->catalogItemModelClass = $catalogItemModelClass;

        return $this;
    }

    public function getModel(): ?BaseModel
    {
        $model = $this->catalogItemModel;

        if (is_null($model)) {
            $modelClass = $this->getCatalogItemModelClass();
            $this->catalogItemModel = $model = new $modelClass();
            $this->catalogItemModel->construct($this->getView()->getCMSVC(), $this)->init();
            $this->getView()->getService()?->postModelInit($model);
        }

        return $model;
    }

    public function getCatalogEntity(): CatalogEntity
    {
        return $this->catalogEntity;
    }

    public function setCatalogEntity(CatalogEntity $catalogEntity): self
    {
        $this->catalogEntity = $catalogEntity;

        return $this;
    }

    public function getTableFieldWithParentId(): string
    {
        return $this->tableFieldWithParentId;
    }

    public function setTableFieldWithParentId(string $tableFieldWithParentId): self
    {
        $this->tableFieldWithParentId = $tableFieldWithParentId;

        return $this;
    }

    public function getTableFieldToDetectType(): string
    {
        return $this->tableFieldToDetectType;
    }

    public function setTableFieldToDetectType(string $tableFieldToDetectType): self
    {
        $this->tableFieldToDetectType = $tableFieldToDetectType;

        return $this;
    }

    public function viewActList(array $DATA_FILTERED_BY_CONTEXT): string
    {
        throw new Exception('Cannot create list of objects for dependent CatalogItemEntity. Please, use it\'s parent CatalogEntity instead.');
    }

    public function drawCatalogItemLine(array $DATA_ITEM): string
    {
        $catalogEntity = $this->getCatalogEntity();
        $catalogEntityFoundIds = $catalogEntity->getCatalogEntityFoundIds();

        $RESPONSE_DATA = '<li><span class="sbi sbi-file"></span>';

        if (in_array($DATA_ITEM['id'], $catalogEntityFoundIds)) {
            $RESPONSE_DATA .= '<a href="/' . KIND . '/' . TextHelper::camelCaseToSnakeCase($this->getName()) . '/' . $DATA_ITEM['id'] . '/act=' .
                $this->getDefaultItemActType()->value . '">';
        }

        foreach ($this->getSortingData() as $sortingItem) {
            if (!($this->ITEMS[$sortingItem->getTableFieldName()] ?? false)) {
                $this->ITEMS[$sortingItem->getTableFieldName()] = $this->getModel()->getElement($sortingItem->getTableFieldName());
            }
            $ITEM = $this->ITEMS[$sortingItem->getTableFieldName()];

            if (!is_null($ITEM)) {
                $RESPONSE_DATA .= $this->drawElementValue($ITEM, $DATA_ITEM, $sortingItem);
            }
        }

        if (str_ends_with($RESPONSE_DATA, '. ')) {
            $RESPONSE_DATA = mb_substr($RESPONSE_DATA, 0, mb_strlen($RESPONSE_DATA) - 1);
        }

        if (in_array($DATA_ITEM['id'], $catalogEntityFoundIds)) {
            $RESPONSE_DATA .= '</a>';
        }
        $RESPONSE_DATA .= '</li>';

        return $RESPONSE_DATA;
    }

    public function detectEntityType(array $data): CatalogEntity|CatalogItemEntity
    {
        if ('{menu}' === ($data[$this->getTableFieldToDetectType()] ?? false)) {
            return $this->getCatalogEntity();
        }

        return $this;
    }
}
