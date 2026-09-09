<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEventGallery;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Response\ArrayResponse;

/** @extends BaseController<CalendarEventGalleryService> */
#[IsAccessible]
#[CMSVC(
    service: CalendarEventGalleryService::class,
)]
class CalendarEventGalleryController extends BaseController
{
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('link', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('name', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('thumb', ApiParamTypeEnum::string, default: ''),
        new ApiParam('author', ApiParamTypeEnum::string, default: ''),
    ])]
    public function addCalendarEventGallery(): ArrayResponse
    {
        return $this->asArray(
            $this->service->addCalendarEventGallery(
                OBJ_ID,
                $this->param('link'),
                $this->param('name'),
                $this->param('thumb'),
                $this->param('author'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('link', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('name', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('thumb', ApiParamTypeEnum::string, default: ''),
        new ApiParam('author', ApiParamTypeEnum::string, default: ''),
    ])]
    public function changeCalendarEventGallery(): ArrayResponse
    {
        return $this->asArray(
            $this->service->changeCalendarEventGallery(
                OBJ_ID,
                $this->param('link'),
                $this->param('name'),
                $this->param('thumb'),
                $this->param('author'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function deleteCalendarEventGallery(): ArrayResponse
    {
        return $this->asArray($this->service->deleteCalendarEventGallery(OBJ_ID));
    }
}
