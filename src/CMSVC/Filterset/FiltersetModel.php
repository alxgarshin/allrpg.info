<?php

declare(strict_types=1);

namespace App\CMSVC\Filterset;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(FiltersetController::class)]
class FiltersetModel extends BaseModel
{
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use IdTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        defaultValue: 'getNameDefault',
        maxChar: 255,
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Text(
        defaultValue: 'getLinkDefault',
        maxChar: 1000,
        obligatory: true,
    )]
    public Item\Text $link;
}
