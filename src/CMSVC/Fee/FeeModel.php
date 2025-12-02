<?php

declare(strict_types=1);

namespace App\CMSVC\Fee;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait, LastUserUpdateIdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(FeeController::class)]
class FeeModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use LastUserUpdateIdTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Checkbox(
        noData: true,
    )]
    public Item\Checkbox $add_to_unpaid_applications;

    #[Attribute\Checkbox]
    public Item\Checkbox $do_not_use_in_budget;

    #[Attribute\Multiselect(
        values: 'getProjectRoomIdsValues',
        helpClass: 'fixed_help',
    )]
    public Item\Multiselect $project_room_ids;

    #[Attribute\Hidden(
        defaultValue: '{menu}',
        obligatory: true,
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
