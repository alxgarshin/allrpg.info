<?php

declare(strict_types=1);

namespace App\CMSVC\Roles;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Group\GroupService;
use App\CMSVC\Trait\{ProjectDataTrait, UserServiceTrait};
use App\Helper\{RightsHelper, TextHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Enum\{EscapeModeEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};

#[Controller(RolesController::class)]
class RolesService extends BaseService
{
    use ProjectDataTrait;
    use UserServiceTrait;

    private ?bool $excelView = null;

    private bool $projectGamemaster = false;
    private array $projectGroupsList = [];
    private int $projectGroupSubstract = 0;
    private bool $applicationClosed = false;
    private bool $showOnlyAcceptedRoles = false;
    private array $charactersAlreadyShown = [];
    private array $charactersApplicationTakenCount = [];
    private array $charactersApplicationMaybetakenCount = [];
    private array $rolesDataArray = [];

    public function getExcelView(): bool
    {
        if (is_null($this->excelView)) {
            $this->excelView = ($_REQUEST['export_to_excel'] ?? 0) > 0;
        }

        return $this->excelView;
    }

    /** Переключение режима показа сетки ролей: мастерам / всем */
    public function switchShowRoleslist(): array
    {
        $LOCALE_ROLES = $this->getLocale();
        $LOCALE_PROJECT = LocaleHelper::getLocale(['project', 'fraym_model']);

        $projectData = $this->getProjectData();

        DB->update(
            'project',
            [
                'show_roleslist' => $projectData->show_roleslist->get() ? '0' : '1',
            ],
            [
                'id' => $this->getActivatedProjectId(),
            ],
        );

        return [
            'response' => 'success',
            'response_data' => sprintf(
                $LOCALE_ROLES['show_roleslist'],
                $LOCALE_PROJECT['elements']['show_roleslist']['values'][$projectData->show_roleslist->get() ? '0' : '1'][1],
            ),
        ];
    }

    /** Переключение режима просмотра сетки ролей: мастер / игрок */
    public function switchViewRoleslistMode(): array
    {
        $LOCALE_ROLES = $this->getLocale();

        $viewmode = 'gamemaster';
        $rightsData = DB->select(
            tableName: 'relation',
            criteria: [
                'obj_type_to' => '{project}',
                'obj_type_from' => '{user}',
                'obj_id_from' => CURRENT_USER->id(),
                'obj_id_to' => $this->getActivatedProjectId(),
                'type' => '{member}',
            ],
            oneResult: true,
        );

        if ($rightsData) {
            $rightsComment = json_decode($rightsData['comment'] ?? '', true);

            if ($rightsComment['view_roleslist_mode'] === 'gamemaster' || $rightsComment['view_roleslist_mode'] === '') {
                $viewmode = 'player';
                $rightsComment['view_roleslist_mode'] = 'player';
            } else {
                $rightsComment['view_roleslist_mode'] = 'gamemaster';
            }
            DB->update(
                tableName: 'relation',
                data: [
                    'comment' => DataHelper::jsonFixedEncode($rightsComment),
                ],
                criteria: [
                    'id' => $rightsData['id'],
                ],
            );
        }

        return [
            'response' => 'success',
            'response_data' => sprintf(
                $LOCALE_ROLES['view_roleslist_mode'],
                $LOCALE_ROLES['view_roleslist_modes'][$viewmode],
            ),
        ];
    }

    /** Вывод сетки ролей */
    public function getRolesList(
        string $objType,
        string|int $objId,
        string $command,
        int $projectId,
        bool $excel,
    ): array {
        $LOCALE = $this->getLocale();

        $returnArr = [];

        ini_set('memory_limit', '500M');

        $responseTimer = microtime(true);
        $responseTimerData = [];
        $responseData = '';

        $projectData = $this->getProjectData($projectId);

        if (is_numeric($objId)) {
            $objId = [$objId];
        } elseif (str_contains($objId, '&open;')) {
            // нам прислали javascript массив
            $objId = str_replace(['&open;', '&close;'], '', $objId);
            $objId = explode(',', $objId);

            foreach ($objId as $key => $value) {
                if (trim($value) === 'all') {
                    $objId[$key] = 'all';
                } else {
                    $objId[$key] = (int) trim($value);
                }
            }
        } elseif (trim($objId) === 'all') {
            $objId = ['all'];
        }

        if ($projectData->id->getAsInt() && $command === 'create') {
            // выгружаем под эксель по ссылке вида: https://www.allrpg.info/roles/action=get_roles_list&command=create&obj_type=group&obj_id=all&excel=1&project_id=1136
            $this->excelView = $excel;

            $projectRights = RightsHelper::checkProjectRights(false, $projectData->id->getAsInt());

            if (!$projectData->show_roleslist->get() || (is_array($projectRights) && DataHelper::inArrayAny(['{admin}', '{gamemaster}'], $projectRights))) {
                $trueProjectGamemaster = false;

                if (is_array($projectRights) && DataHelper::inArrayAny(['{admin}', '{gamemaster}'], $projectRights)) {
                    $this->projectGamemaster = true;
                    $trueProjectGamemaster = true;

                    $rightsData = DB->select(
                        tableName: 'relation',
                        criteria: [
                            'obj_type_to' => '{project}',
                            'obj_type_from' => '{user}',
                            'obj_id_from' => CURRENT_USER->id(),
                            'obj_id_to' => $this->getActivatedProjectId(),
                            'type' => '{member}',
                        ],
                        oneResult: true,
                    );
                    $rightsComment = json_decode($rightsData['comment'] ?? '', true);

                    if (($rightsComment['view_roleslist_mode'] ?? '') === 'player') {
                        $this->projectGamemaster = false;
                    }
                }

                if ($trueProjectGamemaster && $this->projectGamemaster && !$this->getExcelView()) {
                    // выводим возможность создания новой группы
                    $responseData .= '<div class="allrpgRolesListGroup"><div class="allrpgRolesListGroupHeader"><div class="allrpgRolesListGroupName"><a href="' . ABSOLUTE_PATH . '/group/group/act=add&project_id=' . $projectData->id->getAsInt() . '"><span class="sbi sbi-users tooltipBottomRight" title="' . $LOCALE['first_group'] . '"></span></a><a href="' . ABSOLUTE_PATH . '/group/group/act=add&project_id=' . $projectData->id->getAsInt() . '">' .
                        TextHelper::mb_ucfirst($LOCALE['first_group']) . '</a></div><div class="allrpgRolesListGroupDescription"></div></div></div>';
                }

                $this->showOnlyAcceptedRoles = $projectData->showonlyacceptedroles->get();
                $this->applicationClosed = $projectData->status->get() !== '1';

                $result = DB->query(
                    'SELECT * FROM project_group WHERE project_id = :project_id AND rights IN (0, 1)',
                    [
                        ['project_id', $projectId],
                    ],
                );
                $groupsAvailable = count($result) > 0;
                $result = DB->select(
                    tableName: 'project_character',
                    criteria: [
                        'project_id' => $projectId,
                    ],
                );
                $charactersAvailable = count($result) > 0;

                $this->projectGroupsList = [];
                $projectGroupsInfo = [];
                $projectGroupsIds = [];
                $projectGroupsListIdToKey = [];

                if (!$groupsAvailable && !$charactersAvailable && $objType !== 'application') {
                    $objType = 'application';
                    $objId[0] = 'all';
                } elseif ($groupsAvailable) {
                    $this->projectGroupsList = DB->getTreeOfItems(
                        true,
                        'project_group',
                        'parent',
                        null,
                        ' AND project_id = ' . $projectData->id->getAsInt(),
                        'code ASC, name ASC',
                        1,
                        'id',
                        'name',
                        1000000,
                        false,
                    );
                    $projectGroupsDataById = [];

                    foreach ($this->projectGroupsList as $key => $data) {
                        $projectGroupsDataById[$data[0]] = [
                            $data[1],
                            $data[2],
                            $data[3] ?? null,
                        ];
                    }

                    /* отрезаем "лишние ветки", если определены конкретные группы, но оставляем наследующие объекты */
                    if ($objType === 'application' && $objId[0] !== 'all') {
                        $groupsList = [];

                        /** @var ApplicationService */
                        $applicationService = CMSVCHelper::getService('application');

                        $applicationsData = $applicationService->getAll($objId);

                        foreach ($applicationsData as $applicationData) {
                            $lowestGroupId = GroupService::getLowestGroup(
                                $projectGroupsDataById,
                                $applicationData->project_group_ids->get(),
                            );

                            if ($lowestGroupId > 0) {
                                $groupsList[] = $lowestGroupId;
                            }
                        }
                        $groupsList = array_unique($groupsList);
                    } elseif ($objType === 'character' && $objId[0] !== 'all') {
                        $groupsList = [];
                        $charactersData = DB->findObjectsByIds($objId, 'project_character');

                        foreach ($charactersData as $characterData) {
                            $lowestGroupId = GroupService::getLowestGroup(
                                $projectGroupsDataById,
                                DataHelper::multiselectToArray($characterData['project_group_ids']),
                            );

                            if ($lowestGroupId > 0) {
                                $groupsList[] = $lowestGroupId;
                            }
                        }
                        $groupsList = array_unique($groupsList);
                    } else {
                        $groupsList = $objId;
                    }

                    if (($objType === 'group' || $objType === 'application' || $objType === 'character') && $objId[0] !== 'all' && $objId[0] > 0) {
                        $this->projectGroupsList = $this->chopOffFieldTree($this->projectGroupsList, $groupsList);
                    }

                    foreach ($this->projectGroupsList as $key => $projectGroupInfo) {
                        $projectGroupsListIdToKey[$projectGroupInfo[0]] = $key;

                        if ($projectGroupInfo[0] > 0 && $projectGroupInfo[3]['rights'] < 2) {
                            $projectGroupsInfo[$projectGroupInfo[0]] = [
                                'group_id' => $projectGroupInfo[0],
                                'group_name' => '<a href="' . ABSOLUTE_PATH . '/roles/' . $projectData->id->getAsInt() . '/group/' . $projectGroupInfo[0] . '/" data-obj-type="group" data-obj-id="' . $projectGroupInfo[0] . '" id="group_' . $projectGroupInfo[0] . '">' . $projectGroupInfo[1] . '</a>',
                                'group_level' => $projectGroupInfo[2],
                                'group_path' => (($objType === 'group' || $objType === 'application' || $objType === 'character') && $objId[0] !== 'all' && $objId[0] > 0 ? '<span class="allrpgRolesListGroupNamePathElement"><a href="' . ABSOLUTE_PATH . '/roles/' . $projectData->id->getAsInt() . '/" data-obj-type="group" data-obj-id="all">' . $LOCALE['all'] . '</a></span><span class="allrpgRolesListGroupNamePathSeparator">&rarr;</span>' : '') . $this->groupPath(
                                    $key,
                                ),
                                'group_description' => DataHelper::escapeOutput($projectGroupInfo[3]['description'], EscapeModeEnum::forHTMLforceNewLines),
                                'group_image' => DataHelper::escapeOutput($projectGroupInfo[3]['image']),
                                'group_disabled' => $projectGroupInfo[3]['disable_changes'] === '1',
                            ];
                            $projectGroupsIds[] = $projectGroupInfo[0];
                        } elseif ($projectGroupInfo[0] === 0) {
                            $this->projectGroupsList[$key][1] = $LOCALE['noname'];
                        }

                        if ((int) $groupsList[0] === (int) $projectGroupInfo[0]) {
                            $this->projectGroupSubstract = $projectGroupInfo[2] - 1;
                        }
                    }

                    /* отрезаем выбранные группы и их наследников */
                    if (($objType === 'group' || $objType === 'application' || $objType === 'character') && $objId[0] !== 'all' && $objId[0] > 0) {
                        foreach ($projectGroupsIds as $key => $projectGroupId) {
                            foreach ($this->projectGroupsList as $projectGroupInfo) {
                                if ($projectGroupId === $projectGroupInfo[0]) {
                                    if ($projectGroupInfo['chopOffStatus'] === 'parent') {
                                        unset($projectGroupsIds[$key]);
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }

                $this->charactersApplicationTakenCount = [];
                $this->charactersApplicationMaybetakenCount = [];

                $query = false;
                $params = [];

                if ($groupsAvailable && $charactersAvailable && count($projectGroupsIds) > 0) {
                    $charactersApplicationTakenCountQuery = DB->query(
                        "SELECT pc.id, COUNT(pa.id) AS taken_count FROM project_character AS pc LEFT JOIN project_application AS pa ON pa.project_character_id = pc.id AND pa.project_id = pc.project_id AND pa.status = 3 AND pa.deleted_by_player = '0' AND pa.deleted_by_gamemaster = '0' WHERE pc.project_id = :project_id GROUP BY pc.id",
                        [
                            ['project_id', $projectData->id->getAsInt()],
                        ],
                    );

                    foreach ($charactersApplicationTakenCountQuery as $charactersApplicationTakenCountData) {
                        $this->charactersApplicationTakenCount[$charactersApplicationTakenCountData['id']] = $charactersApplicationTakenCountData['taken_count'];
                    }

                    $charactersApplicationMaybetakenCountQuery = DB->query(
                        "SELECT pc.id, COUNT(pa.id) AS taken_count FROM project_character AS pc LEFT JOIN project_application AS pa ON pa.project_character_id = pc.id AND pa.project_id = pc.project_id AND (pa.status = 1 OR pa.status = 2) AND pa.deleted_by_player = '0' AND pa.deleted_by_gamemaster = '0' WHERE pc.project_id = :project_id GROUP BY pc.id",
                        [
                            ['project_id', $projectData->id->getAsInt()],
                        ],
                    );

                    foreach ($charactersApplicationMaybetakenCountQuery as $charactersApplicationMaybetakenCountData) {
                        $this->charactersApplicationMaybetakenCount[$charactersApplicationMaybetakenCountData['id']] = $charactersApplicationMaybetakenCountData['taken_count'];
                    }

                    $query = "SELECT
				pa.id AS application_id,
				pa.status AS application_status,
				pa.sorter AS application_sorter,
				pc.id AS character_id,
				pc.name AS character_name,
				pc.content AS character_description,
				pc.taken AS character_taken,
				pc.maybetaken AS character_maybetaken,
                pc.hide_applications AS character_hide_applications,
				pc.applications_needed_count AS character_applications_needed_count,
				pc.team_character AS character_team_character,
				pc.team_applications_needed_count AS character_team_applications_needed_count,
				pc.disallow_applications AS character_disallow_applications,
				pg.id AS group_id,
				pg.rights AS group_rights,
                pg.disallow_applications AS group_disallow_applications,
				u.*
			FROM project_group AS pg
				LEFT JOIN relation AS r ON
					r.obj_id_to = pg.id AND
					r.obj_type_from = '{character}' AND
					r.obj_type_to = '{group}' AND
					r.type = '{member}'
				LEFT JOIN project_character AS pc ON
					pc.id = r.obj_id_from AND
					pc.project_id = :project_id_1
				LEFT JOIN project_application AS pa ON
					pa.project_character_id = pc.id AND
					pa.project_id = :project_id_2 AND
					pa.deleted_by_player = '0' AND
					pa.deleted_by_gamemaster = '0' AND
					pa.status != '4'
				LEFT JOIN user AS u ON
					u.id = pa.creator_id
			WHERE
				pg.project_id = :project_id_3 AND" .
                        (
                            (in_array($objType, ['group', 'application', 'character']) && $objId[0] !== 'all' && $objId[0] > 0) ?
                            ' pg.id IN(:project_groups_ids) AND ' : ''
                        ) . '
				(pg.rights = 0 OR pg.rights = 1)' . ($this->projectGamemaster ? '' : ' AND pc.id IS NOT NULL') . '
			ORDER BY
				FIELD(pg.id, :field_project_groups_ids),
				cast(r.comment AS unsigned) ASC,
				pc.name ASC,
				u.fio ASC';
                    $params = [
                        ['project_id_1', $projectData->id->getAsInt()],
                        ['project_id_2', $projectData->id->getAsInt()],
                        ['project_id_3', $projectData->id->getAsInt()],
                        ['project_groups_ids', $projectGroupsIds],
                        ['field_project_groups_ids', $projectGroupsIds],
                    ];
                } elseif ($groupsAvailable && !$charactersAvailable && count($projectGroupsIds) > 0) {
                    $query = "SELECT
				pa.id AS application_id,
				pa.status AS application_status,
				pa.sorter AS application_sorter,
				pg.id AS group_id,
				pg.rights AS group_rights,
                pg.disallow_applications AS group_disallow_applications,
				u.*
			FROM project_group AS pg
				LEFT JOIN relation AS r ON
					r.obj_id_to=pg.id AND
					r.type='{member}' AND
					r.obj_type_to='{group}' AND
					r.obj_type_from='{application}'
				LEFT JOIN project_application AS pa ON
					pa.id=r.obj_id_from AND
					pa.project_id=pg.project_id AND
					pa.deleted_by_player = '0' AND
					pa.deleted_by_gamemaster = '0' AND
					pa.status != '4'
				LEFT JOIN user AS u ON
					u.id = pa.creator_id
			WHERE
				pg.project_id = :project_id_2 AND" .
                        ((in_array($objType, ['group', 'application', 'character']) && $objId[0] !== 'all' && $objId[0] > 0) ?
                            ' pg.id IN(:project_groups_ids) AND ' : '') . '
				(pg.rights = 0 OR pg.rights = 1)
			ORDER BY
				FIELD(pg.id, :field_project_groups_ids),
				u.fio';
                    $params = [
                        ['project_id_1', $projectData->id->getAsInt()],
                        ['project_id_2', $projectData->id->getAsInt()],
                        ['project_groups_ids', $projectGroupsIds],
                        ['field_project_groups_ids', $projectGroupsIds],
                    ];
                } elseif (!$groupsAvailable && $charactersAvailable) {
                    $charactersApplicationTakenCountQuery = DB->query(
                        "SELECT pc.id, COUNT(pa.id) AS taken_count FROM project_character AS pc LEFT JOIN project_application AS pa ON pa.project_character_id = pc.id AND pa.project_id = pc.project_id AND pa.status = 3 AND pa.deleted_by_player = '0' AND pa.deleted_by_gamemaster = '0' WHERE pc.project_id = :project_id GROUP BY pc.id",
                        [
                            ['project_id', $projectData->id->getAsInt()],
                        ],
                    );

                    foreach ($charactersApplicationTakenCountQuery as $charactersApplicationTakenCountData) {
                        $this->charactersApplicationTakenCount[$charactersApplicationTakenCountData['id']] = $charactersApplicationTakenCountData['taken_count'];
                    }

                    $charactersApplicationMaybetakenCountQuery = DB->query(
                        "SELECT pc.id, COUNT(pa.id) AS taken_count FROM project_character AS pc LEFT JOIN project_application AS pa ON pa.project_character_id = pc.id AND pa.project_id = pc.project_id AND (pa.status = 1 OR pa.status = 2) AND pa.deleted_by_player = '0' AND pa.deleted_by_gamemaster = '0' WHERE pc.project_id = :project_id GROUP BY pc.id",
                        [
                            ['project_id', $projectData->id->getAsInt()],
                        ],
                    );

                    foreach ($charactersApplicationMaybetakenCountQuery as $charactersApplicationMaybetakenCountData) {
                        $this->charactersApplicationMaybetakenCount[$charactersApplicationMaybetakenCountData['id']] = $charactersApplicationMaybetakenCountData['taken_count'];
                    }

                    $query = "SELECT
				pa.id AS application_id,
				pa.status AS application_status,
				pa.sorter AS application_sorter,
				pc.id AS character_id,
				pc.name AS character_name,
				pc.content AS character_description,
				pc.taken AS character_taken,
				pc.maybetaken AS character_maybetaken,
                pc.hide_applications AS character_hide_applications,
				pc.applications_needed_count AS character_applications_needed_count,
				pc.team_character AS character_team_character,
				pc.team_applications_needed_count AS character_team_applications_needed_count,
				pc.disallow_applications AS character_disallow_applications,
				u.*
			FROM project_character AS pc
				LEFT JOIN project_application AS pa ON
					pa.project_character_id = pc.id AND
					pa.project_id = :project_id_1 AND
					pa.deleted_by_player = '0' AND
					pa.deleted_by_gamemaster = '0' AND
					pa.status != '4'
				LEFT JOIN user AS u ON
					u.id = pa.creator_id
			WHERE
				pc.project_id = :project_id_2
			ORDER BY
				pc.name,
				u.fio";
                    $params = [
                        ['project_id_1', $projectData->id->getAsInt()],
                        ['project_id_2', $projectData->id->getAsInt()],
                    ];
                }

                if ($query) {
                    $this->charactersAlreadyShown = [];

                    $rolesData = DB->query($query, $params);

                    $prevGroupId = -1;
                    $prevCharacterId = -1;
                    $i = 0;
                    $this->rolesDataArray = [];
                    $charactersGroupsToRolesData = [];

                    foreach ($rolesData as $roleData) {
                        // проверяем, видно ли заявку. Если нет и персонаж уже есть в группе, не показываем
                        if (
                            $roleData['application_id'] > 0
                            && $this->showOnlyAcceptedRoles
                            && $roleData['application_status'] !== '3'
                            && is_array($charactersGroupsToRolesData[$roleData['group_id']] ?? null)
                            && in_array($roleData['character_id'] ?? null, $charactersGroupsToRolesData[$roleData['group_id']])
                        ) {
                        } else {
                            $this->rolesDataArray[$i] = $roleData;
                            $charactersGroupsToRolesData[$roleData['group_id']][$i] = $roleData['character_id'] ?? null;
                            ++$i;
                        }
                    }
                    unset($rolesData);

                    if ($groupsAvailable && $charactersAvailable) {
                        /* вычищаем дубликаты персонажей из родительских групп */
                        $checkingForDoubles = [];

                        foreach ($this->rolesDataArray as $roleData) {
                            if (($roleData['character_id'] ?? false) && ($checkingForDoubles[$roleData['character_id']] ?? false)) {
                                /* проверяем, является ли группа родительской для данного инстанса персонажа, и видна ли она */
                                $idInProjectGroupsList = $projectGroupsListIdToKey[$roleData['group_id']];
                                $projectGroupData = $this->projectGroupsList[$idInProjectGroupsList];
                                $level = $projectGroupData[2];

                                while ($level > 0 && $idInProjectGroupsList > 0) {
                                    --$idInProjectGroupsList;
                                    $projectGroupData = $this->projectGroupsList[$idInProjectGroupsList] ?? [];

                                    if ($projectGroupData && $projectGroupData[2] < $level) {
                                        $level = $projectGroupData[2];

                                        if (($projectGroupData[0] ?? false) && ($projectGroupData[0] ?? false) && is_array($charactersGroupsToRolesData[$projectGroupData[0]] ?? false)) {
                                            while (
                                                in_array(
                                                    $roleData['character_id'],
                                                    $charactersGroupsToRolesData[$projectGroupData[0]],
                                                    true,
                                                )
                                            ) {
                                                $keyInRolesDataArray = array_search(
                                                    $roleData['character_id'],
                                                    $charactersGroupsToRolesData[$projectGroupData[0]],
                                                    true,
                                                );
                                                unset($this->rolesDataArray[$keyInRolesDataArray]['character_id']);
                                                unset($charactersGroupsToRolesData[$projectGroupData[0]][$keyInRolesDataArray]);
                                                // $this->rolesDataArray[$keyInRolesDataArray]['character_id'] = 'do_not_show';
                                            }
                                        }
                                    }
                                }
                            }
                            $checkingForDoubles[$roleData['character_id']] = true;
                        }
                    }

                    $responseTimerData['showGroup'] = 0;
                    $responseTimerData['showCharacter'] = 0;
                    $responseTimerData['showApplication'] = 0;
                    $responseTimerData['showApplicationString'] = 0;

                    $i = 1;

                    $groupOpened = false;
                    $listStringsOpened = false;

                    if (count($this->rolesDataArray) > 0) {
                        foreach ($this->rolesDataArray as $roleKey => $roleData) {
                            if ($prevGroupId !== $roleData['group_id'] && $roleData['group_id'] > 0 && $roleData['group_rights'] <= '1' && ($projectGroupsInfo[$roleData['group_id']] ?? false)) {
                                if ($listStringsOpened) {
                                    $responseData .= $this->getExcelView() ? '' : '</div>';
                                }

                                if ($groupOpened) {
                                    $responseData .= $this->getExcelView() ? '' : '</div>';
                                }

                                $startMicrotimer = microtime(true);
                                $responseData .= $this->showGroup($projectGroupsInfo[$roleData['group_id']]);
                                $endMicrotimer = number_format(microtime(true) - $startMicrotimer, 10);
                                $responseTimerData['showGroup'] += (float) $endMicrotimer;

                                $groupOpened = true;

                                $responseData .= $this->getExcelView() ? '' : '<div class="allrpgRolesListStrings">';

                                $listStringsOpened = true;
                            }

                            if (
                                ($objType !== 'application' && $objType !== 'character')
                                || ($objType === 'application' && ($roleData['application_id'] ?? 0) > 0 && in_array(($roleData['application_id'] ?? false), $objId))
                                || ($objType === 'character' && ($roleData['character_id'] ?? 0) > 0 && in_array(($roleData['character_id'] ?? false), $objId))
                            ) {
                                $characterId = $roleData['character_id'] ?? 0;

                                if (
                                    !($characterId > 0 && in_array($characterId, $this->charactersAlreadyShown) && ($roleData['group_rights'] >= '1' || $roleData['character_hide_applications'] === '1'))
                                    && !($objType === 'application' && $roleData['group_rights'] >= '1')
                                    && (($charactersAvailable && $characterId > 0 && $characterId !== 'do_not_show') || !$charactersAvailable)
                                ) {
                                    $responseData .= $this->getExcelView() ? '<tr>' : '<div class="allrpgRolesListString allrpgRolesListString' . ($i % 2 === 0 ? 'Odd' : 'Even') . '">';

                                    if (($objType === 'application' || !$charactersAvailable) && $roleData['application_id'] > 0) {
                                        $startMicrotimer = microtime(true);
                                        $responseData .= $this->showApplicationString($roleData);
                                        $endMicrotimer = number_format(microtime(true) - $startMicrotimer, 10);
                                        $responseTimerData['showApplicationString'] += (float) $endMicrotimer;
                                    } else {
                                        $hideApply = false;

                                        if (($roleData['character_id'] ?? null) > 0 && ($roleData['character_id'] !== $prevCharacterId || $prevGroupId !== $roleData['group_id'])) {
                                            $startMicrotimer = microtime(true);
                                            $responseData .= $this->showCharacter($roleData);
                                            $endMicrotimer = number_format(microtime(true) - $startMicrotimer, 10);
                                            $responseTimerData['showCharacter'] += (float) $endMicrotimer;
                                            $prevCharacterId = $roleData['character_id'];
                                        } elseif (($roleData['character_id'] ?? null) === $prevCharacterId && $prevGroupId === ($roleData['group_id'] ?? null)) {
                                            $hideApply = true;
                                        }
                                        $startMicrotimer = microtime(true);
                                        $responseData .= ($this->getExcelView() ? '<td>' : '') . '<div class="allrpgRolesListApplication">' . $this->showApplication(
                                            $roleKey,
                                            $hideApply,
                                        ) . '</div>' . ($this->getExcelView() ? '</td>' : '');
                                        $endMicrotimer = number_format(microtime(true) - $startMicrotimer, 10);
                                        $responseTimerData['showApplication'] += (float) $endMicrotimer;

                                        if ($roleData['character_id'] > 0) {
                                            $this->charactersAlreadyShown[] = $roleData['character_id'];
                                        }
                                    }

                                    $responseData .= $this->getExcelView() ? '</tr>' : '</div>';
                                    ++$i;
                                }
                            }

                            if ($prevGroupId !== $roleData['group_id'] && $roleData['group_id'] > 0 && $roleData['group_rights'] <= '1') {
                                $prevGroupId = $roleData['group_id'];
                            }
                        }
                    }

                    if ($listStringsOpened) {
                        $responseData .= $this->getExcelView() ? '' : '</div>';
                    }

                    if ($groupOpened) {
                        $responseData .= $this->getExcelView() ? '' : '</div>';
                    }
                }
            }

            $responseTime = number_format(microtime(true) - $responseTimer, 10);

            $returnArr = [
                'response' => 'success',
                'response_data' => $responseData,
                'response_time' => $responseTime,
                // 'response_timer_data' => print_r($responseTimerData, true)
            ];

            if ($this->getExcelView()) {
                // убираем лишнее
                $responseData = preg_replace('#</a>#', '', $responseData);
                $responseData = preg_replace('#<a[^>]+>#', '', $responseData);
                $responseData = preg_replace('#</span>#', '', $responseData);
                $responseData = preg_replace('#<span[^>]+>#', '', $responseData);
                $responseData = preg_replace('#&rarr;#', ' > ', $responseData);

                // формируем заголовок таблицы
                $content2 = '<html><head>
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
<table>' . $responseData . '</table></body></html>';

                // выгружаем в виде таблицы
                header('Content-type: application/vnd.ms-excel');
                header('Content-Disposition: attachment; filename=roleslist ' . date('d.m.Y H-i') . '.xls');
                echo $content2;
                exit;
            }
        }

        return $returnArr;
    }

    /** Удаление объектов из созданного дерева, чтобы остались только выбранные id и их parent'ы */
    public function chopOffFieldTree(array $objectsTree, array $listOfIds): array
    {
        $objectsTreeResult = [];

        foreach ($listOfIds as $searchValue) {
            $tempObjectsBranch = [];
            $tempObjectsChildsBranch = [];
            $groupKey = false;
            $theLevel = false;

            foreach ($objectsTree as $key => $projectGroupData) {
                /** Находим ветку, где лежат данные */
                if ($projectGroupData[0] === (int) $searchValue) {
                    $groupKey = $key;
                    $theLevel = $objectsTree[$groupKey][2];

                    /** Находим всех наследующих */
                    $lookingForChilds = true;

                    while ($lookingForChilds) {
                        ++$key;

                        if (($objectsTree[$key] ?? false) && $objectsTree[$key][2] > $theLevel) {
                            $tempObjectsChildsBranch[] = array_merge(
                                $objectsTree[$key],
                                ['chopOffStatus' => 'child'],
                            );
                        } else {
                            $lookingForChilds = false;
                        }
                    }
                    break;
                }
            }

            if ($groupKey && $theLevel !== false) {
                $tempObjectsBranch[] = array_merge($objectsTree[$groupKey], ['chopOffStatus' => 'main_object']);

                while ($theLevel > 1) {
                    --$groupKey;
                    $prevGroupData = $objectsTree[$groupKey];

                    while ($prevGroupData[2] !== $theLevel - 1) {
                        --$groupKey;
                        $prevGroupData = $objectsTree[$groupKey];
                    }

                    $tempObjectsBranch[] = array_merge($objectsTree[$groupKey], ['chopOffStatus' => 'parent']);
                    --$theLevel;
                }

                $objectsTreeResult = array_merge(
                    $objectsTreeResult,
                    array_reverse($tempObjectsBranch),
                    $tempObjectsChildsBranch,
                );
            }
        }

        return $objectsTreeResult;
    }

    public function groupPath($groupKey)
    {
        $projectData = $this->projectData;

        $path = '';

        if (isset($this->projectGroupsList) && isset($this->projectGroupsList[$groupKey])) {
            $theLevel = $this->projectGroupsList[$groupKey][2];
            $parentKey = $groupKey;

            if ($theLevel > 1) {
                --$parentKey;
                $prevGroupData = $this->projectGroupsList[$parentKey];

                while ($prevGroupData && $prevGroupData[2] !== $theLevel - 1) {
                    --$parentKey;
                    $prevGroupData = $this->projectGroupsList[$parentKey];
                }

                $path = $this->groupPath(
                    $parentKey,
                ) . '<span class="allrpgRolesListGroupNamePathSeparator">&rarr;</span>';
            }
            $path .= '<span class="allrpgRolesListGroupNamePathElement"><a href="' . ABSOLUTE_PATH . '/roles/' . $projectData->id->getAsInt() . '/group/' . $this->projectGroupsList[$groupKey][0] . '/" data-obj-type="group" data-obj-id="' . $this->projectGroupsList[$groupKey][0] . '">' . DataHelper::escapeOutput(
                $this->projectGroupsList[$groupKey][1],
            ) . '</a></span>';
        }

        return $path;
    }

    public function showApplication($roleKey, $hideApply)
    {
        $LOCALE = $this->getLocale();

        $applicationData = $this->rolesDataArray[$roleKey];
        $projectData = $this->projectData;

        $result = '';

        $taken = [];
        $maybetaken = [];
        $applicationsAcceptedCount = 0;
        $applicationsToBeAcceptedCount = 0;

        /* мы еще не выводили инфу по данному персонажу, а значит, должны вывести всё, что записано в него, а заодно посчитать общее количество набранных и предварительно набранных позиций */
        if ($applicationData['character_id'] ?? false) {
            if ($applicationData['character_taken'] ?? false) {
                $takenClone = explode(',', DataHelper::escapeOutput($applicationData['character_taken']));

                foreach ($takenClone as $value) {
                    if (trim($value) !== '') {
                        $taken[] = trim($value);
                    }
                }
            }
            $applicationsAcceptedCount = (int) ($this->charactersApplicationTakenCount[$applicationData['character_id']] ?? 0);

            if ($applicationData['character_maybetaken'] !== null) {
                $maybetakenClone = explode(',', DataHelper::escapeOutput($applicationData['character_maybetaken']));

                foreach ($maybetakenClone as $value) {
                    if (trim($value) !== '') {
                        $maybetaken[] = trim($value);
                    }
                }
            }
            $applicationsToBeAcceptedCount = (int) ($this->charactersApplicationMaybetakenCount[$applicationData['character_id']] ?? 0);
        }

        $showApplyLink = true;

        if ($this->applicationClosed) {
            $showApplyLink = false;
        }

        if (($applicationData['character_applications_needed_count'] ?? 0) <= count($taken) + $applicationsAcceptedCount && $applicationData['character_applications_needed_count'] > 0) {
            $showApplyLink = false;
        }

        if ($applicationData['character_disallow_applications'] === '1' || $applicationData['group_disallow_applications'] === '1') {
            $showApplyLink = false;
        }

        if ($hideApply) {
            $showApplyLink = false;
        }

        $result .= '<div class="allrpgRolesListApplicationsList">';

        if ($showApplyLink) {
            $result .= '<div class="allrpgRolesListApplicationsListApplicationApply"><a href="' . ABSOLUTE_PATH . '/go/' . $projectData->id->getAsInt() . '/' . ($applicationData['character_id'] > 0 ? $applicationData['character_id'] : '') . '">' . $LOCALE['apply'] . ($applicationData['character_applications_needed_count'] > 1 ? ' ' . sprintf(
                $LOCALE['up_to'],
                $applicationData['character_applications_needed_count'],
            ) : '') . '</a></div>';
        }

        if ($this->projectGamemaster) {
            $GLOBALS['kind'] = 'roles_gamemaster'; // чтобы в именах пользователей показывались все возможные данные
        }

        if ($applicationData['group_rights'] !== '1' && $applicationData['character_hide_applications'] !== '1') {
            if ($applicationData['id'] !== null && (!$this->showOnlyAcceptedRoles || $applicationData['application_status'] === '3')) {
                $userModel = $this->getUserService()->arrayToModel($applicationData);

                $result .= '<div class="allrpgRolesListApplicationsListApplication" data-obj-type="application" data-obj-id="' . $applicationData['application_id'] . '">' . ($applicationData['application_id'] !== '' ? '<a class="sbi sbi-info" obj_id="' . $applicationData['id'] . '" obj_type="roleslist" value="' . $projectData->id->getAsInt() . '"></a>' . ($this->projectGamemaster ? '<a href="' . ABSOLUTE_PATH . '/application/' . $applicationData['application_id'] . '/">' . $this->getUserService()->showNameWithId($userModel) . '</a>' : $this->getUserService()->showName($userModel, true)) . ($applicationData['application_status'] !== '3' ? $LOCALE['question'] : '') : '') .
                    ($applicationsAcceptedCount + $applicationsToBeAcceptedCount > 1 ? '<div class="allrpgRolesListApplicationsListApplicationSorter">' .
                        ($this->projectGamemaster ? '<a href="' . ABSOLUTE_PATH . '/application/' . $applicationData['application_id'] . '/">' . DataHelper::escapeOutput($applicationData['application_sorter']) . '</a>' : DataHelper::escapeOutput($applicationData['application_sorter'])) . '</div>' : '') .
                    '</div>';
            }

            /* если у нас не был выведен предыдущий такой же персонаж, где мы уже показали taken и maybetaken, выводим их */
            $prevCharacterId = $this->rolesDataArray[$roleKey - 1]['character_id'] ?? false;
            $curCharacterId = $this->rolesDataArray[$roleKey]['character_id'] ?? false;

            if ($prevCharacterId !== $curCharacterId) {
                if (count($taken) > 0) {
                    foreach ($taken as $value) {
                        $result .= '<div class="allrpgRolesListApplicationsListApplication">' . ($this->projectGamemaster ? '<a href="' . ABSOLUTE_PATH . '/character/' . $applicationData['character_id'] . '/">' . trim(
                            $value,
                        ) . '</a>' : trim($value)) . '</div>';
                    }
                }

                if (count($maybetaken) > 0 && !$this->showOnlyAcceptedRoles) {
                    foreach ($maybetaken as $value) {
                        $result .= '<div class="allrpgRolesListApplicationsListApplication">' . ($this->projectGamemaster ? '<a href="' . ABSOLUTE_PATH . '/character/' . $applicationData['character_id'] . '/">' . trim(
                            $value,
                        ) . $LOCALE['question'] . '</a>' : trim(
                            $value,
                        ) . $LOCALE['question']) . '</div>';
                    }
                }
            }
        } elseif (
            $applicationData['character_id'] > 0 && !in_array(
                $applicationData['character_id'],
                $this->charactersAlreadyShown,
            )
        ) {
            if (count($taken) + $applicationsAcceptedCount > 0) {
                $result .= '<div class="allrpgRolesListApplicationsListApplication">' . sprintf(
                    $LOCALE['taken'],
                    count($taken) + $applicationsAcceptedCount,
                ) . '</div>';
            }

            if (count($maybetaken) + $applicationsToBeAcceptedCount > 0) {
                $result .= '<div class="allrpgRolesListApplicationsListApplication">' . sprintf(
                    $LOCALE['maybetaken'],
                    count($maybetaken) + $applicationsToBeAcceptedCount,
                ) . '</div>';
            }
        }

        $result .= '</div>';

        return $result;
    }

    public function showApplicationString($applicationData)
    {
        $LOCALE_APPLICATION_ELEMENTS = LocaleHelper::getLocale(['application', 'fraym_model', 'elements']);

        $result = '';

        if ($applicationData['group_rights'] !== '1') {
            if ($this->projectGamemaster) {
                $GLOBALS['kind'] = 'roles_gamemaster'; // чтобы в именах пользователей показывались все возможные данные
            }

            $userModel = $this->getUserService()->arrayToModel($applicationData);

            $result .= '<div class="allrpgRolesListApplicationString"><div class="allrpgRolesListApplication"><div class="allrpgRolesListApplicationsList">';
            $result .= '<div class="allrpgRolesListApplicationsListApplication" data-obj-type="application" data-obj-id="' . $applicationData['application_id'] . '">' . ($applicationData['application_id'] !== '' ? (
                $this->projectGamemaster ? '<a href="/application/' . $applicationData['application_id'] . '/">' . $this->getUserService()->showNameWithId($userModel) . '</a>' : $this->getUserService()->showNameExtended(
                    $userModel,
                    true,
                    true,
                    '',
                    true,
                    false,
                    true,
                )
            ) : '') . '</div>';
            $result .= '</div></div>';

            $result .= '<div class="allrpgRolesListSorter">' . DataHelper::escapeOutput($applicationData['application_sorter']) . '</div>';

            $statusText = '';

            foreach ($LOCALE_APPLICATION_ELEMENTS['status']['values'] as $statusValue) {
                if ($statusValue[0] === $applicationData['application_status']) {
                    $statusText = $statusValue[1];
                    break;
                }
            }
            $result .= '<div class="allrpgRolesListStatus">' . $statusText . '</div>';

            if ($applicationData['character_id'] ?? false) {
                $result .= '<div class="allrpgRolesListCharacter" data-obj-type="character" data-obj-id="' . $applicationData['character_id'] . '"><div class="allrpgRolesListCharacterName">' . DataHelper::escapeOutput(
                    $applicationData['character_name'],
                ) . '</div></div>';
            }

            $result .= '</div>';
        }

        return $result;
    }

    public function showCharacter($characterData)
    {
        $projectData = $this->projectData;
        $LOCALE_ROLES = $this->getLocale();

        $applicationsTotal = 0;

        if ($this->projectGamemaster) {
            $taken = [];
            $maybetaken = [];

            if ($characterData['character_id'] > 0) {
                if ($characterData['character_taken'] !== null) {
                    $takenClone = explode(',', DataHelper::escapeOutput($characterData['character_taken']));

                    foreach ($takenClone as $value) {
                        if (trim($value) !== '') {
                            $taken[] = trim($value);
                        }
                    }
                }
                $applicationsAcceptedCount = $this->charactersApplicationTakenCount[$characterData['character_id']] + count(
                    $taken,
                );

                if ($characterData['character_maybetaken'] !== null) {
                    $maybetakenClone = explode(',', DataHelper::escapeOutput($characterData['character_maybetaken']));

                    foreach ($maybetakenClone as $value) {
                        if (trim($value) !== '') {
                            $maybetaken[] = trim($value);
                        }
                    }
                }
                $applicationsToBeAcceptedCount = $this->charactersApplicationMaybetakenCount[$characterData['character_id']] + count(
                    $maybetaken,
                );

                $applicationsTotal = $applicationsAcceptedCount + $applicationsToBeAcceptedCount;
            }
        }

        if ($this->getExcelView()) {
            $result = '<td>' . DataHelper::escapeOutput(
                $characterData['character_name'],
            ) . ($characterData['character_team_character'] === '1' ? ' ' . sprintf(
                $LOCALE_ROLES['team_up_to'],
                $characterData['character_team_applications_needed_count'],
            ) : '') . '</td><td>' . TextHelper::makeURLsActive(
                TextHelper::bbCodesInDescription(
                    $this->imageLinksToWebp(DataHelper::escapeOutput($characterData['character_description'], EscapeModeEnum::forHTMLforceNewLines)),
                ),
            ) . '</td>';
        } else {
            $result = '<div class="allrpgRolesListCharacter' . ($this->projectGamemaster ? ' editable' : '') . '" data-obj-type="character" data-obj-id="' . $characterData['character_id'] . '"><div class="allrpgRolesListCharacterName"><a href="' . ABSOLUTE_PATH . '/roles/' . $projectData->id->getAsInt() . '/character/' . $characterData['character_id'] . '/" id="character_' . $characterData['character_id'] . '">' . DataHelper::escapeOutput(
                $characterData['character_name'],
            ) . ($characterData['character_team_character'] === '1' ? ' ' . sprintf(
                $LOCALE_ROLES['team_up_to'],
                $characterData['character_team_applications_needed_count'],
            ) : '') . ($this->projectGamemaster && ((($characterData['character_disallow_applications'] === '1' || $characterData['group_disallow_applications'] === '1') && $applicationsTotal < $characterData['character_applications_needed_count']) || $applicationsTotal > $characterData['character_applications_needed_count']) ? ' <span class="red">' . sprintf(
                $LOCALE_ROLES['error_on_quantity'],
                $applicationsTotal,
                $characterData['character_applications_needed_count'],
            ) . '</span>' : '') . '</a></div>
<div class="allrpgRolesListCharacterDescription"> ' . ($this->projectGamemaster ? '<a href = "' . ABSOLUTE_PATH . '/character/character/' . $characterData['character_id'] . '/act=edit&project_id=' . $projectData->id->getAsInt() . '"><span class="sbi sbi-pencil" title = "' . $LOCALE_ROLES['edit'] . '"></span></a>' : '');
            $result .= TextHelper::makeURLsActive(
                TextHelper::bbCodesInDescription(
                    $this->imageLinksToWebp(DataHelper::escapeOutput($characterData['character_description'], EscapeModeEnum::forHTMLforceNewLines)),
                ),
            );
            $result .= '</div></div>' . ($this->projectGamemaster ? '<span class="sbi sbi-arrow-move character_move" title="' . $LOCALE_ROLES['change_order'] . '"></span>' : '');
        }

        return $result;
    }

    public function showGroup(array $groupData)
    {
        $projectData = $this->projectData;
        $LOCALE_ROLES = $this->getLocale();
        $LOCALE_GROUP = LocaleHelper::getLocale(['group', 'global']);

        if ($this->getExcelView()) {
            $result = '<tr><td colspan="3"><b>' . $groupData['group_name'] . '</b>' . (str_contains(
                $groupData['group_path'],
                'allrpgRolesListGroupNamePathSeparator',
            ) ? '<br>' . $groupData['group_path'] : '') . '</td></tr>' . ($groupData['group_description'] !== '' ? '<tr><td colspan="3">'
                . TextHelper::makeURLsActive(
                    TextHelper::bbCodesInDescription($this->imageLinksToWebp($groupData['group_description'])),
                ) . '</td></tr>' : '');
        } else {
            $result = '<div class="allrpgRolesListGroup' . ($this->projectGamemaster ? ' editable' : '') . '" data-obj-type="' . (OBJ_TYPE === 'application' ? 'application' : 'group') . '" data-obj-id="' . $groupData['group_id'] . '" data-obj-level="' . min($groupData['group_level'] - $this->projectGroupSubstract, 6) . '">' . ($this->projectGamemaster ? ($groupData['group_disabled'] ? '<span class="sbi sbi-arrow-move group_move tooltipBottomRight disabled" title="' . $LOCALE_GROUP['messages']['disable_changes_active'] . '"></span>' : '<span class="sbi sbi-arrow-move group_move tooltipBottomRight" title="' . $LOCALE_ROLES['change_order'] . '"></span>') : '') . '<div class="allrpgRolesListGroupHeader"><div class="allrpgRolesListGroupName">' . ($this->projectGamemaster ? '<a href="' . ABSOLUTE_PATH . '/character/character/act=add&project_group_ids=-' . $groupData['group_id'] . '-&project_id=' . $this->projectData->id->getAsInt() . '"><span class="sbi sbi-user" title="' . $LOCALE_ROLES['add_character'] . '"></span></a><a href="' . ABSOLUTE_PATH . '/group/group/act=add&parent=' . $groupData['group_id'] . '&project_id=' . $projectData->id->getAsInt() . '"><span class="sbi sbi-users tooltipBottom" title="' . $LOCALE_ROLES['add_group'] . '"></span></a><a href="' . ABSOLUTE_PATH . '/group/group/' . $groupData['group_id'] . '/act=edit&project_id=' . $projectData->id->getAsInt() . '"><span class="sbi sbi-pencil" title = "' . $LOCALE_ROLES['edit'] . '"></span></a>' : '') . $groupData['group_name'] . ' </div> ' . (str_contains($groupData['group_path'], 'allrpgRolesListGroupNamePathSeparator') ? '<div class="allrpgRolesListGroupNamePath">' . $groupData['group_path'] . '</div>' : '') . '
<div class="allrpgRolesListGroupDescription">' . (($groupData['group_image'] ?? false) ? '<div class="allrpgRolesListGroupDescriptionImage"><img src = "' . $this->imageLinkToWebp($groupData['group_image']) . '"></div> ' : '') . TextHelper::makeURLsActive(TextHelper::bbCodesInDescription($this->imageLinksToWebp($groupData['group_description']))) . ' </div></div>';
        }

        return $result;
    }

    /** Превращение ссылки на картинку в webp */
    public function imageLinkToWebp(string $url): string
    {
        $url = preg_replace('#([^$]*)\?([^$]*)#', '$1escq$2', $url);
        $url = preg_replace_callback('#([^]]*)#', static fn ($matches) => urlencode($matches[1]), $url);

        return ABSOLUTE_PATH . '/scripts/roles_image/f=' . $url;
    }

    /** Поиск ссылок на изображения в описаниях и замена их на webp */
    public function imageLinksToWebp(?string $text): string
    {
        if (is_null($text)) {
            return '';
        }

        $text = preg_replace('#\[([^]]*)\?([^]]*)]#', '[$1escq$2]', $text);
        $text = preg_replace_callback('#\[([^]]*)]#', static fn ($matches) => '[' . urlencode($matches[1]) . ']', $text);

        $text = preg_replace('#\[(.*?) (\d+)]#', '<img src="' . $this->imageLinkToWebp('') . '$1" width="$2%">', $text);

        return preg_replace('#\[(http|https|www)(.*?)]#', '<img src="' . $this->imageLinkToWebp('') . '$1$2">', $text);
    }
}
