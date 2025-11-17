<?php

declare(strict_types=1);

namespace App\CMSVC\Community;

use App\CMSVC\User\UserService;
use App\Helper\{DateHelper, DesignHelper, FileHelper, MessageHelper, RightsHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{ActEnum, EscapeModeEnum, TableFieldOrderEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

/** @extends BaseView<CommunityService> */
#[TableEntity(
    'community',
    'community',
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
#[Controller(CommunityController::class)]
class CommunityView extends BaseView
{
    public function Response(): ?Response
    {
        $communityService = $this->getService();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $objData = $communityService->get(DataHelper::getId());

        if (!$objData) {
            return null;
        }

        $objType = 'community';

        $communityAdmin = $communityService->isCommunityAdmin($objData->id->getAsInt());
        $communityModerator = $communityService->isCommunityModerator($objData->id->getAsInt());
        $communityMember = $communityService->isCommunityMember($objData->id->getAsInt());
        $communityAccess = $communityService->hasCommunityAccess($objData->id->getAsInt());

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE['title']);
        $RESPONSE_DATA = '';

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
    <div class="page_block">
        <div class="object_info">
            <div class="object_info_1">
                <a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/" class="object_avatar"><div style="' . DesignHelper::getCssBackgroundImage(FileHelper::getImagePath($objData->attachments->get(), FileHelper::getUploadNumByType('projects_and_communities_avatars')) ?? ABSOLUTE_PATH . '/' . $_ENV['DESIGN_PATH'] . 'no_avatar_community.svg') . '"></div></a>
            </div>
            <div class="object_info_2">
                <h1><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/">' . DataHelper::escapeOutput($objData->name->get()) . '</a></h1>
                <div class="overflown_content em15"><div class="object_description">' . TextHelper::basePrepareText(DataHelper::escapeOutput($objData->description->get(), EscapeModeEnum::plainHTML)) . '</div></div>
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

        if ($communityService->hasCommunityAccess($objData->id->getAsInt()) || CURRENT_USER->isAdmin() || $userService->isModerator()) {
            $RESPONSE_DATA .= '
                    <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                    <div class="actions_list_items">';

            if ($communityAdmin || CURRENT_USER->isAdmin() || $userService->isModerator()) {
                $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/act=edit">' . $LOCALE['edit_community'] . '</a>';
            }

            if ($communityAdmin) {
                $RESPONSE_DATA .= '<a class="community_user_add" obj_type="community" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE['invite_to_community'] . '</a>';
                $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/task/task/act=add&obj_id=' . $objData->id->getAsInt() . '&obj_type=community">' . $LOCALE['add_task'] . '</a>';
                $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/event/event/act=add&obj_id=' . $objData->id->getAsInt() . '&obj_type=community">' . $LOCALE['add_event'] . '</a>';

                if ($_ENV['PROJECTS_NEED_COMMUNITY']) {
                    $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/project/project/act=add&obj_type=community&obj_id=' . $objData->id->getAsInt() . '">' . $LOCALE['add_project'] . '</a>';
                }

                if (!RightsHelper::checkRights('{child}', '{community}', $objData->id->getAsInt(), '{conversation}')) {
                    $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/conversation/act=add&obj_type=community&obj_id=' . $objData->id->getAsInt() . '">' . $LOCALE['create_community_chat'] . '</a>';
                } else {
                    $conversation_id = RightsHelper::findOneByRights('{child}', '{community}', $objData->id->getAsInt(), '{conversation}');

                    if ($conversation_id > 0 && RightsHelper::checkRights('{member}', '{conversation}', $conversation_id)) {
                        $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/conversation/' . $conversation_id . '/#bottom">' . $LOCALE['open_community_chat'] . '</a>';
                    }
                }
            } elseif ($communityMember) {
                $RESPONSE_DATA .= '<a class="nonimportant community_user_add" obj_type="community" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE['invite_to_community'] . '</a>';

                if ($_ENV['PROJECTS_NEED_COMMUNITY']) {
                    $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/project/project/act=add&obj_type=community&obj_id=' . $objData->id->getAsInt() . '">' . $LOCALE['add_project'] . '</a>';
                }

                if (RightsHelper::checkRights('{child}', '{community}', $objData->id->getAsInt(), '{conversation}')) {
                    $conversation_id = RightsHelper::findOneByRights('{child}', '{community}', $objData->id->getAsInt(), '{conversation}');

                    if ($conversation_id > 0 && RightsHelper::checkRights('{member}', '{conversation}', $conversation_id)) {
                        $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/conversation/' . $conversation_id . '/#bottom">' . $LOCALE['open_community_chat'] . '</a>';
                    }
                }

                $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/action=remove_access" class="careful">' . $LOCALE['leave_community'] . '</a>';
            } else {
                $checkAccessData = [];

                if (CURRENT_USER->isLogged()) {
                    $checkAccessData = DB->query(
                        'SELECT c.id, cm.updated_at FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id WHERE cm.message_action_data="{community_id:' . $objData->id->getAsInt() . '}" AND cm.message_action="{get_access}" AND cm.creator_id=' . CURRENT_USER->id(),
                        [],
                        true,
                    );
                }

                $RESPONSE_DATA .= '
                    <a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/action=get_access"><span>' . ($objData->type->get() === '{open}' ? $LOCALE['get_access'] : ($checkAccessData ? $LOCALE['access_request_sent'] : $LOCALE['request_access'])) . '</span></a>';
            }

            $RESPONSE_DATA .= '<a class="show_qr_code no_dynamic_content">' . $LOCALE['show_qr_code'] . '</a>';

            $RESPONSE_DATA .= '
                </div>';
        } else {
            $checkAccessData = [];

            if (CURRENT_USER->isLogged()) {
                $checkAccessData = DB->query(
                    'SELECT c.id, cm.updated_at FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id WHERE cm.message_action_data="{community_id:' . $objData->id->getAsInt() . '}" AND cm.message_action="{get_access}" AND cm.creator_id=' . CURRENT_USER->id(),
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

        if ($communityAccess) {
            $bindedCommunities = RightsHelper::findByRights('{child}', '{community}', $objData->id->getAsInt(), '{community}');
            $bindedProjects = false;

            if ($_ENV['PROJECTS_NEED_COMMUNITY']) {
                $bindedProjects = RightsHelper::findByRights('{child}', '{community}', $objData->id->getAsInt(), '{project}');
            }

            $RESPONSE_DATA .= '
        <div class="fraymtabs">
        <ul>
            <li><a id="wall">' . $LOCALE['wall'] . '</a></li>' . ($communityAdmin || $communityModerator ? '
            <li><a id="tasks">' . $LOCALE['tasks'] . '</a></li>' : '') . '
            <li><a id="conversations">' . $LOCALE['conversations'] . '</a></li>
            <li><a id="members">' . ($communityAdmin ? $LOCALE['members_alt'] : $LOCALE['members']) . '</a></li>
            ' . ($bindedCommunities ? '<li><a id="communities">' . $LOCALE['communities'] . '</a></li>' : '') . '
            ' . ($bindedProjects ? '<li><a id="projects">' . $LOCALE['projects'] . '</a></li>' : '') . '
        </ul>';

            $RESPONSE_DATA .= '
        <div id="fraymtabs-wall">';

            $future_events_data = DB->query(
                "SELECT DISTINCT te.* FROM task_and_event te LEFT JOIN relation r ON te.id=r.obj_id_from WHERE r.obj_type_to='{community}' AND r.obj_type_from='{event}' AND r.type='{child}' AND r.obj_id_to=:obj_id_to AND te.date_from>=CURDATE() ORDER BY te.date_from ASC",
                [
                    ['obj_id_to', $objData->id->getAsInt()],
                ],
            );
            $future_events_count = count($future_events_data);

            $past_events_data = DB->query(
                "SELECT DISTINCT te.* FROM task_and_event te LEFT JOIN relation r ON te.id=r.obj_id_from WHERE r.obj_type_to='{community}' AND r.obj_type_from='{event}' AND r.type='{child}' AND r.obj_id_to=:obj_id_to AND te.date_to<CURDATE() AND te.date_to>=SUBDATE(CURDATE(), 30) ORDER BY te.date_from DESC",
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

            $RESPONSE_DATA .= '
            <div class="block" id="community_wall">
                <div class="block_header">' . MessageHelper::conversationForm(null, '{community_wall}', $objData->id->getAsInt(), $LOCALE['wall_input_text']) . '</div>
                <div class="block_data">
	                <a class="load_wall" obj_type="{community_wall}" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE['show_previous'] . '</a>
                </div>
            </div>';

            $RESPONSE_DATA .= '
        </div>';

            if ($communityAdmin || $communityModerator) {
                $RESPONSE_DATA .= '
        <div id="fraymtabs-tasks">';

                $RESPONSE_DATA .= '
            <div class="fraymtabs">
                <ul>
                    <a class="inner_add_something_button" href="' . ABSOLUTE_PATH . '/task/task/act=add&obj_type=community&obj_id=' . $objData->id->getAsInt() . '"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['add_task'] . '</span></a>

                    <li><a id="task_mine">' . $LOCALE['tasks_mine'] . '<sup id="new_tasks_counter_mine"></sup></a></li>
                    <li><a id="task_membered">' . $LOCALE['tasks_membered'] . '<sup id="new_tasks_counter_membered"></sup></a></li>
                    <li><a id="task_notmembered">' . $LOCALE['tasks_notmembered'] . '</a></li>
                    <li><a id="task_delayed">' . $LOCALE['tasks_delayed'] . '<sup id="new_tasks_counter_delayed"></sup></a></li>
                    <li><a id="task_closed">' . $LOCALE['tasks_closed'] . '<sup id="new_tasks_counter_closed"></sup></a></li>
                </ul>
                <div id="fraymtabs-task_mine"><a class="load_tasks_list" obj_group="community" obj_type="mine" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
                <div id="fraymtabs-task_membered"><a class="load_tasks_list" obj_group="community" obj_type="membered" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
                <div id="fraymtabs-task_notmembered"><a class="load_tasks_list" obj_group="community" obj_type="notmembered" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
                <div id="fraymtabs-task_delayed"><a class="load_tasks_list" obj_group="community" obj_type="delayed" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
                <div id="fraymtabs-task_closed"><a class="load_tasks_list" obj_group="community" obj_type="closed" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
            </div>
            <script>
                window["newTasksCounterObjTypeCache"]="{community}";
                window["newTasksCounterObjIdCache"]=' . $objData->id->getAsInt() . ';
            </script>';

                $RESPONSE_DATA .= '
        </div>';
            }

            $RESPONSE_DATA .= '
        <div id="fraymtabs-conversations">';

            $RESPONSE_DATA .= '
            <div class="block">
                <a class="inner_add_something_button new_community_conversation"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['create_conversation'] .
                '</span></a>
                <div class="tabs_horizontal_shadow"></div>
                
                <div id="community_conversation">
                <div class="block_data">
                    ' . MessageHelper::conversationForm(null, '{community_conversation}', $objData->id->getAsInt(), $LOCALE['conversation_text'], 0, false, true, '{admin}');

            $conversationsData = MessageHelper::prepareConversationTreePreviewData('{community_conversation}', $objData->id->getAsInt());
            $conversationsCount = count($conversationsData);
            $i = 0;

            foreach ($conversationsData as $conversationData) {
                ++$i;
                $RESPONSE_DATA .= MessageHelper::conversationTreePreview($conversationData, 'community', $objData->id->getAsInt(), 'string' . ($i % 2 === 0 ? '1' : '2'));

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
            <div class="block community_users_list" id="community_users_list">
                <a class="inner_add_something_button community_user_add" obj_type="community" obj_id="' . $objData->id->getAsInt() . '"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['invite_to_community'] . '</span></a>
                <input type="text" name="user_rights_lookup" placehold="' . $LOCALE_GLOBAL['input_fio_id_for_search'] . '">
                <div class="tabs_horizontal_shadow"></div>
                <a class="load_users_list" obj_type="community" obj_id="' . $objData->id->getAsInt() . '" limit="0" shown_limit="50">' . $LOCALE_GLOBAL['show_next'] . '</a>
            </div>';

            $RESPONSE_DATA .= '
        </div>';

            if ($bindedCommunities) {
                $RESPONSE_DATA .= '
        <div id="fraymtabs-communities">';

                $bindedCommunitiesData = $communityService->getAll(['id' => $bindedCommunities]);
                $bindedCommunityDataSort = [];

                foreach ($bindedCommunitiesData as $key => $bindedCommunityData) {
                    if ($bindedCommunityData->id->getAsInt()) {
                        $bindedCommunityDataSort[$key] = mb_strtolower(
                            trim(str_replace(['"', '«'], '', DataHelper::escapeOutput($bindedCommunityData->name->get()))),
                        );
                    } else {
                        unset($bindedCommunitiesData[$key]);
                    }
                }
                array_multisort($bindedCommunityDataSort, SORT_ASC, $bindedCommunitiesData);

                $RESPONSE_DATA .= '<div class="overflown_content em15">
            <div class="navitems_plates">';
                $bindedCommunitiesDataCount = 0;

                foreach ($bindedCommunitiesData as $key => $bindedCommunityData) {
                    $membersCount = count(array_unique(RightsHelper::findByRights(null, '{community}', $bindedCommunityData->id->getAsInt(), '{user}', false)));

                    $RESPONSE_DATA .= DesignHelper::drawPlate(KIND, [
                        'id' => $bindedCommunityData->id->getAsInt(),
                        'attachments' => $bindedCommunityData->attachments->get(),
                        'name' => DataHelper::escapeOutput($bindedCommunityData->name->get()),
                        'members_count' => $membersCount,
                    ]);

                    ++$bindedCommunitiesDataCount;
                }
                $RESPONSE_DATA .= '</div>
            </div>';

                if ($bindedCommunitiesDataCount > 4) {
                    $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>';
                }

                $RESPONSE_DATA .= '
        </div>';
            }

            if ($_ENV['PROJECTS_NEED_COMMUNITY'] && $bindedProjects) {
                $RESPONSE_DATA .= '
        <div id="fraymtabs-projects">';

                $projectsData = DB->findObjectsByIds($bindedProjects, 'project');

                if ($projectsData) {
                    $projectsData = iterator_to_array($projectsData);
                }
                $projectsDataSort = [];

                foreach ($projectsData as $key => $projectData) {
                    if ($projectData['id'] !== '') {
                        $projectsDataSort[$key] = mb_strtolower(trim(str_replace(['"', '«'], '', DataHelper::escapeOutput($projectData['name']))));
                    } else {
                        unset($projectsData[$key]);
                    }
                }
                array_multisort($projectsDataSort, SORT_ASC, $projectsData);

                $RESPONSE_DATA .= '<div class="overflown_content em15">
            <div class="navitems_plates">';

                foreach ($projectsData as $key => $projectData) {
                    $membersCount = count(array_unique(RightsHelper::findByRights(null, '{project}', $projectData['id'], '{user}', false)));

                    $RESPONSE_DATA .= DesignHelper::drawPlate('project', [
                        'id' => $projectData['id'],
                        'attachments' => $projectData['attachments'],
                        'name' => DataHelper::escapeOutput($projectData['name']),
                        'members_count' => $membersCount,
                    ]);
                }
                $RESPONSE_DATA .= '</div>
            </div>';

                if (count($bindedProjects) > 4) {
                    $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>';
                }

                $RESPONSE_DATA .= '
        </div>';
            }

            $RESPONSE_DATA .= '
        </div>';
        }
        $RESPONSE_DATA .= '
    </div>
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }

    public function List(): ?Response
    {
        $communityService = $this->getService();

        [$allCommunitiesData, $allCommunitiesCount] = $communityService->getCommunities();

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_START = LocaleHelper::getLocale(['start', 'global']);

        $objType = 'community';

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '';

        $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
    <h1 class="page_header">' . $LOCALE['title'] . '</h1>
	<div class="page_blocks margin_top">
	    <div class="page_block">';

        if (CURRENT_USER->isLogged()) {
            $communities = RightsHelper::findByRights(null, $objType);

            if ($communities) {
                $communities = array_unique($communities);
            }

            $RESPONSE_DATA .= '
            <h2>' . $LOCALE['my_communities'] . '<sup>' . ($communities ? count($communities) : 0) . '</sup></h2>
            <div class="navitems_plates">';

            if ($communities) {
                $communitiesData = $communityService->getAll(['id' => $communities]);
                $communitiesNewCount = [];
                $communitiesDataSort = [];
                $communitiesDataSort2 = [];

                $communitiesData = iterator_to_array($communitiesData);

                if (count($communitiesData) > 0) {
                    foreach ($communitiesData as $key => $objData) {
                        if ($objData->id->getAsInt() !== '') {
                            $communityEvents = UniversalHelper::checkForUpdates($objType, (int) $objData->id->getAsInt());
                            $communitiesDataSort[$key] = $communitiesNewCount[$objData->id->getAsInt()]['new_count'] = $communityEvents;
                            $communitiesDataSort2[$key] = DataHelper::escapeOutput($objData->name->get());
                        }
                    }

                    array_multisort($communitiesDataSort, SORT_DESC, $communitiesDataSort2, SORT_ASC, $communitiesData);
                }

                foreach ($communitiesData as $key => $objData) {
                    $RESPONSE_DATA .= DesignHelper::drawPlate(KIND, [
                        'id' => $objData->id->getAsInt(),
                        'attachments' => $objData->attachments->get(),
                        'name' => DataHelper::escapeOutput($objData->name->get()),
                        'new_count' => $communitiesNewCount[$objData->id->getAsInt()]['new_count'],
                    ]);
                }
            }

            $RESPONSE_DATA .= '<div class="navitems_plate navitems_plate_add_plate"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/act=add"><div class="navitems_plate_more"><div class="navitems_plate_name">' . $LOCALE_START['create_group'] . '</div><div class="navitems_plate_add"><span>' . $LOCALE['add_community'] . '</span></div></div><div class="navitems_plate_avatar"><img src="' . ABSOLUTE_PATH . '/' . $_ENV['DESIGN_PATH'] . 'group-add-avatar.svg"></div></a></div>';

            $RESPONSE_DATA .= '
            </div>';
        }

        $RESPONSE_DATA .= '
            <h2>' . (($_REQUEST['search'] ?? false) ? $LOCALE['found_communities'] : $LOCALE['all_communities']) . '<sup>' . $allCommunitiesCount . '</sup></h2>
            
            <form action="' . ABSOLUTE_PATH . '/' . KIND . '/" method="POST" id="form_inner_search">
                <a class="search_image sbi sbi-search"></a><input class="search_input" name="search" id="search" type="text" value="' . ($_REQUEST['search'] ?? false) . '" placehold="' . $LOCALE['search'] . '" autocomplete="off">
            </form>
            <div class="navitems_plates">';

        foreach ($allCommunitiesData as $objData) {
            $membersCount = count(array_unique(RightsHelper::findByRights(null, '{community}', $objData->id->getAsInt(), '{user}', false)));

            $RESPONSE_DATA .= DesignHelper::drawPlate(KIND, [
                'id' => $objData->id->getAsInt(),
                'attachments' => $objData->attachments->get(),
                'name' => DataHelper::escapeOutput($objData->name->get()),
                'members_count' => $membersCount,
            ]);
        }

        if (!($_REQUEST['search'] ?? false) && $allCommunitiesCount > 12) {
            $RESPONSE_DATA .= '<a class="load_projects_communities_list" obj_type="community" limit="12">' . $LOCALE_GLOBAL['show_next'] . '</a>';
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
        $communityService = $this->getService();

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $objData = $communityService->get(DataHelper::getId());

        $objType = 'community';

        $communityAccess = $communityService->hasCommunityAccess($objData->id->getAsInt());

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE_GLOBAL['title']);
        $RESPONSE_DATA = '';

        $conversationData = DB->select('conversation', [
            'obj_type' => '{community_wall}',
            'obj_id' => $objData->id->getAsInt(),
            'id' => BID,
        ], true);

        if ($conversationData['id'] && $communityAccess) {
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
        $communityService = $this->getService();

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $objData = $communityService->get(DataHelper::getId());

        $objType = 'community';

        $communityAccess = $communityService->hasCommunityAccess($objData->id->getAsInt());

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE_GLOBAL['title']);
        $RESPONSE_DATA = '';

        $conversationData = DB->select('conversation', [
            'obj_type' => '{community_conversation}',
            'obj_id' => $objData->id->getAsInt(),
            'id' => BID,
        ], true);

        if ($conversationData['id'] && $communityAccess) {
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
