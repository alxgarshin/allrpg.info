<?php

declare(strict_types=1);

namespace App\CMSVC\Application;

use App\CMSVC\Group\GroupService;
use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Helper\CMSVCHelper;
use Fraym\Interface\Response;

/** @extends BaseController<ApplicationService> */
#[CMSVC(
    model: ApplicationModel::class,
    service: ApplicationService::class,
    view: ApplicationView::class,
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
class ApplicationController extends BaseController
{
    #[ApiAction(params: [
        new ApiParam('obj_name', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function getApplicationsTable(): ?Response
    {
        return $this->asArray(
            $this->service->getApplicationsTable(
                $this->param('obj_name'),
            ),
        );
    }

    #[ApiAction(params: [
        new ApiParam('obj_name', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function getApplicationsCommentsTable(): ?Response
    {
        return $this->asArray(
            $this->service->getApplicationsCommentsTable(
                $this->param('obj_name'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('filter', ApiParamTypeEnum::string),
    ])]
    public function setSpecialGroup(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->setSpecialGroup(
                    OBJ_ID,
                    $this->param('filter'),
                ),
            );
        }

        return null;
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('name', ApiParamTypeEnum::string, obligatory: true),
    ])]
    public function fixCharacterNameBySorter(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->fixCharacterNameBySorter(
                    OBJ_ID,
                    $this->param('name'),
                ),
            );
        }

        return null;
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('user_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function transferApplication(): ?Response
    {
        if (OBJ_ID > 0 && $this->param('user_id') > 0) {
            return $this->asArray(
                $this->service->transferApplication(
                    OBJ_ID,
                    $this->param('user_id'),
                ),
            );
        }

        return null;
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function transferApplicationCancel(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->transferApplicationCancel(
                    OBJ_ID,
                ),
            );
        }

        return null;
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getListOfRoomNeighboors(): ?Response
    {
        return $this->asArray(
            $this->service->getListOfRoomNeighboors(
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function confirmGroupRequest(): ?Response
    {
        /** @var GroupService */
        $groupService = CMSVCHelper::getService('group');

        return $this->asArray(
            $groupService->confirmGroupRequest(OBJ_ID),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function declineGroupRequest(): ?Response
    {
        /** @var GroupService */
        $groupService = CMSVCHelper::getService('group');

        return $this->asArray(
            $groupService->declineGroupRequest(OBJ_ID),
        );
    }
}
