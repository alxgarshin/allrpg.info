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

namespace Fraym\Entity\Trait;

use Fraym\BaseObject\{BaseModel, BaseView};
use Fraym\Element\{Attribute as Attribute, Item as Item};
use Fraym\Entity\CatalogItemEntity;
use Fraym\Enum\ActEnum;
use Fraym\Helper\{DataHelper, LocaleHelper, ObjectsHelper, TextHelper};
use Fraym\Interface\TabbedEntity;

trait BaseEntityItem
{
    public function viewActItem(array $DATA_ITEM, ?ActEnum $act = null, ?string $contextName = null): string
    {
        $GLOBAL_LOCALE = LocaleHelper::getLocale(['fraym', 'dynamiccreate']);

        $RESPONSE_DATA = '';

        $objectName = TextHelper::camelCaseToSnakeCase($this->getName());
        $modelRights = $this->getView()->getViewRights();

        if (is_null($act)) {
            $act = DataHelper::getActDefault($this);
        }

        if ($act === ActEnum::add && $modelRights->getAddRight()) {
            $RESPONSE_DATA .= '
<form action="/' . KIND . '/" method="POST" enctype="multipart/form-data" id="form_' . $objectName . '">
<input type="hidden" name="kind" value="' . KIND . '" />
<input type="hidden" name="cmsvc" value="' . $objectName . '" />
';
        } elseif ($act === ActEnum::edit) {
            $RESPONSE_DATA .= '
<form' . ($modelRights->getChangeRight() ? ' action="/' . KIND . '/" method="PUT" enctype="multipart/form-data"' : '') . ' id="form_' . $objectName . '">
' . ($modelRights->getChangeRight() ? '<input type="hidden" name="kind" value="' . KIND . '" />
<input type="hidden" name="cmsvc" value="' . $objectName . '" />
<input type="hidden" name="act" value="edit" />' : '') . '
';
        }

        if (
            ($act === ActEnum::view && $modelRights->getViewRight()) ||
            ($act === ActEnum::add && $modelRights->getAddRight()) ||
            ($act === ActEnum::edit && $modelRights->getChangeRight())
        ) {
            /** @var BaseModel */
            $model = $this->getModel();

            if ($contextName === null) {
                $view = null;

                if (!$this instanceof CatalogItemEntity) {
                    /** @var BaseView */
                    $view = $this->getView();
                }

                $contextName = ObjectsHelper::getClassShortNameFromCMSVCObject($view ?? $model);
            }

            if (!REQUEST_TYPE->isApiRequest() && (($act === ActEnum::edit && $modelRights->getChangeRight()) || ($act === ActEnum::add && $modelRights->getAddRight()))) {
                $referer = $_SERVER["HTTP_REFERER"] ?? '';

                /** Защита от перехода из ServiceWorker */
                if (preg_match('#\/js\/#', $referer)) {
                    $referer = null;
                }

                $model->initElement(
                    'go_back_after_save_referer',
                    Item\Hidden::class,
                    new Attribute\Hidden(
                        defaultValue: $referer,
                        noData: true,
                        context: [
                            $contextName . ':view',
                            $contextName . ':create',
                            $contextName . ':update',
                        ],
                    ),
                );

                $model->initElement(
                    'go_back_after_save',
                    Item\Checkbox::class,
                    new Attribute\Checkbox(
                        defaultValue: !CURRENT_USER->getBlockSaveReferer(),
                        noData: true,
                        context: [
                            $contextName . ':view',
                            $contextName . ':create',
                            $contextName . ':update',
                        ],
                    ),
                )->setShownName($GLOBAL_LOCALE['go_back_after_save']);
            }

            $modelElements = $model->getElements();

            /** Обработка групповых полей */
            $groupFieldsPresent = false;
            $groupCount = [];
            $groupJsonRowData = [];
            $elementsByGroups = [];

            if ($act !== ActEnum::add) {
                foreach ($modelElements as $element) {
                    if ($element->getGroup()) {
                        $groupFieldsPresent = true;
                        $element->getAttribute()->setGroupNumber(0);
                        $elementsByGroups[$element->getGroup()][] = $element;

                        /** Замена табуляции */
                        $DATA_ITEM[$element->getName()] = preg_replace('/\t/', '\\t', $DATA_ITEM[$element->getName()] ?? '');

                        $jsonData = DataHelper::jsonFixedDecode($DATA_ITEM[$element->getName()]);

                        if (!$jsonData || count($jsonData) === 0 || $jsonData[0] === 'JSON_ERROR_SYNTAX') {
                            if (method_exists($element, 'getDefaultValue')) {
                                $jsonData = $element->getDefaultValue();
                            }
                        }
                        $groupJsonRowData[$element->getName()] = $jsonData;

                        if (!isset($groupCount[$element->getGroup()])) {
                            $groupCount[$element->getGroup()] = 1;
                        }

                        $elementsCount = 0;

                        if (is_array($jsonData)) {
                            $elementsCount = count($jsonData);
                        }

                        if ($elementsCount > $groupCount[$element->getGroup()]) {
                            $groupCount[$element->getGroup()] = $elementsCount;
                        }
                    }
                }
            }

            /** Простраиваем структуру групповых полей */
            if ($groupFieldsPresent) {
                /** Получили список элементов по группам. Теперь нам нужно поставить нужное количество повторений после последнего элемента группы. */
                foreach ($groupCount as $groupKey => $groupValue) {
                    if ($groupValue > 1) {
                        /** Находим последний элемент в группе */
                        $lastElementInGroup = false;

                        foreach ($modelElements as $key => $elem) {
                            if ($groupKey === $elem->getGroup() && (!isset($modelElements[$key + 1]) || $groupKey !== $modelElements[$key + 1]->getGroup())) {
                                $lastElementInGroup = $key;
                                break;
                            }
                        }

                        if ($lastElementInGroup !== false) {
                            $insertedElements = [];

                            for ($i = 2; $i <= $groupValue; $i++) {
                                foreach ($elementsByGroups[$groupKey] as $field) {
                                    $clonedField = clone $field;
                                    $clonedField->getAttribute()->setGroupNumber($i - 1);
                                    $insertedElements[] = $clonedField;
                                }
                            }
                            /** Вставляем элементы после соответствующего ключа */
                            array_splice($modelElements, $lastElementInGroup + 1, 0, $insertedElements);
                        }
                    }
                }
            }

            /** Установка значений полей */
            $groupJsonRowDataUsed = [];

            foreach ($modelElements as $modelElement) {
                $modelElementName = $modelElement->getName();

                if ($modelElement->getGroup()) {
                    if (!isset($groupJsonRowDataUsed[$modelElementName])) {
                        $groupJsonRowDataUsed[$modelElementName] = 0;
                    } else {
                        $groupJsonRowDataUsed[$modelElementName]++;
                    }
                    $dataValue = $groupJsonRowData[$modelElementName][$groupJsonRowDataUsed[$modelElementName]] ?? null;
                    $modelElement->set($modelElement instanceof Item\Number && !is_null($dataValue) ? (int) $dataValue : $dataValue);
                } else {
                    $modelElement->set($DATA_ITEM[$modelElementName] ?? null);
                }
            }

            /** Построение закладок, если есть */
            if ($this instanceof TabbedEntity) {
                foreach ($modelElements as $modelElementKey => $modelElement) {
                    if ($modelElement instanceof Item\Tab) {
                        $elementIsWritable = $modelElement->checkWritable($act, $contextName);

                        if (!is_null($elementIsWritable)) {
                            $this->addTab($modelElement);
                        }
                    }
                }
            }

            $tabsKeyToName = [];

            if (is_array($this->getTabs()) && count($this->getTabs()) > 0) {
                $RESPONSE_DATA .= '<div class="fraymtabs">
	<ul>
		';

                foreach ($this->getTabs() as $tabkey => $tab) {
                    $RESPONSE_DATA .= '<li><a id="' . $tab->getName() . '">' . $tab->getShownName() . '</a></li>
		';
                    $tabsKeyToName[$tabkey] = $tab->getName();
                }

                $RESPONSE_DATA .= '
	</ul>
';
            }

            /** Отрисовка полей */
            $elementTabindexNum = 1;
            $tabOpen = false;
            $lineNumber = 0;

            foreach ($modelElements as $modelElementKey => $modelElement) {
                $elementIsWritable = $modelElement->checkWritable($act, $contextName);

                if (!is_null($elementIsWritable) && $modelElement->checkVisibility()) {
                    if (
                        $modelElement->getGroupNumber() > 0 &&
                        isset($modelElements[$modelElementKey - 1]) &&
                        $modelElements[$modelElementKey - 1]->getGroupNumber() < $modelElement->getGroupNumber()
                    ) {
                        $RESPONSE_DATA .= '<div class="field_group_separator"></div>';
                    }

                    $FIELD_RESPONSE_DATA = $modelElement->asHTMLWrapped($lineNumber, $elementIsWritable, $elementTabindexNum);
                    $RESPONSE_DATA .= $FIELD_RESPONSE_DATA;

                    if ($FIELD_RESPONSE_DATA !== '') {
                        if ($modelElement->getGroup()) {
                            if (
                                (
                                    !isset($modelElements[$modelElementKey + 1]) ||
                                    $modelElements[$modelElementKey + 1]->getGroup() !== $modelElement->getGroup()
                                ) &&
                                $elementIsWritable
                            ) {
                                $RESPONSE_DATA .= '<button class="nonimportant add_group">' . $GLOBAL_LOCALE['addCapitalized'] . '</button>';
                            }
                        }
                    }
                    $elementTabindexNum += (int) $modelElement->checkDOMVisibility();
                } elseif ($modelElement instanceof Item\Tab && !is_null($elementIsWritable)) {
                    if ($tabOpen) {
                        $RESPONSE_DATA .= '</div>';
                    }
                    $tabOpen = true;
                    $RESPONSE_DATA .= '<div id="fraymtabs-' . $modelElement->getName() . '">';
                }
            }

            if ($tabOpen) {
                $RESPONSE_DATA .= '</div>';
            }

            if (is_array($this->getTabs()) && count($this->getTabs()) > 0) {
                $RESPONSE_DATA .= '</div>';
            }
        }

        $objectLocaleName = $this->getObjectName();

        if ($act === ActEnum::add) {
            if ($modelRights->getAddRight()) {
                $RESPONSE_DATA .= '<div class="control_buttons"><button class="main">' . $GLOBAL_LOCALE['addCapitalized'] . ' ' . $objectLocaleName . '</button></div>
</form>';
            }
        } elseif ($act === ActEnum::edit) {
            if (($modelRights->getChangeRight() || $modelRights->getDeleteRight()) && $modelRights->getViewRight()) {
                $RESPONSE_DATA .= '<div class="control_buttons">';
            }

            if ($modelRights->getChangeRight()) {
                $RESPONSE_DATA .= '<button class="main">' . $GLOBAL_LOCALE['saveCapitalized'] . ' ' . $objectLocaleName . '</button>';
            }

            if ($modelRights->getDeleteRight()) {
                $RESPONSE_DATA .= '<button class="careful" method="DELETE" href="/' . KIND . '/' . CMSVC . '/' . $DATA_ITEM['id'] . '/">' . $GLOBAL_LOCALE['deleteCapitalized'] . ' ' . $objectLocaleName . '</button>';
            }

            if (($modelRights->getChangeRight() || $modelRights->getDeleteRight()) && $modelRights->getViewRight()) {
                $RESPONSE_DATA .= '</div>';
            }

            if (($modelRights->getChangeRight() || $modelRights->getDeleteRight()) && $modelRights->getViewRight()) {
                $RESPONSE_DATA .= '</form>';
            }
        }

        return $RESPONSE_DATA;
    }
}
