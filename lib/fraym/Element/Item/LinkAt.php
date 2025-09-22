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

namespace Fraym\Element\Item;

/** Обертка для создания ссылок вокруг значения элемента */
class LinkAt
{
    public function __construct(
        protected ?string $linkAtBegin,
        protected ?string $linkAtEnd,
    ) {
    }

    public function getLinkAtBegin(): ?string
    {
        return $this->linkAtBegin;
    }

    public function getLinkAtBeginWithValue(int|string|null $value): ?string
    {
        $linkAtBegin = $this->linkAtBegin;

        if (!is_null($linkAtBegin)) {
            if (mb_stripos($linkAtBegin, '{value}') !== false) {
                $linkAtBegin = str_ireplace('{value}', (string) $value, $linkAtBegin);
            }
        }

        return $linkAtBegin;
    }

    public function setLinkAtBegin(?string $linkAtBegin): static
    {
        $this->linkAtBegin = $linkAtBegin;

        return $this;
    }

    public function getLinkAtEnd(): ?string
    {
        return $this->linkAtEnd;
    }

    public function setLinkAtEnd(?string $linkAtEnd): static
    {
        $this->linkAtEnd = $linkAtEnd;

        return $this;
    }
}
