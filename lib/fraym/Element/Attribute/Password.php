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
use Fraym\Element\Validator\{MinMaxCharValidator, ObligatoryValidator, RepeatPasswordValidator};
use Fraym\Interface\MinMaxChar as InterfaceMinMaxChar;

/** Пароль */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Password extends BaseElement implements InterfaceMinMaxChar
{
    use MinMaxChar;

    protected array $basicElementValidators = [
        MinMaxCharValidator::class,
        ObligatoryValidator::class,
        RepeatPasswordValidator::class,
    ];

    public function __construct(
        /** Имя еще одного элемента класса Password для функции: "Введите пароль повторно" */
        private ?string $repeatPasswordFieldName = null,
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
            context: $context,
            additionalValidators: $this->getValidators($additionalValidators),
            alternativeDataColumnName: $alternativeDataColumnName,
            additionalData: $additionalData,
            customAsHTMLRenderer: $customAsHTMLRenderer,
        );
        $this->minChar = $minChar;
        $this->maxChar = $maxChar;
    }

    public function getRepeatPasswordFieldName(): ?string
    {
        return $this->repeatPasswordFieldName;
    }

    public function setRepeatPasswordFieldName(?string $repeatPasswordFieldName): static
    {
        $this->repeatPasswordFieldName = $repeatPasswordFieldName;

        return $this;
    }
}
