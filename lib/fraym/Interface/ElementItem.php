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

use Fraym\BaseObject\BaseModel;
use Fraym\Element\{Attribute as Attribute, Item as Item};
use Fraym\Entity\BaseEntity;
use Fraym\Enum\ActEnum;

interface ElementItem
{
    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string;

    public function asHTML(bool $elementIsWritable, bool $removeHtmlFromValue = false): string;

    public function asArray(): array;

    public function asArrayBase(): array;

    public function getAttribute(): ElementAttribute;

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static;

    public function checkAttribute(ElementAttribute $attribute, string $elementClassName): void;

    public function getOnCreate(): ?Attribute\OnCreate;

    public function setOnCreate(?Attribute\OnCreate $create): static;

    public function getOnChange(): ?Attribute\OnChange;

    public function setOnChange(?Attribute\OnChange $change): static;

    public function getEntity(): ?BaseEntity;

    public function setEntity(?BaseEntity $entity): static;

    public function getModel(): ?BaseModel;

    public function setModel(?BaseModel $model): static;

    public function checkDefaultValueInServiceFunctions(mixed $defaultValue): mixed;

    public function getLineNumber(): ?int;

    public function getLineNumberWrapped(): string;

    public function getObligatory(): bool;

    public function getObligatoryStr(): string;

    public function getGroup(): ?int;

    public function getGroupNumber(): ?int;

    public function getHelpClass(): ?string;

    public function getHelpText(): ?string;

    public function setHelpText(?string $helpText): static;

    public function getLinkAt(): Item\LinkAt;

    public function getName(): ?string;

    public function setName(?string $name): static;

    public function getNoData(): ?bool;

    public function getShownName(): ?string;

    public function setShownName(?string $shownName): static;

    public function getVirtual(): ?bool;

    public function checkContext(array $context): bool;

    public function checkVisibility(): bool;

    public function checkDOMVisibility(): bool;

    public function checkWritable(?ActEnum $act = null, ?string $objectName = null): ?bool;

    public function asHTMLWrapped(?int $lineNumber, bool $elementIsWritable, int $elementTabindexNum): string;

    public function validate(mixed $value, array $options): array;

    public function getDefaultValue(): mixed;

    public function get(): mixed;

    public function set(null $fieldValue): static;
}
