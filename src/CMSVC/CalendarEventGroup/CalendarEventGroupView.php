<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEventGroup;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights};
use Fraym\Interface\Response;

#[MultiObjectsEntity(
    'calendarEventGroup',
    'calendar_event_group',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
    ],
)]
#[Rights(
    viewRight: 'checkRights',
    addRight: 'checkRights',
    changeRight: 'checkRights',
    deleteRight: 'checkRights',
)]
#[Controller(CalendarEventGroupController::class)]
class CalendarEventGroupView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
