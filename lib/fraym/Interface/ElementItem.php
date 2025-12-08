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
    /** Название поля: совпадает с названием в БД */
    public ?string $name { get; set; }

    /** Видимое для пользователей название поля */
    public ?string $shownName { get; set; }

    /** Текст подсказки к полю */
    public ?string $helpText { get; set; }

    /** Родительская сущность */
    public ?BaseEntity $entity { get; set; }

    /** Родительская модель */
    public ?BaseModel $model { get; set; }

    /** Данные для замены значения элемента при create */
    public ?Attribute\OnCreate $create { get; set; }

    /** Данные для замены значения элемента при change */
    public ?Attribute\OnChange $change { get; set; }

    public function getAttribute(): ElementAttribute;

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static;

    public function checkAttribute(ElementAttribute $attribute, string $elementClassName): void;

    public function getDefaultValue(): mixed;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string;

    public function asHTML(bool $elementIsWritable, bool $removeHtmlFromValue = false): string;

    public function asArray(): array;

    public function asArrayBase(): array;

    public function checkDefaultValueInServiceFunctions(mixed $defaultValue): mixed;

    public function getLineNumber(): ?int;

    public function getLineNumberWrapped(): string;

    public function getObligatory(): bool;

    public function getObligatoryStr(): string;

    public function getGroup(): ?int;

    public function getGroupNumber(): ?int;

    public function getHelpClass(): ?string;

    public function getLinkAt(): Item\LinkAt;

    public function getNoData(): ?bool;

    public function getVirtual(): ?bool;

    public function checkContext(array $context): bool;

    public function checkVisibility(): bool;

    public function checkDOMVisibility(): bool;

    public function checkWritable(?ActEnum $act = null, ?string $objectName = null): ?bool;

    public function asHTMLWrapped(?int $lineNumber, bool $elementIsWritable, int $elementTabindexNum): string;

    public function validate(mixed $value, array $options): array;

    public function get(): mixed;

    public function set(null $fieldValue): static;
}
