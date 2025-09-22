<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEventGroup;

use Fraym\BaseObject\{BaseService, Controller};

/** @extends BaseService<CalendarEventGroupModel> */
#[Controller(CalendarEventGroupController::class)]
class CalendarEventGroupService extends BaseService
{
    public function checkRights(): bool
    {
        return CURRENT_USER->isAdmin() || CURRENT_USER->checkAllRights('info');
    }
}
