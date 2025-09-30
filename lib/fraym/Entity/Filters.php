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

use Fraym\Element\{Attribute as Attribute, Item as Item};
use Fraym\Enum\ActionEnum;
use Fraym\Helper\{CookieHelper, DataHelper, LocaleHelper, TextHelper};
use Fraym\Interface\ElementItem;

final class Filters
{
    /** Сущность */
    private BaseEntity $entity;

    /** Строка, добавляемая в sql-запросы для фильтрации */
    private ?string $searchQuerySql = null;

    /** Параметры для строки sql-запроса для фильтрации */
    private ?array $searchQueryParams = null;

    /** Прегенерированная ссылка на данный набор фильтров */
    private ?string $currentFiltersLink = null;

    /** Массив преподготовленных значений для cookie */
    private array $cookieValues = [];

    /** Массив "блоков" фильтров: каждый блок состоит из фильтруемого элемента и вспомогательных элементов, определяющих конкретику фильтрации
     * @var array<int, FiltersBlock> $filtersBlocks
     */
    private array $filtersBlocks = [];

    public function __construct(
        BaseEntity $entity,
    ) {
        $this->entity = $entity;
    }

    /** Вывод HTML-кода панели фильтров */
    public function getFiltersHtml(): string
    {
        if (REQUEST_TYPE->isApiRequest()) {
            return '';
        }

        $entity = $this->entity;
        $LOC = $this->getLocale();

        if (count($this->filtersBlocks) === 0) {
            $this->prepareEntityItemsSet();
        }

        $filtersBlocks = $this->filtersBlocks;

        foreach ($filtersBlocks as $filtersBlock) {
            foreach ($filtersBlock->getFiltersViewItems() as $filtersViewItem) {
                $value = $this->getParameterByName($filtersViewItem->getName());

                if ($value !== null) {
                    /** @phpstan-ignore-next-line */
                    $filtersViewItem->set($value);
                }
            }
        }

        $filtersContent = '';

        if (count($filtersBlocks) > 0) {
            $filtersContent = '
<div class="indexer' . ($this->getFiltersState() ? ' shown' : '') . '"><div id="filters_' . TextHelper::camelCaseToSnakeCase($entity->getName()) . '">
<form action="' . ABSOLUTE_PATH . '/' . KIND . '/" method="POST" enctype="multipart/form-data" id="filter_form">
<input type="hidden" name="kind" value="' . KIND . '">
<input type="hidden" name="action" value="setFilters">
<input type="hidden" name="cmsvc" value="' . TextHelper::camelCaseToSnakeCase($entity->getName()) . '">
<input type="hidden" name="sorting" value="' . SORTING . '">
';

            foreach ($filtersBlocks as $filtersBlock) {
                $filtersContent .= '<div class="filtersBlock">';

                foreach ($filtersBlock->getFiltersViewItems() as $filtersViewItemKey => $filtersViewItem) {
                    $filtersPreContent = $filtersViewItem->asHTML(
                        elementIsWritable: true,
                        removeHtmlFromValue: $filtersViewItem instanceof Item\Number || $filtersViewItem instanceof Item\Text ? true : false,
                    );

                    if ($filtersViewItemKey === 0) {
                        if ($filtersViewItem->getName() === 'searchAllTextFields') {
                            $filtersPreContent = str_replace(" />", ' autocomplete="off" />', $filtersPreContent);
                        }
                        $filtersContent .= '<div class="filtersName">' . $filtersViewItem->getShownName() . '</div>' . $filtersPreContent;

                        if ($filtersViewItem->getName() === 'searchAllTextFields') {
                            $filtersContent .= '<br>';
                        }
                    } else {
                        $filtersContent .= $filtersPreContent;

                        if ($filtersViewItem instanceof Item\Checkbox) {
                            $filtersContent .= '<label for="' . $filtersViewItem->getName() . '">' . $filtersViewItem->getShownName() . '</label><br>';
                        }
                    }
                }
                $filtersContent .= '</div>';
            }
            $filtersOn = $this->getFiltersState();
            $filtersContent .= '<button class="main' . ($filtersOn ? '' : ' full_width') . '">' . $LOC['apply'] . '</button>' .
                (
                    $filtersOn ?
                    '<button class="nonimportant" href="' . ABSOLUTE_PATH . '/' . KIND . '/object=' . TextHelper::camelCaseToSnakeCase(
                        $entity->getName(),
                    ) . '&action=clearFilters&sorting=' . SORTING . '">' .
                    $LOC['cancel'] . '</button>' :
                    ''
                ) .
                '</form></div></div>';
        }

        return $filtersContent;
    }

    /** Подготовка SQL-инъекции в запросы сущности и ссылки на набор фильтров
     * @return array<string>
     */
    public function prepareSearchSqlAndFiltersLink(bool $getDataFromCookies = false, string $kind = KIND): array
    {
        $entity = $this->entity;

        $filtersBlocks = $this->prepareEntityItemsSet();

        $tableFieldToDetectType = '';

        if ($entity instanceof CatalogEntity) {
            $catalogItemEntity = $entity->getCatalogItemEntity();
            $tableFieldToDetectType = $catalogItemEntity->getTableFieldToDetectType();
        }

        $dataArray = ($getDataFromCookies ? ($this->getFiltersCookie()[$kind][$entity->getName()] ?? []) : $_REQUEST);

        $searchQuerySql = is_null($entity->getView()->getViewRights()->getViewRestrict()) ? " WHERE" : "";
        $searchQueryParams = [];

        /** Какой символ отправлять в функцию запроса к БД при поиске в групповых полях на разных типах базы? */
        $groupFieldsQuerySign = $_ENV['DATABASE_TYPE'] === 'mysql' ? "\\\"" : "do_not_change_this_quote";

        $firstSearchQuery = true;
        [$regexpWord, $antiRegexpWord] = $this->getRegexpWords();

        foreach ($filtersBlocks as $filtersBlock) {
            $blockSearchQuerySql = "";

            $filtersViewItems = $filtersBlock->getFiltersViewItems();
            $filtersViewFirstItem = $filtersViewItems[0];
            $filtersViewSecondItem = $filtersViewItems[1] ?? null;

            $modelItem = null;
            $queryElementName = '';

            if ($filtersBlock->getModelItems()[0] ?? false) {
                $modelItem = $filtersBlock->getModelItems()[0];
                $queryElementName = $modelItem->getName();
            }

            if (!$getDataFromCookies) {
                if ($dataArray[$filtersViewFirstItem->getName()] ?? false) {
                    $this->setParameterByName($filtersViewFirstItem->getName(), $dataArray[$filtersViewFirstItem->getName()]);
                }

                if (!is_null($filtersViewSecondItem) && ($dataArray[$filtersViewSecondItem->getName()] ?? false)) {
                    $defaultValue = $filtersViewSecondItem->getDefaultValue();

                    if (is_array($defaultValue)) {
                        $defaultValue = $defaultValue[0];
                    }

                    if ((string) $defaultValue !== (string) $dataArray[$filtersViewSecondItem->getName()]) {
                        $this->setParameterByName($filtersViewSecondItem->getName(), $dataArray[$filtersViewSecondItem->getName()]);
                    }
                }
            }

            if ($filtersViewFirstItem->getName() === 'searchAllTextFields') {
                $allTextFields = [];

                if ($dataArray[$filtersViewSecondItem->getName()] ?? false) {
                    $allTextFieldsQueryValues = $dataArray[$filtersViewSecondItem->getName()];
                } else {
                    $allTextFieldsQueryValues = '';
                }

                if (in_array($allTextFieldsQueryValues, ['search_in', ''])) {
                    if ($dataArray[$filtersViewFirstItem->getName()] ?? false) {
                        $allTextFieldsQuery = $dataArray[$filtersViewFirstItem->getName()];
                    } else {
                        $allTextFieldsQuery = '';
                    }

                    /** Защита от лишних символов */
                    $allTextFieldsQuery = str_replace(['|', '*'], '', $allTextFieldsQuery);

                    $allTextFields = explode(' ', $allTextFieldsQuery);

                    foreach ($allTextFields as $key => $value) {
                        if (trim($value) === '') {
                            unset($allTextFields[$key]);
                        }
                    }
                }

                if (count($allTextFields) > 0 || !in_array($allTextFieldsQueryValues, ['search_in', ''])) {
                    $firstInBlockFound = false;

                    foreach ($filtersViewItems as $filtersViewItem) {
                        if ($filtersViewItem instanceof Item\Checkbox) {
                            if (!$getDataFromCookies) {
                                $this->setParameterByName($filtersViewItem->getName(), $dataArray[$filtersViewItem->getName()] ?? null);
                            }

                            $checkBoxValue = $dataArray[$filtersViewItem->getName()] ?? null;

                            $blockSearchQuerySql = "";

                            $modelItem = $this->getCorrespondingItem($filtersViewItem->getName(), $filtersBlock);

                            if (!is_null($modelItem) && $checkBoxValue === 'on') {
                                $queryElementName = $modelItem->getName();

                                $blockSearchQuerySql .= " (";

                                if ($allTextFieldsQueryValues === 'search_empty') {
                                    if ($modelItem->getVirtual()) {
                                        $blockSearchQuerySql .= "(t1." . $entity->getVirtualField() .
                                            " " . $regexpWord . " '\\\[" . $queryElementName . "\\\]\\\[\\\]' OR t1." . $entity->getVirtualField() .
                                            " " . $antiRegexpWord . " '\\\[" . $queryElementName . "\\\]') AND ";
                                    } else {
                                        $blockSearchQuerySql .= "(t1." . $queryElementName . " IS NULL OR t1." . $queryElementName . "='') AND ";
                                    }
                                } elseif ($allTextFieldsQueryValues === 'search_non_empty') {
                                    if ($modelItem->getVirtual()) {
                                        $blockSearchQuerySql .= "t1." . $entity->getVirtualField() .
                                            " " . $regexpWord . " '\\\[" . $queryElementName . "\\\]\\\[[^]]+\\\]' AND ";
                                    } else {
                                        $blockSearchQuerySql .= "t1." . $queryElementName . " " . $regexpWord . " '";

                                        if ($modelItem->getGroup()) {
                                            $converted_text = DataHelper::jsonFixedEncode(['.+']);
                                            $blockSearchQuerySql .= $converted_text;
                                        } else {
                                            $blockSearchQuerySql .= ".+";
                                        }
                                        $blockSearchQuerySql .= "' AND ";
                                    }
                                } elseif (count($allTextFields) > 0) {
                                    if ($modelItem->getVirtual()) {
                                        foreach ($allTextFields as $allTextField) {
                                            $blockSearchQuerySql .= "LOWER(t1." . $entity->getVirtualField() . ") " . $regexpWord .
                                                " '\\\[" . mb_strtolower($queryElementName) . "\\\]\\\[[^]]*" . mb_strtolower($allTextField) . "[^]]*\\\]' AND ";
                                        }
                                    } else {
                                        foreach ($allTextFields as $allTextField) {
                                            $blockSearchQuerySql .= "LOWER(t1." . $queryElementName . ") " . $regexpWord . " '";

                                            if ($modelItem->getGroup()) {
                                                $converted_text = DataHelper::jsonFixedEncode([$allTextField]);
                                                $converted_text = str_replace(['\\', '"'], '', $converted_text);
                                                $blockSearchQuerySql .= mb_strtolower($converted_text);
                                            } else {
                                                $blockSearchQuerySql .= mb_strtolower($allTextField);
                                            }
                                            $blockSearchQuerySql .= "' AND ";
                                        }
                                    }
                                }
                                $blockSearchQuerySql = mb_substr($blockSearchQuerySql, 0, mb_strlen($blockSearchQuerySql) - 5);
                                $blockSearchQuerySql .= ")";

                                [$firstSearchQuery, $blockSearchQuerySql] = $this->getCatalogEntitySql(
                                    $firstSearchQuery,
                                    $blockSearchQuerySql,
                                    $tableFieldToDetectType,
                                    $modelItem,
                                );

                                if ($firstInBlockFound) {
                                    $blockSearchQuerySql = " OR" . $blockSearchQuerySql;
                                } else {
                                    $firstInBlockFound = true;
                                    $blockSearchQuerySql = " (" . $blockSearchQuerySql;
                                }

                                $searchQuerySql .= $blockSearchQuerySql;
                            }
                        }
                    }

                    if ($firstInBlockFound) {
                        $searchQuerySql .= ")";
                    }
                }
            } elseif ($modelItem instanceof Item\Multiselect || ($modelItem instanceof Item\Select && is_null($modelItem->getHelper()))) {
                /** @var Item\Multiselect $filtersViewFirstItem */
                $vals = $filtersViewFirstItem->getValues();
                $res = [];
                $selectbreaks = true;

                $itemDataArray = $dataArray[$filtersViewFirstItem->getName()] ?? [];

                foreach ($vals as $key => $value) {
                    $blockSearchQuerySql = "";

                    if (($itemDataArray[$value[0]] ?? '') === 'on' || in_array($value[0], $itemDataArray)) {
                        $res[] = $value[0];

                        if ($modelItem instanceof Item\Select) {
                            if (!$firstSearchQuery) {
                                if ($selectbreaks) {
                                    $blockSearchQuerySql .= " AND (";
                                    $selectbreaks = false;
                                } else {
                                    $blockSearchQuerySql .= " OR";
                                }
                            } elseif ($selectbreaks) {
                                $blockSearchQuerySql .= " (";
                                $selectbreaks = false;
                            }

                            $firstSearchQuery = false;

                            if ($this->entity instanceof CatalogEntity) {
                                $blockSearchQuerySql .= "(";
                            }

                            if ($modelItem->getVirtual()) {
                                if ($value[0] === 'not_set') {
                                    $blockSearchQuerySql .= " (t1." . $entity->getVirtualField() .
                                        " LIKE '%[" . $queryElementName . "][]%' OR t1." . $entity->getVirtualField() .
                                        " NOT LIKE '%[" . $queryElementName . "][%')";
                                } else {
                                    $blockSearchQuerySql .= " t1." . $entity->getVirtualField() . " LIKE '%[" . $queryElementName . "][" . $value[0] . "]%'";
                                }
                            } elseif ($modelItem->getGroup()) {
                                if ($value[0] === 'not_set') {
                                    $blockSearchQuerySql .= " (t1." . $queryElementName . " IS NULL OR t1." . $queryElementName . "='')";
                                } else {
                                    $blockSearchQuerySql .= " t1." . $queryElementName . " LIKE '%" . $groupFieldsQuerySign . $value[0] . $groupFieldsQuerySign . "%'";
                                }
                            } elseif ($value[0] === 'not_set') {
                                $blockSearchQuerySql .= " (t1." . $queryElementName . " IS NULL OR t1." . $queryElementName . "='')";
                            } else {
                                $blockSearchQuerySql .= " t1." . $queryElementName . "=" . (is_numeric($value[0]) ? $value[0] : "'" . $value[0] . "'");
                            }
                        } elseif ($modelItem instanceof Item\Multiselect) {
                            if (!$firstSearchQuery) {
                                if ($selectbreaks) {
                                    $blockSearchQuerySql .= " AND (";
                                    $selectbreaks = false;
                                }
                                /** Если здесь поставить AND, то при поиске в мультиселектах нужно будет совпадение со всеми поисковыми галочками,
                                 * выставленными пользователями. Если OR, то хотя бы с одной из них */ elseif ($dataArray[$filtersViewSecondItem->getName()] === '2') {
                                    $blockSearchQuerySql .= " AND";
                                } else {
                                    $blockSearchQuerySql .= " OR";
                                }
                            }

                            if ($firstSearchQuery && $selectbreaks) {
                                $blockSearchQuerySql .= " (";
                                $selectbreaks = false;
                            }

                            $firstSearchQuery = false;

                            if ($this->entity instanceof CatalogEntity) {
                                $blockSearchQuerySql .= "(";
                            }

                            $stripped_val = str_replace('-', '', (string) $value[0]);

                            if ($modelItem->getVirtual()) {
                                if ($value[0] === 'not_set') {
                                    $blockSearchQuerySql .= " (t1." . $entity->getVirtualField() .
                                        " LIKE '%[" . $queryElementName . "][]%' OR t1." . $entity->getVirtualField() .
                                        " LIKE '%[" . $queryElementName . "][-]%' OR t1." . $entity->getVirtualField() .
                                        " LIKE '%[" . $queryElementName . "][--]%' OR t1." . $entity->getVirtualField() .
                                        " NOT LIKE '%[" . $queryElementName . "][%')";
                                } else {
                                    $blockSearchQuerySql .= " (t1." . $entity->getVirtualField() .
                                        " " . $regexpWord . " '\\\[" . $queryElementName . "\\\]\\\[[^]]*-" . $value[0] . "-[^]]*' OR t1." . $entity->getVirtualField() .
                                        " LIKE '%[" . $queryElementName . "][" . $stripped_val . "]%')";
                                }
                            } elseif ($modelItem->getOne() && !($modelItem->getGroup() > 0)) {
                                /** Предполагаем, что тип колонки в этом случае = int */
                                if ($value[0] === 'not_set') {
                                    $blockSearchQuerySql .= " (t1." . $queryElementName . " IS NULL)";
                                } else {
                                    $blockSearchQuerySql .= " (t1." . $queryElementName . "='" . $stripped_val . "')";
                                }
                            }
                            /** Предполагаем, что тип колонки в этом случае = varchar */ elseif ($value[0] === 'not_set') {
                                $blockSearchQuerySql .= " (t1." . $queryElementName . " IS NULL OR t1." . $queryElementName . "='' OR t1." . $queryElementName . "='-'
                                OR t1." . $queryElementName . "='--')";
                            } else {
                                $blockSearchQuerySql .= " (t1." . $queryElementName . " LIKE '%-" . $value[0] . "-%' OR t1." . $queryElementName . " LIKE '%"
                                    . $groupFieldsQuerySign . $value[0] . $groupFieldsQuerySign . "%' OR t1." . $queryElementName . "='" . $stripped_val . "')";
                            }
                        }

                        if ($this->entity instanceof CatalogEntity) {
                            $blockSearchQuerySql .= " AND t1." . $tableFieldToDetectType .
                                ($modelItem->getEntity() instanceof CatalogItemEntity ? "!" : "") . "='{menu}')";
                        }
                    }

                    if (!($vals[($key + 1)] ?? false) && !$selectbreaks) {
                        $blockSearchQuerySql .= ")";
                    }

                    $searchQuerySql .= $blockSearchQuerySql;
                }

                if (!$getDataFromCookies) {
                    $this->setParameterByName($filtersViewFirstItem->getName(), $res);
                }

                if ($modelItem instanceof Item\Multiselect) {
                    $array = $dataArray[$filtersViewFirstItem->getName()] ?? [];
                    $arrayKeys = [];

                    if (is_array($array)) {
                        foreach ($array as $key => $value) {
                            if ($value === 'on') {
                                $arrayKeys[] = $key;
                            }
                        }
                    }

                    if (!$getDataFromCookies) {
                        $this->setParameterByName($filtersViewFirstItem->getName(), $arrayKeys);
                        $this->setParameterByName($filtersViewSecondItem->getName(), $dataArray[$filtersViewSecondItem->getName()] ?? null);
                    }
                }
            } else {
                if ($modelItem instanceof Item\File && ($dataArray[$filtersViewFirstItem->getName()] ?? '') === 'on') {
                    /** @var Item\File $modelItem */
                    $queryElementName = $modelItem->getUploadData()['columnname'];

                    $blockSearchQuerySql .= " t1." . $queryElementName . "!=''";
                } elseif (
                    $modelItem instanceof Item\Calendar &&
                    ($dataArray[$filtersViewFirstItem->getName()] ?? '') !== '' &&
                    ($dataArray[$filtersViewSecondItem->getName()] ?? '') !== ''
                ) {
                    $date_in_format = date("Y-m-d", strtotime($dataArray[$filtersViewSecondItem->getName()]));

                    $selectType = $dataArray[$filtersViewFirstItem->getName()];

                    if ($filtersViewSecondItem->getVirtual()) {
                        if ($selectType === '1') {
                            $blockSearchQuerySql .= " t1." . $entity->getVirtualField() . " LIKE '%[" . $queryElementName . "][" . $date_in_format . "]%'";
                        } elseif ($selectType === '2') {
                            $blockSearchQuerySql .= " t1." . $entity->getVirtualField() . " NOT LIKE '%[" . $queryElementName . "][" . $date_in_format . "]%'";
                        }
                    } else {
                        $blockSearchQuerySql .= " (t1." . $queryElementName . match ($selectType) {
                            '1' => "=",
                            '2' => "!=",
                            '3' => ">",
                            '4' => "<",
                            default => '',
                        }
                        . "'" . $date_in_format . "'";

                        if ($selectType === '2' || $selectType === '4') {
                            $blockSearchQuerySql .= " OR t1." . $queryElementName . " IS NULL";
                        }
                        $blockSearchQuerySql .= ")";
                    }
                } elseif (
                    $modelItem instanceof Item\Timestamp &&
                    ($dataArray[$filtersViewFirstItem->getName()] ?? '') !== '' &&
                    ($dataArray[$filtersViewSecondItem->getName()] ?? '') !== ''
                ) {
                    $thistime1 = strtotime($dataArray[$filtersViewSecondItem->getName()]);
                    $thistime2 = $thistime1 + (60 * 60 * 24);

                    $blockSearchQuerySql .= " (t1." . $queryElementName . match ($dataArray[$filtersViewFirstItem->getName()]) {
                        '1' => ">=" . $thistime1 . " AND t1." . $queryElementName . "<" . $thistime2,
                        '2' => "<" . $thistime1 . " OR t1." . $queryElementName . ">=" . $thistime2,
                        '3' => ">=" . $thistime2,
                        '4' => "<" . $thistime1,
                        default => '',
                    }
                    . ")";
                } elseif (
                    $modelItem instanceof Item\Number &&
                    (
                        (int) ($dataArray[$filtersViewSecondItem->getName()] ?? 0) > 0 ||
                        (
                            (int) ($dataArray[$filtersViewSecondItem->getName()] ?? 0) === 0 &&
                            ($dataArray[$filtersViewFirstItem->getName()] ?? '') !== ''
                        )
                    )
                ) {
                    if ($dataArray[$filtersViewFirstItem->getName()] ?? '' === '') {
                        $dataArray[$filtersViewFirstItem->getName()] = '1';
                    }

                    $searchvals = [];
                    $hasNull = false;

                    if (str_contains($dataArray[$filtersViewSecondItem->getName()], ',')) {
                        /** Это ряд значений через запятую */
                        $searchvals = explode(",", $dataArray[$filtersViewSecondItem->getName()]);

                        foreach ($searchvals as $key => $value) {
                            $searchvals[$key] = (int) trim($value);

                            if ((int) trim($value) === 0) {
                                $hasNull = true;
                            }
                        }
                    } else {
                        $searchvals[] = (int) $dataArray[$filtersViewSecondItem->getName()];
                    }

                    $selectType = $dataArray[$filtersViewFirstItem->getName()];

                    $blockSearchQuerySql .= " (";

                    if ($modelItem->getVirtual()) {
                        if ($selectType === '1') {
                            $blockSearchQuerySql .= "t1." . $entity->getVirtualField() . " LIKE '%[" . $queryElementName . "][" .
                                implode("]%' OR t1." . $entity->getVirtualField() . " LIKE '%[" . $queryElementName . "][", $searchvals) . "]%'";
                        } elseif ($selectType === '2') {
                            $blockSearchQuerySql .= "t1." . $entity->getVirtualField() . " NOT LIKE '%[" . $queryElementName . "][" .
                                implode("]%' AND t1." . $entity->getVirtualField() . " NOT LIKE '%[" . $queryElementName . "][", $searchvals) . "]%'";
                        }
                    } elseif ($selectType === '1') {
                        $blockSearchQuerySql .= "t1." . $queryElementName . "=" . implode(" OR t1." . $queryElementName . "=", $searchvals);

                        if ($hasNull) {
                            $blockSearchQuerySql .= " OR t1." . $queryElementName . " IS NULL";
                        }
                    } elseif ($selectType === '2') {
                        $blockSearchQuerySql .= "t1." . $queryElementName . "!=" . implode(
                            " AND t1." . $queryElementName . "!=",
                            $searchvals,
                        );

                        if ($hasNull) {
                            $blockSearchQuerySql .= " AND t1." . $queryElementName . " IS NOT NULL";
                        }
                    } elseif ($selectType === '3') {
                        $blockSearchQuerySql .= "t1." . $queryElementName . ">" . $searchvals[0]; //не может быть ситуация: "больше нескольких значений через запятую"

                        if ($searchvals[0] < 0) {
                            $blockSearchQuerySql .= " OR t1." . $queryElementName . " IS NULL";
                        }
                    } elseif ($selectType === '4') {
                        $blockSearchQuerySql .= "t1." . $queryElementName . "<" . $searchvals[0]; //не может быть ситуация: "меньше нескольких значений через запятую"

                        if ($searchvals[0] > 0) {
                            $blockSearchQuerySql .= " OR t1." . $queryElementName . " IS NULL";
                        }
                    }
                    $blockSearchQuerySql .= ")";
                } elseif ($modelItem instanceof Item\Checkbox && ($dataArray[$filtersViewFirstItem->getName()] ?? false) > 0) {
                    if ($modelItem->getVirtual()) {
                        if ($dataArray[$filtersViewFirstItem->getName()] === '1') {
                            $blockSearchQuerySql .= " t1." . $entity->getVirtualField() . " LIKE '%[" . $queryElementName . "][1]%'";
                        } elseif ($dataArray[$filtersViewFirstItem->getName()] === '2') {
                            $blockSearchQuerySql .= " t1." . $entity->getVirtualField() . " NOT LIKE '%[" . $queryElementName . "][1]%'";
                        }
                    } elseif ($dataArray[$filtersViewFirstItem->getName()] === '1') {
                        $blockSearchQuerySql .= " t1." . $queryElementName . "='1'";
                    } elseif ($dataArray[$filtersViewFirstItem->getName()] === '2') {
                        $blockSearchQuerySql .= " (t1." . $queryElementName . "!='1' OR t1." . $queryElementName . " IS NULL)";
                    }
                } elseif ($modelItem instanceof Item\Select && !is_null($modelItem->getHelper()) && ($dataArray[$filtersViewFirstItem->getName()] ?? false)) {
                    $blockSearchQuerySql .= " t1." . $queryElementName . "=" . $dataArray[$filtersViewFirstItem->getName()];
                }

                if ($blockSearchQuerySql !== "") {
                    [$firstSearchQuery, $blockSearchQuerySql] = $this->getCatalogEntitySql(
                        $firstSearchQuery,
                        $blockSearchQuerySql,
                        $tableFieldToDetectType,
                        $modelItem,
                    );
                    $searchQuerySql .= $blockSearchQuerySql;
                }
            }
        }

        if (!in_array($searchQuerySql, [" WHERE", ""], true)) {
            /** Ссылка на текущий набор фильтров */
            $currentFiltersLink = ABSOLUTE_PATH . '/' . $kind . '/object=' . TextHelper::camelCaseToSnakeCase($entity->getName()) . '&action=setFilters';

            foreach ($filtersBlocks as $filtersBlock) {
                foreach ($filtersBlock->getFiltersViewItems() as $filtersViewItem) {
                    $objName = $filtersViewItem->getName();
                    $objValue = $this->getParameterByName($objName, $kind);

                    $defaultValue = $filtersViewItem->getDefaultValue();

                    if (is_array($defaultValue) && count($defaultValue) > 0) {
                        $defaultValue = $defaultValue[key($defaultValue)];

                        if (is_array($defaultValue) && count($defaultValue) > 0) {
                            $defaultValue = $defaultValue[key($defaultValue)];
                        }
                    } elseif (is_array($defaultValue)) {
                        $defaultValue = null;
                    }

                    if (method_exists($filtersViewItem, 'getDefaultValue') && (string) $objValue !== (string) $defaultValue) {
                        if (!is_null($objValue)) {
                            if (is_array($objValue)) {
                                foreach ($objValue as $objValueItem) {
                                    $currentFiltersLink .= '&' . $objName . '[' . $objValueItem . ']=on';
                                }
                            } else {
                                $currentFiltersLink .= '&' . $objName . '=' . $objValue;
                            }
                        }
                    }
                }
            }

            $this->searchQuerySql = $searchQuerySql;
            $this->searchQueryParams = $searchQueryParams;
            $this->currentFiltersLink = $currentFiltersLink;

            if (!$getDataFromCookies) {
                $fraymFilters = self::getFiltersCookie();

                if (!array_key_exists($kind, $fraymFilters)) {
                    $fraymFilters[$kind] = [];
                }

                if (!array_key_exists($this->entity->getName(), $fraymFilters[$kind])) {
                    $fraymFilters[$kind][$this->entity->getName()] = [];
                }

                $fraymFilters[$kind][$this->entity->getName()] = $this->cookieValues;

                self::setFiltersCookie($fraymFilters);
            }
        } else {
            $this->clearEntityFiltersData();
        }

        return [
            $this->getSearchQuerySql(),
            $this->getSearchQueryParams(),
            $this->getCurrentFiltersLink(),
        ];
    }

    /** Очистка данных по фильтрам в cookie сущности */
    public function clearEntityFiltersData(): void
    {
        $fraymFilters = self::getFiltersCookie();

        if ($fraymFilters[KIND][$this->entity->getName()] ?? false) {
            unset($fraymFilters[KIND][$this->entity->getName()]);
        }

        if (($fraymFilters[KIND] ?? null) === []) {
            unset($fraymFilters[KIND]);
        }

        self::setFiltersCookie($fraymFilters);
    }

    /** Проверка видимости панели фильтров */
    public function getFiltersState(): bool
    {
        return !($this->getPreparedSearchQuerySql() === '' && !(in_array(ACTION, ActionEnum::getFilterValues())));
    }

    /** Получение локали фильтров */
    public function getLocale(): ?array
    {
        return LocaleHelper::getLocale(['fraym', 'filters']);
    }

    /** Получение ранее подготовленной SQL-инъекции по фильтрам */
    public function getPreparedSearchQuerySql(string $kind = KIND): string
    {
        if (!$this->getSearchQuerySql()) {
            $this->prepareSearchSqlAndFiltersLink(true, $kind);
        }

        return $this->getSearchQuerySql() ?? '';
    }

    /** Получение ранее подготовленных параметров SQL-инъекции по фильтрам */
    public function getPreparedSearchQueryParams(string $kind = KIND): array
    {
        if ($this->getSearchQueryParams() === []) {
            $this->prepareSearchSqlAndFiltersLink(true, $kind);
        }

        return $this->getSearchQueryParams();
    }

    /** Получение ранее подготовленной ссылки на текущий набор фильтров */
    public function getPreparedCurrentFiltersLink(string $kind = KIND): string
    {
        if (!$this->getCurrentFiltersLink()) {
            $this->prepareSearchSqlAndFiltersLink(true, $kind);
        }

        return $this->getCurrentFiltersLink() ?? '';
    }

    /** Проверка наличия cookie фильтров */
    public static function hasFiltersCookie(string $entityName, string $kind = KIND): bool
    {
        return count(self::getFiltersCookie()[$kind][$entityName] ?? []) > 0;
    }

    /** Получение параметра фильтра из куки соответствующей entity и, опционально, kind */
    public static function getFiltersCookieParameterByName(string $parameterName, string $entityName, string $kind = KIND): mixed
    {
        return self::getFiltersCookie()[$kind][$entityName][$parameterName] ?? null;
    }

    /** Подготовка набора item'ов на основе параметра useInFilters из сущности
     * @return array<int, FiltersBlock>
     */
    private function prepareEntityItemsSet(): array
    {
        $entity = $this->entity;
        $LOC = $this->getLocale();

        /** Выбираем все item'ы модели с useInFilters, видимые в list текущей entity */
        $modelItems = $entity->getModel()->getElements();

        foreach ($modelItems as $key => $modelItem) {
            if (!$modelItem->getAttribute()->getUseInFilters()) {
                unset($modelItems[$key]);
            }
        }

        /** Если это модель класса каталог, добавляем поля для поиска из наследующей сущности, но только если сущность отличается от базовой, т.е. не является просто необходимой заглушкой */
        if ($entity instanceof CatalogEntity) {
            $catalogItemEntity = $entity->getCatalogItemEntity();

            if ($catalogItemEntity->getModel()::class !== $entity->getModel()::class) {
                foreach ($catalogItemEntity->getModel()->getElements() as $modelItem) {
                    if ($modelItem->getAttribute()->getUseInFilters()) {
                        $modelItems[] = $modelItem;
                    }
                }
            }
        }

        $filtersBlocks = [];

        $textFieldsExistInSearch = false;

        foreach ($modelItems as $modelItem) {
            if ($modelItem instanceof Item\Text || $modelItem instanceof Item\Textarea || $modelItem instanceof Item\Wysiwyg) {
                $textFieldsExistInSearch = true;
                break;
            }
        }

        if ($textFieldsExistInSearch) {
            $filterBlock = @$filtersBlocks[] = new FiltersBlock();

            $filterBlock->addFiltersViewItem(new Item\Text())
                ->setName('searchAllTextFields')
                ->setShownName($LOC['search_in_all_text_fields'])
                ->setAttribute(new Attribute\Text());

            $filterBlock->addFiltersViewItem(new Item\Select())
                ->setName('searchAllTextFieldsValues')
                ->setShownName('')
                ->setAttribute(
                    new Attribute\Select(
                        defaultValue: 'search_in',
                        values: $LOC['search_in_all_text_fields_values'],
                    ),
                );

            foreach ($modelItems as $modelItem) {
                if ($modelItem instanceof Item\Text || $modelItem instanceof Item\Textarea || $modelItem instanceof Item\Wysiwyg) {
                    $searchFieldName = 'search' . ($modelItem->getEntity() instanceof CatalogItemEntity ? '2' : '') . '_' . $modelItem->getName();

                    $filterBlock->addFiltersViewItem(new Item\Checkbox())
                        ->setName($searchFieldName)
                        ->setShownName($modelItem->getShownName())
                        ->setAttribute(new Attribute\Checkbox());
                    $filterBlock->addModelItem($modelItem);
                }
            }
        }

        foreach ($modelItems as $modelItem) {
            if (
                !(
                    $modelItem instanceof Item\Text ||
                    $modelItem instanceof Item\Textarea ||
                    $modelItem instanceof Item\Wysiwyg ||
                    $modelItem instanceof Item\H1 ||
                    $modelItem instanceof Item\Hidden ||
                    $modelItem instanceof Item\Tab ||
                    $modelItem instanceof Item\Password
                )
            ) {
                $filterBlock = @$filtersBlocks[] = new FiltersBlock();
                $filterBlock->addModelItem($modelItem);

                $searchFieldName = 'search' . ($modelItem->getEntity() instanceof CatalogItemEntity ? '2' : '') . '_' . $modelItem->getName();

                if ($modelItem instanceof Item\Select && !is_null($modelItem->getHelper())) {
                    $filterBlock->addFiltersViewItem(clone ($modelItem))
                        ->setName($searchFieldName)
                        ->getAttribute()->setObligatory(false);
                } elseif ($modelItem instanceof Item\Multiselect) {
                    /** @var Item\Multiselect */
                    $clonedModelItem = $filterBlock->addFiltersViewItem(clone ($modelItem))
                        ->setName($searchFieldName);

                    $clonedModelItem->getAttribute()
                        ->setCreator(null)
                        ->setLocked([])
                        ->setOne(false)
                        ->setValues(
                            array_merge([['not_set', '<i>' . $LOC['not_set'] . '</i>']], $clonedModelItem->getValues() ?? []),
                        )
                        ->setObligatory(false);

                    $filterBlock->addFiltersViewItem(new Item\Multiselect())
                        ->setName($searchFieldName . 'select')
                        ->setAttribute(
                            new Attribute\Multiselect(
                                defaultValue: [1],
                                values: [[1, $LOC['any_match']], [2, $LOC['strict_match']]],
                                one: true,
                            ),
                        );
                } elseif ($modelItem instanceof Item\Select) {
                    $filterBlock->addFiltersViewItem(new Item\Multiselect())
                        ->setName($searchFieldName)
                        ->setShownName($modelItem->getShownName())
                        ->setAttribute(
                            new Attribute\Multiselect(
                                values: array_merge([['not_set', '<i>' . $LOC['not_set'] . '</i>']], $modelItem->getValues() ?? []),
                                search: true,
                            ),
                        );
                } elseif ($modelItem instanceof Item\Calendar || $modelItem instanceof Item\Number) {
                    $filterBlock->addFiltersViewItem(new Item\Select())
                        ->setName($searchFieldName . 'select')
                        ->setShownName($modelItem->getShownName())
                        ->setAttribute(
                            new Attribute\Select(
                                values: $modelItem->getVirtual() ? [['1', '='], ['2', '&lt;&gt;']] : [
                                    ['1', '='],
                                    ['2', '&lt;&gt;'],
                                    ['3', '&gt;'],
                                    ['4', '&lt;'],
                                ],
                            ),
                        );

                    /** @var Item\Calendar|Item\Number */
                    $clonedModelItem =  $filterBlock->addFiltersViewItem(clone ($modelItem))
                        ->setName($searchFieldName);

                    $clonedModelItem->getAttribute()
                        ->setObligatory(false)
                        ->setDefaultValue(null);
                } elseif ($modelItem instanceof Item\File) {
                    $filterBlock->addFiltersViewItem(new Item\Checkbox())
                        ->setName($searchFieldName)
                        ->setShownName($modelItem->getShownName())
                        ->setAttribute(new Attribute\Checkbox());
                } elseif ($modelItem instanceof Item\Checkbox) {
                    $filterBlock->addFiltersViewItem(new Item\Multiselect())
                        ->setName($searchFieldName)
                        ->setShownName($modelItem->getShownName())
                        ->setAttribute(
                            new Attribute\Multiselect(
                                values: [
                                    [1, '<span class="sbi sbi-check"></span>'],
                                    [2, '<span class="sbi sbi-times"></span>'],
                                ],
                                one: true,
                            ),
                        );
                } elseif ($modelItem instanceof Item\Timestamp) {
                    $filterBlock->addFiltersViewItem(new Item\Select())
                        ->setName($searchFieldName . 'select')
                        ->setShownName($modelItem->getShownName())
                        ->setAttribute(
                            new Attribute\Select(
                                values: [
                                    ['1', '='],
                                    ['2', '<>'],
                                    ['3', '>'],
                                    ['4', '<'],
                                ],
                            ),
                        );

                    $filterBlock->addFiltersViewItem(new Item\Calendar())
                        ->setName($searchFieldName)
                        ->setShownName($modelItem->getShownName())
                        ->setAttribute(
                            new Attribute\Calendar(
                                showDatetime: true,
                            ),
                        );
                }
            }
        }

        return $this->filtersBlocks = $filtersBlocks;
    }

    /** Формирование SQL-уточнения для поиска в зависимости от того, является объект частью родительской или наследующей сущности CatalogEntity
     * @return array{bool, string}
     */
    private function getCatalogEntitySql(
        bool $firstSearchQuery,
        string $blockSearchQuerySql,
        string $tableFieldToDetectType,
        ElementItem $modelItem,
    ): array {
        $sql = '';

        if (!$firstSearchQuery) {
            $sql .= ' AND';
        } else {
            $firstSearchQuery = false;
        }

        if ($this->entity instanceof CatalogEntity) {
            $sql .= ' (';
        }

        $sql .= $blockSearchQuerySql;

        if ($this->entity instanceof CatalogEntity) {
            $sql .= " AND t1." . $tableFieldToDetectType .
                ($modelItem->getEntity() instanceof CatalogItemEntity ? "!" : "") . "='{menu}')";
        }

        return [$firstSearchQuery, $sql];
    }

    /** Получение соответствующего item'а из набора item'ов FilterBlock'а по названию элемента во вьюшке */
    private function getCorrespondingItem(string $name, FiltersBlock $filtersBlock): ?ElementItem
    {
        $name = str_ireplace(['search_', 'search2_'], '', $name);

        foreach ($filtersBlock->getModelItems() as $modelItem) {
            if ($modelItem->getName() === $name) {
                return $modelItem;
            }
        }

        return null;
    }

    private function getRegexpWords(): array
    {
        $regexpWord = 'REGEXP';
        $antiRegexpWord = 'NOT REGEXP';

        if ($_ENV['DATABASE_TYPE'] === "pgsql") {
            $regexpWord = "~*";
            $antiRegexpWord = "!~*";
        }

        return [
            $regexpWord,
            $antiRegexpWord,
        ];
    }

    private function getSearchQuerySql(): ?string
    {
        return $this->searchQuerySql;
    }

    private function getSearchQueryParams(): array
    {
        return $this->searchQueryParams ?? [];
    }

    private function getCurrentFiltersLink(): ?string
    {
        return $this->currentFiltersLink;
    }

    private static function getFiltersCookie(): array
    {
        return CookieHelper::getCookie('fraym_filters', true) ?? [];
    }

    private static function setFiltersCookie(array $fraymFilters): void
    {
        CookieHelper::batchSetCookie(['fraym_filters' => $fraymFilters]);
    }

    private function getParameterByName(string $parameterName, string $kind = KIND): mixed
    {
        return $this->cookieValues[$parameterName] ?? self::getFiltersCookie()[$kind][$this->entity->getName()][$parameterName] ?? null;
    }

    private function setParameterByName(string $parameterName, mixed $value): void
    {
        if (!$value || (is_array($value) && count(array_filter($value)) === 0)) {
            return;
        }

        $this->cookieValues[$parameterName] = $value;
    }
}
