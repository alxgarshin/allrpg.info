<?php

declare(strict_types=1);

namespace App\CMSVC\Event;

use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(EventController::class)]
class EventModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Select(
        defaultValue: OBJ_ID,
        values: 'getObjIdValues',
        noData: true,
        context: ['event:create'],
        obligatory: true,
    )]
    public Item\Select $obj_id;

    #[Attribute\Hidden(
        defaultValue: 'getObjType',
        noData: true,
        context: ['event:create'],
    )]
    public Item\Hidden $obj_type;

    #[Attribute\Hidden(
        defaultValue: 'getMessageIdDefault',
        noData: true,
        context: ['event:create'],
    )]
    public Item\Hidden $message_id;

    #[Attribute\Text(
        maxChar: 255,
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Multiselect(
        defaultValue: 'getUserIdDefault',
        values: 'getUserIdValues',
        locked: 'getUserIdLocked',
        search: true,
        noData: true,
    )]
    public Item\Multiselect $user_id;

    #[Attribute\Text(
        minChar: 3,
        maxChar: 1000,
    )]
    public Item\Text $place;

    #[Attribute\Textarea(
        defaultValue: 'getDescriptionDefault',
        obligatory: true,
    )]
    public Item\Textarea $description;

    #[Attribute\Calendar(
        defaultValue: 'getDateFromDefault',
        showDatetime: true,
        obligatory: true,
    )]
    public Item\Calendar $date_from;

    #[Attribute\Calendar(
        defaultValue: 'getDateToDefault',
        showDatetime: true,
        obligatory: true,
    )]
    public Item\Calendar $date_to;
}
