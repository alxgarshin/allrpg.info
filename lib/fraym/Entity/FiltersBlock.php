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

use Fraym\Interface\ElementItem;

final class FiltersBlock
{
    /** Предсозданные элементы для фильтрации в рамках блоках
     * @var array<int, ElementItem> $filtersViewItems
     */
    private array $filtersViewItems = [];

    /** Изначальные элементы, по которым и производится фильтрация
     * @var array<int, ElementItem> $filtratedModelItems
     */
    private array $modelItems = [];

    /** @return array<int, ElementItem> */
    public function getFiltersViewItems(): array
    {
        return $this->filtersViewItems;
    }

    public function addFiltersViewItem(ElementItem $filtersViewItem): ElementItem
    {
        return @$this->filtersViewItems[] = $filtersViewItem;
    }

    /** @return array<int, ElementItem> */
    public function getModelItems(): array
    {
        return $this->modelItems;
    }

    public function addModelItem(ElementItem $modelItem): void
    {
        $this->modelItems[] = @$modelItem;
    }
}
