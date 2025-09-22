<?php

declare(strict_types=1);

namespace App\CMSVC\Group;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait, LastUserUpdateIdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(GroupController::class)]
class GroupModel extends BaseModel
{
    use CreatedUpdatedAtTrait;
    use IdTrait;
    use LastUserUpdateIdTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        defaultValue: 'getLinkToRolesDefault',
        context: ['group:view'],
        saveHtml: true,
    )]
    public Item\Text $link_to_roles;

    #[Attribute\Select(
        defaultValue: 'getParentDefault',
        values: 'getParentValues',
    )]
    public Item\Select $parent;

    #[Attribute\Hidden(
        defaultValue: '{menu}',
        obligatory: true,
    )]
    public Item\Hidden $content;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Textarea(
        rows: 3,
        useInFilters: true,
    )]
    public Item\Textarea $description;

    #[Attribute\Text]
    public Item\Text $image;

    #[Attribute\Select(
        defaultValue: -1,
        values: 'getCodeValues',
    )]
    public Item\Select $code;

    #[Attribute\Select(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Select $rights;

    #[Attribute\Select(
        defaultValue: 15,
        values: 'getResponsibleGamemasterIdValues',
        useInFilters: true,
    )]
    public Item\Select $responsible_gamemaster_id;

    #[Attribute\Multiselect(
        values: 'getDistributedItemAutosetValues',
        locked: 'getDistributedItemAutosetLocked',
        search: true,
        creator: new Item\MultiselectCreator(
            table: 'resource',
            name: 'name',
            additional: [
                'updated_at' => 'getDistributedItemAutosetMultiselectCreatorUpdatedAt',
                'created_at' => 'getDistributedItemAutosetMultiselectCreatorCreatedAt',
                'project_id' => 'getDistributedItemAutosetMultiselectCreatorProjectId',
                'creator_id' => 'getDistributedItemAutosetMultiselectCreatorCreatorId',
                'distributed_item' => '1',
                'price' => 0,
                'quantity_needed' => 1,
                'quantity' => 0,
            ],
        ),
    )]
    public Item\Multiselect $distributed_item_autoset;

    #[Attribute\Checkbox]
    public Item\Checkbox $disallow_applications;

    #[Attribute\Checkbox]
    public Item\Checkbox $user_can_request_access;

    #[Attribute\Checkbox]
    public Item\Checkbox $disable_changes;

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

    #[Attribute\Wysiwyg(
        defaultValue: 'getLinkToCharactersDefault',
        context: ['group:view'],
    )]
    public Item\Wysiwyg $link_to_characters;
}
