<?php

declare(strict_types=1);

namespace App\CMSVC\RulingTagEdit;

use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(RulingTagEditController::class)]
class RulingTagEditModel extends BaseModel
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

    #[Attribute\Checkbox]
    public Item\Checkbox $show_in_cloud;
}
