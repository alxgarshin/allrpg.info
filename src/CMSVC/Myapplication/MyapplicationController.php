<?php

declare(strict_types=1);

namespace App\CMSVC\Myapplication;

use App\CMSVC\Application\ApplicationModel;
use App\CMSVC\User\UserService;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ActEnum, ApiParamSourceEnum, ApiParamTypeEnum};
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
        if (!CURRENT_USER->isLogged() && !(DataHelper::getActDefault($this->entity) === ActEnum::add && !DataHelper::getId() && (int) ($_REQUEST['project_id'] ?? false) === 0)) {
            ResponseHelper::redirect(
                '/login/',
                [
                    'redirectToKind' => KIND,
                    'redirectToId' => DataHelper::getId(),
                    'redirectParams' => ((int) ($_REQUEST['project_id'] ?? false) > 0 ? 'act=add&project_id=' . (int) $_REQUEST['project_id'] : (DataHelper::getActDefault($this->entity) === ActEnum::add ? 'act=add' : '')),
                ],
            );
        }

        if ($_REQUEST['for_project_id'] ?? false) {
            $this->service->responseIfForProjectIdIsSet();
        }

        if (DataHelper::getActDefault($this->entity) === ActEnum::add) {
            if (!DataHelper::getId() && (int) ($_REQUEST['project_id'] ?? false) === 0) {
                /** @var MyapplicationView */
                $myapplicationView = $this->CMSVC->view;

                return $myapplicationView->addApplicationProjectsList();
            } elseif (!$this->service->checkRightsAdd()) {
                /** @var UserService */
                $userService = CMSVCHelper::getService('user');

                $profileCompletion = $userService->calculateProfileCompletion(CURRENT_USER->id());

                if (!$profileCompletion) {
                    /** @var MyapplicationView */
                    $myapplicationView = $this->CMSVC->view;

                    return $myapplicationView->addApplicationProfileCompletionError();
                }
            }
        }

        return parent::Response();
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('project_application_id_hidden[0]', ApiParamTypeEnum::int, obligatory: true),
        new ApiParam('project_payment_type_id[0]', ApiParamTypeEnum::int, obligatory: true),
        new ApiParam('amount[0]', ApiParamTypeEnum::int, obligatory: true),
        new ApiParam('payment_datetime[0]', ApiParamTypeEnum::string),
        new ApiParam('content[0]', ApiParamTypeEnum::string),
    ])]
    public function createTransaction(): ?Response
    {
        return $this->service->createTransaction();
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function acceptApplication(): ?Response
    {
        if (OBJ_ID > 0) {
            $this->service->acceptApplication(
                OBJ_ID,
            );
        }

        return null;
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function declineApplication(): ?Response
    {
        if (OBJ_ID > 0) {
            $this->service->declineApplication(
                OBJ_ID,
            );
        }

        return null;
    }

    #[IsAccessible]
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

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('application_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('user_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('room_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function addNeighboorRequest(): ?Response
    {
        return $this->asArray(
            $this->service->addNeighboorRequest(
                $this->param('application_id'),
                $this->param('user_id'),
                $this->param('room_id'),
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('prev_obj_id', ApiParamTypeEnum::int, default: 0),
    ])]
    public function getListOfGroups(): ?Response
    {
        return $this->asArray(
            $this->service->getListOfGroups(
                OBJ_ID,
                $this->param('prev_obj_id'),
            ),
        );
    }
}
