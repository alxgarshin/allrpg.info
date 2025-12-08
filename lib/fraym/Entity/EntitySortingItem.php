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
    public ?BaseEntity $entity = null;

    public ?BaseService $service {
        get => $this->entity->view->CMSVC->service;
    }

    public function __construct(
        /** Название колонки в таблице в БД (id автоматически скрывается, если сортировать по нему)  */
        public string $tableFieldName,

        /** Порядок сортировки */
        public TableFieldOrderEnum $tableFieldOrder = TableFieldOrderEnum::ASC,

        /** Показывать ли в сводной табличке / каталоге сущности данную переменную? */
        public bool $showFieldDataInEntityTable = true,

        /** Показывать ли в строке каталога (или наследующей сущности) видимое название данной переменной? */
        public bool $showFieldShownNameInCatalogItemString = true,

        /** По умолчанию не сортировать по этому полю, если только пользователь не выбрал прицельно именно этот тип сортировки */
        public bool $doNotUseIfNotSortedByThisField = false,

        /** Вообще никогда не сортировать по этому полю: только выводить из него данные в списках элементов сущности, если нужно */
        public bool $doNotUseInSorting = false,

        /** Убрать точку, которая автоматически ставится после текста в сводной табличке в типе CatalogAndItemsEntity */
        public bool $removeDotAfterText = false,

        /** Подмена получаемого из таблицы значения на значение из другой таблицы, например: вместо id из колонки article_id выдать name из колонки article */
        public ?SubstituteDataTypeEnum $substituteDataType = null,

        /** Забитый массив данных или название функции объекта для получения массива в варианте SubstituteDataTypeEnum::ARRAY */
        public null|array|string $substituteDataArray = null {
            get {
                $defaultValue = $this->substituteDataArray;
                $service = $this->service;

                if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
                    $defaultValue = $service->{$defaultValue}();
                }

                return $defaultValue;
            }
            set(null|array|string $value) {
                if ($this->substituteDataType === SubstituteDataTypeEnum::ARRAY || is_null($value)) {
                    $this->substituteDataArray = $value;
                } else {
                    $this->substituteDataArray = null;
                }
            }
        },

        /** Имя таблицы для поиска для варианта SubstituteDataTypeEnum::TABLE или SubstituteDataTypeEnum::TABLEANDSORT */
        public ?string $substituteDataTableName = null {
            get => $this->substituteDataTableName;
            set(?string $value) {
                if ($this->substituteDataType === SubstituteDataTypeEnum::TABLE || is_null($value)) {
                    $this->substituteDataTableName = $value;
                } else {
                    $this->substituteDataTableName = null;
                }
            }
        },

        /** Идентификатор таблицы, с которым будет производиться сравнение значения, в вариантах SubstituteDataTypeEnum::TABLE или
         * SubstituteDataTypeEnum::TABLEANDSORT
         */
        public ?string $substituteDataTableId = null {
            get => $this->substituteDataTableId;
            set(?string $value) {
                if (!is_null($this->substituteDataTableName) || is_null($value)) {
                    $this->substituteDataTableId = $value;
                } else {
                    $this->substituteDataTableId = null;
                }
            }
        },

        /** Название ячейки таблицы, из которой будет взято значение для показа и сортировки, в вариантах SubstituteDataTypeEnum::TABLE или
         * SubstituteDataTypeEnum::TABLEANDSORT
         */
        public ?string $substituteDataTableField = null {
            get => $this->substituteDataTableField;
            set(?string $value) {
                if (!is_null($this->substituteDataTableName) || is_null($value)) {
                    $this->substituteDataTableField = $value;
                } else {
                    $this->substituteDataTableField = null;
                }
            }
        },
    ) {}
}
