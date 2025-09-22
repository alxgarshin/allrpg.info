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
use Fraym\Element\Attribute\Trait\{MinMaxChar};
use Fraym\Element\Validator\{MinMaxCharValidator, ObligatoryValidator};
use Fraym\Interface\{HasDefaultValue, MinMaxChar as InterfaceMinMaxChar};

/** Большое текстовое поле */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Textarea extends BaseElement implements InterfaceMinMaxChar, HasDefaultValue
{
    use MinMaxChar;

    protected array $basicElementValidators = [
        ObligatoryValidator::class,
        MinMaxCharValidator::class,
    ];

    public function __construct(
        /** Значение по умолчанию */
        private ?string $defaultValue = null,

        /** Количество рядов textarea */
        private ?int $rows = null,
        ?int $minChar = null,
        ?int $maxChar = null,
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
        ?bool $saveHtml = null,
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
            saveHtml: $saveHtml,
            alternativeDataColumnName: $alternativeDataColumnName,
            additionalData: $additionalData,
            customAsHTMLRenderer: $customAsHTMLRenderer,
        );
        $this->minChar = $minChar;
        $this->maxChar = $maxChar;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue = null): static
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    public function getRows(): ?int
    {
        return $this->rows;
    }

    public function setRows(?int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }
}
