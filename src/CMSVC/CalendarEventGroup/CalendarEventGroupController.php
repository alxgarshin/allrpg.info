<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEventGroup;

use Fraym\BaseObject\{BaseController, CMSVC};

/** @extends BaseController<CalendarEventGroupService> */
#[CMSVC(
    model: CalendarEventGroupModel::class,
    service: CalendarEventGroupService::class,
    view: CalendarEventGroupView::class,
)]
class CalendarEventGroupController extends BaseController
{
}
