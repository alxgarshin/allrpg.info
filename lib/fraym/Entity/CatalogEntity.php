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
use Fraym\Entity\Trait\{BaseEntityItem, Tabs};
use Fraym\Helper\{LocaleHelper, TextHelper};
use Fraym\Interface\TabbedEntity;

/** Родительская сущность каталога "родительская сущность + наследующая", например: разделы сайта и текстовые страницы */
#[Attribute(Attribute::TARGET_CLASS)]
final class CatalogEntity extends BaseEntity implements CatalogInterface, TabbedEntity
{
    use BaseEntityItem;
    use Tabs;

    /** Наследующая сущность */
    protected CatalogItemEntity $catalogItemEntity;

    /** Массив оригинальных id, найденных при фильтрах и ограничениях видимости в запросе */
    protected array $catalogEntityFoundIds = [];

    private array $ITEMS = [];

    public function getCatalogItemEntity(): CatalogItemEntity
    {
        return $this->catalogItemEntity;
    }

    public function setCatalogItemEntity(CatalogItemEntity $catalogItemEntity): self
    {
        $this->catalogItemEntity = $catalogItemEntity;

        return $this;
    }

    public function getCatalogEntityFoundIds(): array
    {
        return $this->catalogEntityFoundIds;
    }

    public function setCatalogEntityFoundIds(array $catalogEntityFoundIds): self
    {
        $this->catalogEntityFoundIds = $catalogEntityFoundIds;

        return $this;
    }

    public function viewActList(array $DATA_FILTERED_BY_CONTEXT): string
    {
        $GLOBAL_LOCALE = LocaleHelper::getLocale(['fraym', 'dynamiccreate']);

        $RESPONSE_DATA = '';

        if ($this->getView()->getViewRights()->getAddRight()) {
            $RESPONSE_DATA .= '<a href="/' . KIND . '/' . TextHelper::camelCaseToSnakeCase($this->getName()) .
                '/act=add" class="ctrlink"><span class="sbi sbi-plus"></span>' .
                $GLOBAL_LOCALE['add'] . ' ' . $this->getObjectName() . '</a>' .
                '<a href="/' . KIND . '/' . TextHelper::camelCaseToSnakeCase($this->getCatalogItemEntity()->getName()) .
                '/act=add" class="ctrlink"><span class="sbi sbi-plus"></span>' .
                $GLOBAL_LOCALE['add'] . ' ' . $this->getCatalogItemEntity()->getObjectName() . '</a>';
        }
        $RESPONSE_DATA .= '<div class="clear"></div>';

        $catalogItemEntity = $this->getCatalogItemEntity();

        if (count($DATA_FILTERED_BY_CONTEXT) > 0) {
            $previousCatalogLevel = 0;

            $RESPONSE_DATA .= '<ul class="mainCatalog">';

            foreach ($DATA_FILTERED_BY_CONTEXT as $DATA_ITEM_KEY => $DATA_ITEM) {
                if ($DATA_ITEM_KEY >= 1) {
                    $prevType = $this->detectEntityType($DATA_FILTERED_BY_CONTEXT[$DATA_ITEM_KEY - 1]) instanceof CatalogEntity ? 'catalog' : 'catalogItem';
                } else {
                    $prevType = 'catalog';
                }
                $type = $this->detectEntityType($DATA_ITEM) instanceof CatalogEntity ? 'catalog' : 'catalogItem';

                if ($DATA_ITEM['catalogLevel'] > $previousCatalogLevel && $type === 'catalogItem' && $prevType === 'catalog') {
                    $RESPONSE_DATA .= '<ul class="catalogItems">';
                    $previousCatalogLevel++;
                } elseif ($prevType === 'catalogItem' && $DATA_ITEM['catalogLevel'] < $previousCatalogLevel) {
                    $RESPONSE_DATA .= '</ul></li>';
                    $previousCatalogLevel--;
                }

                if ($type === 'catalog') {
                    if ($DATA_ITEM['catalogLevel'] > $previousCatalogLevel) {
                        $RESPONSE_DATA .= '<ul class="subCatalogs">';
                        $previousCatalogLevel = $DATA_ITEM['catalogLevel'];
                    } elseif ($DATA_ITEM['catalogLevel'] < $previousCatalogLevel) {
                        $close = $previousCatalogLevel - $DATA_ITEM['catalogLevel'];
                        $RESPONSE_DATA .= str_repeat('</ul></li>', $close);
                        $previousCatalogLevel = $DATA_ITEM['catalogLevel'];
                    }
                }

                $RESPONSE_DATA .= ($type === 'catalog' ? $this->drawCatalogLine($DATA_ITEM) : $catalogItemEntity->drawCatalogItemLine($DATA_ITEM));
            }

            $RESPONSE_DATA .= str_repeat('</ul></li>', $previousCatalogLevel);

            $RESPONSE_DATA .= '</ul>';
        }

        return $RESPONSE_DATA;
    }

    public function drawCatalogLine(array $DATA_ITEM): string
    {
        $catalogEntityFoundIds = $this->getCatalogEntityFoundIds();

        $RESPONSE_DATA = '<li><span class="sbi sbi-folder"></span>';

        if (in_array($DATA_ITEM['id'], $catalogEntityFoundIds)) {
            $RESPONSE_DATA .= '<a href="/' . KIND . '/' . TextHelper::camelCaseToSnakeCase($this->getName()) . '/' . $DATA_ITEM['id'] . '/act=' .
                $this->getDefaultItemActType()->value . '">';
        }

        if ($DATA_ITEM['id'] === '0') {
            $RESPONSE_DATA .= '<b>' . mb_strtoupper($DATA_ITEM['name']) . '</b>';
        } else {
            foreach ($this->getSortingData() as $sortingItem) {
                if (!($this->ITEMS[$sortingItem->getTableFieldName()] ?? false)) {
                    $this->ITEMS[$sortingItem->getTableFieldName()] = $this->getModel()->getElement($sortingItem->getTableFieldName());
                }
                $ITEM = $this->ITEMS[$sortingItem->getTableFieldName()];

                if (!is_null($ITEM)) {
                    $RESPONSE_DATA .= $this->drawElementValue($ITEM, $DATA_ITEM, $sortingItem);
                }
            }
        }

        if (str_ends_with($RESPONSE_DATA, '. ')) {
            $RESPONSE_DATA = mb_substr($RESPONSE_DATA, 0, mb_strlen($RESPONSE_DATA) - 1);
        }

        if (in_array($DATA_ITEM['id'], $catalogEntityFoundIds)) {
            $RESPONSE_DATA .= '</a>';
        }

        return $RESPONSE_DATA;
    }

    /** Поиск и удаление объектов-детей и приложенных к ним файлов */
    public function clearDataByParent(string|int $id): void
    {
        $parentField = $this->getCatalogItemEntity()->getTableFieldWithParentId();

        $data = DB->select(
            tableName: $this->getTable(),
            criteria: [
                $parentField => $id,
            ],
        );

        foreach ($data as $item) {
            $this->clearDataByParent($item['id']);
        }

        $this->deleteItem($id);
    }

    public function detectEntityType(array $data): CatalogEntity|CatalogItemEntity
    {
        return $this->getCatalogItemEntity()->detectEntityType($data);
    }
}
