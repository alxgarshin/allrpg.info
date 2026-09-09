<?php

declare(strict_types=1);

namespace App\CMSVC\Project;

use App\CMSVC\Trait\RequestCheckSearchTrait;
use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ActEnum, ActionEnum, ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Helper\{CookieHelper, DataHelper, ResponseHelper};
use Fraym\Interface\Response;
use Fraym\Response\ArrayResponse;

/** @extends BaseController<ProjectService> */
#[CMSVC(
    model: ProjectModel::class,
    service: ProjectService::class,
    view: ProjectView::class,
)]
class ProjectController extends BaseController
{
    use RequestCheckSearchTrait;

    public function Response(): ?Response
    {
        $id = DataHelper::getId();

        if (is_null($id) && (ActionEnum::init() !== ActionEnum::create || ($_REQUEST['search'] ?? false)) && DataHelper::getActDefault($this->entity) === ActEnum::list) {
            $this->requestCheckSearch();
        }

        if (!CURRENT_USER->isLogged() && DataHelper::getActDefault($this->entity) === ActEnum::add) {
            ResponseHelper::redirect('/login/', ['redirectToKind' => KIND, 'redirectToId' => $id, 'redirectParams' => 'act=add']);
        }

        $appId = (int) ($_REQUEST['application_id'] ?? false);

        if ($appId > 0 && CURRENT_USER->isLogged()) {
            ResponseHelper::redirect('/application/' . $appId . '/');
        }

        /** @var ProjectView */
        $projectView = $this->CMSVC->view;

        if (is_null($id) && (ActionEnum::init() !== ActionEnum::create || ($_REQUEST['search'] ?? false)) && DataHelper::getActDefault($this->entity) === ActEnum::list) {
            return $projectView->List();
        }

        if ($id > 0 && ($_REQUEST['show'] ?? false) === 'wall' && BID > 0 && $this->service->hasProjectAccess()) {
            return $projectView->Wall();
        }

        if ($id > 0 && ($_REQUEST['show'] ?? false) === 'conversation' && BID > 0 && $this->service->hasProjectAccess()) {
            return $projectView->Conversation();
        }

        return parent::Response();
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getAccess(): ?Response
    {
        $result = RightsHelper::getAccess(KIND);

        $childObjId = (int) CookieHelper::getCookie('additional_redirectid');
        $childObjType = CookieHelper::getCookie('additional_redirectobj') ? DataHelper::addBraces(CookieHelper::getCookie('additional_redirectobj')) : null;

        if ($childObjType && $childObjId > 0) {
            if (RightsHelper::checkRights('{child}', DataHelper::addBraces(KIND), DataHelper::getId(), $childObjType, $childObjId)) {
                if (!RightsHelper::checkAnyRights($childObjType, $childObjId)) {
                    RightsHelper::getAccess($childObjType, $childObjId);
                    CookieHelper::batchDeleteCookie(['additional_redirectid', 'additional_redirectobj']);
                }
            }
        }

        return new ArrayResponse(is_array($result) ? $result : []);
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function removeAccess(): void
    {
        RightsHelper::removeAccess(KIND);
    }

    #[ApiAction(params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('limit', ApiParamTypeEnum::int, default: 0),
        new ApiParam('search_string', ApiParamTypeEnum::string, default: ''),
    ])]
    public function loadProjectsCommunitiesList(): ?Response
    {
        if (OBJ_TYPE) {
            $projectService = $this->service;

            return $this->asArray(
                $projectService->loadProjectsCommunitiesList(
                    OBJ_TYPE,
                    $this->param('limit'),
                    $this->param('search_string'),
                ),
            );
        }

        return null;
    }

    #[ApiAction(params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getCommunityOrProjectMembersList(): ?Response
    {
        if (CURRENT_USER->isLogged()) {
            $projectService = $this->service;

            return $this->asArray(
                $projectService->getCommunityOrProjectMembersList(
                    OBJ_TYPE,
                    OBJ_ID,
                ),
            );
        }

        return null;
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('task_id', ApiParamTypeEnum::int, default: 0),
    ])]
    public function getCommunityOrProjectTasksList(): ?Response
    {
        if (CURRENT_USER->isLogged()) {
            $projectService = $this->service;
            $taskId = $this->param('task_id');

            return $this->asArray(
                $projectService->getCommunityOrProjectTasksList(
                    OBJ_ID,
                    OBJ_TYPE,
                    $taskId > 0 ? $taskId : null,
                ),
            );
        }

        return null;
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function switchProjectStatus(): ?Response
    {
        if (ALLOW_PROJECT_ACTIONS) {
            $projectService = $this->service;

            return $this->asArray(
                $projectService->switchProjectStatus(
                    OBJ_ID,
                ),
            );
        }

        return null;
    }
}
