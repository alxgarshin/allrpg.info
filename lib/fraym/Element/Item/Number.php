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
use Fraym\Enum\EscapeModeEnum;
use Fraym\Helper\DataHelper;
use Fraym\Interface\ElementAttribute;

/** Число */
class Number extends BaseElement
{
    use CloneTrait;

    /** Значение */
    private ?int $fieldValue;

    private Attribute\Number $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $name = $this->name . $this->getLineNumberWrapped();
        $value = $this->get();

        if ($editableFormat) {
            $html = '<input type="text" name="' . $name . '" value="' . DataHelper::escapeOutput($value, EscapeModeEnum::forAttributes) . '" class="inputnum' .
                $this->getObligatoryStr() . '" />';
        } else {
            $html = $this->getLinkAt()->getLinkAtBegin() . DataHelper::escapeOutput($value) . $this->getLinkAt()->getLinkAtEnd();
        }

        return $html;
    }

    public function asArray(): array
    {
        return array_merge(
            [
                'defaultValue' => $this->getDefaultValue(),
                'fieldValue' => $this->get(),
                'round' => $this->getRound(),
            ],
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\Number
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Number::class);
        }
        /** @var Attribute\Number $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): ?int
    {
        $defaultValue = $this->checkDefaultValueInServiceFunctions($this->attribute->defaultValue);

        if (!is_null($defaultValue)) {
            $defaultValue = (int) $defaultValue;
        }

        return $defaultValue;
    }

    public function get(): ?int
    {
        if (!isset($this->fieldValue)) {
            $pureValue = $this->model?->getModelDataFieldValue($this->name);

            if (!is_null($pureValue) && trim((string) $pureValue) !== '') {
                $this->fieldValue = (int) trim((string) $pureValue);
            } else {
                $this->fieldValue = null;
            }
        }

        return $this->fieldValue ?? $this->getDefaultValue();
    }

    public function set(int|string|null $fieldValue): static
    {
        if (is_string($fieldValue)) {
            $fieldValue = (int) $fieldValue;
        }

        $this->fieldValue = $fieldValue;

        return $this;
    }

    public function getRound(): ?bool
    {
        return $this->getAttribute()->round;
    }
}
