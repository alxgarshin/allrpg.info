<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEvent;

use App\CMSVC\HelperGeographyCity\HelperGeographyCityController;
use App\CMSVC\HelperUsersList\HelperUsersListController;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(CalendarEventController::class)]
class CalendarEventModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;

    #[Attribute\Text(
        defaultValue: 'getToGameDefault',
        noData: true,
        context: ['calendarEvent:view', 'calendarEvent:embedded'],
        saveHtml: true,
    )]
    public Item\Text $togame;

    #[Attribute\Select(
        defaultValue: 1,
        helper: new HelperUsersListController(),
        obligatory: true,
        context: 'getAdminFieldsContext',
    )]
    public Item\Select $creator_id;

    #[Attribute\Text(
        defaultValue: 'getNameDefault',
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Select(
        helper: new HelperGeographyCityController(),
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Select $region;

    #[Attribute\Multiselect(
        values: 'getAreaValues',
        one: true,
        search: true,
        obligatory: true,
    )]
    public Item\Multiselect $area;

    #[Attribute\Multiselect(
        values: 'getGametypeValues',
        one: true,
        obligatory: true,
    )]
    public Item\Multiselect $gametype;

    #[Attribute\Multiselect(
        values: 'getGametype2Values',
        one: true,
        obligatory: true,
    )]
    public Item\Multiselect $gametype2;

    #[Attribute\Select(
        values: 'getGametype3Values',
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Select $gametype3;

    #[Attribute\Multiselect(
        values: 'getGametype4Values',
    )]
    public Item\Multiselect $gametype4;

    #[Attribute\Text]
    public Item\Text $mg;

    #[Attribute\Text]
    public Item\Text $site;

    #[Attribute\Text(
        defaultValue: 'getOrderPageDefault',
        maxChar: 255,
    )]
    public Item\Text $orderpage;

    #[Attribute\Calendar(
        defaultValue: 'getDateFromDefault',
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Calendar $date_from;

    #[Attribute\Calendar(
        defaultValue: 'getDateToDefault',
        obligatory: true,
    )]
    public Item\Calendar $date_to;

    #[Attribute\Calendar(
        obligatory: true,
    )]
    public Item\Calendar $date_arrival;

    #[Attribute\Number(
        defaultValue: 'getPlayerNumDefault',
        obligatory: true,
    )]
    public Item\Number $playernum;

    #[Attribute\Wysiwyg(
        defaultValue: 'getContentDefault',
    )]
    public Item\Wysiwyg $content;

    #[Attribute\File(
        uploadNum: 13,
    )]
    public Item\File $logo;

    #[Attribute\Number(
        context: 'getAdminFieldsContext',
    )]
    public Item\Number $kogdaigra_id;

    #[Attribute\Select(
        values: 'getAgroupValues',
        context: 'getAdminFieldsContext',
    )]
    public Item\Select $agroup;

    #[Attribute\Checkbox(
        context: ['calendarEvent:update', 'calendarEvent:embedded'],
    )]
    public Item\Checkbox $wascancelled;

    #[Attribute\Checkbox(
        context: ['calendarEvent:update', 'calendarEvent:embedded'],
    )]
    public Item\Checkbox $moved;
}
