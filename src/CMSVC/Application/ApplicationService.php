<?php

declare(strict_types=1);

namespace App\CMSVC\Application;

use App\CMSVC\Filterset\FiltersetService;
use App\CMSVC\Trait\{ApplicationServiceTrait, GamemastersListTrait, GetUpdatedAtCustomAsHTMLRendererTrait};
use App\Helper\{DateHelper, MessageHelper, RightsHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Element\{Item};
use Fraym\Entity\{Filters, PostChange, PostDelete, PreChange, PreDelete};
use Fraym\Enum\{ActEnum, ActionEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper, ResponseHelper};

/** @extends BaseService<ApplicationModel> */
#[Controller(ApplicationController::class)]
#[PostChange]
#[PostDelete]
#[PreChange]
#[PreDelete]
class ApplicationService extends BaseService
{
    use ApplicationServiceTrait;
    use GamemastersListTrait;
    use GetUpdatedAtCustomAsHTMLRendererTrait;

    private ?bool $mineView = null;
    private ?bool $noreplyView = null;
    private ?bool $needResponseView = null;
    private ?bool $noFillObligView = null;
    private ?bool $nonSettledView = null;
    private ?bool $paymentApproveView = null;
    private ?bool $plotsCountView = null;

    private ?array $noReplyIds = null;
    private ?array $needResponseIds = null;
    private ?array $noFillObligIds = null;
    private ?array $nonSettledIds = null;
    private ?array $generateDocumentValues = null;

    private ?int $excelType = null;
    private ?int $filterset = null;
    private ?array $fixedFilterSets = null;
    private ?int $totalCount = null;

    /** Переменные модели */
    private ?array $usersDataApplicationView = null;
    private ?string $userDataSickness = null;

    public function postClearFilters(): void
    {
        if (ACTION === ActionEnum::clearFilters && RightsHelper::checkAllowProjectActions(PROJECT_RIGHTS, null)) {
            CookieHelper::batchDeleteCookie(['project_filterset_id']);
        }
    }

    public function checkFilterSets(): void
    {
        /** Если это мастер и есть фильтры, убеждаемся, что они включены */
        if ($this->getFixedFilterSets()) {
            /** @var FiltersetService */
            $filtersetService = CMSVCHelper::getService('filterset');

            $isAdmin = RightsHelper::checkAllowProjectActions(PROJECT_RIGHTS, null);

            if (
                (
                    (
                        (
                            !Filters::hasFiltersCookie('application', 'application') ||
                            ACTION === ActionEnum::clearFilters
                        ) &&
                        !$isAdmin
                    ) ||
                    $this->getFilterset() > 0
                ) &&
                ACTION !== ActionEnum::setFilters
            ) {
                $filterId = $this->getFixedFilterSets()[0];

                if ($this->getFilterset() > 0 && in_array($this->getFilterset(), $this->getFixedFilterSets())) {
                    $filterId = $this->getFilterset();
                }

                CookieHelper::batchSetCookie([
                    'project_filterset_id' => (string) $filterId,
                    'redirect_gamemaster_after_set' => ABSOLUTE_PATH . '/' . KIND . '/' . (DataHelper::getId() > 0 ? DataHelper::getId() . '/' : ''),
                ]);

                $filtersetData = $filtersetService->get($filterId);

                ResponseHelper::redirect(
                    ABSOLUTE_PATH . '/' . KIND . '/?object=' . KIND . '&action=setFilters&filterset=' . $filterId . '&' . $filtersetData->link->get(),
                );
            }

            if (ACTION === ActionEnum::setFilters && $this->getFilterset() === 0) {
                if ($isAdmin) {
                    CookieHelper::batchDeleteCookie(['project_filterset_id']);
                } else {
                    // выборка должна идти обязательно с полями установленного фильтра
                    if (!CookieHelper::getCookie('project_filterset_id')) {
                        CookieHelper::batchSetCookie([
                            'project_filterset_id' => $this->getFixedFilterSets()[0],
                        ]);
                    }

                    $projectFiltersetId = CookieHelper::getCookie('project_filterset_id');
                    $filtersetData = $filtersetService->get($projectFiltersetId);

                    if ($filtersetData) {
                        $requestFields = [];

                        foreach ($_REQUEST as $key => $value) {
                            if (is_array($value)) {
                                foreach ($value as $key2 => $value2) {
                                    $requestFields[] = $key . '[' . $key2 . ']=' . $value2;
                                }
                            } else {
                                $requestFields[] = $key . '=' . $value;
                            }
                        }

                        ResponseHelper::redirect(
                            ABSOLUTE_PATH . '/' . KIND . '/?object=' . KIND . '&action=setFilters&filterset=' . $filtersetData->id->getAsInt() . '&' . $filtersetData->link->get() . '&' . implode('&', $requestFields),
                        );
                    }
                }
            }

            /** Проверка права "мастера": если есть фильтры, то попадает ли в них заявка, чтобы ее редактировать, или разрешен только просмотр? */
            $blockedByFiltersets = false;
            $filtersData = [];
            $filterSet = $this->getFilterset();
            $redirectGamemaster = CookieHelper::getCookie('redirect_gamemaster');
            $trueProjectFiltersetId = CookieHelper::getCookie('true_project_filterset_id');

            /** Фальшивая фильтрация нужная только для того, чтобы сохранить недостающие скули запросов по наборам фильтра для мастера */
            if (
                !$isAdmin
                && ACTION === ActionEnum::setFilters
                && $filterSet > 0
                && !CookieHelper::getCookie('gamemasterFiltersSearchQuerySql_' . $filterSet)
                && !is_null($redirectGamemaster)
            ) {
                $filtersData = $this->getEntity()->getFilters()->prepareSearchSqlAndFiltersLink();
                CookieHelper::batchSetCookie([
                    'gamemasterFiltersSearchQuerySql_' . $filterSet => $filtersData[0],
                    'gamemasterFiltersSearchQueryParams_' . $filterSet => $filtersData[1],
                ]);

                $goto = $redirectGamemaster;
                CookieHelper::batchDeleteCookie(['redirect_gamemaster']);

                if (!is_null($trueProjectFiltersetId)) {
                    CookieHelper::batchSetCookie(['project_filterset_id' => $trueProjectFiltersetId]);
                    CookieHelper::batchDeleteCookie(['true_project_filterset_id']);
                }
                ResponseHelper::redirect($goto);
            }

            /** Проверка наличия всех необходимых скулей запросов наборов фильтров и проверка заявки на совпадение с ними */
            if (DataHelper::getId() > 0 && !$isAdmin && $this->applicationData['responsible_gamemaster_id'] !== CURRENT_USER->id()) {
                $blockedByFiltersets = true;

                foreach ($this->getFixedFilterSets() as $fixedFiltersetId) {
                    $gamemasterFiltersSearchQuerySql = CookieHelper::getCookie('gamemasterFiltersSearchQuerySql_' . $fixedFiltersetId);

                    if (!$gamemasterFiltersSearchQuerySql) {
                        $filtersetData = $filtersetService->get($fixedFiltersetId);

                        if ($filtersetData->link->get()) {
                            CookieHelper::batchSetCookie([
                                'redirect_gamemaster' => ABSOLUTE_PATH . '/' . KIND . '/' . DataHelper::getId() . '/',
                                'true_project_filterset_id' => CookieHelper::getCookie('project_filterset_id'),
                            ]);
                            ResponseHelper::redirect(ABSOLUTE_PATH . '/' . KIND . '/?object=' . KIND . '&action=setFilters&filterset=' . $fixedFiltersetId . '&' . $filtersetData->link->get());
                        }
                    }

                    if ($gamemasterFiltersSearchQuerySql !== '') {
                        $checkIdsQuery = DB->query(
                            'SELECT t1.id FROM project_application AS t1 WHERE ' . $gamemasterFiltersSearchQuerySql,
                            CookieHelper::getCookie('gamemasterFiltersSearchQueryParams' . $fixedFiltersetId, true),
                        );

                        foreach ($checkIdsQuery as $checkIdsData) {
                            if ($checkIdsData['id'] === DataHelper::getId()) {
                                $blockedByFiltersets = false;
                                break;
                            }
                        }
                    }
                }
            }

            if ($blockedByFiltersets) {
                $this->getView()->getViewRights()
                    ->setChangeRight(false)
                    ->setDeleteRight(false);
            }

            if ($filterSet > 0) {
                if (!$filtersData) {
                    $filtersData = $this->getEntity()->getFilters()->prepareSearchSqlAndFiltersLink();
                }

                CookieHelper::batchSetCookie([
                    'gamemasterFiltersSearchQuerySql_' . $filterSet => $filtersData[0],
                    'gamemasterFiltersSearchQueryParams_' . $filterSet => $filtersData[1],
                ]);
            }

            $redirectGamemasterAfterSet = CookieHelper::getCookie('redirect_gamemaster_after_set');

            if (!is_null($redirectGamemasterAfterSet)) {
                $goto = $redirectGamemasterAfterSet;
                CookieHelper::batchDeleteCookie(['redirect_gamemaster_after_set']);
                ResponseHelper::redirect($goto);
            }
        }
    }

    public function getMineView(): bool
    {
        if (is_null($this->mineView)) {
            $mineViewCookie = Filters::getFiltersCookieParameterByName('search_responsible_gamemaster_id', 'application', 'application');
            $this->mineView = ($mineViewCookie[0] ?? null) === CURRENT_USER->id();
        }

        return $this->mineView;
    }

    public function getNoReplyView(): bool
    {
        if (is_null($this->noreplyView)) {
            $this->noreplyView = ($_REQUEST['noreply'] ?? false) === '1';
        }

        return $this->noreplyView;
    }

    public function getNeedResponseView(): bool
    {
        if (is_null($this->needResponseView)) {
            $this->needResponseView = ($_REQUEST['needresponse'] ?? false) === '1';
        }

        return $this->needResponseView;
    }

    public function getNoFillObligView(): bool
    {
        if (is_null($this->noFillObligView)) {
            $this->noFillObligView = ($_REQUEST['nofilloblig'] ?? false) === '1';
        }

        return $this->noFillObligView;
    }

    public function getNonSettledView(): bool
    {
        if (is_null($this->nonSettledView)) {
            $this->nonSettledView = ($_REQUEST['nonsettled'] ?? false) === '1';
        }

        return $this->nonSettledView;
    }

    public function getPaymentApproveView(): bool
    {
        if (is_null($this->paymentApproveView)) {
            $this->paymentApproveView = ($_REQUEST['payment_approve'] ?? false) === '1';
        }

        return $this->paymentApproveView;
    }

    /** Переключатель видимости колонки завязок */
    public function getPlotsCountView(): bool
    {
        if (is_null($this->plotsCountView)) {
            $plotsCountView = false;

            if (($_REQUEST['plots_count'] ?? false) === '1') {
                CookieHelper::batchSetCookie(['plots_count_view' => '1']);
                $plotsCountView = true;
            } elseif (($_REQUEST['plots_count'] ?? false) === '2') {
                CookieHelper::batchDeleteCookie(['plots_count_view']);
                $plotsCountView = false;
            } elseif (CookieHelper::getCookie('plots_count_view') === '1') {
                $plotsCountView = true;
            }

            $this->plotsCountView = $plotsCountView;
        }

        return $this->plotsCountView;
    }

    /** Собираем id всех заявок, в которых последним комментом был коммент от создателя заявки */
    public function getNoReplyIds(): array
    {
        if (is_null($this->noReplyIds)) {
            $this->noReplyIds = [];

            $noreplyApplicationsData = DB->query(
                "SELECT pa.id FROM project_application AS pa LEFT JOIN conversation_message AS cm ON cm.id = (SELECT cm2.id FROM conversation AS c LEFT JOIN conversation_message AS cm2 ON cm2.conversation_id=c.id WHERE c.obj_id=pa.id AND c.obj_type='{project_application_conversation}' AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{to_player}') AND cm2.message_action!='{fee_payment}' AND cm2.icon IS NULL ORDER BY cm2.id DESC LIMIT 1) WHERE pa.project_id=:project_id AND cm.creator_id = pa.creator_id AND pa.deleted_by_gamemaster='0' AND pa.deleted_by_player='0' AND pa.status!=4",
                [
                    ['project_id', $this->getActivatedProjectId()],
                ],
            );

            foreach ($noreplyApplicationsData as $noreplyApplicationData) {
                $this->noReplyIds[] = $noreplyApplicationData['id'];
            }
        }

        return $this->noReplyIds;
    }

    /** Собираем id всех заявок, в которых есть коммент с need_response */
    public function getNeedResponseIds(): array
    {
        if (is_null($this->needResponseIds)) {
            $this->needResponseIds = [];

            $needresponseApplicationsData = DB->query(
                "SELECT pa.id FROM project_application AS pa LEFT JOIN conversation AS c ON c.obj_id=pa.id LEFT JOIN conversation_message AS cm ON cm.conversation_id=c.id WHERE c.obj_type='{project_application_conversation}' AND cm.icon LIKE '%need_response%' AND pa.project_id=:project_id AND pa.deleted_by_gamemaster='0' AND pa.deleted_by_player='0' AND pa.status!=4",
                [
                    ['project_id', $this->getActivatedProjectId()],
                ],
            );

            foreach ($needresponseApplicationsData as $needresponseApplicationData) {
                $this->needResponseIds[] = $needresponseApplicationData['id'];
            }
        }

        return $this->needResponseIds;
    }

    /** Собираем id всех заявок, в которых не заполнено хотя бы одно обязательное поле (т.е. его нет в allinfo) */
    public function getNoFillObligIds(): array
    {
        if (is_null($this->noFillObligIds)) {
            $this->noFillObligIds = [];

            $noFillObligViewScript = [];

            foreach ($this->getApplicationFields() as $applicationFieldId => $applicationField) {
                if ($applicationField->getObligatory()) {
                    $noFillObligViewScript[] = ['allinfo', '%[virtual' . $applicationFieldId . ']%', [OperandEnum::NOT_LIKE]];
                }
            }

            $nofillobligApplicationsData = DB->select(
                tableName: 'project_application',
                criteria: array_merge(
                    [
                        'project_id' => $this->getActivatedProjectId(),
                    ],
                    $noFillObligViewScript,
                ),
                fieldsSet: [
                    'id',
                ],
            );

            foreach ($nofillobligApplicationsData as $nofillobligApplicationData) {
                $this->noFillObligIds[] = $nofillobligApplicationData['id'];
            }
        }

        return $this->noFillObligIds;
    }

    /** Собираем id всех заявок, в которых не заполнено поселение */
    public function getNonSettledIds(): array
    {
        if (is_null($this->nonSettledIds)) {
            $this->nonSettledIds = [];

            $applicationsWithNoRoom = DB->query(
                "SELECT pa.id FROM project_application AS pa LEFT JOIN relation AS r ON pa.id=r.obj_id_from AND r.type='{member}' AND r.obj_type_to='{room}' AND r.obj_type_from='{application}' WHERE pa.project_id=:project_id AND r.obj_id_to IS NULL",
                [
                    ['project_id', $this->getActivatedProjectId()],
                ],
            );

            foreach ($applicationsWithNoRoom as $applicationWithNoRoom) {
                $this->nonSettledIds[] = $applicationWithNoRoom['id'];
            }
        }

        return $this->nonSettledIds;
    }

    /** Проверяем наличие документов в генераторе документов */
    public function getGenerateDocumentValues(): array
    {
        if (is_null($this->generateDocumentValues)) {
            $generateDocumentDatas = DB->select(
                'document',
                [
                    'project_id' => $this->getActivatedProjectId(),
                ],
            );

            if ($generateDocumentDatas) {
                foreach ($generateDocumentDatas as $generateDocumentData) {
                    $this->generateDocumentValues[] = [$generateDocumentData['id'], DataHelper::escapeOutput($generateDocumentData['name'])];
                }
            }
        }

        return $this->generateDocumentValues ?? [];
    }

    public function getExcelType(): int
    {
        if (is_null($this->excelType)) {
            $this->excelType = (int) ($_REQUEST['export_to_excel'] ?? 0) - 1;
        }

        return $this->excelType;
    }

    public function getFilterset(): ?int
    {
        if (is_null($this->filterset)) {
            $filterset = $_REQUEST['filterset'] ?? null;
            $this->filterset = $filterset ? (int) $filterset : null;
        }

        return $this->filterset;
    }

    /** Проверка наличия наборов фильтров */
    public function getFixedFilterSets(): array
    {
        if (is_null($this->fixedFilterSets)) {
            $this->fixedFilterSets = [];

            $userProjectRelations = DB->select(
                'relation',
                [
                    'obj_type_to' => '{project}',
                    'obj_id_to' => $this->getActivatedProjectId(),
                    'obj_type_from' => '{user}',
                    'obj_id_from' => CURRENT_USER->id(),
                    ['type', '{member}', [OperandEnum::NOT_EQUAL]],
                ],
            );

            foreach ($userProjectRelations as $userProjectRelation) {
                if (!is_null($userProjectRelation['comment']) && !json_decode($userProjectRelation['comment'])) {
                    $commentData = DataHelper::multiselectToArray($userProjectRelation['comment']);

                    foreach ($commentData as $possibleFilterset) {
                        if (is_numeric($possibleFilterset)) {
                            $this->fixedFilterSets[] = $possibleFilterset;
                        }
                    }
                }
            }
        }

        return $this->fixedFilterSets;
    }

    public function getTotalCount(): int
    {
        if (is_null($this->totalCount)) {
            $this->totalCount = DB->count(
                tableName: 'project_application',
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                    'deleted_by_gamemaster' => '0',
                    'deleted_by_player' => '0',
                    ['status', '4', [OperandEnum::NOT_EQUAL]],
                ],
            );
        }

        return $this->totalCount;
    }

    /** Массовое выставление группы отфильтрованным заявкам */
    public function setSpecialGroup(int $objId, ?string $filter = null): array
    {
        $LOCALE_APPLICATION = $this->getLocale();

        $returnArr = [];

        $groupData = DB->findObjectById($objId, 'project_group');

        if ($groupData['project_id'] === $this->getActivatedProjectId()) {
            $listOfIds = [];

            $deletedView = $filter === 'deleted_view';
            $noreplyView = $filter === 'noreply_view';
            $needResponseView = $filter === 'needresponse_view';
            $paymentApproveView = $filter === 'payment_approve_view';
            $noFillObligView = $filter === 'nofilloblig_view';
            $nonSettledView = $filter === 'nonsettled_view';

            $applicationsData = DB->query(
                'SELECT t1.* FROM project_application t1 WHERE t1.project_id=:project_id AND project_group_ids NOT LIKE :project_group_ids' .
                    ($deletedView ? ' AND (t1.deleted_by_gamemaster="1" OR t1.deleted_by_player="1")' : ' AND t1.deleted_by_gamemaster="0" AND t1.deleted_by_player="0"') .
                    ($paymentApproveView ? ' AND t1.money_need_approve="1"' : '') .
                    ($noreplyView ? ' AND t1.id IN (' . (count($this->getNoReplyIds()) > 0 ? ':noreply_ids' : '0') . ')' : '') .
                    ($needResponseView ? ' AND id IN (' . (count($this->getNeedResponseIds()) > 0 ? ':needresponse_ids' : '0') . ')' : '') .
                    ($noFillObligView ? ' AND t1.id IN (' . (count($this->getNoFillObligIds()) > 0 ? ':nofilloblig_ids' : '0') . ')' : '') .
                    ($nonSettledView ? ' AND t1.id IN (' . (count($this->getNonSettledIds()) > 0 ? ':nonsettled_ids' : '0') . ')' : '') .
                    $this->getEntity()->getFilters()->getPreparedSearchQuerySql(),
                [
                    ['project_id', $this->getActivatedProjectId()],
                    ['project_group_ids', '%-' . $objId . '-%'],
                    ['noreply_ids', $this->getNoReplyIds()],
                    ['needresponse_ids', $this->getNeedResponseIds()],
                    ['nofilloblig_ids', $this->getNoFillObligIds()],
                    ['nonsettled_ids', $this->getNonSettledIds()],
                    ...$this->getEntity()->getFilters()->getPreparedSearchQueryParams(),
                ],
            );

            foreach ($applicationsData as $applicationData) {
                $listOfIds[] = $applicationData['id'];
            }

            if (count($listOfIds) > 0) {
                DB->query(
                    'UPDATE project_application SET project_group_ids = CONCAT(project_group_ids, :obj_id) WHERE id IN (:ids)',
                    [
                        ['obj_id', $objId . '-'],
                        ['ids', $listOfIds],
                    ],
                );

                foreach ($listOfIds as $applicationId) {
                    RightsHelper::addRights('{member}', '{group}', $objId, '{application}', $applicationId);
                }

                $distributedItemAutoset = DataHelper::multiselectToArray($groupData['distributed_item_autoset']);

                if (count($distributedItemAutoset) > 0) {
                    foreach ($distributedItemAutoset as $distributedItemAutosetId) {
                        DB->query(
                            'UPDATE project_application SET distributed_item_ids = CONCAT(distributed_item_ids, :distributed_item_ids) WHERE id IN (:ids) AND project_id=:project_id AND distributed_item_ids NOT LIKE :distributed_item_autoset_id',
                            [
                                ['distributed_item_ids', $distributedItemAutosetId . '-'],
                                ['ids', $listOfIds],
                                ['project_id', $this->getActivatedProjectId()],
                                ['distributed_item_autoset_id', '%-' . $distributedItemAutosetId . '-%'],
                            ],
                        );
                    }
                }

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE_APPLICATION['messages']['set_special_group'],
                ];
            } else {
                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE_APPLICATION['messages']['set_special_group'],
                ];
            }
        }

        return $returnArr;
    }

    /** Исправление имени персонажа в соответствии с именем из заявки */
    public function fixCharacterNameBySorter(int|string $objId, ?string $name = null): ?array
    {
        if (
            DB->update(
                'project_character',
                [
                    'name' => $name,
                ],
                [
                    'id' => $objId,
                    'project_id' => $this->getActivatedProjectId(),
                ],
            ) !== false
        ) {
            return [
                'response' => 'success',
            ];
        }

        return null;
    }

    /**  Получение информации по заявкам поиском по комментариям */
    public function getApplicationsCommentsTable(string $objName): array
    {
        $LOCALE_APPLICATION = $this->getLocale();
        $LOCALE_REGISTRATION = LocaleHelper::getLocale(['registration', 'global']);
        $LOCALE_PROFILE = LocaleHelper::getLocale(['profile', 'fraym_model']);

        $objName = mb_strtolower($objName);

        $responseData = '<table class="menutable applications_search_table"><thead><tr class="menu"><th id="th_header_application">' . $LOCALE_REGISTRATION['th_header_application'] . '</th><th id="th_header_user">' . $LOCALE_REGISTRATION['th_header_user'] . '</th><th id="th_header_vkontakte">' . $LOCALE_APPLICATION['vkontakte'] . '</th><th id="th_header_telegram">' . $LOCALE_APPLICATION['telegram'] . '</th><th id="th_header_phone">' . $LOCALE_PROFILE['elements']['phone']['shownName'] . '</th></tr></thead><tbody>';

        if ($objName !== '') {
            $result = DB->query(
                "SELECT
				pa.id AS project_application_id,
				pa.sorter AS project_application_sorter,
				u.*
			FROM
				project_application AS pa LEFT JOIN
				user AS u ON u.id = pa.creator_id LEFT JOIN
			    conversation AS c ON c.obj_id=pa.id LEFT JOIN
			    conversation_message AS cm ON cm.conversation_id=c.id
			WHERE
				pa.project_id = :project_id AND
				c.obj_type='{project_application_conversation}' AND
				LOWER(cm.content) LIKE :content AND
				pa.deleted_by_gamemaster = '0'
			GROUP BY
		        pa.id
		    ORDER BY
				pa.sorter",
                [
                    ['project_id', $this->getActivatedProjectId()],
                    ['content', '%' . $objName . '%'],
                ],
            );
            $i = 0;

            foreach ($result as $applicationData) {
                $responseData .= '<tr class="string' . ($i % 2 === 0 ? '1' : '2') . '">';

                $responseData .= '<td><a href="/application/' . $applicationData['project_application_id'] . '/" target="_blank">' . $applicationData['project_application_sorter'] . '</a></td><td>' . preg_replace(
                    '#<a#',
                    '<a target="_blank"',
                    $this->getUserService()->showNameWithId($this->getUserService()->arrayToModel($applicationData), true),
                ) .
                    '</td><td>' . ($applicationData['vkontakte_visible'] ? $this->getUserService()->social2(
                        DataHelper::escapeOutput($applicationData['vkontakte_visible']),
                        'vkontakte',
                    ) : '') .
                    '</td><td>' . ($applicationData['telegram'] ? '<a target="_blank" href="https://t.me/' .
                        DataHelper::escapeOutput($applicationData['telegram']) . '">' .
                        DataHelper::escapeOutput($applicationData['telegram']) . '</a>' : '') .
                    '</td><td>' . ($applicationData['phone'] ? '<a href="tel:' . DataHelper::escapeOutput($applicationData['phone']) . '">' .
                        DataHelper::escapeOutput($applicationData['phone']) . '</a>' : '') . '</td>';

                $responseData .= '</tr>';

                ++$i;
            }
        }

        $responseData .= '</tbody></table>';

        return [
            'response' => 'success',
            'response_data' => $responseData,
        ];
    }

    /** Получение информации по заявкам поиском */
    public function getApplicationsTable(string $objName): array
    {
        $LOCALE_APPLICATION = $this->getLocale();
        $LOCALE_REGISTRATION = LocaleHelper::getLocale(['registration', 'global']);
        $LOCALE_PROFILE = LocaleHelper::getLocale(['profile', 'fraym_model']);

        $objName = mb_strtolower($objName);

        $responseData = '<table class="menutable applications_search_table"><thead><tr class="menu"><th id="th_header_application">' . $LOCALE_REGISTRATION['th_header_application'] . '</th><th id="th_header_user">' . $LOCALE_REGISTRATION['th_header_user'] . '</th><th id="th_header_vkontakte">' . $LOCALE_APPLICATION['vkontakte'] . '</th><th id="th_header_telegram">' . $LOCALE_APPLICATION['telegram'] . '</th><th id="th_header_phone">' . $LOCALE_PROFILE['elements']['phone']['shownName'] . '</th></tr></thead><tbody>';

        if ($objName !== '') {
            $result = DB->query(
                'SELECT
				pa.id AS project_application_id,
				pa.sorter AS project_application_sorter,
				u.*
			FROM
				project_application AS pa LEFT JOIN
				user AS u ON u.id = pa.creator_id
			WHERE
				pa.project_id = :project_id AND
				(
                    LOWER(pa.sorter) LIKE :search_string_1 OR
                    LOWER(u.fio) LIKE :search_string_2 OR
                    LOWER(u.vkontakte_visible) LIKE :social_search_string_1 OR
                    LOWER(u.telegram) LIKE :social_search_string_2 OR
                    LOWER(u.nick) LIKE :search_string_3' . (is_numeric($objName) ? ' OR u.sid = :obj_name' : '') . "
				) AND
				pa.deleted_by_gamemaster = '0'
			GROUP BY
		        pa.id
		    ORDER BY
				pa.sorter",
                [
                    ['project_id', $this->getActivatedProjectId()],
                    ['search_string_1', '%' . mb_strtolower($objName) . '%'],
                    ['search_string_2', '%' . mb_strtolower($objName) . '%'],
                    ['search_string_3', '%' . mb_strtolower($objName) . '%'],
                    ['social_search_string_1', '%' . $this->getUserService()->social($objName) . '%'],
                    ['social_search_string_2', '%' . $this->getUserService()->social($objName) . '%'],
                    ['obj_name', $objName],
                ],
            );
            $i = 0;

            foreach ($result as $applicationData) {
                $responseData .= '<tr class="string' . ($i % 2 === 0 ? '1' : '2') . '">';

                $responseData .= '<td><a href="/application/' . $applicationData['project_application_id'] . '/" target="_blank">' . $applicationData['project_application_sorter'] . '</a></td><td>' . preg_replace(
                    '#<a#',
                    '<a target="_blank"',
                    $this->getUserService()->showNameWithId($this->getUserService()->arrayToModel($applicationData), true),
                ) . '</td><td>' . ($applicationData['vkontakte_visible'] ? $this->getUserService()->social2(
                    DataHelper::escapeOutput($applicationData['vkontakte_visible']),
                    'vkontakte',
                ) : '') .
                    '</td><td>' . ($applicationData['telegram'] ? '<a target="_blank" href="https://t.me/' .
                        DataHelper::escapeOutput($applicationData['telegram']) . '">' .
                        DataHelper::escapeOutput($applicationData['telegram']) . '</a>' : '') .
                    '</td><td>' . ($applicationData['phone'] ? '<a href="tel:' . DataHelper::escapeOutput($applicationData['phone']) . '">' .
                        DataHelper::escapeOutput($applicationData['phone']) . '</a>' : '') . '</td>';

                $responseData .= '</tr>';

                ++$i;
            }
        }

        $responseData .= '</tbody></table>';

        return [
            'response' => 'success',
            'response_data' => $responseData,
        ];
    }

    /** Приглашение игрока (передачи ему) в заявку */
    public function transferApplication(int $objId, int $offerToUserId): array
    {
        $LOCALE_APPLICATION = $this->getLocale();

        DB->update(
            tableName: 'project_application',
            data: [
                'offer_to_user_id' => $offerToUserId,
                'offer_denied' => 0,
                'deleted_by_gamemaster' => '0',
            ],
            criteria: [
                'id' => $objId,
            ],
        );

        return [
            'response' => 'success',
            'response_text' => sprintf(
                $LOCALE_APPLICATION['messages']['transfer_application_success'],
                $this->getUserService()->showNameWithId($this->getUserService()->get($offerToUserId)),
            ),
        ];
    }

    /** Отмена приглашения игрока (передачи ему) в заявку */
    public function transferApplicationCancel(int $objId): array
    {
        $LOCALE_APPLICATION = $this->getLocale();

        $returnArr = [];

        $applicationData = DB->findObjectById($objId, 'project_application');

        if ($applicationData['offer_to_user_id'] > 0) {
            DB->update(
                tableName: 'project_application',
                data: [
                    'offer_to_user_id' => 0,
                    'offer_denied' => 0,
                ],
                criteria: [
                    'id' => $objId,
                ],
            );

            $returnArr = [
                'response' => 'success',
                'response_text' => $LOCALE_APPLICATION['messages']['transfer_application_cancel_success'],
            ];
        }

        return $returnArr;
    }

    /** Функции модели и вьюшки */
    public function preChange(): void
    {
        $this->preChangeHelper();

        $projectData = $this->getProjectData();
        $characterData = $this->getCharacterData();

        /** Выделяем заявку в нового персонажа, если у персонажа выставлен такой признак */
        if (
            $characterData?->auto_new_character_creation->get()
            && $characterData?->applications_needed_count->get() > 1
            && in_array(($_REQUEST['status'][0] ?? null), [2, 3])
        ) {
            $sorterFieldName = $characterData->team_character->get() ? 'sorter2' : 'sorter';

            $newCharacterSorter = $_REQUEST['virtual' . $projectData->$sorterFieldName->get()][0] ?? null;

            if (!$newCharacterSorter) {
                $newCharacterSorter = $characterData->name->get();
            }

            DB->insert(
                tableName: 'project_character',
                data: [
                    'project_group_ids' => DataHelper::arrayToMultiselect($characterData->project_group_ids->get()),
                    'setparentgroups' => $characterData->setparentgroups->get(),
                    'disallow_applications' => 1,
                    'team_character' => $characterData->team_character->get(),
                    'name' => $newCharacterSorter,
                    'applications_needed_count' => 1,
                    'auto_new_character_creation' => 0,
                    'team_applications_needed_count' => $characterData->team_applications_needed_count->get(),
                    'content' => $characterData->name->get(),
                    'project_id' => $this->getActivatedProjectId(),
                    'updated_at' => DateHelper::getNow(),
                    'created_at' => DateHelper::getNow(),
                ],
            );
            $newCharacterId = DB->lastInsertId();

            $characterGroups = $characterData->project_group_ids->get();

            if (is_array($characterGroups) && $characterGroups) {
                /** Добавляем связи с нужным code */
                foreach ($characterGroups as $characterGroup) {
                    $code = 1;
                    $lastCharacterInGroup = DB->select(
                        tableName: 'relation',
                        criteria: [
                            'obj_type_to' => '{group}',
                            'obj_id_to' => $characterGroup,
                            'obj_type_from' => '{character}',
                            'type' => '{member}',
                        ],
                        oneResult: true,
                        order: [
                            'comment DESC',
                        ],
                        limit: 1,
                    );

                    if ((int) $lastCharacterInGroup['comment'] > 0) {
                        $code = (int) $lastCharacterInGroup['comment'] + 1;
                    }

                    RightsHelper::addRights(
                        '{member}',
                        '{group}',
                        $characterGroup,
                        '{character}',
                        $newCharacterId,
                        (string) $code,
                    );
                }
            }

            DB->update(
                tableName: 'project_character',
                data: [
                    'applications_needed_count' => $characterData->applications_needed_count->get() - 1,
                ],
                criteria: [
                    'id' => $characterData->id->getAsInt(),
                ],
            );

            $_REQUEST['project_character_id'][0] = $_REQUEST['project_character_id'][0] = $newCharacterId;

            $characterData = $this->getCharacterData($newCharacterId);

            ResponseHelper::success($this->getLOCALE()['messages']['application_autonewrole_success']);
        }
    }

    public function preDelete(): void
    {
        $LOCALE = $this->getLOCALE()['messages'];
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $applicationData = $this->getApplicationData(DataHelper::getId());
        $projectData = $this->getProjectData();

        $applicationName = DataHelper::escapeOutput($applicationData['sorter']);
        $projectName = DataHelper::escapeOutput($projectData->name->get());
        $updatingUserData = $this->getUserService()->get(CURRENT_USER->id());

        if ($applicationData['deleted_by_player'] === '1') {
            $this->deletedApplicationsData[$applicationData['id']] = $applicationData;

            DB->delete(
                tableName: 'project_application_history',
                criteria: [
                    'project_application_id' => DataHelper::getId(),
                ],
            );
        } else {
            DB->update(
                tableName: 'project_application',
                data: [
                    'deleted_by_gamemaster' => '1',
                ],
                criteria: [
                    'id' => DataHelper::getId(),
                    'project_id' => $this->getActivatedProjectId(),
                ],
            );

            $userIds = [
                $applicationData['creator_id'],
            ];

            $subject = sprintf(
                $LOCALE['application_delete_to_player_subject'],
                $applicationName,
                $projectName,
            );
            $message = sprintf(
                $LOCALE['application_delete_to_player_message'],
                ABSOLUTE_PATH,
                DataHelper::getId(),
                $applicationName,
                ABSOLUTE_PATH,
                $updatingUserData->sid->get(),
                $this->getUserService()->showName($updatingUserData),
            ) . '<br><br><a href="' . ABSOLUTE_PATH . '/myapplication/' . DataHelper::getId() . '/">' . ABSOLUTE_PATH . '/myapplication/' . DataHelper::getId() . '/</a>' . $LOCALE_CONVERSATION['subscription']['base_text2'];

            MessageHelper::prepareEmails($userIds, [
                'author_name' => $this->getUserService()->showName($updatingUserData, false),
                'author_email' => DataHelper::escapeOutput($updatingUserData->em->get()),
                'name' => $subject,
                'content' => $message,
                'obj_type' => 'project_application',
                'obj_id' => $applicationData['id'],
            ]);

            MessageHelper::preparePushs($userIds, [
                'user_id_from' => $updatingUserData->id->getAsInt(),
                'message_img' => $this->getUserService()->photoLink($updatingUserData),
                'header' => $applicationName . ' (' . $projectName . ')',
                'content' => trim(strip_tags(str_replace('<br>', "\n", $subject))),
                'obj_type' => 'myapplication',
                'obj_id' => $applicationData['id'],
            ]);

            $this->postDelete([DataHelper::getId()]);

            ResponseHelper::response([['success', $LOCALE['application_deleted_success']]], '/application/');
        }
    }

    public function getSortCreatorId(): array
    {
        if (is_null($this->usersDataTableView)) {
            $this->getUsersDataTableViewShort();
        }

        return $this->usersDataTableView;
    }

    public function getSortResponsibleGamemasterId(): array
    {
        return $this->getGamemastersList();
    }

    public function getSortLastUpdateUserId(): array
    {
        if (is_null($this->usersDataTableViewShort)) {
            $this->getUsersDataTableViewShort();
        }

        return $this->usersDataTableViewShort;
    }

    public function checkRightsChangeDelete(): bool
    {
        return !$this->getHistoryView();
    }

    public function checkRightsViewRestrict(): string
    {
        return
            'project_id=' . $this->getActivatedProjectId() .
            ($this->getDeletedView() ? ' AND (deleted_by_player="1")' : (in_array($this->getAct(), [ActEnum::view, ActEnum::edit]) ? '' : ' AND deleted_by_gamemaster="0" AND deleted_by_player="0"')) .
            ($this->getPaymentApproveView() ? ' AND money_need_approve="1"' : '') .
            ($this->getNoReplyView() ? ' AND id IN (' . (count($this->getNoReplyIds()) > 0 ? implode(',', $this->getNoReplyIds()) : '0') . ')' : '') .
            ($this->getNeedResponseView() ? ' AND id IN (' . (count($this->getNeedResponseIds()) > 0 ? implode(',', $this->getNeedResponseIds()) : '0') . ')' : '') .
            ($this->getNoFillObligView() ? ' AND id IN (' . (count($this->getNoFillObligIds()) > 0 ? implode(',', $this->getNoFillObligIds()) : '0') . ')' : '') .
            ($this->getNonSettledView() ? ' AND id IN (' . (count($this->getNonSettledIds()) > 0 ? implode(',', $this->getNonSettledIds()) : '0') . ')' : '');
    }

    public function getCreatorIdValues(): array
    {
        if (is_null($this->usersDataApplicationView)) {
            $this->getUsersDataApplicationViewAndMedicalSickness();
        }

        return $this->usersDataApplicationView;
    }

    public function getResponsibleGamemasterIdDefault(): int|string|null
    {
        return CURRENT_USER->id();
    }

    public function getResponsibleGamemasterIdValues(): array
    {
        return $this->getGamemastersList();
    }

    public function getTeamApplicationContext(): array
    {
        if ($this->getHasTeamApplications()) {
            return [ApplicationModel::APPLICATION_VIEW_CONTEXT];
        }

        return [];
    }

    public function getProjectGroupIdsMultiselectCreatorUpdatedAt(): int|string|null
    {
        return DateHelper::getNow();
    }

    public function getProjectGroupIdsMultiselectCreatorCreatedAt(): int|string|null
    {
        return DateHelper::getNow();
    }

    public function getProjectGroupIdsMultiselectCreatorProjectId(): int|string|null
    {
        return $this->getActivatedProjectId();
    }

    public function getDistributedItemIdsValues(): array
    {
        return array_merge(
            [['hidden', '']],
            $this->getActivatedProjectId() ? DB->getArrayOfItemsAsArray('resource WHERE project_id=' . $this->getActivatedProjectId() . " AND distributed_item='1'", 'id', 'name') : [],
        );
    }

    public function getDistributedItemIdsCreatedAtUpdatedAt(): int
    {
        return DateHelper::getNow();
    }

    public function getDistributedItemIdsMultiselectCreatorProjectId(): int|string|null
    {
        return $this->getActivatedProjectId();
    }

    public function getDistributedItemIdsMultiselectCreatorCreatorId(): int|string|null
    {
        return CURRENT_USER->id();
    }

    public function getUserSicknessDefault(): ?string
    {
        if (is_null($this->userDataSickness)) {
            $this->getUsersDataApplicationViewAndMedicalSickness();
        }

        return $this->userDataSickness;
    }

    private function updateApplication(int $successfulResultsId): void
    {
        $LOCALE = $this->getLOCALE()['messages'];
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $projectData = $this->getProjectData();
        $oldApplicationData = $this->getApplicationData();
        $characterData = $this->getCharacterData();
        $currentApplication = $this->get($successfulResultsId, refresh: true);

        /** Корректируем sorter */
        $sorterFieldName = $oldApplicationData['team_application'] === '1' ? 'sorter2' : 'sorter';

        $newCharacterSorter = $_REQUEST['virtual' . $projectData->$sorterFieldName->get()][0] ?? null;

        if (!$newCharacterSorter && $characterData->name->get()) {
            $newCharacterSorter = $characterData->name->get();
        }
        DB->update(
            tableName: 'project_application',
            data: [
                'sorter' => ($newCharacterSorter !== '' ? $newCharacterSorter : $LOCALE_FRAYM['basefunc']['not_set']),
            ],
            criteria: [
                'id' => $successfulResultsId,
            ],
        );

        /** Проверяем требуемый и выплаченный взнос, выставляем или убираем "взнос сдан" */
        if ($this->getCanSetFee()) {
            DB->update(
                tableName: 'project_application',
                data: [
                    'money_paid' => (int) ($currentApplication->money_provided->get() >= $currentApplication->money->get()),
                ],
                criteria: [
                    'id' => $successfulResultsId,
                ],
            );

            /** Если была изменена вручную сумма и у нас есть всего одна опция взноса, логично отвязать ее от изменений через раздел опций, т.к. это какой-то специальный, особенный взнос. При этом мы пока что не хотим совсем уж терять понимание, к какой опции была привязка. */
            if (count($this->getFeeOptions()) === 1 && (int) $oldApplicationData['money'] !== $currentApplication->money->get()) {
                $firstFeeOption = $this->getFeeOptions()[0][0];

                $feeId = str_replace('.1', '', $firstFeeOption);
                $feeData = DB->findObjectById((int) $feeId, 'project_fee');
                DB->update(
                    tableName: 'project_application',
                    data: [
                        'project_fee_ids' => '-' . $firstFeeOption . ((int) $feeData['cost'] === $currentApplication->money->get() ? '' : '.1') . '-',
                    ],
                    criteria: [
                        'id' => $successfulResultsId,
                    ],
                );
            }
        }

        /** Если выставлен статус "принята", то зачищаем "предварительно занят" у персонажа */
        if ($currentApplication->status->get() === 3 && $characterData && $characterData->maybetaken->get() !== null) {
            DB->update(
                tableName: 'project_character',
                data: [
                    'maybetaken' => '',
                ],
                criteria: [
                    'id' => $characterData->id->getAsInt(),
                ],
            );
        }

        /** Проставляем / обновляем связку с проживанием */
        $checkRoom = RightsHelper::findOneByRights('{member}', '{room}', null, '{application}', $successfulResultsId);
        $newRoom = ($_REQUEST['rooms_selector'][0] ?? null);

        if ($newRoom !== $checkRoom) {
            RightsHelper::deleteRights('{member}', '{room}', null, '{application}', $successfulResultsId);

            if ($newRoom > 0) {
                RightsHelper::addRights(
                    '{member}',
                    '{room}',
                    $newRoom,
                    '{application}',
                    $successfulResultsId,
                );
            }
        }

        /** Сравниваем старую информацию в заявке с новой и рассылаем оповещения, если есть изменения */
        $oldAllinfo = DataHelper::unmakeVirtual($oldApplicationData['allinfo']);
        $oldAllinfo = array_merge($oldApplicationData, $oldAllinfo);
        unset($oldAllinfo['allinfo']);

        $newApplicationData = DB->findObjectById($successfulResultsId, 'project_application', true);
        $newAllinfo = DataHelper::unmakeVirtual($newApplicationData['allinfo']);
        $newAllinfo = array_merge($newApplicationData, $newAllinfo);
        unset($newAllinfo['allinfo']);
        unset($newApplicationData);

        $sendChange = false;
        $sendChangeToPlayer = false;
        $fieldsDataToGamemasters = '';
        $fieldsDataToPlayer = '';

        $applicationName = DataHelper::escapeOutput($currentApplication->sorter->get());
        $projectName = DataHelper::escapeOutput($projectData->name->get());
        $updatingUserData = $this->getUserService()->get(CURRENT_USER->id());

        if ($currentApplication->creator_id->getAsInt() > 0) {
            $creatorUserData = $this->getUserService()->get($currentApplication->creator_id->getAsInt());
        } else {
            $creatorUserData = $updatingUserData;
        }

        foreach ($this->getModel()->getElements() as $elem) {
            if (($oldAllinfo[$elem->getName()] ?? null) !== ($newAllinfo[$elem->getName()] ?? null) && !$elem instanceof Item\H1 && !$elem instanceof Item\Timestamp && !$elem instanceof Item\Hidden && $elem->getName() !== 'last_update_user_id') {
                $sendChange = true;

                $elem->set($newAllinfo[$elem->getName()]);
                $elemData = $elem->asHTML(false);
                $elemData = str_replace('<span class="sbi sbi-times"></span>', '-', $elemData);
                $elemData = str_replace('<span class="sbi sbi-check"></span>', '+', $elemData);

                $fieldsDataToGamemasters .= $elem->getShownName() . ':<br>' . $elemData . '<br><br>';

                /** Если это изменения скрытых мастерских групп */
                if ($elem->getName() === 'project_group_ids') {
                    /** @var Item\Multiselect $elem */
                    $visibleGroupChanged = false;
                    $newGroups = DataHelper::multiselectToArray($newAllinfo[$elem->getName()]);
                    $oldGroups = DataHelper::multiselectToArray($oldAllinfo[$elem->getName()]);
                    $groupsDiff = array_diff($newGroups, $oldGroups);

                    foreach ($groupsDiff as $groupsDiffId) {
                        if (!in_array($groupsDiffId, $this->getProjectGroupsPlayerNotSee())) {
                            $visibleGroupChanged = true;
                            break;
                        }
                    }

                    if ($visibleGroupChanged) {
                        $oldValues = $elem->getValues();
                        $newValues = [];

                        foreach ($oldValues as $oldValue) {
                            if (!in_array($oldValue[0], $this->getProjectGroupsPlayerNotSee())) {
                                $newValues[] = $oldValue;
                            }
                        }
                        $elem->getAttribute()->setValues($newValues);

                        $elemData = $elem->asHTML(false);
                        $elemData = str_replace('<span class="sbi sbi-times"></span>', '-', $elemData);
                        $elemData = str_replace('<span class="sbi sbi-check"></span>', '+', $elemData);

                        $sendChangeToPlayer = true;
                        $fieldsDataToPlayer .= $elem->getShownName() . ':<br>' . $elemData . '<br><br>';

                        $elem->getAttribute()->setValues($oldValues);
                        unset($oldValues);
                        unset($newValues);
                    }
                } elseif (in_array(ApplicationModel::MYAPPLICATION_VIEW_CONTEXT, $elem->getAttribute()->getContext())) {
                    $sendChangeToPlayer = true;
                    $fieldsDataToPlayer .= $elem->getShownName() . ':<br>' . $elemData . '<br><br>';
                }
            }
        }

        if ($fieldsDataToGamemasters !== '') {
            $fieldsDataToGamemasters = '<br><br>' . mb_substr($fieldsDataToGamemasters, 0, -8);
        }

        if ($fieldsDataToPlayer !== '') {
            $fieldsDataToPlayer = '<br><br>' . mb_substr($fieldsDataToPlayer, 0, -8);
        }

        if ($sendChange) {
            $userIds = [];

            if ($currentApplication->responsible_gamemaster_id->get() > 0) {
                $userIds[] = $currentApplication->responsible_gamemaster_id->get();
            } else {
                $userIds = RightsHelper::findByRights(
                    ['{admin}', '{gamemaster}'],
                    '{project}',
                    $this->getActivatedProjectId(),
                    '{user}',
                    false,
                );
            }

            $subscriptionUsers = RightsHelper::findByRights(
                '{subscribe}',
                '{project_application}',
                $successfulResultsId,
                '{user}',
                false,
            );

            if (is_array($subscriptionUsers)) {
                $userIds = array_merge($userIds, $subscriptionUsers);
            }
            $userIds = array_unique($userIds);

            $subject = sprintf(
                $LOCALE['application_change_to_gamemasters_subject'],
                $applicationName,
                $projectName,
            );
            $message = sprintf(
                $LOCALE['application_change_to_gamemasters_message'],
                ABSOLUTE_PATH,
                $successfulResultsId,
                $this->getActivatedProjectId(),
                $applicationName,
                ABSOLUTE_PATH,
                $creatorUserData->sid->get(),
                $this->getUserService()->showName($creatorUserData),
                ABSOLUTE_PATH,
                $updatingUserData->sid->get(),
                $this->getUserService()->showName($updatingUserData),
            ) . $fieldsDataToGamemasters . '<br><br><a href="' . ABSOLUTE_PATH . '/application/' . $successfulResultsId . '/act=edit&project_id=' . $this->getActivatedProjectId() . '">' . ABSOLUTE_PATH . '/application/' . $successfulResultsId . '/act=edit&project_id=' . $this->getActivatedProjectId() . '</a>' . $LOCALE_CONVERSATION['subscription']['base_text2'];

            if ($userIds) {
                MessageHelper::prepareEmails($userIds, [
                    'author_name' => $this->getUserService()->showName($updatingUserData, false),
                    'author_email' => DataHelper::escapeOutput($updatingUserData->em->get()),
                    'name' => $subject,
                    'content' => $message,
                    'obj_type' => 'project_application',
                    'obj_id' => $currentApplication->id->getAsInt(),
                ]);

                MessageHelper::preparePushs($userIds, [
                    'user_id_from' => $updatingUserData->id->getAsInt(),
                    'message_img' => $this->getUserService()->photoLink($updatingUserData),
                    'header' => $applicationName . ' (' . $projectName . ')',
                    'content' => trim(strip_tags(str_replace('<br>', "\n", $subject))),
                    'obj_type' => 'application',
                    'obj_id' => $currentApplication->id->getAsInt(),
                ]);
            }
        }

        if ($sendChangeToPlayer && !$currentApplication->deleted_by_player->get()) {
            $userIds = [
                $currentApplication->creator_id->getAsInt(),
            ];

            $subject = sprintf(
                $LOCALE['application_change_to_player_subject'],
                $applicationName,
                $projectName,
            );
            $message = sprintf(
                $LOCALE['application_change_to_player_message'],
                ABSOLUTE_PATH,
                $successfulResultsId,
                $applicationName,
                ABSOLUTE_PATH,
                $updatingUserData->sid->get(),
                $this->getUserService()->showName($updatingUserData),
            ) . $fieldsDataToPlayer . '<br><br><a href="' . ABSOLUTE_PATH . '/myapplication/' . $successfulResultsId . '/">' . ABSOLUTE_PATH . '/myapplication/' . $successfulResultsId . '/</a>' . $LOCALE_CONVERSATION['subscription']['base_text2'];

            MessageHelper::prepareEmails($userIds, [
                'author_name' => $this->getUserService()->showName($updatingUserData, false),
                'author_email' => DataHelper::escapeOutput($updatingUserData->em->get()),
                'name' => $subject,
                'content' => $message,
                'obj_type' => 'project_application',
                'obj_id' => $currentApplication->id->getAsInt(),
            ]);

            MessageHelper::preparePushs($userIds, [
                'user_id_from' => $updatingUserData->id->getAsInt(),
                'message_img' => $this->getUserService()->photoLink($updatingUserData),
                'header' => $applicationName . ' (' . $projectName . ')',
                'content' => trim(strip_tags(str_replace('<br>', "\n", $subject))),
                'obj_type' => 'myapplication',
                'obj_id' => $currentApplication->id->getAsInt(),
            ]);
        }

        $this->updateGroupsInRelation($successfulResultsId);
    }

    private function getUsersDataTableViewShort(): void
    {
        if (is_null($this->usersDataTableView) || is_null($this->usersDataTableViewShort)) {
            $usersDataTableView = [];
            $usersDataTableViewShort = [];

            if (!$this->getExcelView()) {
                $creatorsData = DB->query(
                    'SELECT DISTINCT u.*, g1.name as g_city, g2.name as g_area FROM user u LEFT JOIN geography g1 ON g1.id=u.city LEFT JOIN geography g2 ON g2.id=g1.parent WHERE u.id in (SELECT creator_id FROM project_application WHERE project_id=:project_id_1) OR u.id in (SELECT last_update_user_id FROM project_application WHERE project_id=:project_id_2)',
                    [
                        ['project_id_1', $this->getActivatedProjectId()],
                        ['project_id_2', $this->getActivatedProjectId()],
                    ],
                );

                foreach ($creatorsData as $userData) {
                    $userData = $this->getUserService()->arrayToModel($userData);

                    $usersDataTableView[] = [
                        $userData->id->getAsInt(),
                        '</a>' .
                            '<div class="application_user_info">' .
                            ($userData->em->get() || $userData->vkontakte_visible->get() ? '<div>' . (!$userData->vkontakte_visible->get() ? '<a href="mailto:' . $userData->em->get() . '">' . $userData->em->get() . '</a>' : '') . ($userData->vkontakte_visible->get() ? '<span class="c1">' . $this->getUserService()->social2($userData->vkontakte_visible->get(), 'vkontakte') . '</span>' : '') . '</div>' : '') . ($userData->phone->get() || $userData->telegram->get() ? '<div>' . (!$userData->telegram->get() ? '<a href="tel:' . $userData->phone->get() . '">' . $userData->phone->get() . '</a>' : '') . ($userData->telegram->get() ? '<span class="c2">' . $this->getUserService()->social2($userData->telegram->get(), 'telegram') . '</span>' : '') . '</div>' : '') . '</div><a href="' . ABSOLUTE_PATH . '/people/' . $userData->sid->get() . '/" target="_blank">' . $this->getUserService()->showNameWithId($userData),
                    ];

                    $usersDataTableViewShort[] = [
                        $userData->id->getAsInt(),
                        $this->getUserService()->showNameExtended($userData, true, false, '', true, false, true),
                    ];
                }

                $usersDataTableViewSort = [];

                foreach ($usersDataTableView as $key => $row) {
                    $usersDataTableViewSort[$key] = mb_strtolower($row[1]);
                }
                array_multisort($usersDataTableViewSort, SORT_ASC, $usersDataTableView);
            }

            $this->usersDataTableView = $usersDataTableView;
            $this->usersDataTableViewShort = $usersDataTableViewShort;
        }
    }

    private function getUsersDataApplicationViewAndMedicalSickness(): void
    {
        if (is_null($this->usersDataApplicationView) || is_null($this->userDataSickness)) {
            $userDataSickness = '';
            $usersDataApplicationView = [];

            if (!$this->getExcelView()) {
                $LOCALE = $this->getLOCALE();
                $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
                $LOCALE_PEOPLE = LocaleHelper::getLocale(['people', 'global']);

                $creatorsData = DB->query(
                    'SELECT DISTINCT u.*, g1.name as g_city, g2.name as g_area FROM user u LEFT JOIN geography g1 ON g1.id=u.city LEFT JOIN geography g2 ON g2.id=g1.parent WHERE u.id in (SELECT creator_id FROM project_application WHERE id=:id)',
                    [
                        ['id', DataHelper::getId()],
                    ],
                );

                foreach ($creatorsData as $userData) {
                    $userDataRaw = $userData;
                    $userData = $this->getUserService()->arrayToModel($userData);

                    $usersDataApplicationView[] = [
                        $userData->id->getAsInt(),
                        '<div class="application_user_info">' .
                            ($userData->em->get() || $userData->vkontakte_visible->get() ? '<div>' . ($userData->em->get() ? '<a href="mailto:' . $userData->em->get() . '">' . $userData->em->get() . '</a>' : '') . ($userData->vkontakte_visible->get() ? '<span class="c1">' . $this->getUserService()->social2($userData->vkontakte_visible->get(), 'vkontakte') . '</span>' : '') . '</div>' : '') .
                            ($userData->phone->get() || $userData->telegram->get() ? '<div>' . ($userData->phone->get() ? '<a href="tel:' . $userData->phone->get() . '">' . $userData->phone->get() . '</a>' : '') . ($userData->telegram->get() ? '<span class="c2">' . $this->getUserService()->social2($userData->telegram->get(), 'telegram') . '</span>' : '') . '</div>' : '') .
                            '</div>' .
                            '<a href="' . ABSOLUTE_PATH . '/people/' . $userData->sid->get() . '/">' . $this->getUserService()->photoLink($userData) . '</a>' .
                            $this->getUserService()->showName($userData, true) . ', ' .
                            $LOCALE_GLOBAL['user_id'] . ' ' . $userData->sid->get() . ', ' .
                            ($userData->gender->get() === 2 ? $LOCALE['woman'] : $LOCALE['man']) . ', ' .
                            $LOCALE['birth'] . ' ' . $userData->birth->getAsUsualDate() .
                            ($userDataRaw['g_city'] ? '<br>' . DataHelper::escapeOutput($userDataRaw['g_city']) . ', ' . DataHelper::escapeOutput($userDataRaw['g_area']) : '') .
                            '<br><a class="show_hidden">' . $LOCALE_PEOPLE['show_contacts'] . '</a><div class="hidden"><div class="contact_info"><span class="small">' .
                            ($userData->skype->get() ? $LOCALE['skype'] . ': <a href="skype:' . $userData->skype->get() . '">' . $userData->skype->get() . '</a><br>' : '') .
                            ($userData->facebook_visible->get() ? $LOCALE['facebook_visible'] . ': ' . $this->getUserService()->social2($userData->facebook_visible->get(), 'facebook') . '<br>' : '') .
                            ($userData->twitter->get() ? $LOCALE['twitter'] . ': ' . $this->getUserService()->social2($userData->twitter->get(), 'twitter') . '<br>' : '') .
                            ($userData->livejournal->get() ? $LOCALE['livejournal'] . ': ' . $this->getUserService()->social2($userData->livejournal->get(), 'livejournal') . '<br>' : '') .
                            ($userData->linkedin->get() ? $LOCALE['linkedin'] . ': ' . $userData->linkedin->get() . '<br>' : '') .
                            ($userData->jabber->get() ? $LOCALE['jabber'] . ': ' . $userData->jabber->get() . '<br>' : '') .
                            ($userData->icq->get() ? $LOCALE['icq'] . ': ' . $userData->icq->get() . '<br>' : '') .
                            '</div></span></div>',
                    ];

                    $userDataSickness = $userData->sickness->get();
                }
            }

            $this->userDataSickness = $userDataSickness;
            $this->usersDataApplicationView = $usersDataApplicationView;
        }
    }
}
