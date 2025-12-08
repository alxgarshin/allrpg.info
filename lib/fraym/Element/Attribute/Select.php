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
use Fraym\BaseObject\BaseHelper;
use Fraym\Element\Validator\ObligatoryValidator;
use Fraym\Interface\HasDefaultValue;
use Generator;
use InvalidArgumentException;

/** Выпадающий список */
/** @implements HasDefaultValue<null|string|int> */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Select extends BaseElement implements HasDefaultValue
{
    public array $basicElementValidators = [
        ObligatoryValidator::class,
    ];

    /** Значение по умолчанию */
    public mixed $defaultValue {
        get => $this->_val;
        set {
            if (!is_null($value) && !is_string($value) && !is_int($value)) {
                throw new InvalidArgumentException('Wrong defaultValue type');
            }

            $this->_val = $value;
        }
    }

    private null|string|int $_val = null;

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
        public null|string|array $locked = null,

        /** Механизм динамического поиска списка значений на основе ввода пользователя */
        public ?BaseHelper $helper = null,
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
