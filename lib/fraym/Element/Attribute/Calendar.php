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
use DateTimeImmutable;
use Fraym\Element\Validator\ObligatoryValidator;
use Fraym\Interface\HasDefaultValue;
use InvalidArgumentException;

/** Календарь в формате "дата" или "дата+время" */
/** @implements HasDefaultValue<null|string|DateTimeImmutable> */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Calendar extends BaseElement implements HasDefaultValue
{
    public array $basicElementValidators = [
        ObligatoryValidator::class,
    ];

    /** Значение по умолчанию */
    public mixed $defaultValue {
        get => $this->_val;
        set {
            if (!is_null($value) && !is_string($value) && !$value instanceof DateTimeImmutable) {
                throw new InvalidArgumentException('Wrong defaultValue type');
            }

            $this->_val = $value;
        }
    }

    private null|string|DateTimeImmutable $_val = null;

    public function __construct(
        mixed $defaultValue = null,
        /** Показывать простой календарь или дата+время? */
        public ?bool $showDatetime = null,
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
