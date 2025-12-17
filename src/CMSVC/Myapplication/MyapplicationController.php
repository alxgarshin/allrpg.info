<?php

declare(strict_types=1);

namespace App\CMSVC\Myapplication;

use App\CMSVC\Application\ApplicationModel;
use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Enum\ActEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper, ResponseHelper};
use Fraym\Interface\Response;

/** @extends BaseController<MyapplicationService> */
#[CMSVC(
    model: ApplicationModel::class,
    service: MyapplicationService::class,
    view: MyapplicationView::class,
)]
class MyapplicationController extends BaseController
{
    public function Response(): ?Response
    {
        if (!CURRENT_USER->isLogged() && !(DataHelper::getActDefault($this->getEntity()) === ActEnum::add && !DataHelper::getId() && (int) ($_REQUEST['project_id'] ?? false) === 0)) {
            $forProjectId = (int) ($_REQUEST['for_project_id'] ?? false);
            $characterId = (int) ($_REQUEST['character_id'] ?? false);

            ResponseHelper::redirect(
                '/login/',
                [
                    'redirectToKind' => KIND,
                    'redirectToId' => DataHelper::getId(),
                    'redirectParams' => ((int) ($_REQUEST['project_id'] ?? false) > 0 ? 'act=add&project_id=' . (int) $_REQUEST['project_id'] : ($forProjectId > 0 ? 'for_project_id=' . $forProjectId . ($characterId > 0 ? '&character_id=' . $characterId : '') : (DataHelper::getActDefault($this->getEntity()) === ActEnum::add ? 'act=add' : ''))),
                ],
            );
        }

        if ($_REQUEST['for_project_id'] ?? false) {
            $this->getService()->responseIfForProjectIdIsSet();
        }

        if (DataHelper::getActDefault($this->getEntity()) === ActEnum::add) {
            if (!DataHelper::getId() && (int) ($_REQUEST['project_id'] ?? false) === 0) {
                /** @var MyapplicationView */
                $myapplicationView = $this->getCMSVC()->getView();

                return $myapplicationView->addApplicationProjectsList();
            } elseif (!$this->getService()->checkRightsAdd()) {
                /** @var UserService */
                $userService = CMSVCHelper::getService('user');

                $profileCompletion = $userService->calculateProfileCompletion(CURRENT_USER->id());

                if (!$profileCompletion) {
                    /** @var MyapplicationView */
                    $myapplicationView = $this->getCMSVC()->getView();

                    return $myapplicationView->addApplicationProfileCompletionError();
                }
            }
        }

        return parent::Response();
    }

    #[IsAccessible]
    public function createTransaction(): ?Response
    {
        return $this->getService()->createTransaction();
    }

    #[IsAccessible]
    public function acceptApplication(): ?Response
    {
        if (OBJ_ID > 0) {
            $this->getService()->acceptApplication(
                OBJ_ID,
            );
        }

        return null;
    }

    #[IsAccessible]
    public function declineApplication(): ?Response
    {
        if (OBJ_ID > 0) {
            $this->getService()->declineApplication(
                OBJ_ID,
            );
        }

        return null;
    }

    #[IsAccessible]
    public function getListOfRoomNeighboors(): ?Response
    {
        return $this->asArray(
            $this->getService()->getListOfRoomNeighboors(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible]
    public function addNeighboorRequest(): ?Response
    {
        return $this->asArray(
            $this->getService()->addNeighboorRequest(
                (int) ($_REQUEST['application_id'] ?? false),
                (int) ($_REQUEST['user_id'] ?? false),
                (int) ($_REQUEST['room_id'] ?? false),
            ),
        );
    }

    #[IsAccessible]
    public function getListOfGroups(): ?Response
    {
        return $this->asArray(
            $this->getService()->getListOfGroups(
                OBJ_ID,
                (int) ($_REQUEST['prev_obj_id'] ?? false),
            ),
        );
    }
}
