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

namespace Fraym\BaseObject\Trait;

use DateTimeImmutable;
use Fraym\Element\{Attribute as Attribute, Item as Item};
use Fraym\Helper\DateHelper;

/** Дата мягкого удаления объекта */
/** @phpstan-ignore-next-line */
trait DeletedAtTrait
{
    #[Attribute\Timestamp(
        context: [
            ':list',
            ':update',
            ':delete',
        ],
    )]
    public Item\Timestamp $deleted_at;

    public function getDeletedAtTime(): int|DateTimeImmutable
    {
        return DateHelper::getNow();
    }
}
