<?php

declare(strict_types=1);

namespace App\CMSVC\Myapplication;

use App\CMSVC\Transaction\TransactionService;
use App\Helper\{DateHelper, DesignHelper, FileHelper, MessageHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{ActEnum, SubstituteDataTypeEnum, TableFieldOrderEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper, ResponseHelper, TextHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<MyapplicationService> */
#[TableEntity(
    name: 'myapplication',
    table: 'project_application',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'updated_at',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'status',
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortStatus',
        ),
        new EntitySortingItem(
            tableFieldName: 'project_id',
            substituteDataType: SubstituteDataTypeEnum::TABLE,
            substituteDataTableName: 'project',
            substituteDataTableId: 'id',
            substituteDataTableField: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'sorter',
        ),
        new EntitySortingItem(
            tableFieldName: 'updated_at',
            tableFieldOrder: TableFieldOrderEnum::DESC,
        ),
        new EntitySortingItem(
            tableFieldName: 'last_update_user_id',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortLastUpdateUserId',
        ),
    ],
    virtualField: 'allinfo',
    elementsPerPage: 5000,
)]
#[Rights(
    viewRight: true,
    addRight: 'checkRightsAdd',
    changeRight: 'checkRightsChangeDelete',
    deleteRight: 'checkRightsChangeDelete',
    viewRestrict: 'checkRightsViewRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(MyapplicationController::class)]
class MyapplicationView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function addApplicationProjectsList(): Response
    {
        $LOCALE = $this->getLOCALE();

        $RESPONSE_DATA = '';
        $PAGETITLE = TextHelper::mb_ucfirst($LOCALE['add_application']);

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
    <h1 class="page_header"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/">' . $PAGETITLE . '</a></h1>
	<div class="page_blocks">
	<div class="page_block margin_top">
	
	<div class="myapplication_project_selection">';

        foreach ($this->getService()->getAddApplicationProjectsListData() as $projectInfo) {
            $individualFieldsPresent = $projectInfo['individual_field_id'] > 0;
            $teamFieldsPresent = $projectInfo['team_field_id'] > 0;
            $visibleGroupsPresent = $projectInfo['group_id'] > 0;

            $RESPONSE_DATA .= '<div class="myapplication_project_selection_project_block">
	<div class="myapplication_project_selection_project_info">
		<div class="myapplication_project_selection_project_name">' . ($individualFieldsPresent || $teamFieldsPresent ? '<a href="' . ($individualFieldsPresent ? ($projectInfo['oneorderfromplayer'] === '1' ? ABSOLUTE_PATH . '/go/' . $projectInfo['id'] . '/' : ABSOLUTE_PATH . '/' . KIND . '/act=add&project_id=' . $projectInfo['id'] . '&application_type=0') : ABSOLUTE_PATH . '/' . KIND . '/act=add&project_id=' . $projectInfo['id'] . '&application_type=1') . '">' . DataHelper::escapeOutput($projectInfo['name']) . '</a>' : DataHelper::escapeOutput($projectInfo['name'])) . '</div>
		<div class="myapplication_project_selection_project_dates">' . DateHelper::dateFromToEvent($projectInfo["date_from"], $projectInfo["date_to"]) . '</div>
	</div>
	<div class="myapplication_project_selection_project_links">
		<div class="main">' . ($individualFieldsPresent ? '<a href="' . ($projectInfo['oneorderfromplayer'] === '1' ? ABSOLUTE_PATH . '/go/' . $projectInfo['id'] . '/' : ABSOLUTE_PATH . '/' . KIND . '/act=add&project_id=' . $projectInfo['id'] . '&application_type=0') . '">' . $LOCALE['send_individual_application'] . '</a>' : '') . '</div>
		<div class="main">' . ($teamFieldsPresent ? '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/act=add&project_id=' . $projectInfo['id'] . '&application_type=1">' . $LOCALE['send_team_application'] . '</a>' : '') . '</div>
		' . ($visibleGroupsPresent ? '<a href="' . ABSOLUTE_PATH . '/roles/' . $projectInfo['id'] . '/" class="additional">' . $LOCALE['roles_list'] . '</a>' : '') . '
		' . (DataHelper::escapeOutput($projectInfo['external_link']) !== '' ? '<a href="' . DataHelper::escapeOutput($projectInfo['external_link']) . '" target="_blank" class="additional">' . $LOCALE['website'] . '</a>' : '') . '
		<a href="' . ABSOLUTE_PATH . '/project/' . $projectInfo['id'] . '/" class="additional">' . $LOCALE['project'] . '</a>' . '
	    <div class="project_links_avatar"><img src="' . (FileHelper::getImagePath($projectInfo['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars')) ?? ABSOLUTE_PATH . '/' . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg') . '"></div>
	</div>
    </div>';
        }

        $RESPONSE_DATA .= '</div>
	</div>
	</div>
	</div>';

        return new HtmlResponse(
            html: $RESPONSE_DATA,
            pagetitle: $PAGETITLE,
        );
    }

    public function addApplicationProfileCompletionError(): Response
    {
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $RESPONSE_DATA = '';
        $PAGETITLE = $this->getService()->getProjectData()->name->get();

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
	<div class="page_blocks">
	<div class="page_block">
	' . $LOCALE_GLOBAL['em_or_social_needed'] . '
	</div>
	</div>
	</div>';

        $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, $PAGETITLE);

        return new HtmlResponse(
            html: $RESPONSE_DATA,
            pagetitle: $PAGETITLE,
        );
    }

    public function preViewHandler(): void
    {
        $myapplicationService = $this->getService();

        if (DataHelper::getId()) {
            $applicationData = $myapplicationService->getApplicationData();

            if ($applicationData['offer_to_user_id'] === CURRENT_USER->id() && $applicationData['offer_denied'] !== '1') {
                $rights = $this->getViewRights();
                $rights->setDeleteRight(false);

                foreach ($this->getModel()->getElements() as $element) {
                    $contextElements = $element->getAttribute()->getContext();

                    foreach ($contextElements as $key => $contextElement) {
                        if ($contextElement) {
                            if ($contextElement !== 'myapplication:view' && $contextElement !== ':view') {
                                unset($contextElements[$key]);
                            }
                        }
                    }

                    $contextElements = array_values($contextElements);

                    $element->getAttribute()->setContext($contextElements);
                }
            }
        }
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $myapplicationService = $this->getService();
        $userService = $myapplicationService->getUserService();

        $LOCALE = $this->getLOCALE();
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $RESPONSE_DATA = $response->getHtml();
        $title = $response->getPagetitle();

        $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, $title);
        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($title);

        if (DataHelper::getId()) {
            $projectData = $myapplicationService->getProjectData();
            $applicationData = $myapplicationService->getApplicationData();
            $commentContent = '';

            if ($applicationData) {
                $title = DataHelper::escapeOutput($applicationData['sorter'] ?? '');

                $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, $title);
                $PAGETITLE = DesignHelper::changePageHeaderTextToLink($title);

                if ($applicationData['offer_to_user_id'] === CURRENT_USER->id() && $applicationData['offer_denied'] !== '1') {
                    ResponseHelper::info($LOCALE['messages']['gamemasters_offer_application']);
                }

                if ($applicationData['deleted_by_gamemaster'] === '1') {
                    ResponseHelper::error($LOCALE['messages']['application_deleted_by_gamemaster']);
                }

                $historyViewIds = $myapplicationService->getHistoryViewIds();

                if (!$myapplicationService->getHistoryView()) {
                    $applicationDataConverted = $myapplicationService->arrayToModel($applicationData);

                    $RESPONSE_DATA = DesignHelper::insertScripts($RESPONSE_DATA, $myapplicationService->getViewScripts());

                    if ($applicationData['offer_to_user_id'] === CURRENT_USER->id() && $applicationData['offer_denied'] !== '1') {
                        $commentContent .= '<div class="filter"><a href="/myapplication/action=accept_application&obj_id=' . DataHelper::getId() . '" class="fixed_select inverted">' . $LOCALE['accept_application'] . '</a><a href="/myapplication/action=decline_application&obj_id=' . DataHelper::getId() . '" class="fixed_select">' . $LOCALE['decline_application'] . '</a></div><div class="clear"></div>';

                        $RESPONSE_DATA = preg_replace('#<h1 class="data_h1"#', $commentContent . ' <h1 class="data_h1"', $RESPONSE_DATA, 1);
                    } else {
                        $GLOBAL_LOCALE = LocaleHelper::getLocale(['fraym', 'dynamiccreate']);

                        $commentContent .= '<div class="filter">' .
                            '<a class="fixed_select inverted" id="provide_payment">' .
                            (
                                (($_ENV['USE_PAYMENT_SYSTEMS']['paykeeper'] ?? false) && $projectData->paykeeper_login->get() && $projectData->paykeeper_pass->get() && $projectData->paykeeper_server->get() && $projectData->paykeeper_secret->get()) ||
                                (($_ENV['USE_PAYMENT_SYSTEMS']['paymaster'] ?? false) && $projectData->paymaster_merchant_id->get() && $projectData->paymaster_code->get()) ||
                                (($_ENV['USE_PAYMENT_SYSTEMS']['yandex'] ?? false) && $projectData->yk_acc_id->get() && $projectData->yk_code->get()) ||
                                (($_ENV['USE_PAYMENT_SYSTEMS']['paw'] ?? false) && $projectData->paw_mnt_id->get() && $projectData->paw_code->get()) ?
                                $LOCALE['provide_payment2'] :
                                $LOCALE['provide_payment']
                            ) .
                            '</a>' .
                            '<a class="fixed_select inverted" href="/roles/' . $projectData->id->get() . '/">' . mb_strtolower($LOCALE['roles_list']) . '</a><a class="fixed_select inverted" href="/ingame/' . $applicationData['id'] . '/">' . $LOCALE['ingame'] . '</a>' . ($historyViewIds['now'] > 0 ? '<a href="/myapplication/' . DataHelper::getId() . '/act=view&history_view=1" class="fixed_select">' . $LOCALE['changes_history'] . '</a>' : '') . '</div><div class="clear"></div>';

                        /** Форма ввода платежа */
                        /** @var TransactionService */
                        $transactionService = CMSVCHelper::getService('transaction');

                        $transactionService->getView()->setEntity(
                            (new TableEntity(
                                name: 'transaction',
                                table: 'project_transaction',
                                sortingData: [],
                            )),
                        )->getViewRights()
                            ->setAddRight(true)
                            ->setChangeRight(false)
                            ->setDeleteRight(false)
                            ->setViewRestrict('')
                            ->setChangeRestrict('')
                            ->setDeleteRestrict('');

                        $transactionContent = $transactionService->getEntity()->viewActItem([], ActEnum::add, KIND);

                        $transactionContent = str_replace('<button class="main">' . $GLOBAL_LOCALE['addCapitalized'] . ' ' . $transactionService->getEntity()->getObjectName() . '</button>', '<button class="main">' . $GLOBAL_LOCALE['addCapitalized'] . ' ' . $LOCALE['payment'] . '</button>', $transactionContent);

                        $transactionContent = '<div class="provide_payment_form">' . str_replace('<form', '<form no_dynamic_content', $transactionContent) . '</div>';

                        $transactionContent = str_replace('<input type="hidden" name="kind" value="myapplication" />', '<input type="hidden" name="kind" value="myapplication" /><input type="hidden" name="action" value="create_transaction" />', $transactionContent);
                        $transactionContent = str_replace('action="/' . KIND . '/"', 'action="/' . KIND . '/' . DataHelper::getId() . '/"', $transactionContent);

                        /* добавляем признак того, что это вариант оплаты через PayMaster */
                        $checkPmPaymentType = DB->select(
                            tableName: 'project_payment_type',
                            criteria: [
                                'pm_type' => '1',
                                'project_id' => $projectData->id->get(),
                            ],
                            oneResult: true,
                        );

                        if ($checkPmPaymentType) {
                            $transactionContent = str_replace('<option value="' . $checkPmPaymentType['id'] . '"', '<option value="' . $checkPmPaymentType['id'] . '" pay_type="paymaster"', $transactionContent);
                        }

                        /* добавляем признак того, что это вариант оплаты через PayKeeper */
                        $checkPkPaymentType = DB->select(
                            tableName: 'project_payment_type',
                            criteria: [
                                'pk_type' => '1',
                                'project_id' => $projectData->id->get(),
                            ],
                            oneResult: true,
                        );

                        if ($checkPkPaymentType) {
                            $transactionContent = str_replace('<option value="' . $checkPkPaymentType['id'] . '"', '<option value="' . $checkPkPaymentType['id'] . '" pay_type="paykeeper"', $transactionContent);
                        }

                        $commentContent .= $transactionContent;
                    }

                    $commentContent .= '<h1 class="data_h1">' . $LOCALE['comments'] . '<a id="change_comments_order">' . $LOCALE['change_comments_order'] . '</a></h1>';

                    $commentContent .= '<div class="application_conversations player"> ';

                    $commentContent .= MessageHelper::conversationForm(null, '{project_application_conversation}', DataHelper::getId(), $LOCALE['conversation_text'], '', true, false, '{from_player}');

                    $unreadConversationsIds = [];
                    $conversationsUnreadData = DB->query("SELECT c.id as c_id, c.name as c_name, c.sub_obj_type as c_sub_obj_type FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND cms.user_id=:user_id WHERE c.obj_type='{project_application_conversation}' AND c.obj_id=:obj_id AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{to_player}' OR c.sub_obj_type IS NULL) AND (cms.message_read='0' OR cms.message_read IS NULL OR (cm.updated_at > :updated_at AND cm.message_action='{fee_payment}')) GROUP BY c.id ORDER BY MAX(cm.updated_at) DESC", [
                        ['user_id', CURRENT_USER->id()],
                        ['obj_id', $applicationData['id']],
                        ['updated_at', (time() - 60)],
                    ]);

                    foreach ($conversationsUnreadData as $conversationData) {
                        $subObjType = DataHelper::clearBraces($conversationData['c_sub_obj_type']);
                        $unreadConversationsIds[] = $conversationData['c_id'];
                        $commentContent .= MessageHelper::conversationTree($conversationData['c_id'], 0, 1, '{project_application}', $applicationDataConverted, $subObjType, $LOCALE['titles_conversation_sub_obj_types'][$subObjType]);
                    }

                    $conversationsReadData = DB->query("SELECT c.id as c_id, c.name as c_name, c.sub_obj_type as c_sub_obj_type FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND cms.user_id=:user_id WHERE c.obj_type='{project_application_conversation}' AND c.obj_id=:obj_id AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{to_player}' OR c.sub_obj_type IS NULL) AND cms.message_read='1' AND (cm.updated_at <= :updated_at OR cm.message_action!='{fee_payment}' OR cm.message_action IS NULL)" . (count($unreadConversationsIds) > 0 ? " AND c.id NOT IN (:unread_conversations_ids)" : "") . " GROUP BY c.id ORDER BY MAX(cm.updated_at) DESC", [
                        ['user_id', CURRENT_USER->id()],
                        ['obj_id', $applicationData['id']],
                        ['updated_at', (time() - 60)],
                        ['unread_conversations_ids', $unreadConversationsIds],
                    ]);
                    $conversationsDataCount = count($conversationsReadData);

                    if ($conversationsDataCount > 0) {
                        $commentContent .= '<a class="show_hidden"> ' . $LOCALE['show_all_conversations'] . ' </a><div class="hidden">';
                        $i = 0;
                        $divsToClose = 0;

                        foreach ($conversationsReadData as $conversationData) {
                            $subObjType = DataHelper::clearBraces($conversationData['c_sub_obj_type']);
                            $commentContent .= MessageHelper::conversationTree($conversationData['c_id'], 0, 1, '{project_application}', $applicationDataConverted, $subObjType, $LOCALE['titles_conversation_sub_obj_types'][$subObjType]);
                            $i++;

                            if ($i % 4 === 0) {
                                $commentContent .= '<a class="show_hidden"> ' . $LOCALE['show_all_conversations'] . ' </a><div class="hidden">';
                                $divsToClose++;
                            }
                        }

                        for ($i = 0; $i < $divsToClose; $i++) {
                            $commentContent .= '</div>';
                        }

                        $commentContent .= '</div>';
                    }
                    $commentContent .= '</div>';

                    $RESPONSE_DATA = str_replace('<form', $commentContent . ' <form', $RESPONSE_DATA);
                    $RESPONSE_DATA = str_replace('</button></div></form>', '</button><button class="nonimportant" id="to_comments">' . $LOCALE['to_comments'] . '</button></div></form>', $RESPONSE_DATA);

                    if ($myapplicationService->verifyRoomsAvailable()) {
                        $RESPONSE_DATA = str_replace('<div class="field wysiwyg" id="field_room_neighboors[0]">', '<div class="field wysiwyg" id="field_room_neighboors[0]"><a class="add_something_svg" id="add_neighboor_myapplication" obj_id="' . DataHelper::getId() . '"></a>', $RESPONSE_DATA);
                    }

                    $RESPONSE_DATA = $myapplicationService->postProcessTextsOfReadOnlyFields($RESPONSE_DATA);
                } else {
                    $historyViewNowData = $myapplicationService->getHistoryViewNowData();

                    $commentContent .= '<div class="filter"><a href="/application/' . DataHelper::getId() . '/" class="fixed_select">' . $LOCALE['back_to_application'] . '</a>' . ($historyViewIds['prev'] > 0 ? '<a href="/application/application/' . DataHelper::getId() . '/act=view&history_view=1&history_view_now_id=' . $historyViewIds['prev'] . '" class="fixed_select">' . $LOCALE['prev_change'] . '</a>' : '') . ($historyViewIds['next'] > 0 ? '<a href="/application/application/' . DataHelper::getId() . '/act=view&history_view=1&history_view_now_id=' . $historyViewIds['next'] . '" class="fixed_select">' . $LOCALE['next_change'] . '</a>' : '') . '<a class="fixed_select right">' . DateHelper::showDateTimeUsual($applicationData['updated_at']) . ' ' . $userService->showNameWithId($userService->get($applicationData['last_update_user_id'])) . '</a></div><form>';

                    $RESPONSE_DATA = preg_replace('#maincontent_data autocreated#', 'maincontent_data autocreated table_cell history_view', $RESPONSE_DATA);

                    $commentContentHistory = '<div class="filter"><a class="fixed_select right">' . DateHelper::showDateTimeUsual((string) $historyViewNowData->updated_at->getAsTimeStamp()) . ' ' . $userService->showNameWithId($userService->get($historyViewNowData->creator_id->getAsInt())) . '</a></div><form>';

                    $this->getEntity()
                        ->setTable('project_application_history');
                    $this->getViewRights()
                        ->setAddRight(false)
                        ->setChangeRight(false)
                        ->setDeleteRight(false)
                        ->setViewRestrict('')
                        ->setChangeRestrict('')
                        ->setDeleteRestrict('');

                    /** @var HtmlResponse */
                    $historyHtml = $this->getEntity()->view(ActEnum::view, $historyViewIds['now']);
                    $historyHtml = DesignHelper::insertHeader($historyHtml->getHtml(), '&nbsp;');

                    $historyHtml = preg_replace('#maincontent_data autocreated#', 'maincontent_data autocreated table_cell history_view_old', $historyHtml);

                    $RESPONSE_DATA = preg_replace('#</h1>#', '</h1>' . $commentContent, $RESPONSE_DATA, 1) . '</form>' . preg_replace('#</h1>#', '</h1>' . $commentContentHistory, $historyHtml, 1) . '</form>';
                }
            }
        } elseif (DataHelper::getActDefault($this->getEntity()) === ActEnum::add) {
            $projectData = $myapplicationService->getProjectData();

            $RESPONSE_DATA = DesignHelper::insertScripts($RESPONSE_DATA, $myapplicationService->getViewScripts());

            $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, DataHelper::escapeOutput($projectData->name->get()));
        } else {
            $RESPONSE_DATA = preg_replace('#<div class="indexer_toggle">#', '<div class="filter">' . (!$myapplicationService->getDeletedView() ? '<a href="/myapplication/deleted=1" class="fixed_select">' . $LOCALE['switch_to_deleted'] . '</a>' : '<a href="/myapplication/" class="fixed_select">' . $LOCALE['switch_to_all'] . '</a>') . '</div><div class="indexer_toggle">', $RESPONSE_DATA);

            $RESPONSE_DATA = preg_replace('#' . $LOCALE_FRAYM['dynamiccreate']['add'] . ' ' . $this->getEntity()->getObjectName() . '#', $LOCALE['add_application'], $RESPONSE_DATA);
        }

        return $response->setHtml($RESPONSE_DATA)->setPagetitle($PAGETITLE);
    }
}
