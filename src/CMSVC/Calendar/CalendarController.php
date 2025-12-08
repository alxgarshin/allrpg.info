<?php

declare(strict_types=1);

namespace App\CMSVC\Calendar;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Response\ArrayResponse;

/** @extends BaseController<CalendarService> */
#[CMSVC(
    service: CalendarService::class,
    view: CalendarView::class,
)]
class CalendarController extends BaseController
{
    public function changeCalendarstyle(): ArrayResponse
    {
        return $this->asArray($this->service->changeCalendarstyle());
    }
}
