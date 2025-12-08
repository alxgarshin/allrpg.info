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

use Fraym\Element\Attribute as Attribute;
use Fraym\Element\Item\Trait\CloneTrait;
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\ElementAttribute;

/** Множественный выбор */
class Multiselect extends BaseElement
{
    use CloneTrait;

    /** Значение */
    private array $fieldValue;

    private Attribute\Multiselect $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $html = '';
        $name = $this->name . $this->getLineNumberWrapped();
        $value = $this->get();
        $values = $this->getValues();
        $images = $this->getImages();
        $locked = $this->getLocked();
        $LOCALE = LocaleHelper::getLocale(['fraym']);

        if (!is_array($values)) {
            $values = [];
        }

        if ($removeHtmlFromValue) {
            foreach ($values as $key => $data) {
                $data[1] = strip_tags($data[1]);
                $values[$key] = $data;
            }
        }

        if ($editableFormat) {
            $LOC = $LOCALE['classes']['multiselect'];

            $html .= '<div class="dropfield' . $this->getObligatoryStr() . '" id="selected_' . $name . '">';
            $allSelected = true;

            if (count($value) > 0) {
                if ($this->getOne()) {
                    if (in_array(0, $value)) {
                        $html .= '<div class="options">' . $LOC['do_not_choose'] . '<a rel="' . $name . '[]"></a></div>';
                    }
                }

                $possible_duplicates = [];

                foreach ($values as $key => $data) {
                    if ((in_array($data[0], $value) && !in_array($data[0], $possible_duplicates)) || ($value[$data[0]] ?? '') === 'on') {
                        $html .= '<div class="options">';

                        if (!is_null($images)) {
                            if (isset($images[$key]) && $images[$key] !== '') {
                                if (!str_contains($images[$key][1], '<img')) {
                                    $html .= '<img src="' . $this->getPath() . $images[$key][1] . '" />';
                                } else {
                                    $html .= $images[$key][1];
                                }
                            }
                        }
                        $html .= $data[1] . '<a rel="' . $name . '[' . $data[0] . ']"></a></div>';
                        $possible_duplicates[] = $data[0];
                    } else {
                        $allSelected = false;
                    }
                }
            } else {
                $allSelected = false;
                $html .= '<div>' . $LOC['choose'] . '</div>';
            }
            $html .= '</div>';
            $html .= '<div class="dropfield2" id="choice_' . $name . '">';

            if ($this->getSearch()) {
                $html .= '<div class="dropfield2_search">' .
                    (!empty($this->getCreator()) ? '<a class="create">' . $LOC['add'] . '</a>' : '') . '<input type="text" id="search_' . $name . '" placehold="' .
                    (!empty($this->getCreator()) ? $LOC['for_search_or_create_input_text_here'] : $LOC['for_search_input_text_here']) . '"></div>';
            }

            if (!$this->getOne()) {
                if ($allSelected) {
                    $html .= '<div class="dropfield2_selecter dropfield2_deselect_all"><a>' . $LOC['deselect_all'] . '</a></div>';
                } else {
                    $html .= '<div class="dropfield2_selecter dropfield2_select_all"><a>' . $LOC['select_all'] . '</a></div>';
                }
            }

            if ($this->getOne() && !$this->getObligatory()) {
                $html .= '<div class="dropfield2_field" id="dropfield2_field_' . $name . '[0]"><input type="radio" name="' . $name . '" id="' . $name . '[0]" value="" class="inputradio"' .
                    (in_array(0, $value) ? ' checked' : '') .
                    '><label for="' . $name . '[0]">' . $LOC['do_not_choose'] . '</label></div>';
            }

            foreach ($values as $key => $data) {
                $html .= '<div class="dropfield2_field" id="dropfield2_field_' . $name . '[' . $data[0] . ']"';

                if (isset($data[2]) && $data[2] > 0) {
                    $html .= ' style="padding-left: ' . ($data[2] * 2) . 'em;" level="' . $data[2] . '"';
                }

                if ($this->getOne()) {
                    $html .= '><input type="radio" name="' . $name . '" id="' . $name . '[' . $data[0] . ']" value="' . $data[0] . '" class="inputradio"';
                } else {
                    $html .= '><input type="checkbox" name="' . $name . '[' . $data[0] . ']" id="' . $name . '[' . $data[0] . ']" class="inputcheckbox"';
                }

                if (in_array($data[0], $value) || ($value[$data[0]] ?? '') === 'on') {
                    $html .= ' checked';
                }

                $locked_value = false;

                if ($locked[0] ?? false) {
                    foreach ($locked as $locked_array_value) {
                        if ((is_array($locked_array_value) && $locked_array_value[0] === $data[0]) || ((is_int($locked_array_value) || is_string($locked_array_value)) && $locked_array_value === $data[0])) {
                            $html .= ' disabled';
                            $locked_value = true;
                        }
                    }
                } elseif (is_array($locked) && in_array($data[0], $locked)) {
                    $html .= ' disabled';
                    $locked_value = true;
                }
                $html .= '><label for="' . $name . '[' . $data[0] . ']">';

                if (!is_null($images)) {
                    if (isset($images[$key]) && $images[$key] !== '') {
                        if (!str_contains($images[$key][1], '<img')) {
                            $html .= '<img src="' . $this->getPath() . $images[$key][1] . '" />';
                        } else {
                            $html .= $images[$key][1];
                        }
                    }
                }
                $html .= $data[1] . '</label></div>';

                if ($locked_value && in_array($data[0], $value)) {
                    $html .= '<input type="hidden" name="' . $name . '[' . $data[0] . ']" value="on">';
                }
            }
            $html .= '</div>';
        } else {
            $linkAtEnd = $this->getLinkAt()->getLinkAtEnd();
            $first_string = true;

            foreach ($values as $key => $data) {
                if (in_array($data[0], $value)) {
                    if ($first_string) {
                        $first_string = false;
                    } elseif (!$this->getOne()) {
                        $html .= '<br />';
                    }
                    $linkAtBeginWithValue = $this->getLinkAt()->getLinkAtBeginWithValue($data[0]);
                    $html .= $linkAtBeginWithValue;

                    if (!is_null($images)) {
                        if (isset($images[$key]) && $images[$key] !== '') {
                            if (!str_contains($images[$key][1], '<img')) {
                                $html .= '<img src="' . $this->getPath() . $images[$key][1] . '" />' . $linkAtEnd . $linkAtBeginWithValue;
                            } else {
                                $html .= $images[$key][1] . $linkAtEnd . $linkAtBeginWithValue;
                            }
                        }
                    }
                    $html .= $data[1] . $linkAtEnd;
                }
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
                'one' => $this->getOne(),
                'images' => $this->getImages(),
                'path' => $this->getPath(),
                'search' => $this->getSearch(),
                'creator' => $this->getCreator()->asArray(),
            ],
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\Multiselect
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Multiselect::class);
        }
        /** @var Attribute\Multiselect $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): array
    {
        $defaultValue = $this->checkDefaultValueInServiceFunctions($this->attribute->defaultValue);

        return is_array($defaultValue) ? $defaultValue : ($defaultValue === null ? [] : [$defaultValue]);
    }

    public function get(): array
    {
        if (!isset($this->fieldValue)) {
            $pureValue = $this->model?->getModelDataFieldValue($this->name);

            if (!is_null($pureValue)) {
                if (is_string($pureValue)) {
                    $pureValue = DataHelper::multiselectToArray($pureValue);
                } elseif (!is_array(($pureValue))) {
                    $pureValue = [$pureValue];
                }
                $this->fieldValue = $pureValue;
            } else {
                $this->fieldValue = [];
            }
        }

        return count($this->fieldValue) > 0 ? $this->fieldValue : $this->getDefaultValue();
    }

    public function set(null|array|string|int $fieldValue): static
    {
        if (is_string($fieldValue) || is_int($fieldValue)) {
            $fieldValue = DataHelper::multiselectToArray((string) $fieldValue);
        }

        if (!is_null($fieldValue)) {
            foreach ($fieldValue as $key => $value) {
                if (trim((string) $value) === '') {
                    unset($fieldValue[$key]);
                }
            }
        } else {
            $fieldValue = [];
        }
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

    public function getOne(): bool
    {
        return $this->getAttribute()->one;
    }

    public function getImages(): ?array
    {
        return $this->getAttribute()->images;
    }

    public function getPath(): ?string
    {
        return $this->getAttribute()->path;
    }

    public function getSearch(): ?bool
    {
        return $this->getAttribute()->search;
    }

    public function getCreator(): ?MultiselectCreator
    {
        return $this->getAttribute()->creator;
    }
}
