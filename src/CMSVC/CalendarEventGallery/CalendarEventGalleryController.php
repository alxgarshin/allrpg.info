<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEventGallery;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Response\ArrayResponse;

/** @extends BaseController<CalendarEventGalleryService> */
#[IsAccessible]
#[CMSVC(
    service: CalendarEventGalleryService::class,
)]
class CalendarEventGalleryController extends BaseController
{
    public function addCalendarEventGallery(): ArrayResponse
    {
        return $this->asArray(
            $this->service->addCalendarEventGallery(
                OBJ_ID,
                $_REQUEST['link'] ?? '',
                $_REQUEST['name'] ?? '',
                $_REQUEST['thumb'] ?? '',
                $_REQUEST['author'] ?? '',
            ),
        );
    }

    public function changeCalendarEventGallery(): ArrayResponse
    {
        return $this->asArray(
            $this->service->changeCalendarEventGallery(
                OBJ_ID,
                $_REQUEST['link'] ?? '',
                $_REQUEST['name'] ?? '',
                $_REQUEST['thumb'] ?? '',
                $_REQUEST['author'] ?? '',
            ),
        );
    }

    public function deleteCalendarEventGallery(): ArrayResponse
    {
        return $this->asArray($this->service->deleteCalendarEventGallery(OBJ_ID));
    }
}
