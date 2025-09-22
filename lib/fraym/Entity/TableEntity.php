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
use Fraym\Service\GlobalTimerService;

/** Обычная таблица с переходом на отдельные странички сущности */
#[Attribute(Attribute::TARGET_CLASS)]
class TableEntity extends BaseEntity implements TabbedEntity
{
    use BaseEntityItem;
    use Tabs;

    private array $ITEMS = [];

    public function viewActList(array $DATA_FILTERED_BY_CONTEXT): string
    {
        if ($_ENV['GLOBALTIMERDRAWREPORT']) {
            $_GLOBALTIMERDRAWREPORT = new GlobalTimerService();
        }

        $GLOBAL_LOCALE = LocaleHelper::getLocale(['fraym', 'dynamiccreate']);

        $RESPONSE_DATA = '';

        $entityNameSnakeCase = TextHelper::camelCaseToSnakeCase($this->getName());

        if ($this->getView()->getViewRights()->getAddRight()) {
            $RESPONSE_DATA .= '<a href="/' . KIND . '/' . $entityNameSnakeCase . '/act=add" class="ctrlink"><span class="sbi sbi-plus"></span>' .
                $GLOBAL_LOCALE['add'] . ' ' . $this->getObjectName() . '</a>';
        }
        $RESPONSE_DATA .= '<div class="clear"></div><hr>';

        $RESPONSE_DATA .= '<table class="menutable ' . $entityNameSnakeCase . '"><thead><tr class="menu">';

        foreach ($this->getSortingData() as $sortingItemNum => $sortingItem) {
            if ($sortingItem->getShowFieldDataInEntityTable()) {
                $ITEM = $this->getModel()->getElement($sortingItem->getTableFieldName());

                if (!is_null($ITEM)) {
                    $RESPONSE_DATA .= '<th id="th_header_' . $ITEM->getName() . '">';

                    if (!$sortingItem->getDoNotUseInSorting()) {
                        $RESPONSE_DATA .= '<a href="/' . KIND . '/' . $entityNameSnakeCase . '/page=' . PAGE . '&sorting=';

                        $preparedItemShowName = $ITEM->getShownName();
                        $preparedItemShowName = strip_tags(mb_strtolower((!is_null($preparedItemShowName) ? $preparedItemShowName : '')));
                        $preparedItemShowName = ($preparedItemShowName !== '' ? ' : ' . $preparedItemShowName : '');

                        $upSorting = ($sortingItemNum * 2 + 1);
                        $downSorting = ($sortingItemNum * 2 + 2);

                        $classes = [];

                        if ($upSorting === SORTING) {
                            $classes[] = 'arrow_up';
                        } elseif ($downSorting === SORTING) {
                            $classes[] = 'arrow_down';
                        }

                        switch ($sortingItemNum) {
                            case 1:
                                $classes[] = 'tooltipBottomLeft';
                                break;
                            case count($this->getSortingData()) - 1:
                                $classes[] = 'tooltipBottomRight';
                                break;
                            default:
                                break;
                        }

                        $classHtml = $classes ? ' class="' . implode(' ', $classes) . '"' : '';

                        if ($downSorting === SORTING) {
                            $RESPONSE_DATA .= $upSorting . '" title="[' . $GLOBAL_LOCALE['sort'] . $preparedItemShowName . ' : ' . $GLOBAL_LOCALE['ascending'] . ']"' . $classHtml . '>';
                        } else {
                            $RESPONSE_DATA .= $downSorting . '" title="[' . $GLOBAL_LOCALE['sort'] . $preparedItemShowName . ' : ' . $GLOBAL_LOCALE['descending'] . ']"' . $classHtml . '>';
                        }
                    }
                    $RESPONSE_DATA .= $ITEM->getShownName();

                    if (!$sortingItem->getDoNotUseInSorting()) {
                        $RESPONSE_DATA .= '</a>';
                    }
                    $RESPONSE_DATA .= '</th>';
                }
            }
        }
        $RESPONSE_DATA .= '</tr></thead><tbody>';

        $stringNum = 1;

        foreach ($DATA_FILTERED_BY_CONTEXT as $DATA_ITEM) {
            $RESPONSE_DATA .= '<tr class="string' . ($stringNum % 2 === 1 ? '1' : '2') . '">';

            foreach ($this->getSortingData() as $sortingItem) {
                if ($sortingItem->getShowFieldDataInEntityTable()) {
                    $RESPONSE_DATA .= '<td><a href="/' . KIND . '/' . $entityNameSnakeCase . '/' . $DATA_ITEM['id'] . '/act=' . $this->getDefaultItemActType()->value . '">';

                    if (!($this->ITEMS[$sortingItem->getTableFieldName()] ?? false)) {
                        $this->ITEMS[$sortingItem->getTableFieldName()] = $this->getModel()->getElement($sortingItem->getTableFieldName());
                    }
                    $ITEM = $this->ITEMS[$sortingItem->getTableFieldName()];

                    if (!is_null($ITEM)) {
                        $RESPONSE_DATA .= $this->drawElementValue($ITEM, $DATA_ITEM, $sortingItem);
                    }
                    $RESPONSE_DATA .= '</a></td>';
                }
            }
            $RESPONSE_DATA .= '</tr>';
            ++$stringNum;
        }
        $RESPONSE_DATA .= '</tbody></table>';

        if ($_ENV['GLOBALTIMERDRAWREPORT']) {
            $RESPONSE_DATA .= $_GLOBALTIMERDRAWREPORT->getTimerDiffStr('<!-- tbody draw time: %ss-->');
        }

        return $RESPONSE_DATA;
    }
}
