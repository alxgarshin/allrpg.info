<?php

declare(strict_types=1);

namespace App\CMSVC\Task;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Enum\ActionEnum;
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
                if ($this->getService()->getObjId() === 0 || RightsHelper::checkAnyRights($this->getService()->getObjType(), $this->getService()->getObjId())) {
                    if ((int) $_REQUEST['following_task'][0] !== (int) $_REQUEST['parent_task'][0] || (int) $_REQUEST['parent_task'][0] === 0) {
                    } else {
                        $LOCALE = $this->getLOCALE();
                        ResponseHelper::responseOneBlock(
                            'error',
                            $LOCALE['cant_follow_parent'],
                            ['following_task[0]', 'parent_task[0]'],
                        );
                    }
                } else {
                    $LOCALE = $this->getLOCALE();
                    ResponseHelper::responseOneBlock(
                        'error',
                        $LOCALE['have_no_rights'] . ' ' . ($this->getService()->getObjType() === 'project' ? $LOCALE['have_no_rights_project'] : $LOCALE['have_no_rights_community']) . '.',
                    );
                }
            } else {
                $LOCALE = $this->getLOCALE();
                ResponseHelper::responseOneBlock(
                    'error',
                    $LOCALE['have_no_rights_in_this_task'],
                );
            }
        }

        return parent::Response();
    }

    #[IsAccessible]
    public function loadTasksList(): ?Response
    {
        $taskService = $this->getService();

        return $this->asArray(
            $taskService->loadTasks(
                $_REQUEST['obj_group'] ?? '',
                false,
                false,
            ),
        );
    }

    #[IsAccessible]
    public function loadTasks(): ?Response
    {
        $taskService = $this->getService();

        return $this->asArray(
            $taskService->loadTasks(
                $_REQUEST['obj_group'] ?? '',
                ($_REQUEST['show_list'] ?? '') === 'true',
                ($_REQUEST['widget_style'] ?? '') === 'true',
            ),
        );
    }

    #[IsAccessible]
    public function checkDatesAvailability(): ?Response
    {
        $taskService = $this->getService();

        return $this->asArray(
            $taskService->checkDatesAvailability(
                OBJ_TYPE,
                OBJ_ID,
                is_null($_REQUEST['responsible_id'] ?? null) ? null : (int) $_REQUEST['responsible_id'],
                explode(',', $_REQUEST['user_ids'] ?? []),
                $_REQUEST['date_from'] ?? null,
                $_REQUEST['date_to'] ?? null,
            ),
        );
    }

    #[IsAccessible]
    public function addTask(): ?Response
    {
        $taskService = $this->getService();

        return $this->asArray(
            $taskService->addTask(
                $_REQUEST['name'] ?? null,
            ),
        );
    }

    #[IsAccessible]
    public function changeTaskDates(): ?Response
    {
        $taskService = $this->getService();

        return $this->asArray(
            $taskService->changeTaskDates(
                $_REQUEST['date_from'] ?? '',
                $_REQUEST['date_to'] ?? '',
            ),
        );
    }

    #[IsAccessible]
    public function outdentTask(): ?Response
    {
        $taskService = $this->getService();

        return $this->asArray(
            $taskService->outdentTask(
                (int) ($_REQUEST['parent_task_id'] ?? false),
            ),
        );
    }

    #[IsAccessible]
    public function indentTask(): ?Response
    {
        $taskService = $this->getService();

        return $this->asArray(
            $taskService->indentTask(
                (int) ($_REQUEST['parent_task_id'] ?? false),
            ),
        );
    }

    public function getAccess(): ?Response
    {
        $result = RightsHelper::getAccess(KIND);

        return new ArrayResponse(is_array($result) ? $result : []);
    }

    public function removeAccess(): void
    {
        RightsHelper::removeAccess(KIND);
    }
}
