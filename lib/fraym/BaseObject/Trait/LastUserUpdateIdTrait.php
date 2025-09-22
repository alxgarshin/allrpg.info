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

/** Последний поменявший объект пользователь */
trait LastUserUpdateIdTrait
{
    #[Attribute\Hidden(
        context: [
            ':list',
            ':create',
            ':update',
            ':delete',
        ],
    )]
    #[Attribute\OnCreate(callback: 'getLastUpdateUserId')]
    #[Attribute\OnChange(callback: 'getLastUpdateUserId')]
    public Item\Hidden $last_update_user_id;

    public function getLastUpdateUserId(): ?int
    {
        return CURRENT_USER->id();
    }
}
