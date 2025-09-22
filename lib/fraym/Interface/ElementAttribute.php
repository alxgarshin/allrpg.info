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

namespace Fraym\Interface;

use Fraym\Element\Item\LinkAt;

interface ElementAttribute
{
    public function getLineNumber(): ?int;

    public function getLineNumberWrapped(): string;

    public function setLineNumber(?int $lineNumber): static;

    public function getObligatory(): ?bool;

    public function getObligatoryStr(): string;

    public function setObligatory(?bool $obligatory): static;

    public function getGroup(): ?int;

    public function setGroup(?int $group): static;

    public function getGroupNumber(): ?int;

    public function setGroupNumber(?int $groupNumber): static;

    public function getHelpClass(): ?string;

    public function setHelpClass(?string $helpClass): static;

    public function getLinkAt(): LinkAt;

    public function setLinkAt(LinkAt $linkAt): static;

    public function getNoData(): ?bool;

    public function setNoData(?bool $noData): static;

    public function getVirtual(): ?bool;

    public function setVirtual(?bool $virtual): static;

    public function getUseInFilters(): ?bool;

    public function setUseInFilters(?bool $useInFilters): static;

    public function getContext(): string|array;

    public function setContext(string|array $context): static;

    public function checkContext(string $context): bool;

    public function getAdditionalValidators(): array;

    public function setAdditionalValidators(array $additionalValidators): static;

    public function getBasicElementValidators(): array;

    public function getValidators(array $additionalValidators): array;

    public function getSaveHtml(): ?bool;

    public function setSaveHtml(?bool $saveHtml): static;

    public function getAlternativeDataColumnName(): ?string;

    public function setAlternativeDataColumnName(?string $alternativeDataColumnName): static;

    public function getAdditionalData(): array;

    public function setAdditionalData(array $additionalValidators): static;

    public function getCustomAsHTMLRenderer(): ?string;

    public function setCustomAsHTMLRenderer(?string $customAsHTMLRenderer): static;
}
