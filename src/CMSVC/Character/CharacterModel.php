<?php

declare(strict_types=1);

namespace App\CMSVC\Character;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait, LastUserUpdateIdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(CharacterController::class)]
class CharacterModel extends BaseModel
{
    use CreatedUpdatedAtTrait;
    use IdTrait;
    use LastUserUpdateIdTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        defaultValue: 'getLinkToRolesDefault',
        context: ['character:view'],
        saveHtml: true,
    )]
    public Item\Text $link_to_roles;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Multiselect(
        defaultValue: 'getProjectGroupIdsDefault',
        values: 'getProjectGroupIdsValues',
        search: true,
        creator: new Item\MultiselectCreator(
            table: 'project_group',
            name: 'name',
            additional: [
                'updated_at' => 'getProjectGroupIdsMultiselectCreatorUpdatedAt',
                'created_at' => 'getProjectGroupIdsMultiselectCreatorCreatedAt',
                'parent' => 0,
                'project_id' => 'getProjectGroupIdsMultiselectCreatorProjectId',
                'rights' => 2,
                'code' => 10000,
            ],
        ),
        useInFilters: true,
    )]
    public Item\Multiselect $project_group_ids;

    #[Attribute\Checkbox(
        defaultValue: true,
    )]
    public Item\Checkbox $setparentgroups;

    #[Attribute\Checkbox]
    public Item\Checkbox $disallow_applications;

    #[Attribute\Select(
        obligatory: true,
        context: 'getTeamCharacterContext',
    )]
    public Item\Select $team_character;

    #[Attribute\Number]
    public Item\Number $team_applications_needed_count;

    #[Attribute\Number(
        defaultValue: 1,
        obligatory: true,
    )]
    public Item\Number $applications_needed_count;

    #[Attribute\Checkbox]
    public Item\Checkbox $auto_new_character_creation;

    #[Attribute\Text(
        useInFilters: true,
    )]
    public Item\Text $maybetaken;

    #[Attribute\Text(
        useInFilters: true,
        context: 'getTakenContext',
    )]
    public Item\Text $taken;

    #[Attribute\Text(
        defaultValue: 'getTakenDetails',
        context: ['character:view'],
        saveHtml: true,
    )]
    public Item\Text $taken_details;

    #[Attribute\Checkbox]
    public Item\Checkbox $hide_applications;

    #[Attribute\Textarea(
        rows: 10,
        useInFilters: true,
    )]
    public Item\Textarea $content;

    #[Attribute\Textarea(
        rows: 1,
        useInFilters: true,
    )]
    public Item\Textarea $comments;

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

    #[Attribute\H1(
        context: ['character:update'],
    )]
    public Item\H1 $plots;

    #[Attribute\Wysiwyg(
        defaultValue: 'getPlotsDataDefault',
        context: ['character:viewIfNotNull'],
    )]
    public Item\Wysiwyg $plots_data;

    #[Attribute\Wysiwyg(
        defaultValue: 'getLinkToApplicationsDefault',
        context: ['character:viewIfNotNull'],
    )]
    public Item\Wysiwyg $link_to_applications;
}
