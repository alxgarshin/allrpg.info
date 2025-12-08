<?php

declare(strict_types=1);

namespace App\CMSVC\Character;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Group\GroupService;
use App\CMSVC\Plot\PlotService;
use App\CMSVC\Trait\{GetUpdatedAtCustomAsHTMLRendererTrait, ProjectDataTrait, UserServiceTrait};
use App\Helper\{DateHelper, RightsHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\{PostChange, PostCreate, PreChange};
use Fraym\Enum\{ActEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper, ResponseHelper};

/** @extends BaseService<CharacterModel> */
#[Controller(CharacterController::class)]
#[PostCreate]
#[PostChange]
#[PreChange]
class CharacterService extends BaseService
{
    use GetUpdatedAtCustomAsHTMLRendererTrait;
    use ProjectDataTrait;
    use UserServiceTrait;

    private ?bool $freeView = null;
    private ?bool $differentGroupsInApplicationsView = null;
    private ?array $freeCharacters = null;
    private ?array $projectCharacterTakenCount = null;
    private ?array $differentGroupsInApplicationsCharacters = null;
    private ?bool $teamFieldsPresent = null;
    private ?array $projectGroupsData = null;
    /** @var CharacterModel[] */
    private ?array $preChangeData = null;

    public function getFreeView(): bool
    {
        if (is_null($this->freeView)) {
            $this->freeView = ($_REQUEST['free'] ?? false) === '1';
        }

        return $this->freeView;
    }

    public function getDifferentGroupsInApplicationsView(): bool
    {
        if (is_null($this->differentGroupsInApplicationsView)) {
            $this->differentGroupsInApplicationsView = ($_REQUEST['different_groups'] ?? false) === '1';
        }

        return $this->differentGroupsInApplicationsView;
    }

    public function getProjectsGroupsData(): array
    {
        if (is_null($this->projectGroupsData)) {
            /** @var GroupService */
            $groupService = CMSVCHelper::getService('group');

            $rightsValues = LocaleHelper::getLocale(['group', 'fraym_model', 'elements', 'rights', 'values']);
            $rightsValuesSymbols = [
                0 => '<span class="sbi sbi-eye green" title="' . $rightsValues[0][1] . '"></span>',
                1 => '<span class="sbi sbi-info green" title="' . $rightsValues[1][1] . '"></span>',
                2 => '<span class="sbi sbi-eye-striked red" title="' . $rightsValues[2][1] . '"></span>',
                3 => '<span class="sbi sbi-times" title="' . $rightsValues[3][1] . '"></span>',
            ];
            $projectGroupsData = DB->getTreeOfItems(
                false,
                'project_group',
                'parent',
                null,
                ' AND project_id=' . $this->getProjectData()->id->getAsInt(),
                'code, name',
                0,
                'id',
                'name',
                1000000,
                false,
            );

            foreach ($projectGroupsData as $key => $data) {
                $projectGroupsData[$key][1] = preg_replace(
                    '#<span(.*)</span>#',
                    '',
                    $groupService->createGroupPath($key, $projectGroupsData),
                );

                /** Добавляем указание видимости к названию */
                $projectGroupsData[$key][1] = $rightsValuesSymbols[$data[3]['rights']] . ' ' . $projectGroupsData[$key][1];
            }

            $this->projectGroupsData = $projectGroupsData;
        }

        return $this->projectGroupsData;
    }

    public function getTeamFieldsPresent(): bool
    {
        if (is_null($this->teamFieldsPresent)) {
            $teamFieldsPresentCount = DB->select(
                tableName: 'project_application_field',
                criteria: [
                    'application_type' => '1',
                    'project_id' => $this->getActivatedProjectId(),
                ],
                onlyCount: true,
            );
            $this->teamFieldsPresent = $teamFieldsPresentCount[0] > 0;
        }

        return $this->teamFieldsPresent;
    }

    public function getCharactersCounters(): void
    {
        /** @var ApplicationService */
        $applicationService = CMSVCHelper::getService('application');

        $projectCharacterTakenCount = [];
        $projectCharacterTakenCountSort = [];
        $freeCharacters = [];
        $differentGroupsInApplicationsCharacters = [];
        $takenCharacters = $this->getAll([
            'project_id' => $this->getActivatedProjectId(),
        ]);

        foreach ($takenCharacters as $takenCharacterData) {
            $takenCount = 0;
            $groupsValue = $takenCharacterData->project_group_ids->get();

            $applications = $applicationService->getAll(
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                    ['status', 4, [OperandEnum::NOT_EQUAL]],
                    'project_character_id' => $takenCharacterData->id->getAsInt(),
                    'deleted_by_gamemaster' => '0',
                    'deleted_by_player' => '0',
                ],
            );

            foreach ($applications as $applicationData) {
                if ($applicationData->status->get() === 3) {
                    ++$takenCount;
                }

                $applicationGroups = $applicationData->project_group_ids->get();

                foreach ($groupsValue as $groupId) {
                    if (!in_array($groupId, $applicationGroups)) {
                        $differentGroupsInApplicationsCharacters[] = $takenCharacterData->id->getAsInt();
                    }
                }

                foreach ($applicationGroups as $groupId) {
                    if (!in_array($groupId, $groupsValue)) {
                        $differentGroupsInApplicationsCharacters[] = $takenCharacterData->id->getAsInt();
                    }
                }
            }

            $takenData = $takenCharacterData->taken->get();

            if (!is_null($takenData)) {
                $taken = explode(',', trim($takenData));

                if ($taken[0] !== '') {
                    $takenCount += count($taken);
                }
            }

            $projectCharacterTakenCount[] = [
                $takenCharacterData->id->getAsInt(),
                (int) $takenCharacterData->applications_needed_count->get() . ' / ' . $takenCount . ' ' . ((int) $takenCharacterData->applications_needed_count->get() <= $takenCount ? '<span class="sbi sbi-check"></span>' : '<span class="sbi sbi-times"></span>'),
                ((int) $takenCharacterData->applications_needed_count->get() > $takenCount ? '1' : '0') . '_' . (int) $takenCharacterData->applications_needed_count->get() . '_' . $takenCount,
            ];

            if ($takenCount < (int) $takenCharacterData->applications_needed_count->get()) {
                $freeCharacters[] = $takenCharacterData->id->getAsInt();
            }
        }

        foreach ($projectCharacterTakenCount as $key => $row) {
            $projectCharacterTakenCountSort[$key] = mb_strtolower($row[2]);
        }
        array_multisort($projectCharacterTakenCountSort, SORT_ASC, $projectCharacterTakenCount);

        $this->freeCharacters = $freeCharacters;
        $this->projectCharacterTakenCount = $projectCharacterTakenCount;
        $this->differentGroupsInApplicationsCharacters = $differentGroupsInApplicationsCharacters;
    }

    public function getFreeCharacters(): array
    {
        if (is_null($this->freeCharacters)) {
            $this->getCharactersCounters();
        }

        return $this->freeCharacters;
    }

    public function getProjectCharacterTakenCount(): array
    {
        if (is_null($this->projectCharacterTakenCount)) {
            $this->getCharactersCounters();
        }

        return $this->projectCharacterTakenCount;
    }

    public function getDifferentGroupsInApplicationsCharacters(): array
    {
        if (is_null($this->differentGroupsInApplicationsCharacters)) {
            $this->getCharactersCounters();
        }

        return $this->differentGroupsInApplicationsCharacters;
    }

    /** Смена у персонажей родителей их родительской группы */
    public function changeCharacterGroupParents(array $charactersId, int $groupOldParentId, int $groupNewParentId): bool
    {
        if (count($charactersId) > 0 && $groupOldParentId !== $groupNewParentId) {
            /** Формируем список старых родительских групп группы */
            $oldParentGroupsIds = [
                $groupOldParentId,
            ];
            $groupOldParentData = DB->findObjectById($groupOldParentId, 'project_group');

            while ($groupOldParentData['parent'] > 0) {
                $oldParentGroupsIds[] = $groupOldParentData['parent'];
                $groupOldParentData = DB->findObjectById($groupOldParentData['parent'], 'project_group');
            }

            /** Массив, в который мы помещаем id других групп, для которых убираемые являются родительскими, чтобы не убрать случайно из параллельной группы родителя */
            $groupIsParentToOthers = [];

            foreach ($oldParentGroupsIds as $value) {
                $groupChilds = DB->select(
                    tableName: 'project_group',
                    criteria: [
                        'parent' => $value,
                    ],
                    fieldsSet: [
                        'id',
                    ],
                );

                foreach ($groupChilds as $groupChild) {
                    if (!in_array($groupChild['id'], $oldParentGroupsIds)) {
                        $groupIsParentToOthers[$value][] = $groupChild['id'];
                    }
                }
            }

            /** Формируем список новых родительских групп группы */
            $newParentGroupsIds = [
                $groupNewParentId,
            ];
            $groupNewParentData = DB->findObjectById($groupNewParentId, 'project_group');

            while ($groupNewParentData['parent'] > 0) {
                $newParentGroupsIds[] = $groupNewParentData['parent'];
                $groupNewParentData = DB->findObjectById($groupNewParentData['parent'], 'project_group');
            }

            /** @var ApplicationService */
            $applicationService = CMSVCHelper::getService('application');

            $charactersData = $this->getAll(
                criteria: [
                    'id' => $charactersId,
                ],
            );

            foreach ($charactersData as $characterData) {
                $characterGroups = array_unique($characterData->project_group_ids->get());

                $toDeleteInApplications = [];

                foreach ($oldParentGroupsIds as $value) {
                    if (($key = array_search($value, $characterGroups)) !== false) {
                        /** Дополнительно проверяем, не является ли эта группа родительской для какой-нибудь другой у данного персонажа */
                        $noParallelGroups = true;

                        if (isset($groupIsParentToOthers[$value])) {
                            foreach ($characterGroups as $possibleChildGroup) {
                                if (in_array($possibleChildGroup, $groupIsParentToOthers[$value])) {
                                    $noParallelGroups = false;
                                }
                            }
                        }

                        if ($noParallelGroups) {
                            unset($characterGroups[$key]);
                            RightsHelper::deleteRights(
                                '{member}',
                                '{group}',
                                $value,
                                '{character}',
                                $characterData->id->getAsInt(),
                            );
                            $toDeleteInApplications[] = $value;
                        }
                    }
                }

                foreach ($newParentGroupsIds as $value) {
                    $code = 1;
                    $lastCharacterInGroup = DB->select(
                        tableName: 'relation',
                        criteria: [
                            'obj_type_from' => '{character}',
                            'obj_type_to' => '{group}',
                            'type' => '{member}',
                            'obj_id_to' => $value,
                        ],
                        oneResult: true,
                        order: [
                            'comment DESC',
                        ],
                    );

                    if ((int) $lastCharacterInGroup['comment'] > 0) {
                        $code = (int) $lastCharacterInGroup['comment'] + 1;
                    }
                    RightsHelper::addRights(
                        '{member}',
                        '{group}',
                        $value,
                        '{character}',
                        $characterData->id->getAsInt(),
                        (string) $code,
                    );
                    $characterGroups[] = $value;
                }
                DB->update(
                    tableName: 'project_character',
                    data: [
                        'project_group_ids' => DataHelper::arrayToMultiselect($characterGroups),
                    ],
                    criteria: [
                        'id' => $characterData->id->getAsInt(),
                    ],
                );

                $applicationsData = $applicationService->getAll(
                    criteria: [
                        'project_character_id' => $characterData->id->getAsInt(),
                    ],
                );

                foreach ($applicationsData as $applicationData) {
                    $applicationGroups = $applicationData->project_group_ids->get();

                    foreach ($toDeleteInApplications as $value) {
                        foreach ($applicationGroups as $key => $applicationGroup) {
                            if ($applicationGroup === $value) {
                                unset($applicationGroups[$key]);
                                RightsHelper::deleteRights(
                                    '{member}',
                                    '{group}',
                                    $applicationGroup,
                                    '{application}',
                                    $applicationData->id->getAsInt(),
                                );
                            }
                        }
                    }

                    $applicationGroups = array_unique(array_merge($applicationGroups, $newParentGroupsIds));

                    foreach ($newParentGroupsIds as $newValue) {
                        RightsHelper::addRights(
                            '{member}',
                            '{group}',
                            $newValue,
                            '{application}',
                            $applicationData->id->getAsInt(),
                        );
                    }

                    DB->update(
                        tableName: 'project_application',
                        data: [
                            'project_group_ids' => DataHelper::arrayToMultiselect($applicationGroups),
                        ],
                        criteria: [
                            'id' => $applicationData['id'],
                        ],
                    );
                }
            }
        }

        return true;
    }

    public function setParentGroups(int|string $id, int $key): void
    {
        if (count($this->getProjectsGroupsData()) > 0) {
            /** @var GroupService */
            $groupService = CMSVCHelper::getService('group');

            $projectGroupIds = $groupService->getGroupsListWithParents($this->getProjectsGroupsData(), $_REQUEST['project_group_ids'][$key] ?? []);
            DB->update(
                tableName: 'project_character',
                data: [
                    'project_group_ids' => DataHelper::arrayToMultiselect($projectGroupIds),
                ],
                criteria: [
                    'id' => $id,
                ],
            );
        }
    }

    public function updateGroupsInRelation(int|string $id): void
    {
        /** Апдейтим записи в relation, если есть. Если нет, создаем и выставляем самый низкий код в каждой группе. */
        $projectCharacterData = $this->get($id, null, null, true, false);
        $characterGroups = $projectCharacterData->project_group_ids->get();

        if (count($characterGroups) > 0) {
            /** Удаляем связи со всеми группами, которых более нет у нас */
            RightsHelper::deleteRights(
                '{member}',
                '{group}',
                null,
                '{character}',
                $id,
                ' AND obj_id_to NOT IN (' . implode(',', $characterGroups) . ')',
            );

            /** Добавляем связи с нужным code */
            foreach ($characterGroups as $characterGroup) {
                if (RightsHelper::checkRights('{member}', '{group}', $characterGroup, '{character}', $id)) {
                    // мы уже есть в группе, не трогаем ничего
                } else {
                    $code = 1;
                    $lastCharacterInGroup = DB->select(
                        tableName: 'relation',
                        criteria: [
                            'obj_type_from' => '{character}',
                            'obj_type_to' => '{group}',
                            'type' => '{member}',
                            'obj_id_to' => $characterGroup,
                        ],
                        oneResult: true,
                        order: [
                            'comment DESC',
                        ],
                    );

                    if ($lastCharacterInGroup && (int) $lastCharacterInGroup['comment'] > 0) {
                        $code = (int) $lastCharacterInGroup['comment'] + 1;
                    }

                    RightsHelper::addRights(
                        '{member}',
                        '{group}',
                        $characterGroup,
                        '{character}',
                        $id,
                        (string) $code,
                    );
                }
            }
        } else {
            RightsHelper::deleteRights('{member}', '{group}', null, '{character}', $id);
        }
    }

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            $key = 0;

            if (($_REQUEST['setparentgroups'][$key] ?? false) === 'on') {
                $this->setParentGroups($successfulResultsId, $key);
            }

            $this->updateGroupsInRelation($successfulResultsId);
        }
    }

    public function preChange(): void
    {
        foreach ($_REQUEST['id'] as $id) {
            $id = (int) $id;
            $this->preChangeData[$id] = $this->get($id);
        }
    }

    public function postChange(array $successfulResultsIds): void
    {
        /** @var ApplicationService */
        $applicationService = CMSVCHelper::getService('application');

        foreach ($successfulResultsIds as $successfulResultsId) {
            $oldProjectCharacterData = $this->preChangeData[$successfulResultsId] ?? false;
            $oldProjectCharacterDataGroups = $oldProjectCharacterData->project_group_ids->get();

            $idKey = array_search($successfulResultsId, $_REQUEST['id']);

            if (($_REQUEST['setparentgroups'][$idKey] ?? false) === 'on') {
                $this->setParentGroups($successfulResultsId, $idKey);
            }

            $this->updateGroupsInRelation($successfulResultsId);

            /** По возможности меняем в заявках группы на корректные */
            $newProjectCharacterData = $this->get($successfulResultsId, null, null, true);
            $newProjectCharacterDataGroups = $newProjectCharacterData->project_group_ids->get();

            if ($oldProjectCharacterDataGroups !== $newProjectCharacterDataGroups) {
                $applicationsData = $applicationService->getAll(
                    criteria: [
                        'project_character_id' => $successfulResultsId,
                        'project_id' => RightsHelper::getActivatedProjectId(),
                    ],
                );

                foreach ($applicationsData as $applicationData) {
                    $applicationGroups = $applicationData->project_group_ids->get();

                    if (count($applicationGroups) > 0) {
                        foreach ($applicationGroups as $key => $value) {
                            foreach ($oldProjectCharacterDataGroups as $oldValue) {
                                if ($oldValue === $value) {
                                    unset($applicationGroups[$key]);
                                    RightsHelper::deleteRights(
                                        '{member}',
                                        '{group}',
                                        $value,
                                        '{application}',
                                        $applicationData->id->getAsInt(),
                                    );
                                }
                            }
                        }
                    }

                    $applicationGroups = array_unique(array_merge($applicationGroups, $newProjectCharacterDataGroups));

                    foreach ($newProjectCharacterDataGroups as $newValue) {
                        RightsHelper::addRights(
                            '{member}',
                            '{group}',
                            $newValue,
                            '{application}',
                            $applicationData->id->getAsInt(),
                        );
                    }

                    DB->update(
                        tableName: 'project_application',
                        data: [
                            'project_group_ids' => DataHelper::arrayToMultiselect($applicationGroups),
                        ],
                        criteria: [
                            'id' => $applicationData['id'],
                        ],
                    );
                }

                /** Если изменился набор групп из-за setParentGroups обновляем страницу */
                if (count($newProjectCharacterDataGroups) !== count($_REQUEST['project_group_ids'][$idKey])) {
                    ResponseHelper::response([['success', $this->entity->getObjectMessages($this->entity)[1]]], '/' . KIND . '/' . $successfulResultsId . '/');
                }
            }
        }
    }

    public function getSortId(): array
    {
        return $this->getProjectCharacterTakenCount();
    }

    public function checkRightsViewRestrict(): string
    {
        return 'project_id=' . $this->getActivatedProjectId() . ($this->freeView ? (count($this->getFreeCharacters()) > 0 ? ' AND id IN (' . implode(',', $this->getFreeCharacters()) . ')' : ' AND id=0') : '') . ($this->differentGroupsInApplicationsView ? (count($this->getDifferentGroupsInApplicationsCharacters()) > 0 ? ' AND id IN (' . implode(',', $this->getDifferentGroupsInApplicationsCharacters()) . ')' : ' AND id=0') : '');
    }

    public function getLinkToRolesDefault(): string
    {
        if ($this->act === ActEnum::edit && DataHelper::getId() > 0) {
            $LOCALE = $this->LOCALE;

            return '<a href="/roles/' . $this->getActivatedProjectId() . '/character/' . DataHelper::getId() . '/" target="_blank"><span class="sbi sbi-list"></span> ' . $LOCALE['link_to_roles_default'] . '</a>';
        }

        return '';
    }

    public function getProjectGroupIdsDefault(): ?array
    {
        if ($this->act === ActEnum::add) {
            return ($_REQUEST['project_group_ids'] ?? false) ? DataHelper::multiselectToArray($_REQUEST['project_group_ids']) : null;
        }

        return null;
    }

    public function getProjectGroupIdsValues(): array
    {
        return $this->getProjectsGroupsData();
    }

    public function getProjectGroupIdsMultiselectCreatorUpdatedAt(): int|string
    {
        return DateHelper::getNow();
    }

    public function getProjectGroupIdsMultiselectCreatorCreatedAt(): int|string
    {
        return DateHelper::getNow();
    }

    public function getProjectGroupIdsMultiselectCreatorProjectId(): int|string
    {
        return $this->getActivatedProjectId();
    }

    public function getTeamCharacterContext(): array
    {
        if ($this->getTeamFieldsPresent()) {
            return ['character:view', 'character:create', 'character:update'];
        }

        return [];
    }

    public function getTakenContext(): array
    {
        if ($this->getTeamFieldsPresent()) {
            return ['character:view', 'character:create', 'character:update'];
        }

        return [];
    }

    public function getTakenDetails(): string
    {
        foreach ($this->getProjectCharacterTakenCount() as $value) {
            if ($value[0] === DataHelper::getId()) {
                return $value[1];
            }
        }

        return '';
    }

    public function getPlotsDataDefault(): ?string
    {
        /** @var PlotService */
        $plotService = CMSVCHelper::getService('plot');

        return $plotService->generateAllPlots($this->getActivatedProjectId(), '{character}', (int) DataHelper::getId());
    }

    public function getLinkToApplicationsDefault(): ?string
    {
        if (DataHelper::getId() > 0) {
            $linkToApplications = '';
            $i = 0;

            $LOCALE_STATUS = LocaleHelper::getLOCALE(['application', 'fraym_model', 'elements', 'status', 'values']);

            $userService = $this->getUserService();

            $applications = DB->query(
                "SELECT pa.*, u.*, pa.id AS application_id, pa.status as application_status FROM project_application AS pa LEFT JOIN user AS u ON u.id=pa.creator_id WHERE pa.project_id=:project_id  AND pa.project_character_id=:project_character_id AND pa.deleted_by_gamemaster='0' AND pa.deleted_by_player='0' AND pa.status != 4 ORDER BY pa.sorter",
                [
                    ['project_id', $this->getActivatedProjectId()],
                    ['project_character_id', DataHelper::getId()],
                ],
            );

            foreach ($applications as $applicationData) {
                $linkToApplications .= '<a href="/application/' . $applicationData['application_id'] . '/" class="' . ($i % 2 === 0 ? 'string1' : 'string2') . '"><span class="application_name">' . DataHelper::escapeOutput($applicationData['sorter']) . '</span><span class="application_status">' . $LOCALE_STATUS[$applicationData['application_status'] - 1][1] . '</span><span class="user_name">' . $userService->showNameWithId($userService->arrayToModel($applicationData)) . '</span></a>';
                ++$i;
            }

            if ($linkToApplications === '') {
                $linkToApplications = '<!---->';
            }

            return $linkToApplications;
        }

        return null;
    }
}
