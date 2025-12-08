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
use InvalidArgumentException;

/** WYSIWYG */
/** @implements HasDefaultValue<null|string> */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Wysiwyg extends BaseElement implements InterfaceMinMaxChar, HasDefaultValue
{
    use MinMaxChar;

    public array $basicElementValidators = [
        ObligatoryValidator::class,
        MinMaxCharValidator::class,
    ];

    /** Значение по умолчанию */
    public mixed $defaultValue {
        get => $this->_val;
        set {
            if (!is_null($value) && !is_string($value)) {
                throw new InvalidArgumentException('Wrong defaultValue type');
            }

            $this->_val = $value;
        }
    }

    private ?string $_val = null;

    public function __construct(
        mixed $defaultValue = null,
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
            saveHtml: true,
            alternativeDataColumnName: $alternativeDataColumnName,
            additionalData: $additionalData,
            customAsHTMLRenderer: $customAsHTMLRenderer,
        );

        $this->minChar = $minChar;
        $this->maxChar = $maxChar;
        $this->defaultValue = $defaultValue;
    }
}
