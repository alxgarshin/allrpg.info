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

namespace Fraym\Response;

use Fraym\Interface\Response;

class HtmlResponse implements Response
{
    public function __construct(
        private string $html,
        private ?string $pagetitle = null,
    ) {
    }

    public function getPagetitle(): ?string
    {
        return $this->pagetitle;
    }

    public function setPagetitle(?string $pagetitle = null): static
    {
        $this->pagetitle = $pagetitle;

        return $this;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): static
    {
        $this->html = $html;

        return $this;
    }
}
