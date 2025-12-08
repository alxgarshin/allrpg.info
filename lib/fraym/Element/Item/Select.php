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

namespace Fraym\Element\Item;

use Fraym\BaseObject\BaseHelper;
use Fraym\Element\Attribute as Attribute;
use Fraym\Element\Item\Trait\CloneTrait;
use Fraym\Helper\{LocaleHelper, ObjectsHelper, TextHelper};
use Fraym\Interface\ElementAttribute;

/** Выпадающий список */
class Select extends BaseElement
{
    use CloneTrait;

    /** Значение */
    private int|string|null $fieldValue;

    private Attribute\Select $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $html = '';
        $name = $this->name . $this->getLineNumberWrapped();
        $value = $this->get();
        $values = $this->getValues();
        $locked = $this->getLocked();
        $helper = $this->getHelper();
        $LOCALE = LocaleHelper::getLocale(['fraym']);

        if (!is_array($values)) {
            $values = [];
        }

        foreach ($values as $key => $data) {
            $data[0] = (string) $data[0];
            $values[$key] = $data;
        }

        $value = is_null($value) ? null : (string) $value;

        if ($removeHtmlFromValue) {
            foreach ($values as $key => $data) {
                $data[1] = strip_tags($data[1]);
                $values[$key] = $data;
            }
        }

        if ($editableFormat) {
            if (!is_null($helper)) {
                $html .= '<input type="text" id="helper_' . $name . '" class="inputtext helper' . $this->getObligatoryStr() . '" placehold="' . $LOCALE['classes']['helper']['search_text'] . '" autocomplete="off" action="/' . TextHelper::camelCaseToSnakeCase(ObjectsHelper::getClassShortNameFromCMSVCObject($helper)) . '/" target="' . $name . '" />';
            }

            $html .= '<select name="' . $name . '" class="inputselect' .
                $this->getObligatoryStr() . '"><option value="" class="option_bold">' .
                $LOCALE['classes'][(is_null($helper) ? 'select' : 'helper')]['choose'] . '</option>';

            if (is_null($helper)) {
                foreach ($values as $data) {
                    $html .= '<option value="' . $data[0] . '"' . ($data[0] === $value ? ' selected' : '');

                    if (is_array($locked[0] ?? false)) {
                        foreach ($locked as $locked_value) {
                            if ($locked_value[0] === $data[0]) {
                                $html .= ' disabled';
                            }
                        }
                    } elseif (is_array($locked) && in_array($data[0], $locked, true)) {
                        $html .= ' disabled';
                    }
                    $html .= '>';

                    if (($data[2] ?? 0) > 0) {
                        $html .= str_repeat("&emsp;&emsp;", $data[2]);
                    }
                    $html .= $data[1] . '</option>';
                }
            } elseif (!is_null($value)) {
                $html .= '<option value="' . $value . '" selected>' . $helper->printOut($value) . '</option>';
            }
            $html .= '</select>';
        } else {
            $linkAtEnd = $this->getLinkAt()->getLinkAtEnd();
            $linkAtBeginWithValue = $this->getLinkAt()->getLinkAtBeginWithValue($value);

            if (is_null($helper)) {
                foreach ($values as $data) {
                    if ($data[0] === $value) {
                        $html .= $linkAtBeginWithValue . $data[1] . $linkAtEnd;
                        break;
                    }
                }
            } else {
                $html .= $linkAtBeginWithValue . $helper->printOut($value) . $linkAtEnd;
            }
        }

        return $html;
    }

    public function asArray(): array
    {
        return array_merge(
            [
                'defaultValue' => $this->getDefaultValue(),
                'fieldValue' => $this->get(),
                'values' => $this->getValues(),
                'locked' => $this->getLocked(),
                'helper' => ObjectsHelper::getClassShortName($this->getHelper()::class),
            ],
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\Select
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Select::class);
        }
        /** @var Attribute\Select $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): int|string|null
    {
        return $this->checkDefaultValueInServiceFunctions($this->attribute->defaultValue);
    }

    public function get(): int|string|null
    {
        if (!isset($this->fieldValue)) {
            $pureValue = $this->model?->getModelDataFieldValue($this->name);

            if (!is_null($pureValue)) {
                $this->fieldValue = $pureValue;
            } else {
                $this->fieldValue = null;
            }
        }

        return $this->fieldValue ?? $this->getDefaultValue();
    }

    public function getAsInt(): ?int
    {
        $value = $this->get();

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    public function set(int|string|null $fieldValue): static
    {
        $this->fieldValue = $fieldValue;

        return $this;
    }

    public function getValues(): null|string|array
    {
        return $this->getAttribute()->values;
    }

    public function getLocked(): ?array
    {
        return $this->getAttribute()->locked;
    }

    public function getHelper(): ?BaseHelper
    {
        return $this->getAttribute()->helper;
    }
}
