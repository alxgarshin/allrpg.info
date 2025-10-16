<?php

declare(strict_types=1);

namespace App\CMSVC\Group;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
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
    public function confirmGroupRequest(): ?Response
    {
        $groupService = $this->getService();

        return $this->asArray(
            $groupService->confirmGroupRequest(OBJ_ID),
        );
    }

    public function declineGroupRequest(): ?Response
    {
        $groupService = $this->getService();

        return $this->asArray(
            $groupService->declineGroupRequest(OBJ_ID),
        );
    }

    public function getListOfGroupsByCharacterOrApplication(): ?Response
    {
        $groupService = $this->getService();

        return $this->asArray(
            $groupService->getListOfGroupsByCharacterOrApplication(OBJ_ID, OBJ_TYPE),
        );
    }

    public function getChildGroups(): ?Response
    {
        $groupService = $this->getService();

        return $this->asArray(
            $groupService->getChildGroups(
                is_null(OBJ_ID) ? null : (int) OBJ_ID,
                (int) ($_REQUEST['group_id'] ?? false),
            ),
        );
    }

    public function getResponsibleGamemaster(): ?Response
    {
        $groupService = $this->getService();

        return $this->asArray(
            $groupService->getResponsibleGamemaster((int) OBJ_ID),
        );
    }

    public function changeCharacterCode(): ?Response
    {
        $groupService = $this->getService();

        return $this->asArray(
            $groupService->changeCharacterCode(
                OBJ_ID,
                (int) ($_REQUEST['group_id'] ?? false),
                (int) ($_REQUEST['after_obj_id'] ?? false),
            ),
        );
    }

    public function changeGroupCode(): ?Response
    {
        $groupService = $this->getService();

        return $this->asArray(
            $groupService->changeGroupCode(
                OBJ_ID,
                $_REQUEST['level'] ?? false,
                (int) ($_REQUEST['after_obj_id'] ?? false),
            ),
        );
    }
}
