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

/** Большое текстовое поле */
class Textarea extends BaseElement
{
    use CloneTrait;
    use MinMaxChar;

    /** Значение */
    private ?string $fieldValue;

    private Attribute\Textarea $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $name = $this->name . $this->getLineNumberWrapped();
        $value = DataHelper::escapeOutput($this->get(), $this->getAttribute()->getSaveHtml() ? EscapeModeEnum::plainHTML : EscapeModeEnum::forHTML);
        $rows = $this->getRows();

        if ($editableFormat) {
            $html = '<textarea name="' . $name . '"' .
                (!is_null($rows) && $rows > 0 ? ' rows="' . $rows . '"' : '') .
                (!is_null($this->getMinchar()) ? ' minlength="' . $this->getMinchar() . '"' : '') .
                (!is_null($this->getMaxchar()) ? ' maxlength="' . $this->getMaxchar() . '"' : '') .
                ' class="inputtextarea' . $this->getObligatoryStr() . '">' . $value . '</textarea>';
        } else {
            $html = DataHelper::escapeOutput($this->get(), EscapeModeEnum::forHTMLforceNewLines);
        }

        return (string) $html;
    }

    public function asArray(): array
    {
        return array_merge(
            [
                'defaultValue' => $this->getDefaultValue(),
                'fieldValue' => $this->get(),
                'rows' => $this->getRows(),
            ],
            $this->asArrayMinMaxChar(),
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\Textarea
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Textarea::class);
        }
        /** @var Attribute\Textarea $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->checkDefaultValueInServiceFunctions($this->attribute->getDefaultValue());
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

    public function set(?string $fieldValue): static
    {
        $this->fieldValue = $fieldValue;

        return $this;
    }

    public function getRows(): ?int
    {
        return $this->getAttribute()->getRows();
    }
}
