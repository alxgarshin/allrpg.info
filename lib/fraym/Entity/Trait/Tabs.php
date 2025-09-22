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

namespace Fraym\Entity\Trait;

use Fraym\Element\Item\Tab;

trait Tabs
{
    /** @var Tab[] Список закладок из элементов Tab, прикрепленных к объекту */
    protected ?array $tabs = null;

    /** @return null|Tab[] */
    public function getTabs(): ?array
    {
        return $this->tabs;
    }

    public function setTabs(?array $tabs): static
    {
        $this->tabs = $tabs;

        return $this;
    }

    public function addTab(Tab $baseTab): static
    {
        $tabs = $this->tabs;

        if (!is_null($tabs)) {
            foreach ($tabs as $tab) {
                if ($baseTab->getName() === $tab->getName()) {
                    return $this;
                }
            }
        }
        $this->tabs[] = $baseTab;

        return $this;
    }
}
