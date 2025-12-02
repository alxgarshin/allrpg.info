<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEventGroup;

use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(CalendarEventGroupController::class)]
class CalendarEventGroupModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;

    #[Attribute\Text(
        obligatory: true,
    )]
    public Item\Text $name;
}
