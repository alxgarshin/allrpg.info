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
use Fraym\Interface\ElementAttribute;

/** Галочка */
class Checkbox extends BaseElement
{
    use CloneTrait;

    /** Значение */
    private ?bool $fieldValue;

    private Attribute\Checkbox $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $name = $this->name . $this->getLineNumberWrapped();

        if ($editableFormat) {
            $html = '<input type="checkbox" name="' . $name . '" id="' . $name . '" class="inputcheckbox' . $this->getObligatoryStr() . '"' .
                $this->getFieldValueStr() . ' /><label for="' . $name . '"></label>';
        } else {
            $html = $this->get() ? '<span class="sbi sbi-check"></span>' : '<span class="sbi sbi-times"></span>';
        }

        return $html;
    }

    public function asArray(): array
    {
        return array_merge(
            [
                'defaultValue' => $this->getDefaultValue(),
                'fieldValue' => $this->get(),
            ],
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\Checkbox
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Checkbox::class);
        }
        /** @var Attribute\Checkbox $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): ?bool
    {
        return $this->checkDefaultValueInServiceFunctions($this->attribute->getDefaultValue());
    }

    public function get(): ?bool
    {
        if (!isset($this->fieldValue)) {
            $pureValue = $this->model?->getModelDataFieldValue($this->name);

            if (!is_null($pureValue) && trim($pureValue) !== '') {
                $this->fieldValue = (bool) trim($pureValue);
            } else {
                $this->fieldValue = null;
            }
        }

        return $this->fieldValue ?? $this->getDefaultValue();
    }

    public function getFieldValueStr(): string
    {
        return $this->get() ? ' checked' : '';
    }

    public function set(bool|string|null $fieldValue): static
    {
        $this->fieldValue = is_string($fieldValue) ? (bool) $fieldValue : $fieldValue;

        return $this;
    }
}
