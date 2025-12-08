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
    /** Обязательность элемента */
    public ?bool $obligatory { get; set; }

    /** Класс в зависимости от обязательности элемента */
    public string $obligatoryStr { get; }

    /** Css-класс подсказки по элементу */
    public ?string $helpClass { get; set; }

    /** Номер последовательной группы элемента */
    public ?int $group { get; set; }

    /** В какой части (по номеру) группы находится данный конкретный инстанс элемента? */
    public ?int $groupNumber { get; set; }

    /** Управление элементом нестандартными обработчиками */
    public ?bool $noData { get; set; }

    /** Виртуальность (хранение JSON-блока в одной ячейке таблицы) элемента */
    public ?bool $virtual { get; set; }

    /** Обертка для создания ссылок вокруг значения элемента */
    public LinkAt $linkAt { get; set; }

    /** Открывающая часть ссылки */
    public ?string $linkAtBegin { get; set; }

    /** Закрывающая часть ссылки */
    public ?string $linkAtEnd { get; set; }

    /** Строка элемента в наборе данных */
    public ?int $lineNumber { get; set; }

    /** Обернутая строка элемента в наборе данных */
    public string $lineNumberWrapped { get; }

    /** Использовать элемент в фильтрах */
    public ?bool $useInFilters { get; set; }

    /** Контекст отображения элемента: при отображении полей проверяется совпадение контекста элемента с заданным сейчас контекстом модели. Массив в формате: [модель:list|view|viewIfNotNull|create|update|embedded], например: ['user:view', 'user:add'] */
    public string|array $context { get; set; }

    /** Основные валидаторы элемента */
    public array $basicElementValidators { get; set; }

    /** Список дополнительных валидаторов конкретного элемента конкретной модели
     * @var array<int, string> $additionalValidators
     */
    public array $additionalValidators { get; set; }

    /** Сохранять данные поля с вычисткой и сохранением html в нем */
    public ?bool $saveHtml { get; set; }

    /** Использовать данные из данной колонки таблицы вместо колонки по названию элемента */
    public ?string $alternativeDataColumnName { get; set; }

    /** Массив любых дополнительных данных */
    public array $additionalData { get; set; }

    /** Использовать соответствующую функцию из сервиса вместо стандартного asHTML */
    public ?string $customAsHTMLRenderer { get; set; }

    /** Проверка наличия контекста в списке контекстов */
    public function checkContext(string $context): bool;

    /** Получение полного списка валидаторов, включая дополнительные */
    public function getValidators(array $additionalValidators): array;
}
