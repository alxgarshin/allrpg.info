<?php

declare(strict_types=1);

namespace App\CMSVC\Group;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Interface\Response;

/** @extends BaseController<GroupService> */
#[CMSVC(
    model: GroupModel::class,
    service: GroupService::class,
    view: GroupView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectKindAccessAndRedirect',
)]
class GroupController extends BaseController
{
    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, source: ApiParamSourceEnum::global),
        new ApiParam('group_id', ApiParamTypeEnum::int, default: 0),
    ])]
    public function getChildGroups(): ?Response
    {
        $groupService = $this->service;

        return $this->asArray(
            $groupService->getChildGroups(
                is_null(OBJ_ID) ? null : (int) OBJ_ID,
                $this->param('group_id'),
            ),
        );
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getResponsibleGamemaster(): ?Response
    {
        $groupService = $this->service;

        return $this->asArray(
            $groupService->getResponsibleGamemaster((int) OBJ_ID),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('group_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('after_obj_id', ApiParamTypeEnum::int, default: 0),
    ])]
    public function changeCharacterCode(): ?Response
    {
        $groupService = $this->service;

        return $this->asArray(
            $groupService->changeCharacterCode(
                OBJ_ID,
                $this->param('group_id'),
                $this->param('after_obj_id'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('level', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('after_obj_id', ApiParamTypeEnum::int, default: 0),
    ])]
    public function changeGroupCode(): ?Response
    {
        $groupService = $this->service;

        return $this->asArray(
            $groupService->changeGroupCode(
                OBJ_ID,
                $this->param('level'),
                $this->param('after_obj_id'),
            ),
        );
    }
}
