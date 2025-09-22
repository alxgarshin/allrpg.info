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

/** Выпадающий список */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Select extends BaseElement implements HasDefaultValue
{
    protected array $basicElementValidators = [
        ObligatoryValidator::class,
    ];

    public function __construct(
        /** Значение по умолчанию */
        private null|string|int $defaultValue = null,

        /** Массив возможных значений: массив или строка с callback функции */
        private null|string|array $values = null,

        /** Заблокированные к изменению значения: массив или строка с callback функции */
        private null|string|array $locked = null,

        /** Механизм динамического поиска списка значений на основе ввода пользователя */
        private ?BaseHelper $helper = null,
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
    }

    public function getDefaultValue(): null|string|int
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(null|string|int $defaultValue = null): static
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

    public function setLocked(null|string|array $locked): static
    {
        $this->locked = $locked;

        return $this;
    }

    public function getHelper(): ?BaseHelper
    {
        return $this->helper;
    }

    public function setHelper(?BaseHelper $helper): static
    {
        $this->helper = $helper;

        return $this;
    }
}
