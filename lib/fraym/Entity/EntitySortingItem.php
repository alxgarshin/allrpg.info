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

use Fraym\BaseObject\BaseService;
use Fraym\Enum\{SubstituteDataTypeEnum, TableFieldOrderEnum};

/** Настройки сортировки сущности */
final class EntitySortingItem
{
    /** Родительская сущность */
    protected ?BaseEntity $entity = null;

    public function __construct(
        /** Название колонки в таблице в БД (id автоматически скрывается, если сортировать по нему)  */
        protected string $tableFieldName,

        /** Порядок сортировки */
        protected TableFieldOrderEnum $tableFieldOrder = TableFieldOrderEnum::ASC,

        /** Показывать ли в сводной табличке / каталоге сущности данную переменную? */
        protected bool $showFieldDataInEntityTable = true,

        /** Показывать ли в строке каталога (или наследующей сущности) видимое название данной переменной? */
        protected bool $showFieldShownNameInCatalogItemString = true,

        /** По умолчанию не сортировать по этому полю, если только пользователь не выбрал прицельно именно этот тип сортировки */
        protected bool $doNotUseIfNotSortedByThisField = false,

        /** Вообще никогда не сортировать по этому полю: только выводить из него данные в списках элементов сущности, если нужно */
        protected bool $doNotUseInSorting = false,

        /** Убрать точку, которая автоматически ставится после текста в сводной табличке в типе CatalogAndItemsEntity */
        protected bool $removeDotAfterText = false,

        /** Подмена получаемого из таблицы значения на значение из другой таблицы, например: вместо id из колонки article_id выдать name из колонки article */
        protected ?SubstituteDataTypeEnum $substituteDataType = null,

        /** Забитый массив данных или название функции объекта для получения массива в варианте SubstituteDataTypeEnum::ARRAY */
        protected null|array|string $substituteDataArray = null,

        /** Имя таблицы для поиска для варианта SubstituteDataTypeEnum::TABLE или SubstituteDataTypeEnum::TABLEANDSORT */
        protected ?string $substituteDataTableName = null,

        /** Идентификатор таблицы, с которым будет производиться сравнение значения, в вариантах SubstituteDataTypeEnum::TABLE или
         * SubstituteDataTypeEnum::TABLEANDSORT
         */
        protected ?string $substituteDataTableId = null,

        /** Название ячейки таблицы, из которой будет взято значение для показа и сортировки, в вариантах SubstituteDataTypeEnum::TABLE или
         * SubstituteDataTypeEnum::TABLEANDSORT
         */
        protected ?string $substituteDataTableField = null,
    ) {
    }

    public function getEntity(): ?BaseEntity
    {
        return $this->entity;
    }

    public function setEntity(?BaseEntity $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getService(): ?BaseService
    {
        return $this->entity->getView()->getCMSVC()->getService();
    }

    public function getTableFieldName(): string
    {
        return $this->tableFieldName;
    }

    public function setTableFieldName(string $tableFieldName): self
    {
        $this->tableFieldName = $tableFieldName;

        return $this;
    }

    public function getTableFieldOrder(): TableFieldOrderEnum
    {
        return $this->tableFieldOrder;
    }

    public function setTableFieldOrder(TableFieldOrderEnum $tableFieldOrder): self
    {
        $this->tableFieldOrder = $tableFieldOrder;

        return $this;
    }

    public function getShowFieldDataInEntityTable(): bool
    {
        return $this->showFieldDataInEntityTable;
    }

    public function setShowFieldDataInEntityTable(bool $showFieldDataInEntityTable): self
    {
        $this->showFieldDataInEntityTable = $showFieldDataInEntityTable;

        return $this;
    }

    public function getShowFieldShownNameInCatalogItemString(): bool
    {
        return $this->showFieldShownNameInCatalogItemString;
    }

    public function setShowFieldShownNameInCatalogItemString(bool $showFieldShownNameInCatalogItemString): self
    {
        $this->showFieldShownNameInCatalogItemString = $showFieldShownNameInCatalogItemString;

        return $this;
    }

    public function getDoNotUseIfNotSortedByThisField(): bool
    {
        return $this->doNotUseIfNotSortedByThisField;
    }

    public function setDoNotUseIfNotSortedByThisField(bool $doNotUseIfNotSortedByThisField): self
    {
        $this->doNotUseIfNotSortedByThisField = $doNotUseIfNotSortedByThisField;

        return $this;
    }

    public function getDoNotUseInSorting(): bool
    {
        return $this->doNotUseInSorting;
    }

    public function setDoNotUseInSorting(bool $doNotUseInSorting): self
    {
        $this->doNotUseInSorting = $doNotUseInSorting;

        return $this;
    }

    public function getRemoveDotAfterText(): bool
    {
        return $this->removeDotAfterText;
    }

    public function setRemoveDotAfterText(bool $removeDotAfterText): self
    {
        $this->removeDotAfterText = $removeDotAfterText;

        return $this;
    }

    public function getSubstituteDataType(): ?SubstituteDataTypeEnum
    {
        return $this->substituteDataType;
    }

    public function setSubstituteDataType(?SubstituteDataTypeEnum $substituteDataType): self
    {
        $this->substituteDataType = $substituteDataType;

        return $this;
    }

    public function getSubstituteDataArray(): null|array|string
    {
        $defaultValue = $this->substituteDataArray;
        $service = $this->getService();

        if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
            $defaultValue = $service->{$defaultValue}();
        }

        return $defaultValue;
    }

    public function setSubstituteDataArray(null|array|string $substituteDataArray): self
    {
        if ($this->substituteDataType === SubstituteDataTypeEnum::ARRAY || is_null($substituteDataArray)) {
            $this->substituteDataArray = $substituteDataArray;
        }

        return $this;
    }

    public function getSubstituteDataTableName(): ?string
    {
        return $this->substituteDataTableName;
    }

    public function setSubstituteDataTableName(?string $substituteDataTableName): self
    {
        if ($this->substituteDataType === SubstituteDataTypeEnum::TABLE || is_null($substituteDataTableName)) {
            $this->substituteDataTableName = $substituteDataTableName;
        }

        return $this;
    }

    public function getSubstituteDataTableId(): ?string
    {
        return $this->substituteDataTableId;
    }

    public function setSubstituteDataTableId(?string $substituteDataTableId): self
    {
        if (!is_null($this->substituteDataTableName) || is_null($substituteDataTableId)) {
            $this->substituteDataTableId = $substituteDataTableId;
        }

        return $this;
    }

    public function getSubstituteDataTableField(): ?string
    {
        return $this->substituteDataTableField;
    }

    public function setSubstituteDataTableField(?string $substituteDataTableField): self
    {
        if (!is_null($this->substituteDataTableName) || is_null($substituteDataTableField)) {
            $this->substituteDataTableField = $substituteDataTableField;
        }

        return $this;
    }
}
