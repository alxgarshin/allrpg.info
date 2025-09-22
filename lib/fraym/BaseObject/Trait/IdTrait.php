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

/** Id объекта */
trait IdTrait
{
    #[Attribute\Hidden(
        obligatory: true,
        noData: true,
        context: [
            ':list',
            ':update',
            ':delete',
        ],
    )]
    public Item\Hidden $id;
}
