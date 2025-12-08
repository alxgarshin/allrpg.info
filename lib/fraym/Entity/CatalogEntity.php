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
    public CatalogItemEntity $catalogItemEntity;

    /** Массив оригинальных id, найденных при фильтрах и ограничениях видимости в запросе */
    public array $catalogEntityFoundIds = [];

    private array $ITEMS = [];

    public function viewActList(array $DATA_FILTERED_BY_CONTEXT): string
    {
        $GLOBAL_LOCALE = LocaleHelper::getLocale(['fraym', 'dynamiccreate']);

        $RESPONSE_DATA = '';

        if ($this->view->viewRights->addRight) {
            $RESPONSE_DATA .= '<a href="/' . KIND . '/' . TextHelper::camelCaseToSnakeCase($this->name) .
                '/act=add" class="ctrlink"><span class="sbi sbi-plus"></span>' .
                $GLOBAL_LOCALE['add'] . ' ' . $this->getObjectName() . '</a>' .
                '<a href="/' . KIND . '/' . TextHelper::camelCaseToSnakeCase($this->catalogItemEntity->name) .
                '/act=add" class="ctrlink"><span class="sbi sbi-plus"></span>' .
                $GLOBAL_LOCALE['add'] . ' ' . $this->catalogItemEntity->getObjectName() . '</a>';
        }
        $RESPONSE_DATA .= '<div class="clear"></div>';

        $catalogItemEntity = $this->catalogItemEntity;

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
        $catalogEntityFoundIds = $this->catalogEntityFoundIds;

        $RESPONSE_DATA = '<li><span class="sbi sbi-folder"></span>';

        if (in_array($DATA_ITEM['id'], $catalogEntityFoundIds)) {
            $RESPONSE_DATA .= '<a href="/' . KIND . '/' . TextHelper::camelCaseToSnakeCase($this->name) . '/' . $DATA_ITEM['id'] . '/act=' .
                $this->defaultItemActType->value . '">';
        }

        if ($DATA_ITEM['id'] === '0') {
            $RESPONSE_DATA .= '<b>' . mb_strtoupper($DATA_ITEM['name']) . '</b>';
        } else {
            foreach ($this->sortingData as $sortingItem) {
                if (!($this->ITEMS[$sortingItem->tableFieldName] ?? false)) {
                    $this->ITEMS[$sortingItem->tableFieldName] = $this->model->getElement($sortingItem->tableFieldName);
                }
                $ITEM = $this->ITEMS[$sortingItem->tableFieldName];

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
        $parentField = $this->catalogItemEntity->tableFieldWithParentId;

        $data = DB->select(
            tableName: $this->table,
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
        return $this->catalogItemEntity->detectEntityType($data);
    }
}
