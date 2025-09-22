<?php

declare(strict_types=1);

namespace App\CMSVC\Plot\PlotPlot;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\BaseModel;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait, LastUserUpdateIdTrait};
use Fraym\Element\{Attribute, Item};

class PlotPlotModel extends BaseModel
{
    use CreatedUpdatedAtTrait;
    use IdTrait;
    use LastUserUpdateIdTrait;
    use ProjectIdTrait;

    #[Attribute\Select(
        defaultValue: 'getParentDefaultForChild',
        values: 'getParentValuesForChild',
        obligatory: true,
        helpClass: 'fixed_help',
    )]
    public Item\Select $parent;

    #[Attribute\Text(
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Multiselect(
        values: 'getApplicationsValuesForChild',
        locked: ['hidden'],
        search: true,
        context: ['plotPlot:view', 'plotPlot:create', 'plotPlot:update'],
    )]
    public Item\Multiselect $applications_1_side_ids;

    #[Attribute\Multiselect(
        values: 'getApplicationsValuesForChild',
        locked: ['hidden'],
        search: true,
        context: ['plotPlot:view', 'plotPlot:create', 'plotPlot:update'],
    )]
    public Item\Multiselect $applications_2_side_ids;

    #[Attribute\Checkbox]
    public Item\Checkbox $hideother;

    #[Attribute\Wysiwyg(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Wysiwyg $content;

    #[Attribute\Number]
    public Item\Number $code;

    #[Attribute\Textarea(
        useInFilters: true,
    )]
    public Item\Textarea $todo;

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
