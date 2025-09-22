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

/** Календарь в формате "дата" или "дата+время" */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Calendar extends BaseElement implements HasDefaultValue
{
    protected array $basicElementValidators = [
        ObligatoryValidator::class,
    ];

    public function __construct(
        /** Значение по умолчанию */
        private null|string|DateTimeImmutable $defaultValue = null,

        /** Показывать простой календарь или дата+время? */
        private ?bool $showDatetime = null,
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

    public function getDefaultValue(): null|string|DateTimeImmutable
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(null|string|DateTimeImmutable $defaultValue = null): static
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    public function getShowDatetime(): ?bool
    {
        return $this->showDatetime;
    }

    public function setShowDatetime(?bool $showDatetime): static
    {
        $this->showDatetime = $showDatetime;

        return $this;
    }
}
