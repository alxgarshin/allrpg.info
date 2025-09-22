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
    /** Обертка для создания ссылок вокруг значения элемента */
    protected LinkAt $linkAt;

    /** Основные валидаторы элемента */
    protected array $basicElementValidators = [];

    public function __construct(
        /** Обязательность элемента */
        protected ?bool $obligatory = false,

        /** Css-класс подсказки по элементу */
        protected ?string $helpClass = null,

        /** Номер последовательной группы элемента */
        protected ?int $group = null,

        /** В какой части (по номеру) группы находится данный конкретный инстанс элемента? */
        protected ?int $groupNumber = null,

        /** Управление элементом нестандартными обработчиками */
        protected ?bool $noData = null,

        /** Виртуальность (хранение JSON-блока в одной ячейке таблицы) элемента */
        protected ?bool $virtual = null,

        /** Открывающая часть ссылки */
        protected ?string $linkAtBegin = null,

        /** Закрывающая часть ссылки */
        protected ?string $linkAtEnd = null,

        /** Строка элемента в наборе данных */
        protected ?int $lineNumber = 0,

        /** Использовать элемент в фильтрах */
        protected ?bool $useInFilters = false,

        /** Контекст отображения элемента: при отображении полей проверяется совпадение контекста элемента с заданным сейчас контекстом модели. Массив в формате: [модель:list|view|viewIfNotNull|create|update|embedded], например: ['user:view', 'user:add'] */
        protected string|array $context = [],

        /** Список дополнительных валидаторов конкретного элемента конкретной модели
         * @var array<int, string> $additionalValidators
         */
        protected array $additionalValidators = [],

        /** Сохранять данные поля с вычисткой и сохранением html в нем */
        protected ?bool $saveHtml = null,

        /** Использовать данные из данной колонки таблицы вместо колонки по названию элемента */
        protected ?string $alternativeDataColumnName = null,

        /** Массив любых дополнительных данных */
        protected array $additionalData = [],

        /** Использовать соответствующую функцию из сервиса вместо стандартного asHTML */
        protected ?string $customAsHTMLRenderer = null,
    ) {
        $this->linkAt =
            new LinkAt(
                linkAtBegin: $this->linkAtBegin,
                linkAtEnd: $this->linkAtEnd,
            );
    }

    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    public function getLineNumberWrapped(): string
    {
        return (is_null($this->lineNumber) ? '' : '[' . $this->lineNumber . ']' . (is_null($this->groupNumber) ? '' : '[' . $this->groupNumber . ']'));
    }

    public function setLineNumber(?int $lineNumber): static
    {
        $this->lineNumber = $lineNumber;

        return $this;
    }

    public function getObligatory(): ?bool
    {
        return $this->obligatory;
    }

    public function getObligatoryStr(): string
    {
        return $this->obligatory ? ' obligatory' : '';
    }

    public function setObligatory(?bool $obligatory): static
    {
        $this->obligatory = $obligatory;

        return $this;
    }

    public function getGroup(): ?int
    {
        return $this->group;
    }

    public function setGroup(?int $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getGroupNumber(): ?int
    {
        return $this->group ? $this->groupNumber : null;
    }

    public function setGroupNumber(?int $groupNumber): static
    {
        $this->groupNumber = $groupNumber;

        return $this;
    }

    public function getHelpClass(): ?string
    {
        return $this->helpClass;
    }

    public function setHelpClass(?string $helpClass): static
    {
        $this->helpClass = $helpClass;

        return $this;
    }

    public function getLinkAt(): LinkAt
    {
        return $this->linkAt;
    }

    public function setLinkAt(LinkAt $linkAt): static
    {
        $this->linkAt = $linkAt;

        return $this;
    }

    public function getNoData(): ?bool
    {
        return $this->noData;
    }

    public function setNoData(?bool $noData): static
    {
        $this->noData = $noData;

        return $this;
    }

    public function getVirtual(): ?bool
    {
        return $this->virtual;
    }

    public function setVirtual(?bool $virtual): static
    {
        $this->virtual = $virtual;

        return $this;
    }

    public function getUseInFilters(): ?bool
    {
        return $this->useInFilters;
    }

    public function setUseInFilters(?bool $useInFilters): static
    {
        $this->useInFilters = $useInFilters;

        return $this;
    }

    public function getContext(): string|array
    {
        return $this->context;
    }

    public function setContext(string|array $context): static
    {
        $this->context = $this->flattenContext($context);

        return $this;
    }

    public function flattenContext(null|string|array $context): array
    {
        if (is_array($context) && ($context[0] ?? false) && is_array($context[0])) {
            $context = array_merge(...$context);
        }

        return $context;
    }

    public function checkContext(string $context): bool
    {
        return in_array($context, $this->context);
    }

    public function getAdditionalValidators(): array
    {
        return $this->additionalValidators;
    }

    public function setAdditionalValidators(array $additionalValidators): static
    {
        $this->additionalValidators = $additionalValidators;

        return $this;
    }

    public function getBasicElementValidators(): array
    {
        return $this->basicElementValidators;
    }

    public function getValidators(array $additionalValidators): array
    {
        return array_merge($this->basicElementValidators, $additionalValidators);
    }

    public function getSaveHtml(): ?bool
    {
        return $this->saveHtml;
    }

    public function setSaveHtml(?bool $saveHtml): static
    {
        $this->saveHtml = $saveHtml;

        return $this;
    }

    public function getAlternativeDataColumnName(): ?string
    {
        return $this->alternativeDataColumnName;
    }

    public function setAlternativeDataColumnName(?string $alternativeDataColumnName): static
    {
        $this->alternativeDataColumnName = $alternativeDataColumnName;

        return $this;
    }

    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }

    public function setAdditionalData(array $additionalData): static
    {
        $this->additionalData = $additionalData;

        return $this;
    }

    public function getCustomAsHTMLRenderer(): ?string
    {
        return $this->customAsHTMLRenderer;
    }

    public function setCustomAsHTMLRenderer(?string $customAsHTMLRenderer): static
    {
        $this->customAsHTMLRenderer = $customAsHTMLRenderer;

        return $this;
    }
}
