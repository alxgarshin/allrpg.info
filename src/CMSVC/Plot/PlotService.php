<?php

declare(strict_types=1);

namespace App\CMSVC\Plot;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Character\CharacterService;
use App\CMSVC\Group\GroupService;
use App\CMSVC\Myapplication\MyapplicationService;
use App\CMSVC\Plot\PlotPlot\PlotPlotModel;
use App\CMSVC\Trait\{GamemastersListTrait, GetUpdatedAtCustomAsHTMLRendererTrait};
use App\Helper\TextHelper;
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Entity\{PostCreate, PreCreate};
use Fraym\Helper\{DataHelper, LocaleHelper};
use Generator;

/** @extends BaseService<PlotModel|PlotPlotModel> */
#[Controller(PlotController::class)]
#[PostCreate]
#[PreCreate]
class PlotService extends BaseService
{
    use GetUpdatedAtCustomAsHTMLRendererTrait;
    use GamemastersListTrait;

    #[DependencyInjection]
    public GroupService $groupService;

    #[DependencyInjection]
    public CharacterService $characterService;

    #[DependencyInjection]
    public ApplicationService $applicationService;

    #[DependencyInjection]
    public MyapplicationService $myApplicationService;

    private ?array $plotFromTo = null;

    /** Формирование список "Для" и "Про" в завязках */
    public function getApplicationsListInPlot(?int $storyId): array
    {
        if (is_null($storyId)) {
            return [];
        }

        $userService = $this->getUserService();

        $LOCALE = $this->getLocale();

        $applicationsList = [];
        $storyData = $this->get($storyId);

        if ($storyData->id->getAsInt()) {
            $values = $storyData->project_character_ids->get();

            /* сначала разбираемся, какие у нас группы были выбраны в сюжете и какие персонажи */
            $usedGroupsIds = [];
            $usedCharactersIds = [];

            foreach ($values as $value) {
                if ($value === '0') {
                    // $applicationsList[] = array('all0', '<b>' . $LOCALE['global_story_2'] . '</b>');
                } elseif (str_contains($value, 'group')) {
                    $value = preg_replace('#group#', '', $value);
                    $usedGroupsIds[] = $value;
                } elseif ($value > 0) {
                    $usedCharactersIds[] = $value;
                }
            }

            /* выбираем полный список персонажей проекта */
            $characterService = $this->characterService;

            $allCharsData = [];
            $shownCharactersIds = [];
            $projectCharactersData = $characterService->getAll(
                [
                    'project_id' => $this->getActivatedProjectId(),
                ],
                false,
                ['name'],
            );

            foreach ($projectCharactersData as $projectCharacterData) {
                $allCharsData[] = [
                    $projectCharacterData->id->getAsInt(),
                    str_replace('&#39', '`', $projectCharacterData->name->get()),
                    $projectCharacterData->project_group_ids->get(),
                ];
            }

            /* выбираем полный список заявок игры */
            $allApplicationsData = [];
            $projectApplicationsData = DB->query(
                "SELECT pa.id AS application_id, pa.sorter, pa.project_character_id, u.* FROM project_application AS pa LEFT JOIN user AS u ON u.id=pa.creator_id WHERE pa.project_id=:project_id AND pa.deleted_by_gamemaster='0' AND pa.deleted_by_player='0' AND pa.status!=4 ORDER BY pa.sorter",
                [
                    ['project_id', $this->getActivatedProjectId()],
                ],
            );

            foreach ($projectApplicationsData as $projectApplicationData) {
                $allApplicationsData[] = [
                    $projectApplicationData['application_id'],
                    str_replace('&#39', '`', DataHelper::escapeOutput($projectApplicationData['sorter'])),
                    $projectApplicationData['project_character_id'],
                    $projectApplicationData,
                ];
            }

            /* собираем полный список групп проекта и всех персонажей в них */
            $shownGroupsIds = [];
            $listOfGroups = DB->getTreeOfItems(
                false,
                'project_group',
                'parent',
                null,
                ' AND project_id=' . $this->getActivatedProjectId(),
                'code ASC, name ASC',
                0,
                'id',
                'name',
                1000000,
                false,
            );

            if (count($listOfGroups) > 0) {
                $groupService = $this->groupService;

                foreach ($listOfGroups as $key => $value) {
                    $listOfGroups[$key][1] = $groupService->createGroupPath($key, $listOfGroups);

                    if ($value[0] > 0 && (in_array($value[0], $usedGroupsIds) || in_array($value[3]['parent'], $shownGroupsIds))) {
                        $shownGroupsIds[] = $value[0];
                        $applicationsList[] = [
                            'group' . $value[0],
                            '<span class="sbi sbi-users" title="' . $LOCALE['title_group'] . '"></span><a href="' . ABSOLUTE_PATH . '/group/' . $value[0] . '/" target="_blank" class="edit"></a>' . $listOfGroups[$key][1],
                            $value[2],
                        ];

                        /* показываем всех персонажей группы, но только если эта группа у персонажа самая низкая в данной ветке */
                        if (count($allCharsData) > 0) {
                            foreach ($allCharsData as $allCharData) {
                                if (in_array($value[0], $allCharData[2])) {
                                    $inChildGroup = false;
                                    $nextKey = $key + 1;

                                    if (isset($listOfGroups[$nextKey])) {
                                        $nextLevel = $listOfGroups[$nextKey][2];

                                        while ($nextLevel > $value[2]) {
                                            if (in_array($listOfGroups[$nextKey][0], $allCharData[2])) {
                                                $inChildGroup = true;
                                                break;
                                            }
                                            ++$nextKey;

                                            if (isset($listOfGroups[$nextKey])) {
                                                $nextLevel = $listOfGroups[$nextKey][2];
                                            } else {
                                                break;
                                            }
                                        }
                                    }

                                    if (!$inChildGroup) {
                                        $applicationsList[] = [
                                            'all' . $allCharData[0],
                                            '<span class="sbi sbi-user" title="' . $LOCALE['title_character'] . '"></span><a href="' . ABSOLUTE_PATH . '/character/' . $allCharData[0] . '/" target="_blank" class="edit"></a>' . $allCharData[1],
                                            $value[2] + 1,
                                        ];
                                        $shownCharactersIds[] = $allCharData[0];

                                        foreach ($allApplicationsData as $allApplicationData) {
                                            if ($allApplicationData[2] === $allCharData[0]) {
                                                $applicationsList[] = [
                                                    $allApplicationData[0],
                                                    '<span class="sbi sbi-file-filled" title="' . $LOCALE['title_application'] . '"></span><a href="' . ABSOLUTE_PATH . '/application/' . $allApplicationData[0] . '/" target="_blank" class="edit"></a>' . $allApplicationData[1] . ' (' . $userService->showNameExtended(
                                                        $userService->arrayToModel($allApplicationData[3]),
                                                        true,
                                                        false,
                                                        '',
                                                        false,
                                                        false,
                                                        true,
                                                    ) . ')',
                                                    $value[2] + 2,
                                                ];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                unset($listOfGroups);
            }

            /* а теперь проходимся уже по отдельно выбранным персонажам и заявкам в них только */
            if (count($allCharsData) > 0) {
                foreach ($allCharsData as $allCharData) {
                    if (!in_array($allCharData[0], $shownCharactersIds) && in_array(
                        $allCharData[0],
                        $usedCharactersIds,
                    )) {
                        $applicationsList[] = [
                            'all' . $allCharData[0],
                            '<span class="sbi sbi-user" title="' . $LOCALE['title_character'] . '"></span><a href="' . ABSOLUTE_PATH . '/character/' . $allCharData[0] . '/" target="_blank" class="edit"></a>' . $allCharData[1],
                            0,
                        ];
                        $shownCharactersIds[] = $allCharData[0];

                        foreach ($allApplicationsData as $allApplicationData) {
                            if ($allApplicationData[2] === $allCharData[0]) {
                                $applicationsList[] = [
                                    $allApplicationData[0],
                                    '<span class="sbi sbi-file-filled" title="' . $LOCALE['title_application'] . '"></span><a href="' . ABSOLUTE_PATH . '/application/' . $allApplicationData[0] . '/" target="_blank" class="edit"></a>' . $allApplicationData[1] . ' (' . $userService->showNameExtended(
                                        $userService->arrayToModel($allApplicationData[3]),
                                        true,
                                        false,
                                        '',
                                        false,
                                        false,
                                        true,
                                    ) . ')',
                                    1,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $applicationsList;
    }

    /** Отрисовка всех сюжетов персонажа */
    public function generateAllPlots(int $projectId, string $objType, ?int $objId, bool $forPlayer = false): ?string
    {
        if (is_null($objId)) {
            return null;
        }

        $userService = $this->getUserService();
        $characterService = $this->characterService;
        $groupService = $this->groupService;

        $LOCALE = $this->getLOCALE();

        $objType = DataHelper::clearBraces($objType);

        $objTypePossibleArray = [
            'character',
            'story',
            'application',
        ];

        if (!in_array($objType, $objTypePossibleArray) || !($objId > 0)) {
            return '';
        }

        $resultFor = '';
        $resultAbout = '';

        $objCharacterId = 0;
        $objApplicationsIds = [];
        $objGroups = [];

        if ($objType === 'application') {
            $projectApplicationData = $forPlayer ? $this->myApplicationService->get($objId) : $this->applicationService->get($objId);

            if (!$forPlayer || ($projectApplicationData->status->get() === 3 && !$projectApplicationData->deleted_by_gamemaster->get() && !$projectApplicationData->deleted_by_player->get())) {
                $applicationQuery = "pp.applications_1_side_ids LIKE '%-" . $objId . "-%'";

                $objApplicationsIds[] = $objId;

                $groupsFound = [];
                $projectApplicationGroups = $projectApplicationData->project_group_ids->get();

                foreach ($projectApplicationGroups as $projectApplicationGroup) {
                    if ($projectApplicationGroup > 0 && !in_array($projectApplicationGroup, $groupsFound)) {
                        $groupsFound[] = $projectApplicationGroup;
                        $applicationQuery .= " OR pp.applications_1_side_ids LIKE '%-group" . $projectApplicationGroup . "-%'";
                    }
                }

                if ($projectApplicationData->project_character_id->get()) {
                    $projectCharacterData = $characterService->get($projectApplicationData->project_character_id->get()[0]);

                    if ($projectCharacterData && $projectCharacterData->id->getAsInt()) {
                        $objCharacterId = $projectCharacterData->id->getAsInt();
                        $projectCharacterGroups = $projectCharacterData->project_group_ids->get();

                        $applicationQuery .= " OR pp.applications_1_side_ids LIKE '%-all" . $objCharacterId . "-%'";

                        foreach ($projectCharacterGroups as $projectCharacterGroup) {
                            if ($projectCharacterGroup > 0 && !in_array($projectCharacterGroup, $groupsFound)) {
                                $groupsFound[] = $projectCharacterGroup;
                                $applicationQuery .= " OR pp.applications_1_side_ids LIKE '%-group" . $projectCharacterGroup . "-%'";
                            }
                        }
                    }
                }
                $objGroups = $groupsFound;
                unset($groupsFound);

                if (!$forPlayer) {
                    $applicationQuery .= ' OR ' . preg_replace(
                        '#applications_1_side_ids#',
                        'applications_2_side_ids',
                        $applicationQuery,
                    );
                }

                $plotsData = DB->query(
                    'SELECT DISTINCT pp.*, pp2.todo AS plot_todo, pp2.code AS plot_code FROM project_plot AS pp LEFT JOIN project_plot AS pp2 ON pp2.id=pp.parent WHERE pp.project_id=:project_id AND pp.parent > 0 AND (' . $applicationQuery . ')' . ($forPlayer ? " AND (pp.todo='' OR pp.todo IS NULL) AND (pp2.todo='' OR pp2.todo IS NULL)" : '') . ' ORDER BY pp2.code DESC, pp.code DESC, pp.updated_at DESC',
                    [
                        ['project_id', $projectId],
                    ],
                );
            }
        } elseif ($objType === 'character') {
            $projectCharacterData = $characterService->get($objId);
            $groupQuery = '';
            $groupParams = [];
            $applicationsQuery = '';
            $applicationsParams = [];

            if ($projectCharacterData->id->getAsInt()) {
                $objCharacterId = $projectCharacterData->id->getAsInt();

                $objGroups = $projectCharacterData->project_group_ids->get();
                $objGroupsCount = 1;

                foreach ($objGroups as $projectCharacterGroup) {
                    if ($projectCharacterGroup > 0) {
                        $groupQuery .= ' OR pp.applications_1_side_ids LIKE :project_character_group_' . $objGroupsCount . ' OR pp.applications_2_side_ids LIKE :project_character_group_' . ($objGroupsCount + 1);
                        $groupParams[] = ['project_character_group_' . $objGroupsCount, '%-group' . $projectCharacterGroup . '-%'];
                        $groupParams[] = ['project_character_group_' . ($objGroupsCount + 1), '%-group' . $projectCharacterGroup . '-%'];
                        $objGroupsCount += 2;
                    }
                }

                $projectCharacterApplicationsData = DB->query(
                    "SELECT pa.* FROM project_application AS pa WHERE pa.project_character_id=:project_character_id AND pa.deleted_by_gamemaster='0' AND pa.project_id=:project_id",
                    [
                        ['project_character_id', $objId],
                        ['project_id', $projectId],
                    ],
                );
                $projectCharacterApplicationsDataCount = 1;

                foreach ($projectCharacterApplicationsData as $projectCharacterApplicationData) {
                    $objApplicationsIds[] = $projectCharacterApplicationData['id'];
                    $applicationsQuery .= ' OR pp.applications_1_side_ids LIKE :project_character_application_data_' . $projectCharacterApplicationsDataCount . ' OR pp.applications_2_side_ids LIKE :project_character_application_data_' . ($projectCharacterApplicationsDataCount + 1);
                    $applicationsParams[] = ['project_character_application_data_' . $projectCharacterApplicationsDataCount, '%-' . $projectCharacterApplicationData['id'] . '-%'];
                    $applicationsParams[] = ['project_character_application_data_' . ($projectCharacterApplicationsDataCount + 1), '%-' . $projectCharacterApplicationData['id'] . '-%'];
                    $projectCharacterApplicationsDataCount += 2;
                }
            }

            $plotsData = DB->query(
                'SELECT DISTINCT pp.*, pp2.todo AS plot_todo, pp2.code AS plot_code FROM project_plot AS pp LEFT JOIN project_plot AS pp2 ON pp2.id=pp.parent WHERE pp.project_id=:project_id AND pp.parent>0 AND (pp.applications_1_side_ids LIKE :applications_ids_1 OR pp.applications_2_side_ids LIKE :applications_ids_2' . $groupQuery . $applicationsQuery . ') ORDER BY plot_code DESC, pp.code DESC, pp.updated_at DESC',
                array_merge([
                    ['project_id', $projectId],
                    ['applications_ids_1', '%-all' . $objId . '-%'],
                    ['applications_ids_2', '%-all' . $objId . '-%'],
                ], $applicationsParams, $groupParams),
            );
        } elseif ($objType === 'story') {
            $plotsData = DB->query(
                'SELECT pp.*, pp2.todo AS plot_todo FROM project_plot AS pp LEFT JOIN project_plot AS pp2 ON pp2.id=pp.parent WHERE pp.parent=:parent ORDER BY pp.code DESC',
                [
                    ['parent', $objId],
                ],
            );
        } else {
            return '';
        }

        $plotsAboutCount = 0;

        if (isset($plotsData)) {
            foreach ($plotsData as $plotDataRaw) {
                $plotData = $this->arrayToModel($plotDataRaw);
                $tempResult = '';
                $blockDoseeBecauseOfSize = false;

                $tempResult .= '<div class="plot">
<div class="plot_name_and_participants">';

                if (!$forPlayer) {
                    $tempResult .= '<a href="' . ABSOLUTE_PATH . '/plot/plot_plot/' . $plotData->id->getAsInt() . '/act=edit">' . $LOCALE['plot'];

                    if ($plotData->name->get()) {
                        $tempResult .= ' «' . $plotData->name->get() . '»';
                    }
                    $tempResult .= '</a>';
                }

                $applications1SideIds = $plotData->applications_1_side_ids->get();
                $applications2SideIds = $plotData->applications_2_side_ids->get();

                /* проверяем, указан ли объект внутри группы "Для" у завязки */
                $inGroupFor = false;

                if ($objType === 'story') {
                    $inGroupFor = true;
                } else {
                    foreach ($applications1SideIds as $applications1SideId) {
                        if ($applications1SideId !== '') {
                            if (str_contains($applications1SideId, 'group')) {
                                foreach ($objGroups as $groupId) {
                                    if (in_array('group' . $groupId, $applications1SideIds)) {
                                        $inGroupFor = true;
                                        break;
                                    }
                                }
                            } elseif ($objCharacterId > 0 && $applications1SideId === 'all' . $objCharacterId) {
                                $inGroupFor = true;
                            } elseif ($applications1SideId > 0 && in_array(
                                $applications1SideId,
                                $objApplicationsIds,
                            )) {
                                $inGroupFor = true;
                            }
                        }

                        if ($inGroupFor) {
                            break;
                        }
                    }
                }

                $dosee = '';

                if (!$forPlayer) {
                    $tempResult .= ' ' . mb_strtolower($LOCALE['for']) . ' ';

                    $gotSomeCharacter = false;
                    $gotSomeDosee = false;

                    if (count($applications1SideIds) > 50) {
                        $tempResult .= '<i>' . $LOCALE['too_many'] . '</i>';
                    } else {
                        foreach ($applications1SideIds as $applications1SideId) {
                            if ($applications1SideId !== '') {
                                $query = '';
                                $queryParams = [];

                                if (str_contains($applications1SideId, 'group')) {
                                    $projectGroupData = $groupService->get((int) str_replace('group', '', $applications1SideId), ['project_id' => $projectId]);

                                    if ($projectGroupData->name->get()) {
                                        $tempResult .= '<a href="' . ABSOLUTE_PATH . '/group/' . $projectGroupData->id->getAsInt() . '/">' .
                                            $projectGroupData->name->get() . '</a>, ';
                                        $query = "SELECT * FROM project_application WHERE project_group_ids LIKE :project_group_ids AND deleted_by_gamemaster='0' AND project_id=:project_id";
                                        $queryParams[] = ['project_group_ids', '%-' . $projectGroupData->id->getAsInt() . '-%'];
                                        $queryParams[] = ['project_id', $projectId];
                                    } else {
                                        $tempResult .= '<i>' . $LOCALE['deleted_group'] . '</i>, ';
                                    }
                                    $gotSomeCharacter = true;
                                } elseif (str_contains($applications1SideId, 'all')) {
                                    $projectCharacterData = $characterService->get((int) str_replace('all', '', $applications1SideId), ['project_id' => $projectId]);

                                    if ($projectCharacterData->name->get()) {
                                        $tempResult .= '<a href="' . ABSOLUTE_PATH . '/character/' . $projectCharacterData->id->getAsInt() . '/">' . $projectCharacterData->name->get() . '</a>, ';
                                        $query = "SELECT * FROM project_application WHERE project_character_id=:project_character_id AND deleted_by_gamemaster='0' AND project_id=:project_id";
                                        $queryParams[] = ['project_character_id', $projectCharacterData->id->getAsInt()];
                                        $queryParams[] = ['project_id', $projectId];
                                    } elseif ($applications1SideId === 0) {
                                        $tempResult .= '<i>' . $LOCALE['global_story'] . '</i>, ';
                                    } else {
                                        $tempResult .= '<i>' . $LOCALE['deleted_character'] . '</i>, ';
                                    }
                                    $gotSomeCharacter = true;
                                } elseif ($applications1SideId > 0) {
                                    $projectApplicationData = $this->applicationService->get(
                                        id: $applications1SideId,
                                        criteria: [
                                            'deleted_by_gamemaster' => '0',
                                            'project_id' => $projectId,
                                        ],
                                    );

                                    if ($projectApplicationData) {
                                        $tempResult .= '<a href="' . ABSOLUTE_PATH . '/application/' . $projectApplicationData->id->getAsInt() . '/">' .
                                            DataHelper::escapeOutput($projectApplicationData->sorter->get()) . '</a>, ';
                                    } else {
                                        $tempResult .= '<i>' . $LOCALE['deleted_application'] . '</i>, ';
                                    }
                                    $query = "SELECT * FROM project_application WHERE id=:applications_1_side_id AND deleted_by_gamemaster='0' AND project_id=:project_id";
                                    $queryParams[] = ['applications_1_side_id', $applications1SideId];
                                    $queryParams[] = ['project_id', $projectId];
                                    $gotSomeCharacter = true;
                                }

                                if (!$blockDoseeBecauseOfSize) {
                                    if ($query !== '') {
                                        $applicationsData = DB->query($query, $queryParams);
                                        $applicationsDataCount = count($applicationsData);

                                        if ($applicationsDataCount > 20) {
                                            $blockDoseeBecauseOfSize = true;
                                            $dosee = '';
                                        } else {
                                            foreach ($applicationsData as $applicationData) {
                                                if (
                                                    in_array($applicationData['id'], $plotData->applications_1_side_ids->get())
                                                    || in_array($applications1SideId, $plotData->applications_1_side_ids->get())
                                                ) {
                                                    $dosee .= '<a href="' . ABSOLUTE_PATH . '/application/' . $applicationData['id'] . '/">' . ($applicationData['deleted_by_player'] !== '1' ? '' : '<s>') .
                                                        DataHelper::escapeOutput($applicationData['sorter']) .
                                                        ($applicationData['deleted_by_player'] !== '1' ? '' : '</s>') . '</a>';

                                                    if (
                                                        in_array($applications1SideId, $plotData->applications_1_side_ids->get())
                                                        && $applicationData['status'] < 3 && $applicationData['deleted_by_player'] !== '1'
                                                    ) {
                                                        $dosee .= ' <span class="sbi sbi-times" title="' . $LOCALE['will_see_when_accepted'] . '"></span>';
                                                    }
                                                    $dosee .= ', ';
                                                    $gotSomeDosee = true;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if ($gotSomeCharacter) {
                            $tempResult = mb_substr($tempResult, 0, mb_strlen($tempResult) - 2);
                        }

                        if ($gotSomeDosee) {
                            $dosee = mb_substr($dosee, 0, mb_strlen($dosee) - 2);
                        }
                    }
                }

                $plotAboutSpecificApplications = [];
                $tempResult2 = '';

                if (($forPlayer && !$plotData->hideother->get()) || !$forPlayer) {
                    $gotSomeCharacter = false;

                    if (count($applications2SideIds) > 50) {
                        $tempResult2 = ' ' . ($forPlayer ? TextHelper::mb_ucfirst($LOCALE['about']) : mb_strtolower($LOCALE['about'])) .
                            ' <i>' . $LOCALE['too_many'] . '</i>';
                    } else {
                        foreach ($applications2SideIds as $applications2SideId) {
                            if ($applications2SideId !== '') {
                                if (str_contains($applications2SideId, 'group')) {
                                    $projectGroupData = $groupService->get((int) str_replace('group', '', $applications2SideId), ['project_id' => $projectId]);

                                    if ($projectGroupData->name->get()) {
                                        if (!$forPlayer) {
                                            $tempResult2 .= '<a href="' . ABSOLUTE_PATH . '/group/' . $projectGroupData->id->getAsInt() . '/">' . $projectGroupData->name->get() . '</a>, ';
                                            $gotSomeCharacter = true;
                                        } elseif (!in_array($projectGroupData->rights->get(), [2, 3])) {
                                            $gotSomeCharacter = true;
                                            $projectApplications = DB->query(
                                                "SELECT u.*, pa.sorter AS application_sorter, pc.name AS character_name, pc.id as character_id FROM project_application AS pa LEFT JOIN project_character AS pc ON pc.id=pa.project_character_id LEFT JOIN user AS u ON u.id=pa.creator_id WHERE pa.project_group_ids LIKE :project_group_ids AND pa.deleted_by_gamemaster='0' AND pa.project_id=:project_id",
                                                [
                                                    ['project_group_ids', '%-' . $projectGroupData->id->getAsInt() . '-%'],
                                                    ['project_id', $projectId],
                                                ],
                                            );

                                            foreach ($projectApplications as $projectApplicationData) {
                                                $plotAboutSpecificApplications[$projectGroupData->id->getAsInt()][] = [
                                                    'group_id' => $projectGroupData->id->getAsInt(),
                                                    'group_name' => $projectGroupData->name->get(),
                                                    'character_name' => DataHelper::escapeOutput($projectApplicationData['character_name']),
                                                    'character_id' => $projectApplicationData['character_id'],
                                                    'creator_data' => $projectApplicationData,
                                                    'application_sorter' => DataHelper::escapeOutput($projectApplicationData['application_sorter']),
                                                ];
                                            }
                                            $tempResult2 .= '<a href="' . ABSOLUTE_PATH . '/roles/' . $projectId . '/#group_' . $projectGroupData['id'] . '" target="_blank">' . $projectGroupData->name->get() . '</a>, ';
                                        }
                                    } else {
                                        $tempResult2 .= '<i>' . $LOCALE['deleted_group_2'] . '</i>, ';
                                        $gotSomeCharacter = true;
                                    }
                                } elseif (str_contains($applications2SideId, 'all')) {
                                    $projectCharacterData = $characterService->get((int) str_replace('all', '', $applications2SideId), ['project_id' => $projectId]);

                                    if ($projectCharacterData?->name->get()) {
                                        if (!$forPlayer) {
                                            $tempResult2 .= '<a href="' . ABSOLUTE_PATH . '/character/' . $projectCharacterData->id->getAsInt() . '/">' .
                                                $projectCharacterData->name->get() . '</a>, ';
                                        } else {
                                            $projectApplications = DB->query(
                                                "SELECT u.*, pa.sorter AS application_sorter, pc.name AS character_name, pc.id as character_id FROM project_application AS pa LEFT JOIN project_character AS pc ON pc.id=pa.project_character_id LEFT JOIN user AS u ON u.id=pa.creator_id WHERE pa.project_character_id=:project_character_id AND pa.deleted_by_gamemaster='0' AND pa.project_id=:project_id",
                                                [
                                                    ['project_character_id', $projectCharacterData->id->getAsInt()],
                                                    ['project_id', $projectId],
                                                ],
                                            );

                                            foreach ($projectApplications as $projectApplicationData) {
                                                $plotAboutSpecificApplications[0][] = [
                                                    'character_name' => DataHelper::escapeOutput($projectApplicationData['character_name']),
                                                    'character_id' => $projectApplicationData['character_id'],
                                                    'creator_data' => $projectApplicationData,
                                                    'application_sorter' => DataHelper::escapeOutput($projectApplicationData['application_sorter']),
                                                ];
                                            }
                                            $tempResult2 .= '<a href="' . ABSOLUTE_PATH . '/roles/' . $projectId . '/character/' . $projectCharacterData->id->getAsInt() . '/">' .
                                                $projectCharacterData->name->get() . '</a>, ';
                                        }
                                    } elseif ($applications2SideId === 0) {
                                        $tempResult2 .= '<i>' . $LOCALE['global_story_2'] . '</i>, ';
                                    } else {
                                        $tempResult2 .= '<i>' . $LOCALE['deleted_character_2'] . '</i>, ';
                                    }
                                    $gotSomeCharacter = true;
                                } else {
                                    $projectApplicationData = DB->query(
                                        "SELECT u.*, pa.id AS application_id, pa.sorter AS application_sorter, pc.name AS character_name, pc.id as character_id FROM project_application AS pa LEFT JOIN project_character AS pc ON pc.id=pa.project_character_id LEFT JOIN user AS u ON u.id=pa.creator_id WHERE pa.id=:applications_2_side_id AND pa.deleted_by_gamemaster='0' AND pa.project_id=:project_id",
                                        [
                                            ['applications_2_side_id', $applications2SideId],
                                            ['project_id', $projectId],
                                        ],
                                        true,
                                    );

                                    if ((int) $projectApplicationData['id'] > 0) {
                                        if (!$forPlayer) {
                                            $tempResult2 .= '<a href="' . ABSOLUTE_PATH . '/application/' . $projectApplicationData['application_id'] . '/">' .
                                                DataHelper::escapeOutput($projectApplicationData['application_sorter']) . '</a>, ';
                                        } else {
                                            $tempResult2 .= '<a href="' . ABSOLUTE_PATH . '/people/' . $projectApplicationData['sid'] . '/" target="_blank">' .
                                                DataHelper::escapeOutput($projectApplicationData['application_sorter']) . '</a>, ';
                                            $plotAboutSpecificApplications[0][] = [
                                                'character_name' => DataHelper::escapeOutput($projectApplicationData['character_name']),
                                                'character_id' => $projectApplicationData['character_id'],
                                                'creator_data' => $projectApplicationData,
                                                'application_sorter' => DataHelper::escapeOutput($projectApplicationData['application_sorter']),
                                            ];
                                        }
                                    } else {
                                        $tempResult2 .= '<i>' . $LOCALE['deleted_application_2'] . '</i>, ';
                                    }
                                    $gotSomeCharacter = true;
                                }
                            }
                        }

                        if ($gotSomeCharacter) {
                            $tempResult2 = ($forPlayer ? TextHelper::mb_ucfirst($LOCALE['about']) : ' ' .
                                mb_strtolower($LOCALE['about'])) . ' ' . $tempResult2;
                            $tempResult2 = mb_substr($tempResult2, 0, mb_strlen($tempResult2) - 2);
                        }
                    }
                } elseif ($plotData->hideother->get()) {
                    $tempResult2 = TextHelper::mb_ucfirst($LOCALE['about']) . ' ' . $LOCALE['hidden_about'];
                }
                $tempResult .= $tempResult2;

                if (!$forPlayer) {
                    $doseeAdditional = '';

                    if ($plotData->hideother->get()) {
                        $doseeAdditional .= ' <span class="sbi sbi-info" title="' . $LOCALE['hidden_plot_participant'] . '"></span>';
                    }

                    if ($plotData->todo->get()) {
                        $doseeAdditional .= ' <span class="sbi sbi-times" title="' . $LOCALE['plot_not_ready'] . ': 
' . str_replace("\n", '<br>', $plotData->todo->get()) . '"></span>';
                    }

                    if ($dosee !== '') {
                        $tempResult .= ' (' . sprintf($LOCALE['visible_to'], $doseeAdditional) . $dosee . ')';
                    }
                }
                $tempResult .= '</div>';

                if (!$forPlayer) {
                    $parentPlotData = $this->get($plotData->parent->get());
                    $tempResult .= '<div class="plot_parent_name">' . $LOCALE['story'] . ' «<a href="' . ABSOLUTE_PATH . '/plot/' . $parentPlotData->id->getAsInt() . '/">' .
                        $parentPlotData->name->get() . '</a>»' . ($parentPlotData->todo->get() ?
                            ' <span class="sbi sbi-times" title="' . $LOCALE['plot_not_ready'] . ': ' .
                            str_replace("\n", '<br>', $parentPlotData->todo->get()) . '"></span>' : '') . '</div>';
                } elseif (count($plotAboutSpecificApplications) > 0) {
                    $tempResult .= '<a class="show_hidden">' . $LOCALE['details'] . '</a><div class="hidden">
<div class="plot_specific_applications"><ul>';

                    foreach ($plotAboutSpecificApplications as $plotAboutSpecificApplicationList) {
                        if (isset($plotAboutSpecificApplicationList[0]['group_id'])) { // это группа
                            $tempResult .= '<li><b><a href="' . ABSOLUTE_PATH . '/roles/' . $projectId . '/#group_' . $plotAboutSpecificApplicationList[0]['group_id'] . '" target="_blank">' . $plotAboutSpecificApplicationList[0]['group_name'] . '</a></b><ul>';
                        }

                        foreach ($plotAboutSpecificApplicationList as $plotAboutSpecificApplication) {
                            $tempResult .= '<li><div>' . $LOCALE['player'] . ': ' . str_replace(
                                'neww"',
                                'neww" target="_blank"',
                                $userService->showNameExtended(
                                    $userService->arrayToModel($plotAboutSpecificApplication['creator_data']),
                                    false,
                                    true,
                                    'neww',
                                    false,
                                    false,
                                    true,
                                ),
                            ) . '. ';

                            if ($plotAboutSpecificApplication['character_id'] > 0) {
                                $tempResult .= $LOCALE['character_name'] . ': <a href="' . ABSOLUTE_PATH . '/roles/' . $projectId . '/character/' . $plotAboutSpecificApplication['character_id'] . '/">' . $plotAboutSpecificApplication['application_sorter'] . '</a>.' . ($plotAboutSpecificApplication['application_sorter'] !== $plotAboutSpecificApplication['character_name'] ? ' ' . $LOCALE['character'] . ': <a href="' . ABSOLUTE_PATH . '/roles/' . $projectId . '/character/' . $plotAboutSpecificApplication['character_id'] . '/">' . $plotAboutSpecificApplication['character_name'] . '</a>.' : '');
                            }
                            $tempResult .= '</div></li>';
                        }

                        if (isset($plotAboutSpecificApplicationList[0]['group_id'])) { // это группа
                            $tempResult .= '</ul></li>';
                        }
                    }
                    $tempResult .= '</ul></div></div>';
                }

                /* изменение ссылок на объекты в описании завязки */
                $plotContent = $plotData->content->get();
                $plotContent = preg_replace(
                    '#(?<!vk\.com/)@([^\[]+)\[all(\d+)]#',
                    '<a href="' . ABSOLUTE_PATH . '/roles/' . $projectId . '/character/$2/">$1</a>',
                    $plotContent,
                );
                $plotContent = preg_replace(
                    '#(?<!vk\.com/)@([^\[]+)\[group(\d+)]#',
                    '<a href="' . ABSOLUTE_PATH . '/roles/' . $projectId . '/group/$2/">$1</a>',
                    $plotContent,
                );

                unset($matches);
                preg_match_all('#(?<!vk\.com/)@([^\[]+)\[(\d+)]#', $plotContent, $matches);

                foreach ($matches[2] as $key => $applicationId) {
                    if ((int) $applicationId > 0) {
                        $creatorData = DB->query(
                            'SELECT u.* FROM project_application AS pa LEFT JOIN user AS u ON u.id=pa.creator_id WHERE pa.id=:id AND pa.project_id=:project_id',
                            [
                                ['id', $applicationId],
                                ['project_id', $projectId],
                            ],
                            true,
                        );

                        if ($creatorData) {
                            $plotContent = preg_replace(
                                '#' . preg_quote($matches[0][$key]) . '#',
                                '<a href="' . ABSOLUTE_PATH . '/people/' . $creatorData['sid'] . '/">' . $matches[1][$key] . '</a>',
                                $plotContent,
                            );
                        }
                    }
                }

                $tempResult .= '
<div class="plot_content">' . $plotContent . '</div>
</div>';

                if ($inGroupFor) {
                    $resultFor .= $tempResult;
                } else {
                    $resultAbout .= $tempResult;
                    ++$plotsAboutCount;
                }
            }
        }

        $result = $resultFor;

        if ($resultAbout !== '' && $resultFor !== '') {
            $result .= '<a class="show_hidden">' . sprintf($LOCALE['about_character_not_see'], $plotsAboutCount) . '</a>
<div class="hidden">
<div class="plot"><h2>' . sprintf($LOCALE['about_character_not_see'], $plotsAboutCount) . '</h2></div>';
        }

        $result .= $resultAbout;

        if ($resultAbout !== '' && $resultFor !== '') {
            $result .= '</div>';
        }

        return $result;
    }

    public function preCreate(): void
    {
        if ($this->getEntity()->getName() === CMSVC) {
            $_REQUEST['go_back_after_save'][0] = '';
        }
    }

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            /** Если это сюжет, сразу перебрасываем в создание завязки к сюжету */
            if ($this->getEntity()->getName() === CMSVC) {
                $this->getEntity()->setFraymActionRedirectPath(ABSOLUTE_PATH . '/plot/plot_plot/act=add&parent=' . $successfulResultsId);
                break;
            }
        }
    }

    public function getSortId(): array
    {
        $LOCALE = $this->getLOCALE();

        if (is_null($this->plotFromTo)) {
            $plotFromTo = [];
            $fullGroupData = [];
            $fullGroupDataQuery = DB->query(
                "SELECT pg.id, pg.name, COUNT(DISTINCT pa.id) AS application_count FROM project_group AS pg LEFT JOIN relation AS r ON r.obj_id_to=pg.id AND r.type='{member}' AND r.obj_type_to='{group}' AND r.obj_type_from='{application}' LEFT JOIN project_application AS pa ON pa.id=r.obj_id_from AND pa.project_id=pg.project_id AND pa.status!=4 AND pa.deleted_by_gamemaster='0' AND pa.deleted_by_player='0' WHERE pg.project_id=:project_id GROUP BY pg.id",
                [
                    ['project_id', $this->getActivatedProjectId()],
                ],
            );

            foreach ($fullGroupDataQuery as $fullGroupDataItem) {
                $fullGroupData[$fullGroupDataItem['id']] = [
                    DataHelper::escapeOutput($fullGroupDataItem['name']),
                    $fullGroupDataItem['application_count'],
                ];
            }

            $fullCharacterData = [];
            $fullCharacterDataQuery = DB->query(
                "SELECT pc.id, pc.name, COUNT(DISTINCT pa.id) AS application_count FROM project_character AS pc LEFT JOIN project_application AS pa ON pa.project_character_id=pc.id AND pa.project_id=pc.project_id AND pa.status!=4 AND pa.deleted_by_gamemaster='0' AND pa.deleted_by_player='0' WHERE pc.project_id=:project_id GROUP BY pc.id",
                [
                    ['project_id', $this->getActivatedProjectId()],
                ],
            );

            foreach ($fullCharacterDataQuery as $fullCharacterDataItem) {
                $fullCharacterData[$fullCharacterDataItem['id']] = [
                    DataHelper::escapeOutput($fullCharacterDataItem['name']),
                    $fullCharacterDataItem['application_count'],
                ];
            }

            $plotsDataForFromTo = $this->getAll([
                'project_id' => $this->getActivatedProjectId(),
            ]);

            foreach ($plotsDataForFromTo as $plotData) {
                /** @var PlotModel|PlotPlotModel $plotData */
                if (!isset($plotFromTo[$plotData->id->getAsInt()])) {
                    $result = '';

                    if ($plotData instanceof PlotPlotModel) {
                        $applications1SideIds = $plotData->applications_1_side_ids->get();
                        $applications2SideIds = $plotData->applications_2_side_ids->get();

                        $gotSomeCharacter = false;
                        $resultFor = '';

                        foreach ($applications1SideIds as $applications1SideId) {
                            if ($applications1SideId !== '') {
                                $checkFollowup = false;
                                $applicationsCount = 0;

                                if (str_contains($applications1SideId, 'group')) {
                                    if ($fullGroupData[str_replace('group', '', $applications1SideId)][0] !== '') {
                                        $resultFor .= $fullGroupData[str_replace('group', '', $applications1SideId)][0];
                                    } else {
                                        $resultFor .= '<i>' . $LOCALE['deleted_group'] . '</i>';
                                    }
                                    $applicationsCount = $fullGroupData[str_replace('group', '', $applications1SideId)][1];
                                    $checkFollowup = true;
                                    $gotSomeCharacter = true;
                                } elseif (str_contains($applications1SideId, 'all')) {
                                    if ($fullCharacterData[str_replace('all', '', $applications1SideId)][0] !== '') {
                                        $resultFor .= $fullCharacterData[str_replace('all', '', $applications1SideId)][0];
                                    } elseif ($applications1SideId === 0) {
                                        $resultFor .= '<i>' . $LOCALE['global_story'] . '</i>';
                                    } else {
                                        $resultFor .= '<i>' . $LOCALE['deleted_character'] . '</i>';
                                    }
                                    $applicationsCount = $fullCharacterData[str_replace('all', '', $applications1SideId)][1];
                                    $checkFollowup = true;
                                    $gotSomeCharacter = true;
                                } elseif ($applications1SideId > 0) {
                                    $projectApplicationData = $this->applicationService->get(
                                        id: $applications1SideId,
                                        criteria: [
                                            'project_id' => $this->getActivatedProjectId(),
                                        ],
                                    );

                                    if ($projectApplicationData) {
                                        $resultFor .= DataHelper::escapeOutput($projectApplicationData->sorter->get());
                                        $applicationsCount = 1;
                                    } else {
                                        $resultFor .= '<i>' . $LOCALE['deleted_application'] . '</i>';
                                    }
                                    $checkFollowup = true;
                                    $gotSomeCharacter = true;
                                }

                                if ($checkFollowup) {
                                    $resultFor .= ' <sup><span ' . ($applicationsCount === 0 ? 'class="red"' : '') . '><span class="sbi sbi-file-filled"></span> ' . $applicationsCount . '</span></sup>, ';
                                }
                            }
                        }

                        if ($gotSomeCharacter) {
                            $result = '</b>' . $LOCALE['for'] . ' <b>' . mb_substr($resultFor, 0, mb_strlen($resultFor) - 2);
                        }
                        $gotSomeCharacter = false;
                        $resultAbout = '';

                        foreach ($applications2SideIds as $applications2SideId) {
                            if ($applications2SideId !== '') {
                                $checkFollowup = false;
                                $applicationsCount = 0;

                                if (str_contains($applications2SideId, 'group')) {
                                    if ($fullGroupData[str_replace('group', '', $applications2SideId)][0] ?? false) {
                                        $resultAbout .= $fullGroupData[str_replace('group', '', $applications2SideId)][0];
                                    } else {
                                        $resultAbout .= '<i>' . $LOCALE['deleted_group_2'] . '</i>';
                                    }
                                    $applicationsCount = $fullGroupData[str_replace('group', '', $applications2SideId)][1];
                                    $checkFollowup = true;
                                    $gotSomeCharacter = true;
                                } elseif (str_contains($applications2SideId, 'all')) {
                                    if ($fullCharacterData[str_replace('all', '', $applications2SideId)][0] ?? false) {
                                        $resultAbout .= $fullCharacterData[str_replace('all', '', $applications2SideId)][0];
                                    } elseif ($applications2SideId === 0) {
                                        $resultAbout .= '<i>' . $LOCALE['global_story_2'] . '</i>';
                                    } else {
                                        $resultAbout .= '<i>' . $LOCALE['deleted_character_2'] . '</i>';
                                    }
                                    $applicationsCount = $fullCharacterData[str_replace('all', '', $applications2SideId)][1];
                                    $checkFollowup = true;
                                    $gotSomeCharacter = true;
                                } elseif ($applications2SideId > 0) {
                                    $projectApplicationData = $this->applicationService->get(
                                        id: $applications2SideId,
                                        criteria: [
                                            'project_id' => $this->getActivatedProjectId(),
                                        ],
                                    );

                                    if ($projectApplicationData) {
                                        $resultAbout .= DataHelper::escapeOutput($projectApplicationData->sorter->get());
                                        $applicationsCount = 1;
                                    } else {
                                        $resultAbout .= '<i>' . $LOCALE['deleted_application_2'] . '</i>';
                                    }
                                    $checkFollowup = true;
                                    $gotSomeCharacter = true;
                                }

                                if ($checkFollowup) {
                                    $resultAbout .= ' <sup><span ' . ($applicationsCount === 0 ? 'class="red"' : '') . '><span class="sbi sbi-file-filled"></span> ' . $applicationsCount . '</span></sup>, ';
                                }
                            }
                        }

                        if ($gotSomeCharacter) {
                            $result .= '</b> ' . ($resultFor === '' ? TextHelper::mb_ucfirst(
                                $LOCALE['about'],
                            ) : $LOCALE['about']) . ' <b>' . mb_substr($resultAbout, 0, mb_strlen($resultAbout) - 2);
                        }
                        $result .= '</b>';
                    }

                    if ($plotData->todo->get()) {
                        $result .= ' <span class="sbi sbi-times" title="' . ($plotData instanceof PlotPlotModel ? $LOCALE['not_ready'] : $LOCALE['story_not_ready']) . ': ' . str_replace("\n", '<br>', $plotData->todo->get()) . '"></span>';
                    }
                    $plotFromTo[$plotData->id->getAsInt()] = [$plotData->id->getAsInt(), $result];
                }
            }

            $this->plotFromTo = $plotFromTo;
        }

        return $this->plotFromTo;
    }

    public function getResponsibleGamemasterIdDefault(): ?int
    {
        return CURRENT_USER->id();
    }

    public function getResponsibleGamemasterIdValues(): array
    {
        return $this->getGamemastersList();
    }

    public function getSearchGroupsByNameDefaultApplicationDefault(): int
    {
        return (int) ($_REQUEST['application_id'] ?? 0);
    }

    public function getSearchGroupsByNameDefaultApplicationContext(): array
    {
        if ((int) ($_REQUEST['application_id'] ?? 0) > 0) {
            $objectName = 'plot';

            return [
                $objectName . ':view',
                $objectName . ':create',
                $objectName . ':update',
                $objectName . ':embedded',
            ];
        }

        return [];
    }

    public function getSearchGroupsByNameDefaultCharacterDefault(): int
    {
        return (int) ($_REQUEST['character_id'] ?? 0);
    }

    public function getSearchGroupsByNameDefaultCharacterContext(): array
    {
        if ((int) ($_REQUEST['character_id'] ?? 0) > 0) {
            $objectName = 'plot';

            return [
                $objectName . ':view',
                $objectName . ':create',
                $objectName . ':update',
                $objectName . ':embedded',
            ];
        }

        return [];
    }

    public function getProjectCharacterIdsDefault(): array
    {
        return ($_REQUEST['project_character_ids'] ?? false) ? DataHelper::multiselectToArray($_REQUEST['project_character_ids']) : [];
    }

    public function getProjectCharacterIdsValues(): array
    {
        $groupService = $this->groupService;

        $LOCALE = $this->getLOCALE();
        $LOCALE_PLOT = LocaleHelper::getLocale(['plot', 'global']);

        $listOfGroupsCharacters = [];
        $listOfGroups = DB->getTreeOfItems(
            false,
            'project_group',
            'parent',
            null,
            ' AND project_id=' . $this->getActivatedProjectId(),
            'code, name',
            0,
            'id',
            'name',
            1000000,
        );

        if (count($listOfGroups) > 0) {
            foreach ($listOfGroups as $key => $value) {
                if ($value[0] > 0) {
                    $listOfGroups[$key][1] = $groupService->createGroupPath($key, $listOfGroups);
                    $listOfGroupsCharacters[] = [
                        'group' . $value[0],
                        '<span class="sbi sbi-users" title="' . $LOCALE['title_group'] . '"></span><a href="' . ABSOLUTE_PATH . '/group/' . $value[0] . '/" target="_blank" class="edit"></a>' . $listOfGroups[$key][1],
                        $value[2],
                        $value[3],
                    ];
                }
            }
        } else {
            $listOfGroupsCharacters = DB->getArrayOfItemsAsArray(
                'project_character WHERE project_id=' . $this->getActivatedProjectId() . ' ORDER BY name',
                'id',
                'name',
            );

            foreach ($listOfGroupsCharacters as $characterKey => $characterValue) {
                $listOfGroupsCharacters[$characterKey][1] = '<span class="sbi sbi-user" title="' . $LOCALE_PLOT['title_character'] . '"></span><a href="' . ABSOLUTE_PATH . '/character/' . $characterValue[0] . '/" target="_blank" class="edit"></a>' . $characterValue[1];
            }
        }

        return $listOfGroupsCharacters;
    }

    public function getPlotsDataDefault(): ?string
    {
        $id = DataHelper::getId();
        $id = $id === '' ? null : (int) $id;

        return $this->generateAllPlots($this->getActivatedProjectId(), '{story}', $id);
    }

    public function getParentDefaultForChild(): ?string
    {
        return ($_REQUEST['parent'] ?? false) ? (is_array($_REQUEST['parent']) ? $_REQUEST['parent'][0] : $_REQUEST['parent']) : null;
    }

    public function getParentValuesForChild(): Generator
    {
        return DB->getArrayOfItems('project_plot WHERE project_id=' . $this->getActivatedProjectId() . ' AND parent IS NULL ORDER BY name', 'id', 'name');
    }

    public function getApplicationsValuesForChild(): array
    {
        $applicationsList = [];
        $storyData = [];

        $parentId = $this->getParentDefaultForChild();

        if (is_null($parentId)) {
            $storyData = DB->query(
                'SELECT * FROM project_plot WHERE id IN (SELECT parent FROM project_plot WHERE project_id=:project_id AND id=:id)',
                [
                    ['project_id', $this->getActivatedProjectId()],
                    ['id', DataHelper::getId()],
                ],
                true,
            );
        } else {
            $storyData = DB->select(
                'project_plot',
                [
                    'id' => $parentId,
                ],
                true,
            );
        }

        if (($storyData['id'] ?? false) > 0) {
            $applicationsList = $this->getApplicationsListInPlot($storyData['id']);
        }

        if (count($applicationsList) === 0) {
            $applicationsList = [['hidden', '']];
        }

        return $applicationsList;
    }
}
