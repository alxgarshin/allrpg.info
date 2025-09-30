<?php

declare(strict_types=1);

namespace App\CMSVC\Event;

use App\CMSVC\User\UserService;
use App\Helper\{DesignHelper, MessageHelper, RightsHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{ActEnum, EscapeModeEnum, TableFieldOrderEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

#[TableEntity(
    'event',
    'task_and_event',
    [
        new EntitySortingItem(
            tableFieldName: 'date_from',
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
    viewRight: 'checkViewRights',
    addRight: 'checkAddRights',
    changeRight: 'checkChangeRights',
    deleteRight: 'checkChangeRights',
)]
#[Controller(EventController::class)]
class EventView extends BaseView
{
    public function Response(): ?Response
    {
        /** @var EventService $eventService */
        $eventService = $this->getService();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $objData = $eventService->get(DataHelper::getId());

        if (!$objData) {
            return null;
        }

        $parentObjId = $eventService->getObjId();
        $parentObjType = $eventService->getObjType();
        $eventAdmin = $eventService->isEventAdmin($objData->id->getAsInt());
        $eventAccess = $eventService->hasEventAccess($objData->id->getAsInt());
        $eventParentAccess = $eventService->hasEventParentAccess();
        $accessToChilds = $eventService->hasAccessToChilds();

        if (!($eventAccess || $eventParentAccess)) {
            return null;
        }
        $objType = 'event';

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE['title']);
        $RESPONSE_DATA = '';

        $parentObj = $eventService->getParentData();

        $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
    <div class="page_block">
        <div class="object_info">
            <div class="object_info_1">
                <a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/" class="object_avatar"><div style="' . DesignHelper::getCssBackgroundImage(ABSOLUTE_PATH . '/' . $_ENV['DESIGN_PATH'] . 'no_avatar_event.svg') . '"></div></a>
            </div>
            <div class="object_info_2">
                <div class="event_dates">
                    <div>' . $objData->date_from->getAsUsualDateTime() . '</div>
                    <div>' . $objData->date_to->getAsUsualDateTime() . '</div>
                </div>
                <h1><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->id->getAsInt() . '/">' . DataHelper::escapeOutput($objData->name->get()) . '</a></h1>
                ' . ($objData->description->get() ? '<div class="overflown_content em15"><div class="object_description">' . TextHelper::basePrepareText(DataHelper::escapeOutput($objData->description->get(), EscapeModeEnum::forHTMLforceNewLines)) . '</div></div>
                <a class="show_hidden">' . $LOCALE_GLOBAL['show_next'] . '</a>' : '') . '
                <div class="object_info_2_additional">
                    ' . ($parentObj ? '<span class="gray">' . ($parentObjType === 'project' ? $LOCALE['project'] : $LOCALE['community']) . ':</span><a href="' . ABSOLUTE_PATH . '/' . $parentObjType . '/' . $parentObjId . '/">' . DataHelper::escapeOutput($parentObj['name']) . '</a><br>' : '');

        $place_html = $objData->place->asHTML(false);

        if ($place_html !== '') {
            $RESPONSE_DATA .= '<span class="gray">' . $LOCALE['place'] . ':</span>' . $place_html . '<br>';
        }

        $RESPONSE_DATA .= '
                </div>
            </div>
            <div class="object_info_3">';

        $authorData = $userService->get($objData->creator_id->getAsInt());

        if ($authorData) {
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

        if ($eventService->hasEventAccess($objData->id->getAsInt()) || CURRENT_USER->isAdmin() || $userService->isModerator()) {
            $RESPONSE_DATA .= '
                    <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                    <div class="actions_list_items">';

            if ($eventAdmin || CURRENT_USER->isAdmin() || $userService->isModerator()) {
                $RESPONSE_DATA .= '<a class="main" href="' . ABSOLUTE_PATH . '/event/' . $objData->id->getAsInt() . '/act=edit">' . $LOCALE['edit_event'] . '</a>';
            }

            $RESPONSE_DATA .= '<a class="nonimportant project_user_add" obj_type="event" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE['invite_into_event'] . '</a>';

            if ($eventAdmin && !RightsHelper::checkRights('{child}', '{event}', DataHelper::getId(), '{conversation}')) {
                $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/conversation/act=add&obj_type=event&obj_id=' . DataHelper::getId() . '">' . $LOCALE['create_event_chat'] . '</a>';
            } else {
                $conversationId = RightsHelper::findOneByRights('{child}', '{event}', DataHelper::getId(), '{conversation}');

                if ($conversationId > 0 && RightsHelper::checkRights('{member}', '{conversation}', $conversationId)) {
                    $RESPONSE_DATA .= '<a class="nonimportant" href="' . ABSOLUTE_PATH . '/conversation/' . $conversationId . '/#bottom">' . $LOCALE['open_event_chat'] . '</a>';
                }
            }

            if (!$eventAdmin) {
                $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . DataHelper::getId() . '/action=remove_access" class="careful">' . $LOCALE['leave_event_capitalized'] . '</a>';
            }

            $RESPONSE_DATA .= '<a class="nonimportant show_qr_code no_dynamic_content">' . $LOCALE['show_qr_code'] . '</a>';

            $RESPONSE_DATA .= '
                </div>';
        } elseif ($eventParentAccess) {
            $checkAccessData = [];

            if (CURRENT_USER->isLogged()) {
                $checkAccessData = DB->query(
                    'SELECT c.id, cm.updated_at FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id WHERE cm.message_action_data="{event_id:' . DataHelper::getId() . '}" AND cm.message_action="{get_access}" AND cm.creator_id=' . CURRENT_USER->id(),
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

        if ($eventAccess) {
            $RESPONSE_DATA .= '
        <div class="fraymtabs">
            <ul>
                <li><a id="history">' . $LOCALE['history'] . '</a></li>
                <li><a id="members">' . ($eventAdmin ? $LOCALE['members_alt'] : $LOCALE['members']) . '</a></li>
            </ul>';

            $RESPONSE_DATA .= '
            <div id="fraymtabs-history">';

            $RESPONSE_DATA .= '
                <div class="block" id="task_wall">
                    <div class="block_header event_comment_form">' . MessageHelper::conversationForm(null, '{event_comment}', DataHelper::getId(), $LOCALE['input_message']) . '</div>
                    <div class="block_data">';
            $result = DB->select(
                'conversation',
                [
                    'obj_type' => '{event_comment}',
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
            <div id="fraymtabs-members">';

            $RESPONSE_DATA .= '
                <div class="block event_users_list" id="event_users_list">
                    <a class="inner_add_something_button event_user_add" obj_type="event" obj_id="' . $objData->id->getAsInt() . '"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['invite_into_event'] . '</span></a>
                    <input type="text" name="user_rights_lookup" placehold="' . $LOCALE_GLOBAL['input_fio_id_for_search'] . '">
                    <div class="tabs_horizontal_shadow"></div>
                    <a class="load_users_list" obj_type="event" obj_id="' . $objData->id->getAsInt() . '" limit="0" shown_limit="50">' . $LOCALE_GLOBAL['show_next'] . '</a>
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
