<?php

declare(strict_types=1);

namespace App\CMSVC\Portfolio;

use App\CMSVC\HelperGamesList\HelperGamesListController;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(PortfolioController::class)]
class PortfolioModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Text(
        defaultValue: 'getToGameDefault',
        noData: true,
        context: ['portfolio:view', 'portfolio:embedded'],
        saveHtml: true,
    )]
    public Item\Text $togame;

    #[Attribute\Select(
        defaultValue: 'getCalendarEventIdDefault',
        helper: new HelperGamesListController(),
        obligatory: true,
    )]
    public Item\Select $calendar_event_id;

    #[Attribute\Text]
    public Item\Text $role;

    #[Attribute\Text]
    public Item\Text $locat;

    #[Attribute\Multiselect(
        values: 'getSpecializValues',
    )]
    public Item\Multiselect $specializ;

    #[Attribute\Multiselect(
        values: 'getSpecializ2Values',
    )]
    public Item\Multiselect $specializ2;

    #[Attribute\Multiselect(
        values: 'getSpecializ3Values',
    )]
    public Item\Multiselect $specializ3;

    #[Attribute\Text]
    public Item\Text $photo;

    #[Attribute\Checkbox(
        defaultValue: true,
    )]
    public Item\Checkbox $active;
}
