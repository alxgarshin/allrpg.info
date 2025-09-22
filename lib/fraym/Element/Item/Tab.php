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
use Fraym\Entity\TableEntity;
use Fraym\Interface\ElementAttribute;

/** Вкладка */
class Tab extends BaseElement
{
    use CloneTrait;

    private Attribute\Tab $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $entity = $this->entity;

        if ($entity instanceof TableEntity) {
            $tabkey = 0;
            $tabs = $entity->getTabs();

            foreach ($tabs as $key => $tab) {
                if ($this->name === $tab->getName()) {
                    $tabkey = $key;
                    break;
                }
            }

            return '<li><a id="' . $this->name . '">' . $this->shownName . '</a></li>';
        }

        return '';
    }

    public function asArray(): array
    {
        return array_merge(
            [],
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\Tab
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Tab::class);
        }
        /** @var Attribute\Tab $attribute */
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
