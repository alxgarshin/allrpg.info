<?php

declare(strict_types=1);

namespace App\CMSVC\RulingItemEdit;

use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(RulingItemEditController::class)]
class RulingItemEditModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Text(
        defaultValue: 'getObjHelper2Default',
        context: ['rulingItemEdit:view'],
        saveHtml: true,
    )]
    public Item\Text $obj_helper_2;

    #[Attribute\Text(
        defaultValue: 'getObjHelper3Default',
        context: ['rulingItemEdit:view'],
        saveHtml: true,
    )]
    public Item\Text $obj_helper_3;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Text(
        context: ['rulingItemEdit:view'],
        saveHtml: true,
    )]
    public Item\Text $obj_helper_1;

    #[Attribute\Wysiwyg(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Wysiwyg $content;

    #[Attribute\Multiselect(
        values: 'getRulingTagIdsValues',
        search: true,
        creator: new Item\MultiselectCreator(
            table: 'ruling_tag',
            name: 'name',
            additional: [
                'creator_id' => 'getRulingTagIdsMultiselectCreatorCreatorId',
                'updated_at' => 'getRulingTagIdsMultiselectCreatorGetNow',
                'created_at' => 'getRulingTagIdsMultiselectCreatorGetNow',
                'parent' => 0,
                'content' => '{menu}',
            ],
        ),
        useInFilters: true,
    )]
    public Item\Multiselect $ruling_tag_ids;

    #[Attribute\Multiselect(
        values: 'getAuthorValues',
        search: true,
    )]
    public Item\Multiselect $author;

    #[Attribute\H1]
    public Item\H1 $h1_group;

    #[Attribute\Multiselect(
        values: 'getShowIfValues',
        search: true,
        group: 1,
        useInFilters: true,
    )]
    public Item\Multiselect $show_if;
}
