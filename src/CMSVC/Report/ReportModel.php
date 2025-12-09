<?php

declare(strict_types=1);

namespace App\CMSVC\Report;

use App\CMSVC\HelperGamesList\HelperGamesListController;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(ReportController::class)]
class ReportModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Select(
        defaultValue: 'getDefaultCalendarEventId',
        helper: new HelperGamesListController(),
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Select $calendar_event_id;

    #[Attribute\Text]
    public Item\Text $name;

    #[Attribute\Wysiwyg(
        obligatory: true,
    )]
    public Item\Wysiwyg $content;

    #[Attribute\Select(
        values: 'getSortCreatorId',
        useInFilters: true,
        context: [],
        noData: true,
        alternativeDataColumnName: 'creator_id',
    )]
    public Item\Select $creator_id_filtered;
}
