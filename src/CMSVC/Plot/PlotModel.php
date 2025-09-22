<?php

declare(strict_types=1);

namespace App\CMSVC\Plot;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait, LastUserUpdateIdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(PlotController::class)]
class PlotModel extends BaseModel
{
    use CreatedUpdatedAtTrait;
    use IdTrait;
    use LastUserUpdateIdTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Select(
        defaultValue: 'getResponsibleGamemasterIdDefault',
        values: 'getResponsibleGamemasterIdValues',
        useInFilters: true,
    )]
    public Item\Select $responsible_gamemaster_id;

    #[Attribute\Text(
        noData: true,
    )]
    public Item\Text $search_groups_by_name;

    #[Attribute\Hidden(
        defaultValue: 'getSearchGroupsByNameDefaultApplicationDefault',
        context: 'getSearchGroupsByNameDefaultApplicationContext',
        noData: true,
    )]
    public Item\Hidden $search_groups_by_name_default_application;

    #[Attribute\Hidden(
        defaultValue: 'getSearchGroupsByNameDefaultCharacterDefault',
        context: 'getSearchGroupsByNameDefaultCharacterContext',
        noData: true,
    )]
    public Item\Hidden $search_groups_by_name_default_character;

    #[Attribute\Multiselect(
        defaultValue: 'getProjectCharacterIdsDefault',
        values: 'getProjectCharacterIdsValues',
        search: true,
        useInFilters: true,
    )]
    public Item\Multiselect $project_character_ids;

    #[Attribute\Textarea(
        rows: 10,
        useInFilters: true,
    )]
    public Item\Textarea $description;

    #[Attribute\Number]
    public Item\Number $code;

    #[Attribute\Textarea(
        useInFilters: true,
    )]
    public Item\Textarea $todo;

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

    #[Attribute\H1(
        context: ['plot:update'],
    )]
    public Item\H1 $plots;

    #[Attribute\Wysiwyg(
        defaultValue: 'getPlotsDataDefault',
        context: ['plot:viewIfNotNull'],
    )]
    public Item\Wysiwyg $plots_data;
}
