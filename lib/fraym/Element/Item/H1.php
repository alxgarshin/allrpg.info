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

/** Заголовок */
class H1 extends BaseElement
{
    use CloneTrait;

    private Attribute\H1 $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        return '<h1 class="data_h1"' . (!empty($this->name) ? ' id="field_' . $this->name . $this->getLineNumberWrapped() . '"' : '') . '>' . $this->shownName . '</h1>';
    }

    public function asArray(): array
    {
        return array_merge(
            [],
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\H1
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\H1::class);
        }
        /** @var Attribute\H1 $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): mixed
    {
        return null;
    }

    public function get(): mixed
    {
        return null;
    }

    public function set(mixed $fieldValue): static
    {
        return $this;
    }
}
