<?php

declare(strict_types=1);

namespace App\CMSVC\Notion;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Response\ArrayResponse;

/** @extends BaseController<NotionService> */
#[IsAccessible]
#[CMSVC(
    service: NotionService::class,
)]
class NotionController extends BaseController
{
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('text', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('rating', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function notionMessageSave(): ArrayResponse
    {
        return $this->asArray(
            $this->service->notionMessageSave(
                OBJ_ID,
                $this->param('text'),
                $this->param('rating'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function notionMessageDelete(): ArrayResponse
    {
        return $this->asArray($this->service->notionMessageDelete(OBJ_ID));
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function showHideNotion(): ArrayResponse
    {
        return $this->asArray($this->service->showHideNotion(OBJ_ID));
    }
}
