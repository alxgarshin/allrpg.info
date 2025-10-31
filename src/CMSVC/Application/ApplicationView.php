<?php

declare(strict_types=1);

namespace App\CMSVC\Application;

use App\CMSVC\Filterset\FiltersetService;
use App\CMSVC\Plot\PlotService;
use App\Helper\{DateHelper, DesignHelper, MessageHelper, RightsHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Element\{Attribute, Item};
use Fraym\Entity\{EntitySortingItem, Filters, Rights, TableEntity};
use Fraym\Enum\{ActEnum, ActionEnum, SubstituteDataTypeEnum, TableFieldOrderEnum};
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper, ResponseHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<ApplicationService> */
#[TableEntity(
    'application',
    'project_application',
    [
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
            tableFieldName: 'sorter',
        ),
        new EntitySortingItem(
            tableFieldName: 'creator_id',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortCreatorId',
        ),
        new EntitySortingItem(
            tableFieldName: 'responsible_gamemaster_id',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortResponsibleGamemasterId',
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
    'allinfo',
    500,
)]
#[Rights(
    viewRight: true,
    addRight: false,
    changeRight: 'checkRightsChangeDelete',
    deleteRight: 'checkRightsChangeDelete',
    viewRestrict: 'checkRightsViewRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(ApplicationController::class)]
class ApplicationView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function preViewHandler(): void
    {
        $applicationService = $this->getService();

        /** Проверяем наборы фильтров и устанавливаем доп.ограничения, если есть */
        $applicationService->checkFilterSets();

        /** Если включен какой-либо быстрый фильтр, то фактически убираем пагинацию */
        if ($applicationService->getDeletedView() || $applicationService->getNoReplyView() || $applicationService->getPaymentApproveView()) {
            $this->getEntity()->setElementsPerPage(5000);
        }

        /** Переключаемся на формирование excel'я, если выставлен excelView: дальнейшая обработка не происходит */
        if ($applicationService->getExcelView()) {
            $this->generateExcel();
        }

        /** Добавляем колонку персонажей, если они вообще есть */
        if (count($applicationService->getProjectCharacterIds()) > 0) {
            $entitySortingItem = new EntitySortingItem(
                tableFieldName: 'project_character_id',
                substituteDataType: SubstituteDataTypeEnum::TABLE,
                substituteDataTableName: 'project_character',
                substituteDataTableId: 'id',
                substituteDataTableField: 'name',
            );
            $this->getEntity()->insertEntitySortingData($entitySortingItem, 2);
        }

        /** Добавляем колонку групп, если они вообще есть */
        if (count($applicationService->getProjectGroupsData()) > 0) {
            $entitySortingItem = new EntitySortingItem(
                tableFieldName: 'project_group_ids',
                substituteDataType: SubstituteDataTypeEnum::TABLE,
                substituteDataTableName: 'project_group',
                substituteDataTableId: 'id',
                substituteDataTableField: 'name',
            );
            $this->getEntity()->insertEntitySortingData($entitySortingItem, 2);
        }

        /** Добавляем колонку счетчика завязок, если включен режим их просмотра */
        if ($applicationService->getPlotsCountView()) {
            $entitySortingItem = new EntitySortingItem(
                tableFieldName: 'id',
                doNotUseIfNotSortedByThisField: true,
            );
            $this->getEntity()->addEntitySortingData($entitySortingItem);
        }

        /** Добавление динамических полей колонками таблицы */
        foreach ($this->getModel()->getElements() as $applicationField) {
            if ($applicationField->getVirtual() && ($applicationField->getAttribute()->getAdditionalData()['show_in_table'] ?? false) === '1') {
                $substituteDataType = null;
                $substituteDataArray = null;

                if ($applicationField instanceof Item\Select || $applicationField instanceof Item\Multiselect) {
                    $substituteDataType = SubstituteDataTypeEnum::ARRAY;
                    $substituteDataArray = $applicationField->getValues();
                }

                $entitySortingItem = new EntitySortingItem(
                    tableFieldName: $applicationField->getName(),
                    doNotUseIfNotSortedByThisField: true,
                    substituteDataType: $substituteDataType,
                    substituteDataArray: $substituteDataArray,
                );
                $this->getEntity()->addEntitySortingData($entitySortingItem);
            }
        }
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $applicationService = $this->getService();
        $userService = $applicationService->getUserService();

        /** @var FiltersetService */
        $filtersetService = CMSVCHelper::getService('filterset');

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_MYAPPLICATION = LocaleHelper::getLocale(['myapplication', 'global']);
        $LOCALE_PLOT = LocaleHelper::getLocale(['plot', 'global']);
        $LOCALE_PROJECT = LocaleHelper::getLocale(['project', 'global']);
        $LOCALE_SUBSCRIPTION = $LOCALE_GLOBAL['subscription'];

        $model = $this->getModel();

        $title = $LOCALE_GLOBAL['project_control_items'][KIND][0];
        $RESPONSE_DATA = $response->getHtml();

        $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, $title);
        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($title);

        if (DataHelper::getId()) {
            $commentContent = '';
            $applicationData = $applicationService->getApplicationData();

            if ($applicationData) {
                $title = DataHelper::escapeOutput($applicationData['sorter'] ?? '');

                $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, $title);
                $PAGETITLE = DesignHelper::changePageHeaderTextToLink($title);

                if ($applicationData['offer_to_user_id'] > 0 && $applicationData['offer_denied'] === '1') {
                    ResponseHelper::error($LOCALE['messages']['application_declined']);
                }

                if ($applicationData['deleted_by_player'] === '1' && ACTION !== ActionEnum::delete) {
                    ResponseHelper::error($LOCALE['messages']['application_deleted_by_player']);
                }

                if ($applicationData['deleted_by_gamemaster'] === '1' && ACTION !== ActionEnum::delete) {
                    ResponseHelper::error($LOCALE_MYAPPLICATION['messages']['application_deleted_by_gamemaster']);
                }

                $RESPONSE_DATA = str_replace('<h1 class="data_h1" id="field_plots[0]">', '<h1 class="data_h1" id="field_plots[0]"><a class="add_something_svg" href="' . ABSOLUTE_PATH . '/plot/act=add&application_id=' . $applicationData['id'] . '"></a>', $RESPONSE_DATA);

                $historyViewIds = $applicationService->getHistoryViewIds();

                if (!$applicationService->getHistoryView()) {
                    $applicationDataConverted = $applicationService->arrayToModel($applicationData);

                    $RESPONSE_DATA = DesignHelper::insertScripts($RESPONSE_DATA, $applicationService->getViewScripts());

                    $commentContent .= '<div class="filter">' . ($historyViewIds['now'] > 0 ? '<a href="/application/application/' . DataHelper::getId() . '/act=view&history_view=1" class="fixed_select">' . $LOCALE['changes_history'] . '</a>' : '') . '<a class="fixed_select' . ($applicationData['offer_to_user_id'] > 0 ? '' : ' hidden') . '" id="transfer_application_cancel" obj_id="' . DataHelper::getId() . '">' . $LOCALE['transfer_application_cancel'] . ($applicationData['offer_to_user_id'] > 0 ? ' (' . $userService->showNameWithId($userService->get($applicationData['offer_to_user_id'])) . ')' : '') . '</a><a class="fixed_select' . ($applicationData['offer_to_user_id'] > 0 ? ' hidden' : '') . '" id="transfer_application" obj_id="' . DataHelper::getId() . '">' . $LOCALE['transfer_application'] . '</a><a obj_type="{project_application}" obj_id="' . DataHelper::getId() . '" class="fixed_select ' . (RightsHelper::checkRights('{subscribe}', '{project_application}', DataHelper::getId()) ? 'unsubscribe">' . $LOCALE_SUBSCRIPTION['unsubscribe'] : 'subscribe">' . $LOCALE_SUBSCRIPTION['subscribe']) . '</a></div>';

                    $commentContent .= '<h1 class="data_h1">' . $LOCALE['comments'] . '<a id="change_comments_order">' . $LOCALE['change_comments_order'] . '</a></h1>';

                    $commentContent .= '<div class="application_conversations"> ';

                    $commentContent .= MessageHelper::conversationForm(null, '{project_application_conversation}', DataHelper::getId(), $LOCALE['conversation_text'], '', true, false, $LOCALE['conversation_sub_obj_types']);

                    $unreadConversationsIds = [];
                    $conversationsUnreadData = DB->query("SELECT c.id as c_id, c.name as c_name, c.sub_obj_type as c_sub_obj_type FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND cms.user_id=:user_id WHERE c.obj_type='{project_application_conversation}' AND c.obj_id=:obj_id AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{gamemaster}' OR c.sub_obj_type='{to_player}' OR c.sub_obj_type IS NULL) AND (cms.message_read='0' OR cms.message_read IS NULL) GROUP BY c.id ORDER BY MAX(cm.updated_at) DESC", [
                        ['obj_id', $applicationData['id']],
                        ['user_id', CURRENT_USER->id()],
                    ]);

                    foreach ($conversationsUnreadData as $conversationData) {
                        $unreadConversationsIds[] = $conversationData['c_id'];
                        $commentContent .= MessageHelper::conversationTree($conversationData['c_id'], 0, 1, '{project_application}', $applicationDataConverted, DataHelper::clearBraces($conversationData['c_sub_obj_type']), $LOCALE['titles_conversation_sub_obj_types'][DataHelper::clearBraces($conversationData['c_sub_obj_type'])]);
                    }

                    $conversationsReadData = DB->query("SELECT c.id as c_id, c.name as c_name, c.sub_obj_type as c_sub_obj_type FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND cms.user_id=:user_id WHERE c.obj_type='{project_application_conversation}' AND c.obj_id=:obj_id AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{gamemaster}' OR c.sub_obj_type='{to_player}' OR c.sub_obj_type IS NULL) AND cms.message_read='1'" . (count($unreadConversationsIds) > 0 ? ' AND c.id NOT IN (:c_ids)' : '') . ' GROUP BY c.id ORDER BY MAX(cm.updated_at) DESC', [
                        ['obj_id', $applicationData['id']],
                        ['user_id', CURRENT_USER->id()],
                        ['c_ids', $unreadConversationsIds],
                    ]);
                    $conversationsDataCount = count($conversationsReadData);

                    if ($conversationsDataCount > 0) {
                        $commentContent .= '<a class="show_hidden"> ' . $LOCALE['show_all_conversations'] . ' </a><div class="hidden">';
                        $i = 0;
                        $divsToClose = 0;

                        foreach ($conversationsReadData as $conversationData) {
                            $commentContent .= MessageHelper::conversationTree($conversationData['c_id'], 0, 1, '{project_application}', $applicationDataConverted, DataHelper::clearBraces($conversationData['c_sub_obj_type']), $LOCALE['titles_conversation_sub_obj_types'][DataHelper::clearBraces($conversationData['c_sub_obj_type'])]);
                            ++$i;

                            if ($i % 4 === 0) {
                                $commentContent .= '<a class="show_hidden"> ' . $LOCALE['show_all_conversations'] . ' </a><div class="hidden">';
                                ++$divsToClose;
                            }
                        }

                        for ($i = 0; $i < $divsToClose; ++$i) {
                            $commentContent .= '</div>';
                        }

                        $commentContent .= '</div>';
                    }
                    $commentContent .= '</div>';

                    $RESPONSE_DATA = str_replace('<form', $commentContent . ' <form', $RESPONSE_DATA);
                    $RESPONSE_DATA = str_replace('</button></div></form>', '</button><button class="nonimportant" id="to_comments">' . $LOCALE['to_comments'] . '</button></div></form>', $RESPONSE_DATA);
                } else {
                    $historyViewNowData = $applicationService->getHistoryViewNowData();

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
        } elseif (DataHelper::getActDefault($this->getEntity()) === ActEnum::list) {
            /** Считаем завязки у видимых в списке заявок */
            $LIST_OF_FOUND_IDS = $this->getEntity()->getListOfFoundIds();

            if ($applicationService->getPlotsCountView() && count($LIST_OF_FOUND_IDS) > 0) {
                $listOfIds = [];

                $plotsApplicationsData = DB->select(
                    tableName: 'project_application',
                    criteria: [
                        'project_id' => $applicationService->getActivatedProjectId(),
                        'id' => $LIST_OF_FOUND_IDS,
                    ],
                    fieldsSet: [
                        'id',
                        'project_character_id',
                        'project_group_ids',
                    ],
                );

                foreach ($plotsApplicationsData as $plotsApplicationData) {
                    $listOfIds[$plotsApplicationData['id']] = [
                        'project_character_id' => $plotsApplicationData['project_character_id'],
                        'project_group_ids' => DataHelper::multiselectToArray($plotsApplicationData['project_group_ids']),
                    ];
                }

                if (count($listOfIds) > 0) {
                    $plotsInfo = [];
                    $plotsData = DB->query(
                        'SELECT DISTINCT pp.*, pp2.todo AS plot_todo FROM project_plot AS pp LEFT JOIN project_plot AS pp2 ON pp2.id=pp.parent WHERE pp.project_id=:project_id AND pp.parent > 0',
                        [
                            ['project_id', $applicationService->getActivatedProjectId()],
                        ],
                    );

                    foreach ($plotsData as $plotData) {
                        $plotsInfo[$plotData['id']] = $plotData;
                    }

                    foreach ($listOfIds as $applicationId => $applicationData) {
                        $plotsFromPersonalCount = 0;
                        $plotsFromCount = 0;
                        $plotsFromNotVisibleCount = 0;
                        $plotsToCount = 0;

                        foreach ($plotsInfo as $plotInfo) {
                            if (preg_match('#-' . $applicationId . '-#', ($plotInfo['applications_1_side_ids'] ?? '')) || preg_match('#-all' . $applicationData['project_character_id'] . '-#', ($plotInfo['applications_1_side_ids'] ?? null))) {
                                ++$plotsFromPersonalCount;

                                if (($plotInfo['todo'] ?? '') !== '' || ($plotInfo['plot_todo'] ?? '') !== '') {
                                    ++$plotsFromNotVisibleCount;
                                }
                            } elseif (preg_match('#-group(' . implode('|', ($applicationData['project_group_ids'] ?? [])) . ')-#', ($plotInfo['applications_1_side_ids'] ?? ''))) {
                                ++$plotsFromCount;

                                if (($plotInfo['todo'] ?? '') !== '' || ($plotInfo['plot_todo'] ?? '') !== '') {
                                    ++$plotsFromNotVisibleCount;
                                }
                            } elseif (preg_match('#-' . $applicationId . '-#', ($plotInfo['applications_2_side_ids'] ?? '')) || preg_match('#-all' . $applicationData['project_character_id'] . '-#', ($plotInfo['applications_2_side_ids'] ?? '')) || preg_match('#-group[' . implode('|', ($applicationData['project_group_ids'] ?? [])) . ']-#', ($plotInfo['applications_2_side_ids'] ?? ''))) {
                                ++$plotsToCount;
                            }
                        }

                        $RESPONSE_DATA = preg_replace('#<td><a[^>]+>' . $applicationId . '</a></td>#', '<td><a href="/application/' . $applicationId . '/"><div title="' . $LOCALE_PLOT['counter_personal'] . '">' . $plotsFromPersonalCount . ' <span class="sbi sbi-user"></span></div><div title="' . $LOCALE_PLOT['counter_group'] . '">' . $plotsFromCount . ' <span class="sbi sbi-eye"></span></div>' . ($plotsFromNotVisibleCount > 0 ? '<div title="' . $LOCALE_PLOT['counter_not_ready'] . '">' . $plotsFromNotVisibleCount . ' <span class="sbi sbi-times"></span></div>' : '') . '<div title="' . $LOCALE_PLOT['counter_about'] . '">' . $plotsToCount . ' <span class="sbi sbi-eye-striked"></span></div></a></td>', $RESPONSE_DATA);
                    }
                }
            }

            /** Список сохраненных наборов фильтров */
            $fixedFiltersetsHtml = '';
            $fixedFiltersets = $applicationService->getFixedFilterSets();

            if (count($fixedFiltersets) > 0) {
                $filtersetsData = $filtersetService->getAll(['id' => $fixedFiltersets]);
                $projectFiltersetId = CookieHelper::getCookie('project_filterset_id');

                foreach ($filtersetsData as $filtersetData) {
                    if ($projectFiltersetId === (string) $filtersetData->id->getAsInt()) {
                        $fixedFiltersetsHtml .= '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/object=' . KIND . '&action=clearFilters&sorting=0" class="fixed_select inverted">' . DataHelper::escapeOutput($filtersetData->name->get()) . '</a>';
                    } else {
                        $fixedFiltersetsHtml .= '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/filterset=' . $filtersetData->id->getAsInt() . '" class="fixed_select">' . DataHelper::escapeOutput($filtersetData->name->get()) . '</a>';
                    }
                }
            }

            $masterGroupSelector = new Item\Select();
            $masterGroupSelector
                ->setName('master_group_selector')
                ->setShownName('Список мастерских групп');
            $masterGroupSelectorAttribute = new Attribute\Select(
                values: $applicationService->getMasterGroupSelectorValues(),
            );
            $masterGroupSelector->setAttribute($masterGroupSelectorAttribute);

            $documentsGeneratorSelector = new Item\Select();
            $documentsGeneratorSelector
                ->setName('documents_generator_selector')
                ->setShownName('');
            $documentsGeneratorSelectorAttribute = new Attribute\Select(
                values: $applicationService->getGenerateDocumentValues(),
            );
            $documentsGeneratorSelector->setAttribute($documentsGeneratorSelectorAttribute);

            $RESPONSE_DATA = preg_replace(
                '#<div class="clear"></div><hr>#',
                '<div class="filter">' .
                    (!$applicationService->getMineView() ? '<a href="/application/object=application&action=setFilters&search_responsible_gamemaster_id[' . CURRENT_USER->id() . ']=on" class="fixed_select">' . $LOCALE['switch_to_mine'] . '</a>' : '<a href="/application/object=application&action=clearFilters&sorting=0" class="fixed_select inverted">' . $LOCALE['switch_to_mine'] . '</a>') .
                    (!$applicationService->getDeletedView() ? '<a href="/application/deleted=1" class="fixed_select">' . $LOCALE['switch_to_deleted'] . '</a>' : '<a href="/application/" class="fixed_select inverted">' . $LOCALE['switch_to_deleted'] . '</a>') .
                    (!$applicationService->getPaymentApproveView() ? '<a href="/application/payment_approve=1" class="fixed_select">' . $LOCALE['switch_to_payment_approve'] . '</a>' : '<a href="/application/" class="fixed_select inverted">' . $LOCALE['switch_to_payment_approve'] . '</a>') .
                    (!$applicationService->getNoReplyView() ? '<a href="/application/noreply=1" class="fixed_select">' . $LOCALE['switch_to_non_replied_comments'] . '</a>' : '<a href="/application/" class="fixed_select inverted">' . $LOCALE['switch_to_non_replied_comments'] . '</a>') .
                    (!$applicationService->getNeedResponseView() ? '<a href="/application/needresponse=1" class="fixed_select">' . $LOCALE['switch_to_need_response'] . '</a>' : '<a href="/application/" class="fixed_select inverted">' . $LOCALE['switch_to_need_response'] . '</a>') .
                    (!$applicationService->getNoFillObligView() ? '<a href="/application/nofilloblig=1" class="fixed_select">' . $LOCALE['switch_to_non_filled_mustbes'] . '</a>' : '<a href="/application/" class="fixed_select inverted">' . $LOCALE['switch_to_non_filled_mustbes'] . '</a>') .
                    (count($applicationService->getRoomsData()) > 0 ? (!$applicationService->getNonSettledView() ? '<a href="/application/nonsettled=1" class="fixed_select">' . $LOCALE['switch_to_non_settled'] . '</a>' : '<a href="/application/" class="fixed_select inverted">' . $LOCALE['switch_to_non_settled'] . '</a>') : '') .
                    (!$applicationService->getPlotsCountView() ? '<a href="/application/plots_count=1" class="fixed_select">' . $LOCALE['switch_to_plots_count_on'] . '</a>' : '<a href="/application/plots_count=2" class="fixed_select inverted">' . $LOCALE['switch_to_plots_count_off'] . '</a>') . '<a id="set_special_group" class="fixed_select" title="' . $LOCALE['set_special_group_title'] . '" filter="' . ($applicationService->getDeletedView() ? 'deleted_view' : '') . ($applicationService->getPaymentApproveView() ? 'payment_approve_view' : '') . ($applicationService->getNoReplyView() ? 'noreply_view' : '') . ($applicationService->getNeedResponseView() ? 'needresponse_view' : '') . ($applicationService->getNoFillObligView() ? 'nofilloblig_view' : '') . '">' . $LOCALE['set_special_group'] . '</a>' .
                    (count($applicationService->getGenerateDocumentValues()) > 0 ? '<a id="applications_generate_documents" class="fixed_select">' . $LOCALE['generate_documents'] . '</a>' . $documentsGeneratorSelector->asHTML(true) : '') .
                    '<a href="/application/export_to_excel=1" class="fixed_select" target="_blank">' . $LOCALE['export_to_excel'] . '</a>' .
                    ($applicationService->getHasTeamApplications() ? '<a href="/application/export_to_excel=2" class="fixed_select" target="_blank">' . $LOCALE['export_to_excel_2'] . '</a>' : '') . $masterGroupSelector->asHTML(true) .
                    '<a class="fixed_select">' . $LOCALE['total'] . $applicationService->getTotalCount() . '</a>' .
                    '<a class="fixed_select" href="/myapplication/act=add&project_id=' . $applicationService->getActivatedProjectId() . '&application_type=0">' . $LOCALE_PROJECT['send_application'] . '</a>' .
                    $fixedFiltersetsHtml .
                    '</div>' .
                    MessageHelper::conversationForm(null, '{project_application_conversation}', 0, $LOCALE['conversation_text_global'], ($applicationService->getDeletedView() ? 'deleted_view' : '') . ($applicationService->getPaymentApproveView() ? 'payment_approve_view' : '') . ($applicationService->getNoReplyView() ? 'noreply_view' : '') . ($applicationService->getNeedResponseView() ? 'needresponse_view' : ''), true, false, $LOCALE['conversation_sub_obj_type_filter']) . '<div><div><div><input type="text" id="application_search" placehold="' . $LOCALE['type_data_to_search'] . '" autocomplete="off"><input type="text" id="application_comments_search" placehold="' . $LOCALE['type_data_to_search_comments'] . '" autocomplete="off"></div></div></div><div class="clear"></div><hr>',
                $RESPONSE_DATA,
            );

            if (!(!Filters::hasFiltersCookie('application', 'application') && !in_array(ACTION, ActionEnum::getFilterValues()))) {
                $filtersetLink = str_replace(ABSOLUTE_PATH . '/application/object=application&action=setFilters&', '', $this->getEntity()->getFilters()->getPreparedCurrentFiltersLink());
                $filtersetNamesAndValues = explode('&', $filtersetLink);
                $filtersetNames = explode('=', $filtersetNamesAndValues[preg_match('#searchAllTextFields#', $filtersetNamesAndValues[0]) ? 1 : 0]);
                $filtersetObj = str_replace('search_', '', $filtersetNames[0]);
                preg_match('#\[([^]]+)]#', $filtersetObj, $match);
                $filtersetValue = $match[1] ?? null;
                $filtersetObj = preg_replace('#\[[^]]+]#', '', $filtersetObj);
                $filtersetName = '';

                $element = $model->getElement($filtersetObj);

                if ($element) {
                    $filtersetName = $element->getShownName();

                    if ($filtersetValue) {
                        if ($element instanceof Item\Select || $element instanceof Item\Multiselect) {
                            $fieldValues = $element->getValues();

                            foreach ($fieldValues as $value) {
                                if ($value[0] === $filtersetValue) {
                                    $filtersetName .= ': ' . $value[1];
                                    break;
                                }
                            }
                        }
                    }
                }

                CookieHelper::batchSetCookie(['filters_name' => $filtersetName]);

                $RESPONSE_DATA = str_replace('<div class="copy_filters_link">', '<div class="copy_filters_link"><a href="' . ABSOLUTE_PATH . '/filterset/save=1" target="_blank">' . $LOCALE['to_filterset'] . '</a>', $RESPONSE_DATA);
            }
        }

        return $response->setHtml($RESPONSE_DATA)->setPagetitle($PAGETITLE);
    }

    private function generateExcel(): void
    {
        set_time_limit(600);
        ini_set('memory_limit', '500M');

        $applicationsLimitForPlots = 100;
        $applicationsLimitForComments = 200;

        $excludeElements = [
            'allinfo',
            'sorter',
            'last_update_user_id',
            'id',
            'switch_to_character',
            'fix_character_name_by_sorter',
            'user_sickness',
            'updated_at',
        ];

        $applicationService = $this->getService();

        $userService = $this->getService()->getUserService();

        /** @var PlotService */
        $plotService = CMSVCHelper::getService('plot');

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_INGAME = LocaleHelper::getLocale(['myapplication', 'global']);

        $excelHtml = '<tr><th>#</th><th>' . $LOCALE_INGAME['to_application'] . '</th>';

        foreach ($this->getModel()->getElements() as $element) {
            if (!$element instanceof Item\H1 && !in_array($element->getName(), $excludeElements)) {
                $excelHtml .= '<th' . ($element->getName() === 'plots_data' ? ' style="width: 40em;"' : '') . '>' . $element->getShownName() . '</th>';
            }
        }

        $createdAt = $this->getModel()->getElement('created_at');

        if ($createdAt instanceof Item\Timestamp) {
            $createdAt->getAttribute()
                ->setContext([ApplicationModel::APPLICATION_VIEW_CONTEXT, ApplicationModel::APPLICATION_WRITE_CONTEXT])
                ->setShowInObjects(true);
        }

        $excelHtml .= '<th style="width: 40em;">' . $LOCALE['comments'] . '</th></tr>';

        $searchQuerySql = $this->getEntity()->getFilters()->getPreparedSearchQuerySql();

        $applicationsData = DB->query(
            'SELECT DISTINCT t1.*, t1.id as application_id, u.*, t1.status as status, g1.name as g_city, g2.name as g_area, t1.created_at AS created_at FROM project_application t1 LEFT JOIN user u ON u.id=t1.creator_id LEFT JOIN geography g1 ON g1.id=u.city LEFT JOIN geography g2 ON g2.id=g1.parent WHERE t1.project_id=' . $applicationService->getActivatedProjectId() . ' AND t1.deleted_by_gamemaster="0" AND t1.team_application="' . ($applicationService->getExcelType() > 0 ? $applicationService->getExcelType() : 0) . '"' .
                ($applicationService->getDeletedView() ? ' AND (t1.deleted_by_gamemaster="1" OR t1.deleted_by_player="1")' : ' AND t1.deleted_by_gamemaster="0" AND t1.deleted_by_player="0"') .
                ($applicationService->getPaymentApproveView() ? ' AND t1.money_need_approve="1"' : '') .
                ($applicationService->getNoReplyView() ? ' AND t1.id IN (' . (count($applicationService->getNoReplyIds()) > 0 ? implode(',', $applicationService->getNoReplyIds()) : '0') . ')' : '') .
                ($applicationService->getNeedResponseView() ? ' AND id IN (' . (count($applicationService->getNeedResponseIds()) > 0 ? implode(',', $applicationService->getNeedResponseIds()) : '0') . ')' : '') .
                ($applicationService->getNoFillObligView() ? ' AND t1.id IN (' . (count($applicationService->getNoFillObligIds()) > 0 ? implode(',', $applicationService->getNoFillObligIds()) : '0') . ')' : '') .
                ($applicationService->getNonSettledView() ? ' AND id IN (' . (count($applicationService->getNonSettledIds()) > 0 ? implode(',', $applicationService->getNonSettledIds()) : '0') . ')' : '') .
                ($searchQuerySql ? ' AND' . $searchQuerySql : ''),
            $this->getEntity()->getFilters()->getPreparedSearchQueryParams(),
        );
        $applicationsDataCount = count($applicationsData);

        foreach ($applicationsData as $applicationData) {
            if ($applicationData['creator_id'] > 0) {
                $excelHtml .= '<tr><td>' . $applicationData['application_id'] . '</td><td><a href="' . ABSOLUTE_PATH . '/application/application/' . $applicationData['application_id'] . '/act=edit&project_id=' . $applicationData['project_id'] . '">' . $LOCALE_INGAME['to_application'] . '</a></td>';

                $applicationData = array_merge($applicationData, DataHelper::unmakeVirtual($applicationData['allinfo']));

                foreach ($this->getModel()->getElements() as $element) {
                    if (!$element instanceof Item\H1 && !in_array($element->getName(), $excludeElements)) {
                        if ($element->getName() === 'plots_data') {
                            if ($applicationsDataCount < $applicationsLimitForPlots) {
                                $plotsData = $plotService->generateAllPlots($applicationService->getActivatedProjectId(), '{application}', $applicationData['application_id']);
                                $plotsData = preg_replace('#<div[^>]+>#', '', $plotsData);
                                $plotsData = preg_replace('#</div>#', '<br>', $plotsData);
                            } else {
                                $plotsData = sprintf($LOCALE['too_many_applications_for_plots'], $applicationsLimitForPlots);
                            }
                            $element->set($plotsData);
                        } elseif ($element->getName() === 'creator_id') {
                            if ($element instanceof Item\Select) {
                                $userInfo = [
                                    [
                                        $applicationData['creator_id'],
                                        ($applicationData['em'] !== '' ? '<a href="mailto:' . DataHelper::escapeOutput($applicationData['em']) . '">' . DataHelper::escapeOutput($applicationData['em']) . '</a><br>' : '') .
                                            ($applicationData['phone'] !== '' ? '<a href="tel:' . DataHelper::escapeOutput($applicationData['phone']) . '">' . DataHelper::escapeOutput($applicationData['phone']) . '</a><br>' : '') .
                                            '<br>' .
                                            $userService->showName($userService->arrayToModel($applicationData), true) . ', ' .
                                            $LOCALE_GLOBAL['user_id'] . ' ' . $applicationData['sid'] . ', ' .
                                            ($applicationData['gender'] === 2 ? $LOCALE['woman'] : $LOCALE['man']) . ', ' .
                                            $LOCALE['birth'] . ' ' . date('d.m.Y', strtotime($applicationData['birth'])) . '<br>' .
                                            DataHelper::escapeOutput($applicationData['g_city']) . ', ' . DataHelper::escapeOutput($applicationData['g_area']) . '<br>' .
                                            ($applicationData['telegram'] !== '' ? $LOCALE['telegram'] . ': <a target="_blank" href="https://t.me/' . DataHelper::escapeOutput($applicationData['telegram']) . '">' . DataHelper::escapeOutput($applicationData['telegram']) . '</a><br>' : '') .
                                            ($applicationData['skype'] !== '' ? $LOCALE['skype'] . ': <a href="skype:' . DataHelper::escapeOutput($applicationData['skype']) . '">' . DataHelper::escapeOutput($applicationData['skype']) . '</a><br>' : '') .
                                            ($applicationData['facebook_visible'] !== '' ? (true ? '' : $LOCALE['facebook_visible'] . ': ' . $userService->social2(DataHelper::escapeOutput($applicationData['facebook_visible']), 'facebook') . '<br>') : '') . // @phpstan-ignore-line
                                            ($applicationData['vkontakte_visible'] !== '' ? $LOCALE['vkontakte'] . ': ' . $userService->social2(DataHelper::escapeOutput($applicationData['vkontakte_visible']), 'vkontakte') . '<br>' : '') .
                                            ($applicationData['twitter'] !== '' ? $LOCALE['twitter'] . ': ' . $userService->social2(DataHelper::escapeOutput($applicationData['twitter']), 'twitter') . '<br>' : '') .
                                            ($applicationData['livejournal'] !== '' ? $LOCALE['livejournal'] . ': ' . $userService->social2(DataHelper::escapeOutput($applicationData['livejournal']), 'livejournal') . '<br>' : '') .
                                            ($applicationData['linkedin'] !== '' ? $LOCALE['linkedin'] . ': ' . DataHelper::escapeOutput($applicationData['linkedin']) . '<br>' : '') .
                                            ($applicationData['jabber'] !== '' ? $LOCALE['jabber'] . ': ' . DataHelper::escapeOutput($applicationData['jabber']) . '<br>' : '') .
                                            ($applicationData['icq'] !== '' ? $LOCALE['icq'] . ': ' . DataHelper::escapeOutput($applicationData['icq']) . '<br>' : '') .
                                            ($applicationData['sickness'] !== '' ? $LOCALE['sickness'] . ': ' . DataHelper::escapeOutput($applicationData['sickness']) . '<br>' : ''),
                                    ],
                                ];
                                $element->getAttribute()->setValues($userInfo);
                                $element->set($applicationData[$element->getName()] ?? null);
                            }
                        } else {
                            $element->set($applicationData[$element->getName()] ?? null);
                        }

                        $excelHtml .= '<td>' . $this->prepareForExcel($element->asHTML(false)) . '</td>';
                    }
                }

                $excelHtml .= '<td>';

                $excelComments = '';

                if ($applicationsDataCount < $applicationsLimitForComments) {
                    $conversationsData = DB->query("SELECT c.id as c_id, c.name as c_name, c.sub_obj_type as c_sub_obj_type FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id WHERE c.obj_type='{project_application_conversation}' AND c.obj_id=:obj_id GROUP BY c.id ORDER BY MAX(cm.updated_at) DESC", [
                        ['obj_id', $applicationData['application_id']],
                    ]);
                    $conversationsDataCount = count($conversationsData);
                    $dialogData = [];

                    if ($conversationsDataCount > 0) {
                        $applicationModel = $applicationService->arrayToModel($applicationData);

                        foreach ($conversationsData as $conversationData) {
                            $dialogData[] = MessageHelper::conversationTree(
                                $conversationData['c_id'],
                                0,
                                1,
                                '{project_application}',
                                $applicationModel,
                                API_REQUEST: true,
                            );
                        }

                        foreach ($dialogData as $dialogGroup) {
                            foreach ($dialogGroup as $key => $dialogArray) {
                                if ($key !== 'parent') {
                                    $excelComments .= $this->prepareForExcel($dialogArray['author_name'] . ' (' . $LOCALE['titles_conversation_sub_obj_types'][DataHelper::clearBraces($dialogGroup['parent']['save_sub_obj_type'])] . ') - ' . DateHelper::showDateTimeUsual($dialogArray['timestamp']) . '<br>' . $dialogArray['content'] . '<br><br>');
                                }
                            }
                        }
                    }
                } else {
                    $excelComments .= sprintf($LOCALE['too_many_applications_for_comments'], $applicationsLimitForComments);
                }

                $excelHtml .= $excelComments . '</td>';

                $excelHtml .= '</tr>';
            }
        }

        // формируем заголовок таблицы
        $RESPONSE_DATA = '<html><head>
<style>
	table,tr,td {
		border: .5pt black solid;
		border-spacing: 0;
		border-collapse: collapse;
	}
	td {
		padding: 5px;
		vertical-align: top;
		width: auto;
	}
	br {mso-data-placement:same-cell;}
</style>
</head><body>
<table>' . $excelHtml . '</table></body></html>';

        // выгружаем в виде таблицы
        header('Content-type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename=data ' . date('d.m.Y H-i') . '.xls');
        echo $RESPONSE_DATA;
        exit;
    }

    private function prepareForExcel(?string $line): ?string
    {
        if (!is_null($line)) {
            $line = str_replace('<span class="sbi sbi-times"></span>', '-', $line);
            $line = str_replace('<span class="sbi sbi-check"></span>', '+', $line);
            $line = strip_tags($line, '<br><b><i>');
        }

        return $line;
    }
}
