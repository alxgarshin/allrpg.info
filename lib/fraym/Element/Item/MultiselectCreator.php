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

use Fraym\BaseObject\BaseService;

class MultiselectCreator
{
    public function __construct(
        /** Таблица, куда вносить */
        private ?string $table,

        /** В какое поле вносить */
        private ?string $name,

        /** Массив предустановленных значений других полей таблицы в формате [название_поля]=>'значение' */
        private ?array $additional,
    ) {
    }

    public function asArray(): array
    {
        return [
            'table' => $this->table,
            'name' => $this->name,
            'additional' => $this->additional,
        ];
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    public function setTable(?string $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAdditional(): ?array
    {
        return $this->additional;
    }

    public function setAdditional(?array $additional): static
    {
        $this->additional = $additional;

        return $this;
    }

    public function createItem(string $newName, BaseService $service): int|string
    {
        $newItem = DB->select($this->table, [$this->name => $newName], true);

        if ($newItem) {
            return $newItem['id'];
        } else {
            $additionalFields = $this->additional;

            foreach ($additionalFields as $key => $additionalField) {
                if (is_string($additionalField) && method_exists($service, $additionalField)) {
                    $additionalFields[$key] = $service->{$additionalField}();
                }
            }

            DB->insert($this->table, array_merge([$this->name => $newName], $additionalFields));

            return DB->lastInsertId();
        }
    }
}
