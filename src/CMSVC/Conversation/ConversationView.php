<?php

declare(strict_types=1);

namespace App\CMSVC\Conversation;

use App\CMSVC\User\UserService;
use App\Helper\{DateHelper, DesignHelper, MessageHelper, RightsHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\TableFieldOrderEnum;
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

#[TableEntity(
    'conversation',
    'conversation',
    [
        new EntitySortingItem(
            tableFieldName: 'id',
            tableFieldOrder: TableFieldOrderEnum::DESC,
        ),
    ],
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: false,
    deleteRight: false,
)]
#[Controller(ConversationController::class)]
class ConversationView extends BaseView
{
    public function Response(): ?Response
    {
        /** @var ConversationService $conversationService */
        $conversationService = $this->CMSVC->service;

        if (ACTION === 'contact') {
            $conversationService->contact((int) ($_REQUEST['user'] ?? 0));
        }

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->LOCALE;
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_USER = LocaleHelper::getLocale(['user', 'global']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '';

        if (DataHelper::getId() > 0) {
            $c_count = 10;

            DB->query(
                "SELECT
            c.id as c_id,
            c.name as c_name,
            cm.*,
            cms.id as cms_id,
            cms.message_read as cms_read
        FROM conversation c
        LEFT JOIN relation r ON
            r.obj_id_to=c.id
        LEFT JOIN conversation_message cm ON
            cm.conversation_id=c.id
        LEFT JOIN conversation_message_status cms ON
            cms.message_id=cm.id
        WHERE
            (
              r.obj_id_from=:obj_id_from AND
              r.type='{member}' AND
              r.obj_type_from='{user}' AND
              r.obj_type_to='{conversation}'
            ) AND
            c.obj_id IS NULL AND
            (
               cms.message_deleted!='1' OR
               cms.message_deleted IS NULL
            ) AND
            cms.user_id=:user_id AND
            c.id=:c_id
        ORDER BY
            cm.updated_at DESC
        LIMIT 1",
                [
                    ['obj_id_from', CURRENT_USER->id()],
                    ['user_id', CURRENT_USER->id()],
                    ['c_id', DataHelper::getId()],
                ],
                true,
            );
            $hasMessages = DB->selectCount() > 0;

            $conversationData = DB->findObjectById(DataHelper::getId(), 'conversation');
            $conversationName = DataHelper::escapeOutput($conversationData['name']);
            $contactsData = RightsHelper::findByRights('{member}', '{conversation}', DataHelper::getId(), '{user}', false);
            $cotalkerData = false;

            if (!$conversationName) {
                foreach ($contactsData as $contactData) {
                    if ($contactData !== CURRENT_USER->id()) {
                        $cotalkerData = $userService->get($contactData);
                        $conversationName = $userService->showName($cotalkerData, true);
                        break;
                    }
                }
            }

            if (!$conversationName) {
                $conversationName = '&nbsp;';
            }
            $isGroup = count($contactsData) > 2;
            $bindedToObject = DB->select(
                'relation',
                [
                    'type' => '{child}',
                    'obj_type_from' => '{conversation}',
                    'obj_id_from' => DataHelper::getId(),
                ],
                true,
            );

            if ($conversationData && ($_REQUEST['show'] ?? false) === 'users') {
                if (MODAL) {
                    $PAGETITLE = '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $conversationData['id'] . '/" class="modal_title_first">' .
                        $LOCALE['modal_header_conversation_members'] . ': «' . strip_tags($conversationName) . '»</a>';
                }
                $RESPONSE_DATA .= '<div class="page_block"><div class="task_users_list">
<a class="load_users_list" obj_type="conversation" obj_id="' . $conversationData['id'] . '" limit="0" shown_limit="50">' . $LOCALE_GLOBAL['show_next'] . '</a>
</div></div>';
            } elseif ($hasMessages) {
                if (!REQUEST_TYPE->isDynamicRequest() || CookieHelper::getCookie('full_conversation_kind') === 'true') {
                    $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
	<a class="outer_add_something_button" href="/conversation/act=add"><span class="sbi sbi-add-something"></span><span class="outer_add_something_button_text">' . $LOCALE['placeholders']['new_message'] . '</span></a>
	<h1 class="page_header"><a href="/conversation/">' . $LOCALE['title'] . '</a></h1>
	<div class="conversation_message_switcher inside_conversation">
		<div class="conversation_message_switcher_search_container">
		    <a class="search_image sbi sbi-search"></a><input class="search_input" type="text" name="conversation_search_input" id="conversation_search_input" autocomplete="off" placehold="' . $LOCALE['placeholders']['conversation_search_input'] . '">
		</div>
		<div class="conversation_message_switcher_scroller">
			<a class="load_wall" obj_type="{main_conversation}">' . $LOCALE['previous'] . ' ' . $c_count . '</a>
		</div>
	</div>';
                }

                $RESPONSE_DATA .= '<div class="conversation_message_maincontent">
	<div class="conversation_message_maincontent_header">
        <h2 class="nopadding">' . $conversationName . '</h2>
        <div class="actions_list_switcher sbi">
            <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
            <div class="actions_list_items" obj_id="' . DataHelper::getId() . '">
	            <a class="conversations_widget_message_functions_add_people">' . $LOCALE['placeholders']['functions_invite'] . '</a>' .
                    (
                        $conversationData['creator_id'] === CURRENT_USER->id() ?
                        '<a class="conversations_widget_message_functions_change_people fraymmodal-window" href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $conversationData['id'] . '/show=users" hash="users">' . $LOCALE['placeholders']['functions_change_people'] . '</a>' : ''
                    );

                $objTypeTo = $bindedToObject ? DataHelper::clearBraces($bindedToObject['obj_type_to']) : '';

                $RESPONSE_DATA .= (
                    $bindedToObject ?
                    '<a class="conversations_widget_message_functions_go_to_binded" href="' . ABSOLUTE_PATH . '/' . $objTypeTo . '/' . $bindedToObject['obj_id_to'] . '/">' .
                    $LOCALE['placeholders']['functions_go_to_binded'] . $LOCALE['obj_types'][$objTypeTo . '3'] . '</a>' : ''
                ) . (
                    $objTypeTo === 'project' && $conversationData['creator_id'] === CURRENT_USER->id() ?
                    '<a class="conversations_widget_message_functions_switch_use_names_type">' . $LOCALE['placeholders']['functions_switch_use_names_type'] . '</a>' : ''
                ) . ($isGroup || $bindedToObject ?
                    '<a class="conversations_widget_message_functions_rename">' . $LOCALE['placeholders']['functions_rename'] . '</a><a class="conversations_widget_message_functions_leave careful" action_request="conversation/leave_dialog" obj_id="' .
                    DataHelper::getId() . '">' . $LOCALE['placeholders']['functions_leave'] . '</a>' : '') . '
	        </div>
	    ' . (!$isGroup && $cotalkerData ? '<div class="user_was_online"><span>' .
                    sprintf($LOCALE_USER['was_online'], LocaleHelper::declineVerb($cotalkerData)) . ':</span>' .
                    (
                        $cotalkerData->updated_at->getAsTimeStamp() < time() - 180 ?
                        DateHelper::showDateTime($cotalkerData->updated_at->getAsTimeStamp()) :
                        $LOCALE_USER['online']
                    ) .
                    '</div>' : '') . '
	    </div>
	</div>
	<div class="conversation_message_maincontent_scroller">
		<div class="conversation_message_maincontent_scroller_wrap">
			<a class="load_conversation" obj_limit="0" obj_id="' . DataHelper::getId() . '">' . $LOCALE['previous'] . ' ' . $c_count . '</a>
			<a id="bottom"></a>
		</div>
	</div>';

                $RESPONSE_DATA .= '
<div class="message_conversation_form">' . MessageHelper::conversationForm(
                    DataHelper::getId(),
                    '{conversation_message}',
                    DataHelper::getId(),
                    $LOCALE['type_your_message'],
                    0,
                    true,
                    false,
                    '',
                    true,
                ) . '</div></div>';

                if (!REQUEST_TYPE->isDynamicRequest() || CookieHelper::getCookie('full_conversation_kind') === 'true') {
                    $RESPONSE_DATA .= '
	</div>';
                }

                if (CookieHelper::getCookie('full_conversation_kind')) {
                    CookieHelper::batchDeleteCookie(['full_conversation_kind']);
                }
            }
        } else {
            $c_count = 20;

            $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
	<a class="outer_add_something_button" href="' . ABSOLUTE_PATH . '/' . KIND . '/act=add"><span class="sbi sbi-add-something"></span><span 
	class="outer_add_something_button_text">' . $LOCALE['placeholders']['new_message'] . '</span></a>
	<h1 class="page_header"><a href="/' . KIND . '/">' . $LOCALE['title'] . '</a></h1>
	<div class="conversation_message_switcher">
		<div class="conversation_message_switcher_search_container">
		    <a class="search_image sbi sbi-search"></a><input class="search_input" type="text" name="conversation_search_input" id="conversation_search_input" autocomplete="off" placehold="' . $LOCALE['placeholders']['conversation_search_input'] . '">
		</div>
		<div class="conversation_message_switcher_scroller">
			<a class="load_wall" obj_type="{main_conversation}">' . $LOCALE['previous'] . ' ' . $c_count . '</a>
		</div>
	</div>
	<div class="conversation_message_maincontent"></div>
	</div>';
        }

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
