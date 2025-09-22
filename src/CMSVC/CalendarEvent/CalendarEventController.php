<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEvent;

use Fraym\BaseObject\{BaseController, CMSVC};

/** @extends BaseController<CalendarEventService> */
#[CMSVC(
    model: CalendarEventModel::class,
    service: CalendarEventService::class,
    view: CalendarEventView::class,
)]
class CalendarEventController extends BaseController
{
}
