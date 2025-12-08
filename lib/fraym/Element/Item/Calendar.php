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

use DateTimeImmutable;
use DateTimeInterface;
use Fraym\Element\Attribute as Attribute;
use Fraym\Element\Item\Trait\CloneTrait;
use Fraym\Helper\{DateHelper, LocaleHelper};
use Fraym\Interface\ElementAttribute;

/** Календарь в формате "дата" или "дата+время" */
class Calendar extends BaseElement
{
    use CloneTrait;

    /** Значение */
    private ?DateTimeImmutable $fieldValue;

    private Attribute\Calendar $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $html = '';

        if ($editableFormat) {
            $value = $this->get();
            $name = $this->name . $this->getLineNumberWrapped();

            $html = '<input type="date' . ($this->getShowDatetime() ? 'time-local' : '') . '" name="' . $name . '" id="' . $name . '" class="dpkr' . ($this->getShowDatetime() ? '_time' : '') . $this->getObligatoryStr() . '" value="' . $value?->format('Y-m-d' . ($this->getShowDatetime() ? ' H:i' : '')) . '" />';
        } else {
            $linkAtBegin = $this->getLinkAt()->getLinkAtBegin();
            $linkAtEnd = $this->getLinkAt()->getLinkAtEnd();
            $value = $this->get();

            if (!is_null($value)) {
                $html = $linkAtBegin . $value->format($this->getShowDatetime() ? 'd.m.Y H:i' : 'd.m.Y') . $linkAtEnd;
            }
        }

        return $html;
    }

    public function asArray(): array
    {
        return array_merge(
            [
                'fieldValue' => $this->get(),
                'defaultValue' => $this->getDefaultValue(),
                'showDatetime' => $this->getShowDatetime(),
            ],
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\Calendar
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\Calendar::class);
        }
        /** @var Attribute\Calendar $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): ?DateTimeImmutable
    {
        $defaultValue = $this->checkDefaultValueInServiceFunctions($this->attribute->defaultValue);

        if (!is_a($defaultValue, 'DateTimeImmutable') && !is_null($defaultValue)) {
            $defaultValue = new DateTimeImmutable($defaultValue);
        }

        return $defaultValue;
    }

    public function get(): ?DateTimeImmutable
    {
        if (!isset($this->fieldValue)) {
            $pureValue = $this->model?->getModelDataFieldValue($this->name);
            $this->fieldValue = DateHelper::setDateToUTC($pureValue);
        }

        return $this->fieldValue ?? $this->getDefaultValue();
    }

    public function getAsAtom(): ?string
    {
        return $this->get()?->format(DateTimeInterface::ATOM);
    }

    public function getAsUsualDate(): ?string
    {
        return $this->get()?->format('d.m.Y');
    }

    public function getAsUsualDateTime(): ?string
    {
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        return $this->get()?->format('d.m.Y ' . $LOCALE_FRAYM['datetime']['at'] . ' H:i');
    }

    public function set(null|DateTimeImmutable|string $fieldValue): static
    {
        if (!is_a($fieldValue, 'DateTimeImmutable') && !is_null($fieldValue)) {
            $fieldValue = new DateTimeImmutable($fieldValue);
        }

        $this->fieldValue = $fieldValue;

        return $this;
    }

    public function getShowDatetime(): ?bool
    {
        return $this->getAttribute()->showDatetime;
    }
}
