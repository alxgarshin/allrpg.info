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
use Fraym\Helper\DataHelper;
use Fraym\Interface\ElementAttribute;

/** Скрытое поле */
class Hidden extends BaseElement
{
    use CloneTrait;

    /** Значение */
    private null|int|string $fieldValue;

    private Attribute\Hidden $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        return '<input type="hidden" name="' . $this->name . $this->getLineNumberWrapped() . '" value="' . DataHelper::escapeOutput($this->get()) . '" />';
    }

    public function asArray(): array
    {
        return array_merge(
            [
                'defaultValue' => $this->getDefaultValue(),
            ],
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\Hidden
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Hidden::class);
        }
        /** @var Attribute\Hidden $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): null|int|string
    {
        return $this->checkDefaultValueInServiceFunctions($this->attribute->defaultValue);
    }

    public function get(): null|int|string
    {
        if (!isset($this->fieldValue)) {
            $pureValue = $this->model?->getModelDataFieldValue($this->name);

            if (!is_null($pureValue) && trim((string) $pureValue) !== '') {
                $this->fieldValue = trim((string) $pureValue);
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

    public function set(null|int|string $fieldValue): static
    {
        $this->fieldValue = $fieldValue;

        return $this;
    }
}
