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

use Fraym\Element\Item\LinkAt;
use Fraym\Interface\ElementAttribute;

abstract class BaseElement implements ElementAttribute
{
    public LinkAt $linkAt;

    public array $basicElementValidators = [];

    public string $lineNumberWrapped {
        get => (is_null($this->lineNumber) ? '' : '[' . $this->lineNumber . ']' . (is_null($this->groupNumber) ? '' : '[' . $this->groupNumber . ']'));
    }

    public string $obligatoryStr {
        get => $this->obligatory ? ' obligatory' : '';
    }

    /**
     * @param array<int, string> $additionalValidators
     */
    public function __construct(
        public ?bool $obligatory = false,
        public ?string $helpClass = null,
        public ?int $group = null,
        public ?int $groupNumber = null {
            get => $this->group ? $this->groupNumber : null;
        },
        public ?bool $noData = null,
        public ?bool $virtual = null,
        public ?string $linkAtBegin = null,
        public ?string $linkAtEnd = null,
        public ?int $lineNumber = 0,
        public ?bool $useInFilters = false,
        public string|array $context = [] {
            set => $this->context = $this->flattenContext($value);
        },
        public array $additionalValidators = [],
        public ?bool $saveHtml = null,
        public ?string $alternativeDataColumnName = null,
        public array $additionalData = [],
        public ?string $customAsHTMLRenderer = null,
    ) {
        $this->linkAt =
            new LinkAt(
                linkAtBegin: $this->linkAtBegin,
                linkAtEnd: $this->linkAtEnd,
            );
    }

    public function checkContext(string $context): bool
    {
        return in_array($context, $this->context);
    }

    public function getValidators(array $additionalValidators): array
    {
        return array_merge($this->basicElementValidators, $additionalValidators);
    }

    private function flattenContext(null|string|array $context): string|array
    {
        if (is_array($context) && ($context[0] ?? false) && is_array($context[0])) {
            $context = array_merge(...$context);
        }

        return $context ?? [];
    }
}
