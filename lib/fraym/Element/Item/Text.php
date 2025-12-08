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
use Fraym\Element\Item\Trait\{CloneTrait, MinMaxChar};
use Fraym\Enum\EscapeModeEnum;
use Fraym\Helper\DataHelper;
use Fraym\Interface\ElementAttribute;

/** Текстовая строка */
class Text extends BaseElement
{
    use CloneTrait;
    use MinMaxChar;

    /** Значение */
    private ?string $fieldValue;

    private Attribute\Text $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $name = $this->name . $this->getLineNumberWrapped();
        $value = $this->get();

        if ($editableFormat) {
            $html = '<input type="text" name="' . $name . '" value="' . DataHelper::escapeOutput($value, EscapeModeEnum::forAttributes) . '"' .
                (!is_null($this->getMinchar()) ? ' minlength="' . $this->getMinchar() . '"' : '') .
                (!is_null($this->getMaxchar()) ? ' maxlength="' . $this->getMaxchar() . '"' : '') .
                ' class="inputtext' . $this->getObligatoryStr() . '"';

            if ($this instanceof Login) {
                $html .= ' autocomplete="off"';
            }
            $html .= ' />';
        } else {
            $html = $this->getLinkAt()->getLinkAtBegin() .
                DataHelper::escapeOutput($value, $this->getAttribute()->saveHtml ? EscapeModeEnum::plainHTML : EscapeModeEnum::forHTML) .
                $this->getLinkAt()->getLinkAtEnd();
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
            $this->asArrayMinMaxChar(),
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\Text
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Text::class);
        }
        /** @var Attribute\Text $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->checkDefaultValueInServiceFunctions($this->attribute->defaultValue);
    }

    public function get(): ?string
    {
        if (!isset($this->fieldValue)) {
            $pureValue = $this->model?->getModelDataFieldValue($this->name);

            if (!is_null($pureValue) && trim($pureValue) !== '') {
                $this->fieldValue = trim($pureValue);
            } else {
                $this->fieldValue = null;
            }
        }

        return $this->fieldValue ?? $this->getDefaultValue();
    }

    public function set(mixed $fieldValue): static
    {
        $this->fieldValue = !is_null($fieldValue) ? (string) $fieldValue : null;

        return $this;
    }
}
