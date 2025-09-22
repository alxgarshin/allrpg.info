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
use Fraym\Element\Validator\ObligatoryValidator;
use Fraym\Interface\HasDefaultValue;

/** Число */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Number extends BaseElement implements HasDefaultValue
{
    protected array $basicElementValidators = [
        ObligatoryValidator::class,
    ];

    public function __construct(
        /** Значение по умолчанию */
        private null|string|int $defaultValue = null,

        /** Принудительное округление чисел в данном поле */
        private bool $round = false,
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

    public function getRound(): ?bool
    {
        return $this->round;
    }

    public function setRound(?bool $round): static
    {
        $this->round = $round;

        return $this;
    }
}
