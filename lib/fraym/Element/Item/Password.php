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
use Fraym\Interface\ElementAttribute;

/** Пароль */
class Password extends BaseElement
{
    use CloneTrait;
    use MinMaxChar;

    /** Значение */
    private ?string $fieldValue;

    private Attribute\Password $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $name = $this->name . $this->getLineNumberWrapped();

        if ($editableFormat) {
            $html = '<input type="password" autocomplete="new-password" name="' . $name . '"' .
                (!is_null($this->getMinchar()) ? ' minlength="' . $this->getMinchar() . '"' : '') .
                (!is_null($this->getMaxchar()) ? ' maxlength="' . $this->getMaxchar() . '"' : '') .
                ' class="inputtext' . $this->getObligatoryStr() . '" />';
        } else {
            $html = '******';
        }

        return $html;
    }

    public function asArray(): array
    {
        return array_merge(
            [
                'fieldValue' => '******',
            ],
            $this->asArrayMinMaxChar(),
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\Password
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Password::class);
        }
        /** @var Attribute\Password $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): mixed
    {
        return null;
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

        return $this->fieldValue;
    }

    public function set(?string $fieldValue): static
    {
        $this->fieldValue = $fieldValue;

        return $this;
    }
}
