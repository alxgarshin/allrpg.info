<?php

declare(strict_types=1);

namespace App\CMSVC\Project;

use App\CMSVC\User\UserService;
use App\Helper\{DateHelper, DesignHelper, FileHelper, MessageHelper, RightsHelper, TextHelper, UniversalHelper};
use DateTimeImmutable;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{ActEnum, EscapeModeEnum, TableFieldOrderEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

/** @extends BaseView<ProjectService> */
#[TableEntity(
    'project',
    'project',
    [
        new EntitySortingItem(
            tableFieldName: 'id',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            showFieldDataInEntityTable: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'name',
            tableFieldOrder: TableFieldOrderEnum::DESC,
        ),
        new EntitySortingItem(
            tableFieldName: 'date_from',
        ),
        new EntitySortingItem(
            tableFieldName: 'date_to',
        ),
    ],
    useCustomView: true,
    defaultItemActType: ActEnum::view,
)]
#[Rights(
    viewRight: true,
    addRight: 'checkRights',
    changeRight: 'checkRights',
    deleteRight: 'checkRights',
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(ProjectController::class)]
class ProjectView extends BaseView
{
    public function Response(): ?Response
    {
        $projectService = $this->getService();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_MYAPPLICATION = LocaleHelper::getLocale(['myapplication', 'global']);

        $objData = $projectService->get(DataHelper::getId());

        if (!$objData) {
            return null;
        }

        $objType = 'project';

        $projectAdmin = $projectService->isProjectAdmin();
        $projectGamemaster = $projectService->isProjectGamemaster();
        $projectMember = $projectService->isProjectMember();
        $projectAccess = $projectService->hasProjectAccess();

        $projectInfoData = $projectService->getProjectInfoData($objData->id->getAsInt());
        $applicationData = $projectService->getApplicationData($objData->id->getAsInt());
        $calendarEventData = $projectService->getCalendarEventData($objData);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE['title']);
        $RESPONSE_DATA = '';

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
    <div class="page_block">
        <div class="object_info">
            <div class="object_info_1">
                <a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/" class="object_avatar"><div style="' . DesignHelper::getCssBackgroundImage(FileHelper::getImagePath($objData->attachments->get(), FileHelper::getUploadNumByType('projects_and_communities_avatars')) ?? ABSOLUTE_PATH . '/' . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg') . '"></div></a>
            </div>
            <div class="object_info_2">';

        if ($projectAccess) {
            $RESPONSE_DATA .= '
                <div class="object_dates">
                    <div>' . $objData->date_from->getAsUsualDate() . '</div>
                    <div>' . $objData->date_to->getAsUsualDate() . '</div>
                </div>';
        }

        $RESPONSE_DATA .= '
                <h1><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/">' . DataHelper::escapeOutput($objData->name->get()) . '</a></h1>
                <div class="control_buttons">
                    ' . (($objData->status->get() === '1' && (!$objData->oneorderfromplayer->get() || is_null($applicationData)) && $projectInfoData['individual_field_id']) || !is_null($applicationData) ? '<a href="' . ABSOLUTE_PATH . '/go/' . $objData->id->getAsInt() . '/">' . ($applicationData ? $LOCALE['view_application'] : $LOCALE_MYAPPLICATION['send_individual_application']) . '</a>' : '') . '
                    ' . (($objData->status->get() === '1' && (!$objData->oneorderfromplayer->get() || is_null($applicationData)) && $projectInfoData['team_field_id']) ? '<a href="' . ABSOLUTE_PATH . '/myapplication/act=add&project_id=' . $objData->id->getAsInt() . '&application_type=1">' . $LOCALE_MYAPPLICATION['send_team_application'] . '</a>' : '') . '
                    ' . ($projectInfoData['group_id'] > 0 && $objData->show_roleslist->get() !== '1' ? '<a href="' . ABSOLUTE_PATH . '/roles/' . $objData->id->getAsInt() . '/">' . $LOCALE_MYAPPLICATION['roles_list'] . '</a>' : '') . '
	            </div>
                <div class="overflown_content em15"><div class="object_description">' . TextHelper::basePrepareText(DataHelper::escapeOutput($projectAccess ? $objData->description->get() : $objData->annotation->get(), EscapeModeEnum::plainHTML)) . '</div></div>
                <a class="show_hidden">' . $LOCALE_GLOBAL['show_next'] . '</a>
                <div class="object_info_2_additional">
                    
                </div>
            </div>
            <div class="object_info_3">';

        $authorData = $userService->get($objData->creator_id->getAsInt());

        if ($authorData->id->getAsInt() > 0) {
            $RESPONSE_DATA .= '
                <div class="object_author"><span>' . $LOCALE['author'] . ':</span>
                <span class="sbi sbi-send"></span>' . $userService->showName($authorData, true) . '</div>';
        }

        $membersCount = count(array_unique(RightsHelper::findByRights('{member}', '{' . KIND . '}', $objData->id->getAsInt(), '{user}', false)));
        $RESPONSE_DATA .= '
                <div class="object_members"><span>' . $LOCALE['members'] . ':</span>
                <div>' . $membersCount . '</div></div>
                
                ' . UniversalHelper::drawImportant($objType, $objData->id->getAsInt()) . '
                
                <div class="actions_list_switcher">';

        if ($projectService->hasProjectAccess() || CURRENT_USER->isAdmin() || $userService->isModerator()) {
            $RESPONSE_DATA .= '
                    <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                    <div class="actions_list_items">';

            if ($projectAdmin || CURRENT_USER->isAdmin() || $userService->isModerator()) {
                $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/act=edit">' . $LOCALE['edit_project'] . '</a>';
                $RESPONSE_DATA .= '<a action_request="project/switch_project_status" obj_id="' . $objData->id->getAsInt() . '">' . ($objData->status->get() === '1' ? $LOCALE['switch_status_to_off'] : $LOCALE['switch_status_to_on']) . '</a>';
            }

            if ($projectAdmin || $projectGamemaster) {
                $RESPONSE_DATA .= '<a class="nonimportant project_user_add" obj_type="project" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE['invite_to_project'] . '</a>';
                $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/task/task/act=add&obj_id=' . $objData->id->getAsInt() . '&obj_type=project">' . $LOCALE['add_task'] . '</a>';
                $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/event/event/act=add&obj_id=' . $objData->id->getAsInt() . '&obj_type=project">' . $LOCALE['add_event'] . '</a>';

                if (!RightsHelper::checkRights('{child}', '{project}', $objData->id->getAsInt(), '{conversation}')) {
                    if ($projectAdmin) {
                        $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/conversation/act=add&obj_type=project&obj_id=' . $objData->id->getAsInt() . '">' . $LOCALE['create_project_chat'] . '</a>';
                    }
                } else {
                    $conversation_id = RightsHelper::findOneByRights('{child}', '{project}', $objData->id->getAsInt(), '{conversation}');

                    if ($conversation_id > 0 && RightsHelper::checkRights('{member}', '{conversation}', $conversation_id)) {
                        $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/conversation/' . $conversation_id . '/#bottom">' . $LOCALE['open_project_chat'] . '</a>';
                    }
                }
            } elseif ($projectMember) {
                $RESPONSE_DATA .= '<a class="nonimportant project_user_add" obj_type="project" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE['invite_to_project'] . '</a>';

                if (RightsHelper::checkRights('{child}', '{project}', $objData->id->getAsInt(), '{conversation}')) {
                    $conversation_id = RightsHelper::findOneByRights('{child}', '{project}', $objData->id->getAsInt(), '{conversation}');

                    if ($conversation_id > 0 && RightsHelper::checkRights('{member}', '{conversation}', $conversation_id)) {
                        $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/conversation/' . $conversation_id . '/#bottom">' . $LOCALE['open_project_chat'] . '</a>';
                    }
                }

                $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/action=remove_access" class="careful">' . $LOCALE['leave_project'] . '</a>';
            } else {
                $checkAccessData = [];

                if (CURRENT_USER->isLogged()) {
                    $checkAccessData = DB->query(
                        'SELECT c.id, cm.updated_at FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id WHERE cm.message_action_data="{project_id:' . $objData->id->getAsInt() . '}" AND cm.message_action="{get_access}" AND cm.creator_id=' . CURRENT_USER->id(),
                        [],
                        true,
                    );
                }

                $RESPONSE_DATA .= '
                    <a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/action=get_access"><span>' . ($objData->type->get() === '{open}' ? $LOCALE['get_access'] : ($checkAccessData ? $LOCALE['access_request_sent'] : $LOCALE['request_access'])) . '</span></a>';
            }

            if ($applicationData) {
                $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/myapplication/' . $applicationData->id->getAsInt() . '/">' . $LOCALE['view_application'] . '</a>';
            }

            if ($objData->status->get() === '1' && (!$objData->oneorderfromplayer->get() || is_null($applicationData)) && ($projectInfoData['individual_field_id'] || $projectInfoData['team_field_id'])) {
                if ($projectInfoData['individual_field_id']) {
                    $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/go/' . $objData->id->getAsInt() . '/">' . $LOCALE['send_application'] . '</a>';
                }

                if ($projectInfoData['team_field_id']) {
                    $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/myapplication/act=add&project_id=' . $objData->id->getAsInt() . '&application_type=1">' . $LOCALE['send_team_application'] . '</a>';
                }
            }

            if ($projectInfoData['group_id'] > 0 && !$objData->show_roleslist->get()) {
                $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/roles/' . $objData->id->getAsInt() . '/">' . $LOCALE['roles_list'] . '</a>';
            }

            if (($calendarEventData['id'] ?? false) > 0) {
                $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendarEventData['id'] . '/">' . $LOCALE['calendar_event'] . '</a>';
            }

            $RESPONSE_DATA .= '<a class="show_qr_code no_dynamic_content">' . $LOCALE['show_qr_code'] . '</a>';

            $RESPONSE_DATA .= '
                </div>';
        } else {
            $checkAccessData = [];

            if (CURRENT_USER->isLogged()) {
                $checkAccessData = DB->query(
                    'SELECT c.id, cm.updated_at FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id WHERE cm.message_action_data="{project_id:' . $objData->id->getAsInt() . '}" AND cm.message_action="{get_access}" AND cm.creator_id=' . CURRENT_USER->id(),
                    [],
                    true,
                );
            }

            $RESPONSE_DATA .= '
                    <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/action=get_access"><span>' . ($objData->type->get() === '{open}' ? $LOCALE['get_access'] : ($checkAccessData ? $LOCALE['access_request_sent'] : $LOCALE['request_access'])) . '</span></a></div>';
        }

        $RESPONSE_DATA .= '
                <div class="object_was_registered"><span>' . $LOCALE['was_registered'] . ':</span>' . $objData->created_at->getAsUsualDate() . '</div>
                </div>
            </div>
        </div>
    </div>
    <div class="page_block">';

        if ($projectAccess) {
            /* $bindedProjects = RightsHelper::findByRights('{child}', '{project}', $objData->id->getAsInt(), '{project}');
            $bindedProjects = false;
            if ($_ENV['PROJECTS_NEED_COMMUNITY']) {
                $bindedProjects = RightsHelper::findByRights('{child}', '{project}', $objData->id->getAsInt(), '{project}');
            } */

            $RESPONSE_DATA .= '
        <div class="fraymtabs">
        <ul>
            <li><a id="wall">' . $LOCALE['wall'] . '</a></li>' . ($projectAdmin || $projectGamemaster ? '
            <li><a id="tasks">' . $LOCALE['tasks'] . '</a></li>
            <li><a id="documents">' . $LOCALE['documents'] . '</a></li>' : '') . '
            <li><a id="conversations">' . $LOCALE['conversations'] . '</a></li>
            <li><a id="members">' . ($projectAdmin ? $LOCALE['members_alt'] : $LOCALE['members']) . '</a></li>
        </ul>';

            $RESPONSE_DATA .= '
        <div id="fraymtabs-wall">';

            $future_events_data = DB->query(
                "SELECT DISTINCT te.* FROM task_and_event te LEFT JOIN relation r ON te.id=r.obj_id_from WHERE r.obj_type_to='{project}' AND r.obj_type_from='{event}' AND r.type='{child}' AND r.obj_id_to=:obj_id_to AND te.date_from>=CURDATE() ORDER BY te.date_from ASC",
                [
                    ['obj_id_to', $objData->id->getAsInt()],
                ],
            );
            $future_events_count = count($future_events_data);

            $past_events_data = DB->query(
                "SELECT DISTINCT te.* FROM task_and_event te LEFT JOIN relation r ON te.id=r.obj_id_from WHERE r.obj_type_to='{project}' AND r.obj_type_from='{event}' AND r.type='{child}' AND r.obj_id_to=:obj_id_to AND te.date_to<CURDATE() AND te.date_to>=SUBDATE(CURDATE(), 30) ORDER BY te.date_from DESC",
                [
                    ['obj_id_to', $objData->id->getAsInt()],
                ],
            );
            $past_events_count = count($past_events_data);

            if ($future_events_count > 0 || $past_events_count > 0) {
                $RESPONSE_DATA .= '<div class="project_events_tab">';

                if ($future_events_count > 0) {
                    $RESPONSE_DATA .= '
<div class="project_events_tab_header">
<h3><span class="sbi project_events_tab_icon project_events_tab_icon_future"></span>' . $LOCALE['future_events'] . '</h3>
</div>';

                    $i = 0;

                    foreach ($future_events_data as $event_data) {
                        ++$i;
                        $RESPONSE_DATA .= '<div class="project_event_small project_event_future">
<div class="project_event_small_name"><a href="' . ABSOLUTE_PATH . '/event/' . $event_data['id'] . '/">' . DataHelper::escapeOutput($event_data['name']) . '</a></div>
<div class="project_event_small_time">' . DateHelper::dateFromToCalendar(
                            strtotime($event_data['date_from']),
                            strtotime($event_data['date_to']),
                        ) . '</div>' . (DataHelper::escapeOutput($event_data['place']) !== '' ? '<div class="project_event_small_place">' . DataHelper::escapeOutput(
                            $event_data['place'],
                        ) . '</div>' : '') . '
</div>';

                        if ($i === 4 && $future_events_count > 4) {
                            $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>
<div class="hidden">';
                        }
                    }

                    if ($i > 4) {
                        $RESPONSE_DATA .= '</div>';
                    }
                }

                if ($past_events_count > 0) {
                    $RESPONSE_DATA .= '
<div class="project_events_tab_header project_events_tab_header_past">
<h3><span class="sbi project_events_tab_icon project_events_tab_icon_past"></span>' . $LOCALE['past_events'] . '</h3>
</div>';

                    $i = 0;

                    foreach ($past_events_data as $event_data) {
                        ++$i;
                        $RESPONSE_DATA .= '<div class="project_event_small project_event_past">
<div class="project_event_small_name"><a href="' . ABSOLUTE_PATH . '/event/' . $event_data['id'] . '/">' . DataHelper::escapeOutput($event_data['name']) . '</a></div>
<div class="project_event_small_time">' . DateHelper::dateFromToCalendar(
                            strtotime($event_data['date_from']),
                            strtotime($event_data['date_to']),
                        ) . '</div>' . (DataHelper::escapeOutput($event_data['place']) !== '' ? '<div class="project_event_small_place">' . DataHelper::escapeOutput(
                            $event_data['place'],
                        ) . '</div>' : '') . '
</div>';

                        if ($i === 4 && $past_events_count > 4) {
                            $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>
<div class="hidden">';
                        }
                    }

                    if ($i > 4) {
                        $RESPONSE_DATA .= '</div>';
                    }
                }

                $RESPONSE_DATA .= '
</div>';
            }

            if ($objData->show_budget_info->get()) {
                $fees_min = 0;
                $fee_options_data = DB->query(
                    "SELECT * FROM project_fee WHERE project_id=:project_id AND content='{menu}' AND (do_not_use_in_budget IS NULL OR do_not_use_in_budget='0')",
                    [
                        ['project_id', $objData->id->getAsInt()],
                    ],
                );

                foreach ($fee_options_data as $fee_option_data) {
                    $fee_option_date_data = DB->select(
                        'project_fee',
                        [
                            'parent' => $fee_option_data['id'],
                        ],
                        true,
                        [
                            'date_from',
                        ],
                        1,
                    );

                    if ($fee_option_date_data['id'] > 0) {
                        $fees_min += $fee_option_date_data['cost'];
                    }
                }

                if ($projectAdmin || $projectGamemaster || ($fees_min > 0 && $objData->player_count->get() > 0)) {
                    if ($fees_min > 0 && $objData->player_count->get() > 0) {
                        $budget_paid_data = DB->query(
                            'SELECT SUM(money_provided) as paid FROM project_application WHERE project_id=:project_id',
                            [
                                ['project_id', $objData->id->getAsInt()],
                            ],
                            true,
                        );
                        $fees_paid = (int) $budget_paid_data['paid'];
                        $fees_needed = ceil($fees_min * $objData->player_count->get());
                        $fees_percent = floor($fees_paid / ($fees_needed / 100));

                        if ($fees_percent > 100) {
                            $fees_percent = 100;
                        }

                        if ($fees_percent < 0) {
                            $fees_percent = 0;
                        }
                        $paid_width = $fees_percent;

                        if ($paid_width < 5) {
                            $paid_width = 5;
                        }

                        $RESPONSE_DATA .= '<div class="project_budget_info">
<div class="project_budget_info_needed">
<div class="project_budget_info_paid" style="width: ' . $paid_width . '%">' . $LOCALE['budget_info'] . ': ' . $fees_percent . '%</div>
' . (100 - $paid_width > 0 ? '<div class="project_budget_info_not_paid" style="width: ' . (100 - $paid_width) . '%"></div>' : '') . '
</div>
</div>';
                    } else {
                        $RESPONSE_DATA .= $LOCALE['budget_info_no_info'];
                    }
                }
            }

            $RESPONSE_DATA .= '
            <div class="block" id="project_wall">
                <div class="block_header">' . MessageHelper::conversationForm(null, '{project_wall}', $objData->id->getAsInt(), $LOCALE['wall_input_text']) . '</div>
                <div class="block_data">
	                <a class="load_wall" obj_type="{project_wall}" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE['show_previous'] . '</a>
                </div>
            </div>';

            $RESPONSE_DATA .= '
        </div>';

            if ($projectAdmin || $projectGamemaster) {
                $RESPONSE_DATA .= '
        <div id="fraymtabs-tasks">';

                $RESPONSE_DATA .= '
            <div class="fraymtabs">
                <ul>
                    <a class="inner_add_something_button" href="' . ABSOLUTE_PATH . '/task/task/act=add&obj_type=project&obj_id=' . $objData->id->getAsInt() . '"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['add_task'] . '</span></a>

                    <li><a id="task_mine">' . $LOCALE['tasks_mine'] . '<sup id="new_tasks_counter_mine"></sup></a></li>
                    <li><a id="task_membered">' . $LOCALE['tasks_membered'] . '<sup id="new_tasks_counter_membered"></sup></a></li>
                    <li><a id="task_notmembered">' . $LOCALE['tasks_notmembered'] . '</a></li>
                    <li><a id="task_delayed">' . $LOCALE['tasks_delayed'] . '<sup id="new_tasks_counter_delayed"></sup></a></li>
                    <li><a id="task_closed">' . $LOCALE['tasks_closed'] . '<sup id="new_tasks_counter_closed"></sup></a></li>
                </ul>
                <div id="fraymtabs-task_mine"><a class="load_tasks_list" obj_group="project" obj_type="mine" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
                <div id="fraymtabs-task_membered"><a class="load_tasks_list" obj_group="project" obj_type="membered" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
                <div id="fraymtabs-task_notmembered"><a class="load_tasks_list" obj_group="project" obj_type="notmembered" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
                <div id="fraymtabs-task_delayed"><a class="load_tasks_list" obj_group="project" obj_type="delayed" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
                <div id="fraymtabs-task_closed"><a class="load_tasks_list" obj_group="project" obj_type="closed" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
            </div>
            <script>
                window["newTasksCounterObjTypeCache"]="{project}";
                window["newTasksCounterObjIdCache"]=' . $objData->id->getAsInt() . ';
            </script>';

                $RESPONSE_DATA .= '
        </div>';
            }

            if ($projectAdmin || $projectGamemaster) {
                $RESPONSE_DATA .= '
            <div id="fraymtabs-documents">';

                $RESPONSE_DATA .= '
                <div class="fraymtabs">
                    <ul>
                        <li><a id="documents_project">' . $LOCALE['documents_project'] . '</a></li>
                        <li><a id="documents_tasks">' . $LOCALE['documents_tasks'] . '</a></li>
                    </ul>
                    <div id="fraymtabs-documents_project">
                        <a class="load_library" obj_type="{project}" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_hidden'] . '</a>
                    </div>
                    <div id="fraymtabs-documents_tasks">';
                $RESPONSE_DATA .= '
                        <div class="project_tasks_library">';

                $filesParentNames = [];
                $filesParentNamesSort = [];

                $result = DB->query(
                    "SELECT DISTINCT te.* FROM task_and_event te LEFT JOIN relation r ON te.id=r.obj_id_from WHERE r.obj_type_from='{task}' AND r.type='{child}' AND r.obj_type_to='{project}' AND ((r.obj_id_to=:obj_id_to_1 AND te.attachments!='') OR (r.obj_id_from IN (SELECT obj_id_to FROM relation WHERE obj_type_from='{file}' AND type='{child}' AND obj_type_to='{task}' AND obj_id_to IN (SELECT obj_id_from FROM relation WHERE obj_type_from='{task}' AND type='{child}' AND obj_type_to='{project}' AND obj_id_to=:obj_id_to_2)))) ORDER BY te.name ASC",
                    [
                        ['obj_id_to_1', $objData->id->getAsInt()],
                        ['obj_id_to_2', $objData->id->getAsInt()],
                    ],
                );

                foreach ($result as $taskData) {
                    if (RightsHelper::checkAnyRights('{task}', $taskData['id'])) {
                        $filesParentNames[] = [$taskData['id'], DataHelper::escapeOutput($taskData['name'])];
                        $filesParentNamesSort[] = DataHelper::escapeOutput($taskData['name']);
                    }
                }
                array_multisort($filesParentNamesSort, SORT_ASC, $filesParentNames);

                if (count($filesParentNames) > 0) {
                    $RESPONSE_DATA .= '<a class="expand_all_branches">' . $LOCALE_GLOBAL['show_hidden'] . '</a>';
                }

                foreach ($filesParentNames as $value) {
                    $RESPONSE_DATA .= '
                            <div class="files_group uploaded_file folder">' . $value[1] . ' <a href="' . ABSOLUTE_PATH . '/task/' . $value[0] . '/" target="_blank">-></a></div>
			                <div class="files_group_data"><a class="load_library" obj_type="{task}" obj_id="' . $value[0] . '" external="true">' . $LOCALE_GLOBAL['show_hidden'] . '</a></div>';
                }

                $RESPONSE_DATA .= '
                        </div>
                    </div>
                </div>';

                $RESPONSE_DATA .= '
            </div>';
            }

            $RESPONSE_DATA .= '
        <div id="fraymtabs-conversations">';

            $RESPONSE_DATA .= '
            <div class="block">
                <a class="inner_add_something_button new_project_conversation"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['create_conversation'] . '</span></a>
                <div class="tabs_horizontal_shadow"></div>
                
                <div id="project_conversation">
                    <div class="block_data">
                    ' . MessageHelper::conversationForm(null, '{project_conversation}', $objData->id->getAsInt(), $LOCALE['conversation_text'], 0, false, true, '{admin}');

            $conversationsData = MessageHelper::prepareConversationTreePreviewData('{project_conversation}', $objData->id->getAsInt());
            $conversationsCount = count($conversationsData);
            $i = 0;

            foreach ($conversationsData as $conversationData) {
                ++$i;
                $RESPONSE_DATA .= MessageHelper::conversationTreePreview($conversationData, 'project', $objData->id->getAsInt(), 'string' . ($i % 2 === 0 ? '1' : '2'));

                if ($i === 4 && $conversationsCount > 4) {
                    $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>
                                    <div class="hidden">';
                }
            }

            if ($i > 4) {
                $RESPONSE_DATA .= '</div>';
            }
            $RESPONSE_DATA .= '
                    </div>
                </div>
            </div>';

            $RESPONSE_DATA .= '
        </div>';

            $RESPONSE_DATA .= '
        <div id="fraymtabs-members">';

            $RESPONSE_DATA .= '
            <div class="block project_users_list" id="project_users_list">
                <a class="inner_add_something_button project_user_add" obj_type="project" obj_id="' . $objData->id->getAsInt() . '"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['invite_to_project'] . '</span></a>
                <input type="text" name="user_rights_lookup" placehold="' . $LOCALE_GLOBAL['input_fio_id_for_search'] . '">
                <div class="tabs_horizontal_shadow"></div>
                <a class="load_users_list" obj_type="project" obj_id="' . $objData->id->getAsInt() . '" limit="0" shown_limit="50">' . $LOCALE_GLOBAL['show_next'] . '</a>
            </div>';

            $RESPONSE_DATA .= '
        </div>';

            $RESPONSE_DATA .= '
        </div>';
        }
        $RESPONSE_DATA .= '
    </div>
</div>
';

        $RESPONSE_DATA .= '<script>
    window["projectControlId"]=' . $objData->id->getAsInt() . ';
    window["projectControlItems"]="' . ($projectAdmin || $projectGamemaster ? 'show' : 'hide') . '";
	window["projectControlItemsName"]="' . str_replace('"', '\"', DataHelper::escapeOutput($objData->name->get())) . '";
	window["projectControlItemsRights"]="' . (PROJECT_RIGHTS ? implode(' ', PROJECT_RIGHTS) : '') . '";
</script>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }

    public function List(): ?Response
    {
        $projectService = $this->getService();

        [$allProjectsData, $allProjectsCount] = $projectService->getProjects();

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_START = LocaleHelper::getLocale(['start', 'global']);

        $objType = 'project';

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '';

        $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
    <h1 class="page_header">' . $LOCALE['title'] . '</h1>
	<div class="page_blocks margin_top">
	    <div class="page_block">';

        if (CURRENT_USER->isLogged()) {
            $projects = RightsHelper::findByRights(null, $objType);

            if ($projects) {
                $projects = array_unique($projects);
            }

            $projectsData = [];
            $projectsNewCount = [];

            if ($projects) {
                $projectsData = $this->getService()->getAll(['id' => $projects]);
                $projectsNewCount = [];
                $projectsDataSort = [];
                $projectsDataSort2 = [];
                $projectsDataSort3 = [];

                $projectsData = iterator_to_array($projectsData);

                foreach ($projectsData as $key => $objData) {
                    if ($objData->date_to->getModel()->getModelDataFieldValue('date_to') !== '' && $objData->date_to->get() >= new DateTimeImmutable('today')) {
                        $projectEvents = UniversalHelper::checkForUpdates('{project}', (int) $objData->id->getAsInt());
                        $projectsDataSort[$key] = $projectsNewCount[$objData->id->getAsInt()]['new_count'] = $projectEvents;
                        $projectsDataSort2[$key] = DataHelper::escapeOutput($objData->name->get());
                        $projectsDataSort3[$key] = $objData->date_to->get();
                    } else {
                        unset($projectsData[$key]);
                    }
                }

                if (count($projectsData) > 0) {
                    array_multisort($projectsDataSort, SORT_DESC, $projectsDataSort3, SORT_DESC, $projectsDataSort2, SORT_ASC, $projectsData);
                }
            }

            $RESPONSE_DATA .= '
            <h2>' . $LOCALE['my_projects'] . '<sup>' . count($projectsData) . '</sup></h2>
            <div class="navitems_plates">';

            if (count($projectsData) > 0) {
                foreach ($projectsData as $key => $objData) {
                    $RESPONSE_DATA .= DesignHelper::drawPlate(KIND, [
                        'id' => $objData->id->getAsInt(),
                        'attachments' => $objData->attachments->get(),
                        'name' => DataHelper::escapeOutput($objData->name->get()),
                        'new_count' => $projectsNewCount[$objData->id->getAsInt()]['new_count'],
                    ]);
                }
            }

            $RESPONSE_DATA .= '<div class="navitems_plate navitems_plate_add_plate"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/act=add"><div class="navitems_plate_more"><div class="navitems_plate_name">' . $LOCALE_START['create_application_system'] . '</div><div class="navitems_plate_add"><span>' . $LOCALE['add_project'] . '</span></div></div><div class="navitems_plate_avatar"><img src="' . ABSOLUTE_PATH . '/' . $_ENV['DESIGN_PATH'] . 'group-add-avatar.svg"></div></a></div>';

            $RESPONSE_DATA .= '
            </div>';

            $projectsData = [];
            $projectsNewCount = [];

            if ($projects) {
                $projectsData = $this->getService()->getAll(['id' => $projects]);
                $projectsNewCount = [];
                $projectsDataSort = [];
                $projectsDataSort2 = [];

                $projectsData = iterator_to_array($projectsData);

                foreach ($projectsData as $key => $objData) {
                    if ($objData->date_to->get() < new DateTimeImmutable('today')) {
                        $projectEvents = UniversalHelper::checkForUpdates('{project}', (int) $objData->id->getAsInt());
                        $projectsDataSort[$key] = $projectsNewCount[$objData->id->getAsInt()]['new_count'] = $projectEvents;
                        $projectsDataSort2[$key] = $objData->date_to->get();
                    } else {
                        unset($projectsData[$key]);
                    }
                }

                if (count($projectsData) > 0) {
                    array_multisort($projectsDataSort, SORT_DESC, $projectsDataSort2, SORT_DESC, $projectsData);
                }
            }

            if (count($projectsData) > 0) {
                $RESPONSE_DATA .= '
            <h2>' . $LOCALE['past_projects'] . '<sup>' . count($projectsData) . '</sup></h2>
            <div class="overflown_content em15">
            <div class="navitems_plates">';

                foreach ($projectsData as $key => $objData) {
                    $RESPONSE_DATA .= DesignHelper::drawPlate(KIND, [
                        'id' => $objData->id->getAsInt(),
                        'attachments' => $objData->attachments->get(),
                        'name' => DataHelper::escapeOutput($objData->name->get()),
                        'new_count' => $projectsNewCount[$objData->id->getAsInt()]['new_count'],
                    ]);
                }

                $RESPONSE_DATA .= '
            </div>
            </div>';

                if (count($projectsData) > 6) {
                    $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>';
                }
            }
        }

        $RESPONSE_DATA .= '
            <h2>' . (($_REQUEST['search'] ?? false) ? $LOCALE['found_projects'] : $LOCALE['all_projects']) . '<sup>' . $allProjectsCount . '</sup></h2>
            
            <form action="' . ABSOLUTE_PATH . '/' . KIND . '/" method="POST" id="form_inner_search">
                <a class="search_image sbi sbi-search"></a><input class="search_input" name="search" id="search" type="text" value="' . ($_REQUEST['search'] ?? false) . '" placehold="' . $LOCALE['search'] . '" autocomplete="off">
            </form>
            <div class="navitems_plates">';

        foreach ($allProjectsData as $objData) {
            $membersCount = count(array_unique(RightsHelper::findByRights(null, '{project}', $objData->id->getAsInt(), '{user}', false)));

            $RESPONSE_DATA .= DesignHelper::drawPlate(KIND, [
                'id' => $objData->id->getAsInt(),
                'attachments' => $objData->attachments->get(),
                'name' => DataHelper::escapeOutput($objData->name->get()),
                'members_count' => $membersCount,
            ]);
        }

        if (!($_REQUEST['search'] ?? false) && $allProjectsCount > 12) {
            $RESPONSE_DATA .= '<a class="load_projects_communities_list" obj_type="project" limit="12">' . $LOCALE_GLOBAL['show_next'] . '</a>';
        }
        $RESPONSE_DATA .= '
	        </div>
	    </div>
	</div>
	</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }

    public function Wall(): ?Response
    {
        $projectService = $this->getService();

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $objData = $projectService->get(DataHelper::getId());

        $objType = 'project';

        $projectAccess = $projectService->hasProjectAccess();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE_GLOBAL['title']);
        $RESPONSE_DATA = '';

        $conversationData = DB->select('conversation', [
            'obj_type' => '{project_wall}',
            'obj_id' => $objData->id->getAsInt(),
            'id' => BID,
        ], true);

        if ($conversationData && $projectAccess) {
            $name = (DataHelper::escapeOutput($conversationData['name']) !== '' ? DataHelper::escapeOutput($conversationData['name']) : $LOCALE['wall']);

            if (MODAL) {
                $PAGETITLE = '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/show=wall&bid=' . $conversationData['id'] . '" class="modal_title_first">' . $name . '</a><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/" class="modal_title_second">' . DataHelper::escapeOutput($objData->name->get()) . '</a>';
            } else {
                $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '"><div class="page_blocks"><h2>' . $name . '</h2>';
            }

            $RESPONSE_DATA .= MessageHelper::conversationWall($conversationData, $objType, $objData);

            if (!MODAL) {
                $RESPONSE_DATA .= '</div></div>';
            }
        }

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }

    public function Conversation(): ?Response
    {
        $projectService = $this->getService();

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $objData = $projectService->get(DataHelper::getId());

        $objType = 'project';

        $projectAccess = $projectService->hasProjectAccess();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE_GLOBAL['title']);
        $RESPONSE_DATA = '';

        $conversationData = DB->select('conversation', [
            'obj_type' => '{project_conversation}',
            'obj_id' => $objData->id->getAsInt(),
            'id' => BID,
        ], true);

        if ($conversationData['id'] && $projectAccess) {
            $name = (DataHelper::escapeOutput($conversationData['name']) !== '' ? DataHelper::escapeOutput($conversationData['name']) : $LOCALE['conversation']);

            if (MODAL) {
                $PAGETITLE = '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/show=conversation&bid=' . $conversationData['id'] . '" class="modal_title_first">' . $name . '</a><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/" class="modal_title_second">' . DataHelper::escapeOutput($objData->name->get()) . '</a>';
            } else {
                $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '"><div class="page_blocks"><h2>' . DataHelper::escapeOutput($conversationData['name']) . '</h2>';
            }

            $RESPONSE_DATA .= MessageHelper::conversationTree((int) BID, 0, 1, $objType, $objData);

            if (!MODAL) {
                $RESPONSE_DATA .= '</div></div>';
            }
        }

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
