<?php

declare(strict_types=1);

namespace App\CMSVC\Task;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ActionEnum, ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Helper\{DataHelper, ResponseHelper};
use Fraym\Interface\Response;
use Fraym\Response\ArrayResponse;

/** @extends BaseController<TaskService> */
#[CMSVC(
    model: TaskModel::class,
    service: TaskService::class,
    view: TaskView::class,
)]
class TaskController extends BaseController
{
    public function Response(): ?Response
    {
        if (!CURRENT_USER->isLogged()) {
            if (OBJ_TYPE && OBJ_ID > 0) {
                ResponseHelper::redirect(
                    '/login/',
                    [
                        'redirectToKind' => DataHelper::clearBraces(OBJ_TYPE),
                        'redirectToId' => (string) OBJ_ID,
                        'additional_redirectobj' => KIND,
                        'additional_redirectid' => DataHelper::getId(),
                    ],
                );
            }

            ResponseHelper::redirect('/login/', ['redirectToKind' => KIND, 'redirectToId' => DataHelper::getId()]);
        }

        if (in_array(ACTION, ActionEnum::getBaseValues())) {
            if (ACTION === ActionEnum::create || (DataHelper::getId() && RightsHelper::checkRights(['{admin}', '{responsible}'], '{task}', DataHelper::getId()))) {
                if ($this->service->getObjId() === 0 || RightsHelper::checkAnyRights($this->service->getObjType(), $this->service->getObjId())) {
                    if ((int) $_REQUEST['following_task'][0] !== (int) $_REQUEST['parent_task'][0] || (int) $_REQUEST['parent_task'][0] === 0) {
                    } else {
                        $LOCALE = $this->LOCALE;
                        ResponseHelper::responseOneBlock(
                            'error',
                            $LOCALE['cant_follow_parent'],
                            ['following_task[0]', 'parent_task[0]'],
                        );
                    }
                } else {
                    $LOCALE = $this->LOCALE;
                    ResponseHelper::responseOneBlock(
                        'error',
                        $LOCALE['have_no_rights'] . ' ' . ($this->service->getObjType() === 'project' ? $LOCALE['have_no_rights_project'] : $LOCALE['have_no_rights_community']) . '.',
                    );
                }
            } else {
                $LOCALE = $this->LOCALE;
                ResponseHelper::responseOneBlock(
                    'error',
                    $LOCALE['have_no_rights_in_this_task'],
                );
            }
        }

        return parent::Response();
    }

    #[IsAccessible]
    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_group', ApiParamTypeEnum::string, default: ''),
    ])]
    public function loadTasksList(): ?Response
    {
        $taskService = $this->service;

        return $this->asArray(
            $taskService->loadTasks(
                $this->param('obj_group'),
                false,
                false,
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_group', ApiParamTypeEnum::string, default: ''),
        new ApiParam('show_list', ApiParamTypeEnum::bool, default: false),
        new ApiParam('widget_style', ApiParamTypeEnum::bool, default: false),
    ])]
    public function loadTasks(): ?Response
    {
        $taskService = $this->service;

        return $this->asArray(
            $taskService->loadTasks(
                $this->param('obj_group'),
                $this->param('show_list'),
                $this->param('widget_style'),
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('responsible_id', ApiParamTypeEnum::int),
        new ApiParam('user_ids', ApiParamTypeEnum::string, default: ''),
        new ApiParam('date_from', ApiParamTypeEnum::string),
        new ApiParam('date_to', ApiParamTypeEnum::string),
    ])]
    public function checkDatesAvailability(): ?Response
    {
        $taskService = $this->service;

        return $this->asArray(
            $taskService->checkDatesAvailability(
                OBJ_TYPE,
                OBJ_ID,
                $this->param('responsible_id'),
                explode(',', $this->param('user_ids')),
                $this->param('date_from'),
                $this->param('date_to'),
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('name', ApiParamTypeEnum::string, obligatory: true),
    ])]
    public function addTask(): ?Response
    {
        $taskService = $this->service;

        return $this->asArray(
            $taskService->addTask(
                $this->param('name'),
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('date_from', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('date_to', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function changeTaskDates(): ?Response
    {
        $taskService = $this->service;

        return $this->asArray(
            $taskService->changeTaskDates(
                $this->param('date_from'),
                $this->param('date_to'),
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('parent_task_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function outdentTask(): ?Response
    {
        $taskService = $this->service;

        return $this->asArray(
            $taskService->outdentTask(
                $this->param('parent_task_id'),
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('parent_task_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function indentTask(): ?Response
    {
        $taskService = $this->service;

        return $this->asArray(
            $taskService->indentTask(
                $this->param('parent_task_id'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getAccess(): ?Response
    {
        $result = RightsHelper::getAccess(KIND);

        return new ArrayResponse(is_array($result) ? $result : []);
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function removeAccess(): void
    {
        RightsHelper::removeAccess(KIND);
    }
}
