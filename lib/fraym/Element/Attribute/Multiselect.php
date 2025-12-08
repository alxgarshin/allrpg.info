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
use InvalidArgumentException;
use RuntimeException;

/** Множественный выбор */
/** @implements HasDefaultValue<null|string|array|Generator> */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Multiselect extends BaseElement implements HasDefaultValue
{
    public array $basicElementValidators = [
        ObligatoryValidator::class,
    ];

    /** Значение по умолчанию */
    public mixed $defaultValue {
        get => $this->_val;
        set {
            if (!is_null($value) && !is_string($value) && !is_array($value) && !$value instanceof Generator) {
                throw new InvalidArgumentException('Wrong defaultValue type');
            }

            $this->_val = $value;
        }
    }

    private null|string|array|Generator $_val = null;

    public function __construct(
        mixed $defaultValue = null,

        /** Массив возможных значений: массив или строка с callback функции */
        public null|string|array $values = null {
            set(null|string|array|Generator $values) {
                if ($values instanceof Generator) {
                    $values = iterator_to_array($values);
                }

                $this->values = $values;
            }
        },

        /** Заблокированные к изменению значения: массив или строка с callback функции */
        public null|string|array $locked = null {
            set(null|string|array|Generator $locked) {
                if ($locked instanceof Generator) {
                    $locked = iterator_to_array($locked);
                }

                $this->locked = $locked;
            }
        },

        /** Одновыборность (radio) из всего массива */
        public bool $one = false,

        /** Массив данных, из которых создаются картинки для соответствующих значений: массив или строка с callback функции */
        public null|string|array $images = null {
            set(null|string|array|Generator $images) {
                if ($images instanceof Generator) {
                    $images = iterator_to_array($images);
                }

                $this->images = $images;
            }
        },

        /** Путь до папки картинок $images */
        public ?string $path = null,

        /** Добавить строку внутреннего фильтра выборов */
        public ?bool $search = null,

        /** Механизм пополнения списка путем вписания нового объекта в имеющуюся связанную таблицу */
        public ?MultiselectCreator $creator = null,
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

        $this->defaultValue = $defaultValue;
    }
}
