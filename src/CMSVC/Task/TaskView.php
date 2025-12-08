<?php

declare(strict_types=1);

namespace App\CMSVC\Task;

use App\CMSVC\User\UserService;
use App\Helper\{DesignHelper, MessageHelper, RightsHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{ActEnum, EscapeModeEnum, SubstituteDataTypeEnum, TableFieldOrderEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

#[TableEntity(
    'task',
    'task_and_event',
    [
        new EntitySortingItem(
            tableFieldName: 'date_from',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'name',
            tableFieldOrder: TableFieldOrderEnum::DESC,
        ),
        new EntitySortingItem(
            doNotUseIfNotSortedByThisField: true,
            tableFieldName: 'date_from',
        ),
        new EntitySortingItem(
            doNotUseIfNotSortedByThisField: true,
            tableFieldName: 'date_to',
        ),
        new EntitySortingItem(
            tableFieldName: 'status',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortStatus',
        ),
    ],
    useCustomView: true,
    defaultItemActType: ActEnum::view,
)]
#[Rights(
    viewRight: 'checkViewRights',
    addRight: 'checkAddRights',
    changeRight: 'checkChangeRights',
    deleteRight: 'checkChangeRights',
)]
#[Controller(TaskController::class)]
class TaskView extends BaseView
{
    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $html = $response->getHtml();

        $html = str_replace('<h1 class="form_header"><a href="https://www.allrpg.loc/task/"', '<h1 class="form_header"><a href="/tasklist/"', $html);

        $response->setHtml($html);

        return $response;
    }

    public function Response(): ?Response
    {
        /** @var TaskService $taskService */
        $taskService = $this->service;

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->LOCALE;
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $objData = $taskService->get(DataHelper::getId());

        if (!$objData) {
            return null;
        }

        $parentObjId = $taskService->getObjId();
        $parentObjType = $taskService->getObjType();
        $taskAdmin = $taskService->isTaskAdmin($objData->id->getAsInt());
        $taskResponsible = $taskService->isTaskResponsible($objData->id->getAsInt());
        $taskAccess = $taskService->hasTaskAccess($objData->id->getAsInt());
        $taskParentAccess = $taskService->hasTaskParentAccess();
        $accessToChilds = $taskService->hasAccessToChilds();

        if (!($taskAccess || $taskParentAccess)) {
            return null;
        }
        $parentObjData = $taskService->getParentData();

        $objType = 'task';

        [$stateName, $stateCssClass] = $taskService->getStateNameAndCss($objData);

        $percentHtml = null;

        if ($objData->percentage->get() > 0) {
            $percent = (int) $objData->percentage->get();
            $percentHtml = '<div class="task_percentage" title="' . $LOCALE['percentage'] . '" style="background-position: left ' . ($percent / 100 * 3.36) . 'em bottom 0px;">' . $percent . '%</div>';
        }

        $childTaskDataStr = null;
        $childTasksIds = $taskService->getChildTasksIds();

        if (!is_null($childTasksIds)) {
            $childTasksData = $taskService->getAll(['id' => $childTasksIds], false, ['name']);

            foreach ($childTasksData as $childTaskData) {
                $childTaskDataStr .= '<a href="' . ABSOLUTE_PATH . '/task/' . $childTaskData->id->get() . '/" class="child_task' . ($childTaskData->status->get() === '{delayed}' || $childTaskData->status->get() === '{closed}' ? ' closed_child_task' : '') . '">' . DataHelper::escapeOutput($childTaskData->name->get()) . '</a>';
            }
        }

        $precedingTaskDataStr = null;
        $pregoingTasksIds = $taskService->getPregoingTasksIds();

        if (!is_null($pregoingTasksIds)) {
            $preceding_task_data = $taskService->getAll(['id' => $pregoingTasksIds], false, ['name']);

            foreach ($preceding_task_data as $pregoingTaskItem) {
                $precedingTaskDataStr .= '<a href="' . ABSOLUTE_PATH . '/task/' . $pregoingTaskItem->id->getAsInt() . '/">' . DataHelper::escapeOutput($pregoingTaskItem->name->get()) . '</a>, ';
            }
            $precedingTaskDataStr = mb_substr($precedingTaskDataStr, 0, mb_strlen($precedingTaskDataStr) - 2);
        }

        $placeHtml = $objData->place->asHTML(false);

        $resultHtml = $objData->result->asHTML(false);

        if ($resultHtml !== '') {
            $resultHtml = '<div class="block" id="task_wall">
        <h2>' . $LOCALE['result'] . '</h2>
<div class="publication_content">' . TextHelper::basePrepareText($resultHtml) . '</div>
<h2>' . $LOCALE['history'] . '</h2>';
        }

        $parentTask = $taskService->getParentTask();
        $followingTask = $taskService->getFollowingTask();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE['title']);
        $RESPONSE_DATA = '';

        $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
    <div class="page_block">
        <div class="object_info">
            <div class="object_info_1">
                <a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/" class="object_avatar"><div style="' . DesignHelper::getCssBackgroundImage(ABSOLUTE_PATH . '/' . $_ENV['DESIGN_PATH'] . 'no_avatar_task.svg') . '"></div></a>
            </div>
            <div class="object_info_2">
            <div class="task_dates">
                <div>' . ($objData->date_from->get() ? $objData->date_from->getAsUsualDateTime() : '<span class="gray">' . $LOCALE['no_start_date'] . '</span>') . '</div>
                <div>' . ($objData->date_to->get() ? $objData->date_to->getAsUsualDateTime() : '<span class="gray">' . $LOCALE['no_due_date'] . '</span>') . '</div>
            </div>
            <div class="task_state ' . $stateCssClass . '" title="' . $LOCALE['state_name_lowercase'] . '">' . $stateName . '</div>
            ' . ($percentHtml ?? '') . '
            <h1><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/">' . DataHelper::escapeOutput($objData->name->get()) . '</a></h1>
            ' . ($objData->description->get() ? '<div class="overflown_content em15"><div class="object_description">' . TextHelper::basePrepareText(DataHelper::escapeOutput($objData->description->get(), EscapeModeEnum::forHTMLforceNewLines)) . '</div></div>
            <a class="show_hidden">' . $LOCALE_GLOBAL['show_next'] . '</a>' : '') . '
            <div class="object_info_2_additional">
                ' . (($parentObjType !== '' && $parentObjId > 0 && $parentObjId !== 'all') ? '<span class="gray">' . ($parentObjType === 'project' ? $LOCALE['project'] : $LOCALE['community']) . ':</span><a href="' . ABSOLUTE_PATH . '/' . $parentObjType . '/' . $parentObjId . '/#tasks">' . DataHelper::escapeOutput($parentObjData['name']) . '</a><br>' : '') . '
                ' . ($parentTask ? '<span class="gray">' . $LOCALE['parent_task'] . ':</span><a href="' . ABSOLUTE_PATH . '/task/' . $parentTask->id->getAsInt() . '/">' . DataHelper::escapeOutput($parentTask->name->get()) . '</a><br>' : '') .
            ($childTaskDataStr ? '<span class="gray">' . $LOCALE['child_task'] . ':</span>' . $childTaskDataStr : '') .
            ($followingTask ? '<span class="gray">' . $LOCALE['following_task'] . ':</span><a href="' . ABSOLUTE_PATH . '/task/' . $followingTask->id->getAsInt() . '/">' . DataHelper::escapeOutput($followingTask->name->get()) . '</a><br>' : '') .
            ($precedingTaskDataStr ? '<span class="gray">' . $LOCALE['preceding_task'] . ':</span>' . $precedingTaskDataStr . '<br>' : '') .
            ($placeHtml ? '<span class="gray">' . $LOCALE['place'] . ':</span>' . $placeHtml . '<br>' : '') . '
                <div class="task_status"><span class="gray">' . $LOCALE['status'] . ':</span>' . $objData->status->asHTML(false) . '</div>
                <div class="task_priority"><span class="gray">' . $LOCALE['priority'] . ':</span>' . $objData->priority->asHTML(false) . '</div>
            </div>
        </div>
        <div class="object_info_3">';

        $responsibleId = RightsHelper::findOneByRights('{responsible}', '{task}', $objData->id->getAsInt(), '{user}', false);

        if ($responsibleId > 0) {
            $authorData = $userService->get($responsibleId);
        } else {
            $authorData = $userService->get($objData->creator_id->getAsInt());
        }

        if ($authorData) {
            $RESPONSE_DATA .= '
            <div class="object_author"><span>' . ($responsibleId > 0 ? $LOCALE['group_responsibles'] : $LOCALE['author']) . ':</span>
            <span class="sbi sbi-send"></span>' . $userService->showName($authorData, true) . '</div>';
        }

        $membersCount = count(array_unique(RightsHelper::findByRights('{member}', '{' . KIND . '}', $objData->id->getAsInt(), '{user}', false)));
        $RESPONSE_DATA .= '
                <div class="object_members"><span>' . $LOCALE['members'] . ':</span>
                <div>' . $membersCount . '</div></div>
                ' . UniversalHelper::drawImportant($objType, $objData->id->getAsInt()) . '
                <div class="actions_list_switcher">';

        if ($taskService->hasTaskAccess($objData->id->getAsInt()) || CURRENT_USER->isAdmin() || $userService->isModerator()) {
            $RESPONSE_DATA .= '
                    <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                    <div class="actions_list_items">';

            if ($taskAdmin || $taskResponsible || CURRENT_USER->isAdmin() || $userService->isModerator()) {
                $RESPONSE_DATA .= '<a class="main" href="' . ABSOLUTE_PATH . '/task/' . $objData->id->getAsInt() . '/act=edit">' . $LOCALE['edit_task'] . '</a>';
            }

            $RESPONSE_DATA .= '<a class="nonimportant project_user_add" obj_type="task" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE['invite_into_task'] . '</a>';

            if ($taskAdmin && !RightsHelper::checkRights('{child}', '{task}', DataHelper::getId(), '{conversation}')) {
                $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/conversation/act=add&obj_type=task&obj_id=' . DataHelper::getId() . '">' . $LOCALE['create_task_chat'] . '</a>';
            } else {
                $conversationId = RightsHelper::findOneByRights('{child}', '{task}', DataHelper::getId(), '{conversation}');

                if ($conversationId > 0 && RightsHelper::checkRights('{member}', '{conversation}', $conversationId)) {
                    $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/conversation/' . $conversationId . '/#bottom">' . $LOCALE['open_task_chat'] . '</a>';
                }
            }

            if (!$taskAdmin) {
                $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . DataHelper::getId() . '/action=remove_access" class="careful">' . $LOCALE['leave_task_capitalized'] . '</a>';
            }

            $RESPONSE_DATA .= '<a class="nonimportant show_qr_code no_dynamic_content">' . $LOCALE['show_qr_code'] . '</a>';

            $RESPONSE_DATA .= '
                </div>';
        } elseif ($taskParentAccess) {
            $checkAccessData = [];

            if (CURRENT_USER->isLogged()) {
                $checkAccessData = DB->query(
                    'SELECT c.id, cm.updated_at FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id WHERE cm.message_action_data="{task_id:' . DataHelper::getId() . '}" AND cm.message_action="{get_access}" AND cm.creator_id=' . CURRENT_USER->id(),
                    [],
                    true,
                );
            }

            $RESPONSE_DATA .= '
                <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . DataHelper::getId() . '/action=get_access"><span>' . ($checkAccessData ? $LOCALE['access_request_sent'] : ($accessToChilds ? $LOCALE['get_access'] : $LOCALE['request_access'])) . '</span></a></div>';
        }

        $RESPONSE_DATA .= '
                <div class="object_was_registered"><span>' . $LOCALE['was_registered'] . ':</span>' . $objData->created_at->getAsUsualDate() . '</div>
                </div>
            </div>
        </div>
    </div>
    <div class="page_block">';

        if ($taskAccess) {
            $RESPONSE_DATA .= '
        <div class="fraymtabs">
            <ul>
                <li><a id="history">' . $LOCALE['history'] . '</a></li>
                <li><a id="documents">' . $LOCALE['materials'] . '</a></li>
                <li><a id="members">' . ($taskAdmin ? $LOCALE['members_alt'] : $LOCALE['members']) . '</a></li>
            </ul>';

            $RESPONSE_DATA .= '
            <div id="fraymtabs-history">';

            $RESPONSE_DATA .= '
                ' . ($resultHtml ? $resultHtml : '<div class="block" id="task_wall">') . '
                    <div class="block_header task_comment_form">' . MessageHelper::conversationForm(null, '{task_comment}', DataHelper::getId(), $LOCALE['input_message']) . '</div>
                    <div class="block_data">';
            $result = DB->select(
                'conversation',
                [
                    'obj_type' => '{task_comment}',
                    'obj_id' => $objData->id->getAsInt(),
                ],
                false,
                ['updated_at DESC'],
            );
            $totalConversationCount = $conversation_count = count($result);
            $i = 0;

            foreach ($result as $conversation_data) {
                ++$i;
                $RESPONSE_DATA .= MessageHelper::conversationTask($conversation_data, $conversation_count);
                --$conversation_count;

                if ($i === 4 && $totalConversationCount > 4) {
                    $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>
					<div class="hidden">';
                }
            }

            if ($i > 4) {
                $RESPONSE_DATA .= '</div>';
            }
            $RESPONSE_DATA .= '
                    </div>
                </div>';

            $RESPONSE_DATA .= '
            </div>';

            $RESPONSE_DATA .= '
            <div id="fraymtabs-documents">';

            $RESPONSE_DATA .= '
                <a class="load_library" obj_type="{task}" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_hidden'] . '</a>';

            $RESPONSE_DATA .= '
            </div>';

            $RESPONSE_DATA .= '
            <div id="fraymtabs-members">';

            $RESPONSE_DATA .= '
                <div class="block task_users_list" id="task_users_list">
                    <a class="inner_add_something_button task_user_add" obj_type="task" obj_id="' . $objData->id->getAsInt() . '"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['invite_into_task'] . '</span></a>
                    <input type="text" name="user_rights_lookup" placehold="' . $LOCALE_GLOBAL['input_fio_id_for_search'] . '">
                    <div class="tabs_horizontal_shadow"></div>
                    <a class="load_users_list" obj_type="task" obj_id="' . $objData->id->getAsInt() . '" limit="0" shown_limit="50">' . $LOCALE_GLOBAL['show_next'] . '</a>
                </div>';

            $RESPONSE_DATA .= '
            </div>';

            $RESPONSE_DATA .= '
        </div>';
        }

        $RESPONSE_DATA .= '
    </div>
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
