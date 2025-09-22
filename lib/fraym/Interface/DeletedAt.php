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

use DateTimeImmutable;
use Fraym\Element\Item;

/** Интерфейс моделей для мягкого удаления данных. Требует подключения к модели трейта DeletedAtTrait или аналога, а также выставления соответствующих прав видимости для модели, исключающих (или, например, нет в зависимости от флага) записи с deleted_at IS NOT NULL */
/**
 * @property Item\Hidden $deleted_at
 */
interface DeletedAt
{
    public function getDeletedAtTime(): int|DateTimeImmutable;
}
