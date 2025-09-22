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

use Fraym\Element\{Attribute as Attribute, Item as Item};
use Fraym\Helper\DateHelper;

/** Дата обновления объекта */
trait CreatedUpdatedAtTrait
{
    #[Attribute\Timestamp(
        context: [
            ':list',
            ':create',
        ],
    )]
    #[Attribute\OnCreate(callback: 'getTime')]
    public Item\Timestamp $created_at;

    #[Attribute\Timestamp(
        context: [
            ':list',
            ':create',
            ':update',
            ':delete',
        ],
    )]
    #[Attribute\OnCreate(callback: 'getTime')]
    #[Attribute\OnChange(callback: 'getTime')]
    public Item\Timestamp $updated_at;

    public function getTime(): int
    {
        return DateHelper::getNow();
    }
}
