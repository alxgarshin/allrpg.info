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

namespace Fraym\Element\Attribute;

use Attribute;
use Fraym\Element\Item\MultiselectCreator;
use Fraym\Element\Validator\ObligatoryValidator;
use Fraym\Interface\HasDefaultValue;
use Generator;
use RuntimeException;

/** Множественный выбор */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Multiselect extends BaseElement implements HasDefaultValue
{
    protected array $basicElementValidators = [
        ObligatoryValidator::class,
    ];

    public function __construct(
        /** Значение по умолчанию */
        private null|string|array|Generator $defaultValue = null,

        /** Массив возможных значений: массив или строка с callback функции */
        private null|string|array $values = null,

        /** Заблокированные к изменению значения: массив или строка с callback функции */
        private null|string|array $locked = null,

        /** Одновыборность (radio) из всего массива */
        private bool $one = false,

        /** Массив данных, из которых создаются картинки для соответствующих значений: массив или строка с callback функции */
        private null|string|array $images = null,

        /** Путь до папки картинок $images */
        private ?string $path = null,

        /** Добавить строку внутреннего фильтра выборов */
        private ?bool $search = null,

        /** Механизм пополнения списка путем вписания нового объекта в имеющуюся связанную таблицу */
        private ?MultiselectCreator $creator = null,
        ?bool $obligatory = null,
        ?string $helpClass = null,
        ?int $group = null,
        ?int $groupNumber = null,
        ?bool $noData = null,
        ?bool $virtual = null,
        ?string $linkAtBegin = null,
        ?string $linkAtEnd = null,
        ?int $lineNumber = null,
        ?bool $useInFilters = null,
        string|array $context = [],
        array $additionalValidators = [],
        ?string $alternativeDataColumnName = null,
        array $additionalData = [],
        ?string $customAsHTMLRenderer = null,
    ) {
        if ($this->one && !is_null($this->creator)) {
            throw new RuntimeException(
                "It is not allowed to use MultiselectCreator within a multiselect in a select-one mode. Please, change 'one' to false or remove 'creator'.",
            );
        }

        parent::__construct(
            obligatory: $obligatory,
            helpClass: $helpClass,
            group: $group,
            groupNumber: $groupNumber,
            noData: $noData,
            virtual: $virtual,
            linkAtBegin: $linkAtBegin,
            linkAtEnd: $linkAtEnd,
            lineNumber: $lineNumber,
            useInFilters: $useInFilters,
            context: $context,
            additionalValidators: $this->getValidators($additionalValidators),
            alternativeDataColumnName: $alternativeDataColumnName,
            additionalData: $additionalData,
            customAsHTMLRenderer: $customAsHTMLRenderer,
        );
    }

    public function getDefaultValue(): null|string|array|Generator
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(null|string|array|Generator $defaultValue = null): static
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    public function getValues(): null|string|array
    {
        return $this->values;
    }

    public function setValues(null|string|array|Generator $values): static
    {
        if ($values instanceof Generator) {
            $values = iterator_to_array($values);
        }
        $this->values = $values;

        return $this;
    }

    public function getLocked(): null|string|array
    {
        return $this->locked;
    }

    public function setLocked(null|string|array|Generator $locked): static
    {
        if ($locked instanceof Generator) {
            $locked = iterator_to_array($locked);
        }
        $this->locked = $locked;

        return $this;
    }

    public function getOne(): bool
    {
        return $this->one;
    }

    public function setOne(bool $one): static
    {
        $this->one = $one;

        return $this;
    }

    public function getImages(): null|string|array
    {
        return $this->images;
    }

    public function setImages(null|string|array|Generator $images): static
    {
        if ($images instanceof Generator) {
            $images = iterator_to_array($images);
        }
        $this->images = $images;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getSearch(): ?bool
    {
        return $this->search;
    }

    public function setSearch(?bool $search): static
    {
        $this->search = $search;

        return $this;
    }

    public function getCreator(): ?MultiselectCreator
    {
        return $this->creator;
    }

    public function setCreator(?MultiselectCreator $creator): static
    {
        $this->creator = $creator;

        return $this;
    }
}
