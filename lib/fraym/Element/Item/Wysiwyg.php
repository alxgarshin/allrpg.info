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

/** WYSIWYG */
class Wysiwyg extends BaseElement
{
    use CloneTrait;
    use MinMaxChar;

    /** Значение */
    private ?string $fieldValue;

    private Attribute\Wysiwyg $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $name = $this->name . $this->getLineNumberWrapped();
        $value = DataHelper::escapeOutput($this->get(), EscapeModeEnum::plainHTML);

        if ($editableFormat) {
            $html = '<div class="wysiwyg-editor' . $this->getObligatoryStr() . '" name="' . $name . '" id="' . $name . '">' . $value . '</div>';
        } else {
            $html = $value ?? '';
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

    public function getAttribute(): Attribute\Wysiwyg
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Wysiwyg::class);
        }
        /** @var Attribute\Wysiwyg $attribute */
        $this->attribute = $attribute;
        $this->attribute->setSaveHtml(true);

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
}
