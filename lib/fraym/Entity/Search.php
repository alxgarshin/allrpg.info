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

namespace Fraym\Entity;

use Attribute;

/** Права */
#[Attribute(Attribute::TARGET_CLASS)]
class Search
{
    public function __construct(
        /** @param string[] $searchFields Имена всех элементов, по которым можно искать */
        private array $searchFields,
    ) {
    }

    public function getSearchFields(): array
    {
        return $this->searchFields;
    }

    public function setSearchFields(array $searchFields): static
    {
        $this->searchFields = $searchFields;

        return $this;
    }
}
