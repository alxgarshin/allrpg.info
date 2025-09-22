<?php

declare(strict_types=1);

namespace App\CMSVC\Fee\FeeOption;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\BaseModel;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait, LastUserUpdateIdTrait};
use Fraym\Element\{Attribute, Item};

class FeeOptionModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use LastUserUpdateIdTrait;
    use ProjectIdTrait;

    #[Attribute\Select(
        defaultValue: 'getParentDefault',
        values: 'getParentValuesForChild',
        obligatory: true,
    )]
    public Item\Select $parent;

    #[Attribute\Number(
        obligatory: true,
    )]
    public Item\Number $cost;

    #[Attribute\Calendar(
        obligatory: true,
    )]
    public Item\Calendar $date_from;

    #[Attribute\Hidden(
        defaultValue: null,
    )]
    public Item\Hidden $content;

    #[Attribute\Timestamp(
        context: [
            ':list',
            ':create',
            ':update',
            ':delete',
        ],
        showInObjects: true,
        customAsHTMLRenderer: 'getUpdatedAtCustomAsHTMLRenderer',
    )]
    #[Attribute\OnCreate(callback: 'getTime')]
    #[Attribute\OnChange(callback: 'getTime')]
    public Item\Timestamp $updated_at;
}
