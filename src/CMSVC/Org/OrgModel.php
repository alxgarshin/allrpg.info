<?php

declare(strict_types=1);

namespace App\CMSVC\Org;

use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(OrgController::class)]
class OrgModel extends BaseModel
{
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use IdTrait;

    #[Attribute\Multiselect(
        values: 'getObjIdFromValues',
        one: true,
        search: true,
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Multiselect $obj_id_from;

    #[Attribute\Multiselect(
        one: true,
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Multiselect $type;

    #[Attribute\Multiselect(
        values: 'getCommentValues',
        locked: ['help_1', 'help_2'],
        search: true,
        useInFilters: true,
    )]
    public Item\Multiselect $comment;

    #[Attribute\Select(
        context: ['org:list'],
    )]
    public Item\Select $obj_type_from;

    #[Attribute\Hidden(
        defaultValue: 'getObjIdToDefault',
    )]
    public Item\Hidden $obj_id_to;

    #[Attribute\Hidden(
        defaultValue: '{project}',
    )]
    public Item\Hidden $obj_type_to;
}
