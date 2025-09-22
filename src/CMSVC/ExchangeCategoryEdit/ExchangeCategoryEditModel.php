<?php

declare(strict_types=1);

namespace App\CMSVC\ExchangeCategoryEdit;

use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(ExchangeCategoryEditController::class)]
class ExchangeCategoryEditModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Select(
        values: 'getParentValues',
    )]
    public Item\Select $parent;

    #[Attribute\Text(
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Hidden(
        defaultValue: '{menu}',
        obligatory: true,
    )]
    public Item\Hidden $content;
}
