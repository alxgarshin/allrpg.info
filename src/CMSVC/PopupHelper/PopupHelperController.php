<?php

declare(strict_types=1);

namespace App\CMSVC\PopupHelper;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Interface\Response;

/** @extends BaseController<PopupHelperService> */
#[CMSVC(
    service: PopupHelperService::class,
)]
class PopupHelperController extends BaseController
{
    #[IsAccessible]
    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getUnreadPeople(): ?Response
    {
        return $this->asArray($this->service->getUnreadPeople(OBJ_ID));
    }

    #[IsAccessible]
    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getTaskUnreadPeople(): ?Response
    {
        return $this->asArray($this->service->getTaskUnreadPeople(OBJ_ID));
    }

    #[IsAccessible]
    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getApplicationUnreadPeople(): ?Response
    {
        return $this->asArray($this->service->getApplicationUnreadPeople(OBJ_ID));
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('value', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function getVote(): ?Response
    {
        return $this->asArray($this->service->getVote(OBJ_ID, $this->param('value')));
    }

    #[ApiAction(params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getImportant(): ?Response
    {
        return $this->asArray($this->service->getImportant(OBJ_TYPE, OBJ_ID));
    }

    #[ApiAction(params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getAuthors(): ?Response
    {
        return $this->asArray($this->service->getAuthors(OBJ_TYPE, OBJ_ID));
    }

    #[ApiAction(params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('value', ApiParamTypeEnum::int, default: 0),
    ])]
    public function showUserInfo(): ?Response
    {
        return $this->asArray(
            $this->service->showUserInfo(
                OBJ_TYPE,
                OBJ_ID,
                $this->param('value'),
            ),
        );
    }

    #[ApiAction(params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('value', ApiParamTypeEnum::int, default: 0),
    ])]
    public function showUserInfoFromRolelist(): ?Response
    {
        return $this->asArray(
            $this->service->showUserInfo(
                OBJ_TYPE,
                OBJ_ID,
                $this->param('value'),
            ),
        );
    }
}
