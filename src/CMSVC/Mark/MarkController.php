<?php

declare(strict_types=1);

namespace App\CMSVC\Mark;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Interface\Response;

/** @extends BaseController<MarkService> */
#[CMSVC(
    service: MarkService::class,
)]
class MarkController extends BaseController
{
    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function markNeedResponse(): ?Response
    {
        $markService = $this->service;

        return $this->asArray(
            $markService->markNeedResponse(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function markHasResponse(): ?Response
    {
        $markService = $this->service;

        return $this->asArray(
            $markService->markHasResponse(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function markReadMessage(): ?Response
    {
        $markService = $this->service;

        return $this->asArray(
            $markService->markReadMessage(
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function markRead(): ?Response
    {
        $markService = $this->service;

        return $this->asArray(
            $markService->markRead(
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function markImportant(): ?Response
    {
        $markService = $this->service;

        return $this->asArray(
            $markService->markImportant(
                OBJ_TYPE,
                OBJ_ID,
            ),
        );
    }
}
