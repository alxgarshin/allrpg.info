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
use Fraym\Enum\{ActEnum, MultiObjectsEntitySubTypeEnum};
use Fraym\Helper\{LocaleHelper, TextHelper};

/** Сущность, в которой на одной странице выводится сразу много объектов: в еxcel-подобном или карточном формате */
#[Attribute(Attribute::TARGET_CLASS)]
class MultiObjectsEntity extends BaseEntity
{
    public function __construct(
        string $name,
        string $table,
        /** @var EntitySortingItem[] $sortingData */
        array $sortingData,
        ?string $virtualField = null,
        ?int $elementsPerPage = 50,
        bool $useCustomView = false,
        bool $useCustomList = false,

        /** Формат вывода объектов: excel-подобный или карточный */
        private MultiObjectsEntitySubTypeEnum $subType = MultiObjectsEntitySubTypeEnum::Excel,
    ) {
        parent::__construct(
            name: $name,
            table: $table,
            sortingData: $sortingData,
            virtualField: $virtualField,
            elementsPerPage: $elementsPerPage,
            useCustomView: $useCustomView,
            useCustomList: $useCustomList,
        );
    }

    public function getSubType(): MultiObjectsEntitySubTypeEnum
    {
        return $this->subType;
    }

    public function setSubType(MultiObjectsEntitySubTypeEnum $subType): static
    {
        $this->subType = $subType;

        return $this;
    }

    public function viewActList(array $DATA_FILTERED_BY_CONTEXT): string
    {
        $GLOBAL_LOCALE = LocaleHelper::getLocale(['fraym', 'dynamiccreate']);

        $RESPONSE_DATA = '';

        $entityNameSnakeCase = TextHelper::camelCaseToSnakeCase($this->getName());
        $modelRights = $this->getView()->getViewRights();
        $modelElements = $this->getModel()->getElements();

        $visibleColumnsCount = 0;

        foreach ($modelElements as $modelElement) {
            $elementIsWritable = $modelElement->checkWritable();

            if (!is_null($elementIsWritable) && $modelElement->checkDOMVisibility()) {
                $visibleColumnsCount++;
            }
        }

        $isExcel = $this->getSubType() === MultiObjectsEntitySubTypeEnum::Excel;
        $isCards = $this->getSubType() === MultiObjectsEntitySubTypeEnum::Cards;

        $RESPONSE_DATA .= '<div class="multi_objects_table' . ($isExcel ? ' excel' : '') . ($isCards ? ' cards' : '') .
            ($isExcel ? (!$modelRights->getDeleteRight() ? ' without_delete_column' : '') . '" style="--mot_columns_count: ' . $visibleColumnsCount : '') . '">';

        if ($isExcel) {
            $RESPONSE_DATA .= '<div class="tr menu">';

            if ($modelRights->getDeleteRight()) {
                $RESPONSE_DATA .= '<div class="th"></div>';
            }
            $RESPONSE_DATA .= '<div class="th">№</div>';

            $lastVisibleElement =  null;

            foreach ($modelElements as $modelElement) {
                $elementIsWritable = $modelElement->checkWritable();

                if (!is_null($elementIsWritable) && $modelElement->checkDOMVisibility()) {
                    $lastVisibleElement = $modelElement;
                }
            }

            foreach ($modelElements as $modelElement) {
                $elementIsWritable = $modelElement->checkWritable();

                if (!is_null($elementIsWritable) && $modelElement->checkDOMVisibility()) {
                    $isSortable = false;
                    $RESPONSE_DATA .= '<div class="th' . ($lastVisibleElement === $modelElement ? ' tooltipBottomRight' : '') . '" id="th_' . $modelElement->getName() . '" ' .
                        (!is_null($modelElement->getHelpText()) ? ' title="' . $modelElement->getHelpText() . '"' : '') . '>';

                    foreach ($this->getSortingData() as $sortingItemNum => $sortingItem) {
                        if ($sortingItem->getTableFieldName() === $modelElement->getName()) {
                            $upSorting = ($sortingItemNum * 2 + 1);
                            $downSorting = ($sortingItemNum * 2 + 2);
                            $reversedSorting = $downSorting === SORTING;

                            $classes = [];

                            if ($upSorting === SORTING) {
                                $classes[] = 'arrow_up';
                            } elseif ($downSorting === SORTING) {
                                $classes[] = 'arrow_down';
                            }

                            if ($lastVisibleElement === $modelElement) {
                                $classes[] = 'tooltipBottomRight';
                            }

                            $classHtml = $classes ? ' class="' . implode(' ', $classes) . '"' : '';

                            $RESPONSE_DATA .= '<a href="/' . KIND . '/' . $entityNameSnakeCase . '/page=' . PAGE . '&sorting=' .
                                ($sortingItemNum * 2 + ($reversedSorting ? 1 : 2)) . '" title="[' .
                                $GLOBAL_LOCALE['sort'] . ' : ' . strip_tags(mb_strtolower($modelElement->getShownName())) . ' : ' .
                                $GLOBAL_LOCALE[($reversedSorting ? 'ascending' : 'descending')] . ']"' .
                                $classHtml .
                                '>' . $modelElement->getShownName() . '</a>';

                            $isSortable = true;
                            break;
                        }
                    }

                    if (!$isSortable) {
                        $RESPONSE_DATA .= $modelElement->getShownName();
                    }

                    if (!is_null($modelElement->getHelpText())) {
                        $RESPONSE_DATA .= '<span class="sup">?</span>';
                    }
                    $RESPONSE_DATA .= '</div>';
                }
            }

            $RESPONSE_DATA .= '</div>';
        }

        $lineNumber = 0;
        $elementTabindexNum = 1;

        if ($modelRights->getAddRight()) {
            $RESPONSE_DATA .= '<div class="tr" id="line' . $lineNumber . '">';

            if ($isExcel) {
                if ($modelRights->getDeleteRight()) {
                    $RESPONSE_DATA .= '<div class="td"></div>';
                }
                $RESPONSE_DATA .= '<div class="td"></div>';
            }

            foreach ($modelElements as $modelElement) {
                $elementIsWritable = $modelElement->checkWritable(ActEnum::add);

                if (!is_null($elementIsWritable)) {
                    if ($modelElement->checkDOMVisibility()) {
                        $RESPONSE_DATA .= '<div class="td"' . ($isExcel ? ' tabIndex="' . $elementTabindexNum . '"' : '') . ($isExcel ? '' : ' id="cardtable_card_' . $modelElement->getName() . '[' . $lineNumber . ']"') . '>';
                        ++$elementTabindexNum;
                    }

                    $modelElement->set(null);

                    if ($modelElement->checkVisibility()) {
                        $modelElement->getAttribute()->setLineNumber($lineNumber);
                        $RESPONSE_DATA .= $isExcel ?
                            $modelElement->asHTML($elementIsWritable) : $modelElement->asHTMLWrapped($lineNumber, $elementIsWritable, $elementTabindexNum);
                    }

                    if ($modelElement->checkDOMVisibility()) {
                        $RESPONSE_DATA .= '</div>';
                    }
                }
            }

            $RESPONSE_DATA .= '</div><div class="tr menu full_width"><div class="td"><button class="main" method="POST">' .
                $GLOBAL_LOCALE['addCapitalized'] . ' ' . $this->getObjectName() . '</button></div></div>';
        }

        $lineNumber = 1;

        $changeRestrictIds = [];

        if (!is_null($modelRights->getChangeRestrict())) {
            $changeRestrictIdsData = DB->query('SELECT id FROM ' . $this->getTable() . ' WHERE ' . $modelRights->getChangeRestrict(), []);

            foreach ($changeRestrictIdsData as $changeRestrictIdsItem) {
                $changeRestrictIds[] = $changeRestrictIdsItem['id'];
            }
        }

        $deleteRestrictIds = [];

        if (!is_null($modelRights->getDeleteRestrict())) {
            $deleteRestrictIdsData = DB->query('SELECT id FROM ' . $this->getTable() . ' WHERE ' . $modelRights->getDeleteRestrict(), []);

            foreach ($deleteRestrictIdsData as $deleteRestrictIdsItem) {
                $deleteRestrictIds[] = $deleteRestrictIdsItem['id'];
            }
        }

        foreach ($DATA_FILTERED_BY_CONTEXT as $DATA_ITEM) {
            $RESPONSE_DATA .= '<div class="tr string' . ($lineNumber % 2 === 0 ? 2 : 1) .
                ($modelRights->getChangeRight() && (is_null($modelRights->getChangeRestrict()) || in_array($DATA_ITEM['id'], $changeRestrictIds)) ? '' : ' readonly') .
                '" id="line' . $lineNumber . '" obj_id="' . $DATA_ITEM['id'] . '">';

            /** Права на изменения есть, но нет права на изменение данной конкретной строки */
            if ($modelRights->getChangeRight() && !(is_null($modelRights->getChangeRestrict()) || in_array($DATA_ITEM['id'], $changeRestrictIds))) {
                $RESPONSE_DATA .= '<input type="hidden" name="readonly[' . $lineNumber . ']" value="' . $DATA_ITEM['id'] . '" />';
            }

            if ($isCards) {
                $RESPONSE_DATA .= '<div class="cardtable_card_num">№' . $lineNumber . '<span class="cardtable_card_num_name">' . ($DATA_ITEM['name'] ?? '') . '</span></div>';
            }

            if ($modelRights->getDeleteRight() && (is_null($modelRights->getDeleteRestrict()) || in_array($DATA_ITEM['id'], $deleteRestrictIds))) {
                $RESPONSE_DATA .= '<div class="td multi_objects_delete"><a class="trash careful" title="' .
                    $GLOBAL_LOCALE['delete'] . ' ' . $this->getObjectName() . '" method="DELETE" href="/' . KIND . '/' . CMSVC . '/' . $DATA_ITEM['id'] .
                    '/page=' . PAGE . '&sorting=' . SORTING . '">' .
                    ($isExcel ? '' : '<span>' . $GLOBAL_LOCALE['delete'] . '</span>') . '</a></div>';
            } elseif ($isExcel && !is_null($modelRights->getDeleteRestrict()) && !in_array($DATA_ITEM['id'], $deleteRestrictIds)) {
                $RESPONSE_DATA .= '<div class="td multi_objects_delete"></div>';
            }

            if ($isExcel) {
                $RESPONSE_DATA .= '<div class="td multi_objects_num">' . $lineNumber . '</div>';
            }

            foreach ($modelElements as $modelElement) {
                $modelElement->set($DATA_ITEM[$modelElement->getName()] ?? null);
            }

            foreach ($modelElements as $modelElement) {
                $elementIsWritable = $modelElement->checkWritable(ActEnum::edit);

                if (!is_null($elementIsWritable)) {
                    $elementIsWritable = $elementIsWritable &&
                        $modelRights->getChangeRight() &&
                        (is_null($modelRights->getChangeRestrict()) || in_array($DATA_ITEM['id'], $changeRestrictIds));
                }

                if (!is_null($elementIsWritable)) {
                    if ($modelElement->checkDOMVisibility()) {
                        $RESPONSE_DATA .= '<div class="td"' . ($isExcel ? ' tabIndex="' . $elementTabindexNum . '"' : '') . ($isExcel ? '' : ' id="cardtable_card_' . $modelElement->getName() . '[' . $lineNumber . ']"') . '>';
                        ++$elementTabindexNum;
                    }

                    if ($modelElement->checkVisibility()) {
                        $modelElement->getAttribute()->setLineNumber($lineNumber);
                        $RESPONSE_DATA .= $isExcel ?
                            $modelElement->asHTML($elementIsWritable) : $modelElement->asHTMLWrapped($lineNumber, $elementIsWritable, $elementTabindexNum);
                    }

                    if ($modelElement->checkDOMVisibility()) {
                        $RESPONSE_DATA .= '</div>';
                    }
                }
            }

            $RESPONSE_DATA .= '</div>';
            ++$lineNumber;
        }

        if ($modelRights->getChangeRight() && $DATA_FILTERED_BY_CONTEXT) {
            $RESPONSE_DATA .= '<div class="tr menu full_width"><div class="td"><button class="main" method="PUT">' . $GLOBAL_LOCALE['save_changes'] . '</button></div></div>';
        }
        $RESPONSE_DATA .= '</div>';

        if ($RESPONSE_DATA !== '') {
            if ($modelRights->getAddRight() || ($modelRights->getChangeRight() && $DATA_FILTERED_BY_CONTEXT)) {
                $RESPONSE_DATA = '
<form action="/' . KIND . '/" enctype="multipart/form-data" id="form_' . $entityNameSnakeCase . '_add">
<input type="hidden" name="kind" value="' . KIND . '" />
<input type="hidden" name="cmsvc" value="' . $entityNameSnakeCase . '" />
<input type="hidden" name="page" value="' . PAGE . '" />
<input type="hidden" name="sorting" value="' . SORTING . '" />
' . $RESPONSE_DATA;
            } else {
                $RESPONSE_DATA = '
<form>' . $RESPONSE_DATA;
            }

            $RESPONSE_DATA .= '</form>';
        }

        return $RESPONSE_DATA;
    }

    /** У объектов типа MultiObjects обработка представления происходит только для всех объектов сразу: см. viewActList */
    public function viewActItem(array $DATA_ITEM, ?ActEnum $act = null, ?string $contextName = null): string
    {
        return '';
    }
}
