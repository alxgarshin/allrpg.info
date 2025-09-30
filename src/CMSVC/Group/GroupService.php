<?php

declare(strict_types=1);

namespace App\CMSVC\Group;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Character\CharacterService;
use App\CMSVC\Message\MessageService;
use App\CMSVC\Trait\{GetUpdatedAtCustomAsHTMLRendererTrait, ProjectDataTrait};
use App\Helper\{DateHelper, RightsHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Element\Attribute;
use Fraym\Entity\{PostChange, PostCreate, PreChange};
use Fraym\Enum\{ActEnum, ActionEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper, ResponseHelper};

/** @extends BaseService<GroupModel> */
#[Controller(GroupController::class)]
#[PostCreate]
#[PostChange]
#[PreChange]
class GroupService extends BaseService
{
    use GetUpdatedAtCustomAsHTMLRendererTrait;
    use ProjectDataTrait;

    private ?array $gamemastersList = null;
    private ?GroupModel $savedGroupData = null;

    /** Изменение последовательности персонажей в сетке ролей */
    public function changeCharacterCode(int $objId, int $groupId, int $afterObjId): array
    {
        $returnArr = [];

        /** @var CharacterService */
        $characterService = CMSVCHelper::getService('character');

        $characterData = $characterService->get($objId);
        $groupData = $this->get($groupId);

        if ($characterData->project_id->getAsInt() === $this->getActivatedProjectId() && $groupData->project_id->getAsInt() === $this->getActivatedProjectId()) {
            $newCode = 1;

            if ($afterObjId > 0) {
                $afterObjData = DB->select(
                    tableName: 'relation',
                    criteria: [
                        'obj_type_to' => '{group}',
                        'obj_type_from' => '{character}',
                        'type' => '{member}',
                        'obj_id_to' => $groupData->id->getAsInt(),
                        'obj_id_from' => $afterObjId,
                    ],
                    oneResult: true,
                );

                if ($afterObjId !== $objId) {
                    $newCode = (int) $afterObjData['comment'] + 1;
                } else {
                    $newCode = (int) $afterObjData['comment'];
                }
            }

            DB->update(
                tableName: 'relation',
                data: [
                    'comment' => $newCode,
                    'updated_at' => DateHelper::getNow(),
                ],
                criteria: [
                    'obj_type_to' => '{group}',
                    'obj_type_from' => '{character}',
                    'type' => '{member}',
                    'obj_id_to' => $groupData->id->getAsInt(),
                    'obj_id_from' => $characterData->id->getAsInt(),
                ],
            );

            /* перепрокладываем коды в верную последовательность у персонажей соответствующей группы */
            $code = 1;
            $rehashData = DB->select(
                tableName: 'relation',
                criteria: [
                    'obj_type_to' => '{group}',
                    'obj_type_from' => '{character}',
                    'type' => '{member}',
                    'obj_id_to' => $groupData->id->getAsInt(),
                ],
                order: [
                    'cast(comment AS unsigned)',
                    'updated_at DESC',
                ],
            );

            foreach ($rehashData as $rehashDataItem) {
                if ($code === $newCode) {
                    ++$code;
                }
                DB->update(
                    tableName: 'relation',
                    data: [
                        'comment' => $code,
                    ],
                    criteria: [
                        'id' => $rehashDataItem['id'],
                    ],
                );
                ++$code;
            }

            $returnArr = [
                'response' => 'success',
            ];
        }

        return $returnArr;
    }

    /** Изменение последовательности групп в сетке ролей */
    public function changeGroupCode(int $objId, string $level, int $afterObjId): array
    {
        /** @var CharacterService */
        $characterService = CMSVCHelper::getService('character');

        $returnArr = [];

        $groupData = $this->get($objId);

        if ($groupData->project_id->getAsInt() === $this->getActivatedProjectId()) {
            $afterObjData = false;

            if ($afterObjId > 0) {
                $afterObjData = DB->findObjectById($afterObjId, 'project_group');

                if ($afterObjData['project_id'] !== $this->getActivatedProjectId()) {
                    $afterObjData = false;
                }
            }

            /* вносим изменения */
            $rehashSiblingsOnParentId = false;
            $newCode = 0;

            if ($afterObjId === 0 || ($level === 'child' && $afterObjData)) {
                DB->update(
                    'project_group',
                    [
                        'parent' => $afterObjId,
                        'code' => 1,
                        'updated_at' => DateHelper::getNow(),
                    ],
                    [
                        'id' => $objId,
                    ],
                );
                $rehashSiblingsOnParentId = $afterObjId;
                $newCode = 1;
            } elseif ($level === 'sibling' && $afterObjData) {
                DB->update(
                    'project_group',
                    [
                        'parent' => (int) $afterObjData['parent'],
                        'code' => ((int) $afterObjData['code'] + 1),
                        'updated_at' => DateHelper::getNow(),
                    ],
                    [
                        'id' => $objId,
                    ],
                );
                $rehashSiblingsOnParentId = (int) $afterObjData['parent'];
                $newCode = ((int) $afterObjData['code'] + 1);
            }

            if ($rehashSiblingsOnParentId !== false) {
                /* все наследовавшие группы переносим на родителя, если только parent не остался тем же */
                if ((int) $groupData->parent->get() !== $rehashSiblingsOnParentId) {
                    DB->update(
                        'project_group',
                        [
                            'parent' => (int) $groupData->parent->get(),
                        ],
                        [
                            'parent' => $objId,
                        ],
                    );
                }

                /* все родительские группы меняем у персонажей группы, если только parent не остался тем же */
                if ((int) $groupData->parent->get() !== $rehashSiblingsOnParentId) {
                    $charactersId = [];
                    $charactersData = DB->select(
                        'project_character',
                        [
                            'project_id' => $this->getActivatedProjectId(),
                            ['project_group_ids', '-' . $groupData->id->getAsInt() . '-', [OperandEnum::LIKE]],
                        ],
                    );

                    foreach ($charactersData as $characterData) {
                        $charactersId[] = $characterData['id'];
                    }
                    $characterService->changeCharacterGroupParents(
                        $charactersId,
                        (int) $groupData->parent->get(),
                        $rehashSiblingsOnParentId,
                    );
                }

                /* перепрокладываем коды в верную последовательность у групп соответствующего parent'а */
                $code = 1;
                $rehashData = DB->select(
                    'project_group',
                    [
                        'project_id' => $this->getActivatedProjectId(),
                        'parent' => $rehashSiblingsOnParentId,
                        ['id', $objId, [OperandEnum::NOT_EQUAL]],
                    ],
                    false,
                    [
                        'cast(code AS unsigned)',
                        'updated_at DESC',
                    ],
                );

                foreach ($rehashData as $rehashDataItem) {
                    if ($code === $newCode) {
                        ++$code;
                    }
                    DB->update(
                        'project_group',
                        [
                            'code' => $code,
                        ],
                        [
                            'id' => $rehashDataItem['id'],
                        ],
                    );
                    ++$code;
                }

                $returnArr = [
                    'response' => 'success',
                ];
            }
        }

        return $returnArr;
    }

    /** Подтверждение запроса вступления в группу */
    public function confirmGroupRequest(int $objId): array
    {
        $LOCALE_APPLICATION = LocaleHelper::getLocale(['application', 'global']);
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $returnArr = [];

        $conversationMessageData = DB->select(
            'conversation_message',
            [
                'id' => $objId,
            ],
            true,
        );

        if ($conversationMessageData['id']) {
            /** @var ApplicationService */
            $applicationService = CMSVCHelper::getService('application');

            $messageAction = DataHelper::escapeOutput($conversationMessageData['message_action']);
            $messageActionData = DataHelper::escapeOutput($conversationMessageData['message_action_data']);

            preg_match('#{([^:]+):([^,]+),\s*resolved:(.*)}#', $messageActionData, $actionData);

            if (!isset($actionData[3])) {
                preg_match('#{([^:]+):([^,]+)}#', $messageActionData, $actionData);
            }

            $actionData[2] = (int) trim($actionData[2]);

            $conversationData = DB->findObjectById($conversationMessageData['conversation_id'], 'conversation');
            $applicationData = $applicationService->get($conversationData['obj_id']);

            if ($messageAction === '{request_group}' && $actionData[1] === 'project_group_id' && $actionData[2] > 0 && $conversationData['id'] > 0 && $applicationData && !isset($actionData[3])) {
                $applicationGroups = $applicationData->project_group_ids->get();
                $applicationGroups[] = $actionData[2];
                DB->update(
                    tableName: 'project_application',
                    data: [
                        'project_group_ids' => DataHelper::arrayToMultiselect(array_unique($applicationGroups)),
                    ],
                    criteria: [
                        'id' => $applicationData->id->getAsInt(),
                    ],
                );

                /** @var MessageService $messageService */
                $messageService = CMSVCHelper::getService('message');

                $message = $LOCALE_APPLICATION['messages']['group_request_accepted'];
                $messageService->newMessage(
                    $conversationMessageData['conversation_id'],
                    $message,
                    '',
                    [],
                    [],
                    [
                        'obj_type' => '{project_application_conversation}',
                        'obj_id' => $conversationMessageData['obj_id'],
                        'sub_obj_type' => '{to_player}',
                    ],
                    '',
                    '',
                    $objId,
                );

                $resolvedData = DB->select(
                    'conversation_message',
                    [
                        'parent' => $conversationMessageData['id'],
                    ],
                    true,
                    [
                        'created_at DESC',
                    ],
                );
                DB->update(
                    'conversation_message',
                    [
                        'message_action_data' => mb_substr($conversationMessageData['message_action_data'], 0, mb_strlen($conversationMessageData['message_action_data']) - 1) . ', resolved:' . $resolvedData['id'] . '}',
                    ],
                    [
                        'id' => $conversationMessageData['id'],
                    ],
                );

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE_APPLICATION['messages']['group_request_accepted'],
                    'response_data' => '<div class="done">' . $LOCALE_CONVERSATION['actions']['request_done'] . '</div>',
                    'response_group' => $actionData[2],
                ];
            }
        }

        return $returnArr;
    }

    /** Неподтверждение запроса вступления в группу */
    public function declineGroupRequest(int $objId): array
    {
        $LOCALE_APPLICATION = LocaleHelper::getLocale(['application', 'global']);
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $returnArr = [];

        $conversationMessageData = DB->select(
            'conversation_message',
            [
                'id' => $objId,
            ],
            true,
        );

        if ($conversationMessageData['id']) {
            /** @var ApplicationService */
            $applicationService = CMSVCHelper::getService('application');

            $messageAction = DataHelper::escapeOutput($conversationMessageData['message_action']);
            $messageActionData = DataHelper::escapeOutput($conversationMessageData['message_action_data']);

            preg_match('#{([^:]+):([^,]+),\s*resolved:(.*)}#', $messageActionData, $actionData);

            if (!isset($actionData[3])) {
                preg_match('#{([^:]+):([^,]+)}#', $messageActionData, $actionData);
            }

            $actionData[2] = (int) trim($actionData[2]);

            $conversationData = DB->findObjectById($conversationMessageData['conversation_id'], 'conversation');
            $applicationData = $applicationService->get($conversationData['obj_id']);

            if ($messageAction === '{request_group}' && $actionData[1] === 'project_group_id' && $actionData[2] > 0 && $conversationData['id'] > 0 && $applicationData && !isset($actionData[3])) {
                /** @var MessageService */
                $messageService = CMSVCHelper::getService('message');

                $message = $LOCALE_APPLICATION['messages']['group_request_declined'];
                $messageService->newMessage(
                    $conversationMessageData['conversation_id'],
                    $message,
                    '',
                    [],
                    [],
                    [
                        'obj_type' => '{project_application_conversation}',
                        'obj_id' => $conversationMessageData['obj_id'],
                        'sub_obj_type' => '{to_player}',
                    ],
                    '',
                    '',
                    $objId,
                );

                $resolvedData = DB->select(
                    'conversation_message',
                    [
                        'parent' => $conversationMessageData['id'],
                    ],
                    true,
                    [
                        'created_at DESC',
                    ],
                );
                DB->update(
                    'conversation_message',
                    [
                        'message_action_data' => mb_substr($conversationMessageData['message_action_data'], 0, mb_strlen($conversationMessageData['message_action_data']) - 1) . ', resolved:' . $resolvedData['id'] . '}',
                    ],
                    [
                        'id' => $conversationMessageData['id'],
                    ],
                );

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE_APPLICATION['messages']['group_request_declined'],
                    'response_data' => '<div class="done">' . $LOCALE_CONVERSATION['actions']['request_done'] . '</div>',
                ];
            }
        }

        return $returnArr;
    }

    /** Изменение у всех наследующих групп и состояших в них заявок ответственного мастера */
    public function changeResponsibleGamemasterId(
        int $groupId,
        int $oldResponsibleGamemasterId,
        int $newResponsibleGamemasterId,
    ): void {
        $projectGroupChilds = DB->select(
            'project_group',
            [
                'parent' => $groupId,
                'project_id' => $this->getActivatedProjectId(),
            ],
        );

        foreach ($projectGroupChilds as $projectGroupChild) {
            $this->changeResponsibleGamemasterId(
                $projectGroupChild['id'],
                $oldResponsibleGamemasterId,
                $newResponsibleGamemasterId,
            );
        }

        DB->update(
            tableName: 'project_group',
            data: [
                'responsible_gamemaster_id' => $newResponsibleGamemasterId,
            ],
            criteria: [
                'id' => $groupId,
                'responsible_gamemaster_id' => $oldResponsibleGamemasterId,
            ],
        );

        DB->update(
            tableName: 'project_application',
            data: [
                'responsible_gamemaster_id' => $newResponsibleGamemasterId,
            ],
            criteria: [
                'responsible_gamemaster_id' => $oldResponsibleGamemasterId,
                ['project_group_ids', $groupId, [OperandEnum::LIKE]],
                'project_id' => $this->getActivatedProjectId(),
            ],
        );
    }

    /** Получение полного списка групп, включая все родительские */
    public function getGroupsListWithParents(array $projectGroupsData, array $projectGroupIdsData): array
    {
        $projectGroupIds = [];

        if (count($projectGroupsData) > 0) {
            foreach ($projectGroupIdsData as $groupId => $value) {
                /* ищем данную группу в массиве */
                $foundGroupKey = false;

                foreach ($projectGroupsData as $groupKey => $groupData) {
                    if ($groupData[0] === $groupId) {
                        $foundGroupKey = $groupKey;
                        break;
                    }
                }

                if ($foundGroupKey !== false) {
                    $projectGroupIds = array_unique(
                        array_merge($projectGroupIds, $this->getGroupParents($foundGroupKey, $projectGroupsData)),
                    );
                }
            }
        }

        return $projectGroupIds;
    }

    /** Получение полного списка родителей группы */
    public function getGroupParents(int $groupKey, array $projectGroupsList, int $minLevel = 0): array
    {
        $groupIds = [];

        if (isset($projectGroupsList[$groupKey])) {
            $theLevel = $projectGroupsList[$groupKey][2];

            if ($theLevel > $minLevel && $groupKey > 0) {
                $parentKey = $groupKey - 1;
                $prevGroupData = $projectGroupsList[$parentKey];

                while ($prevGroupData[2] !== $theLevel - 1) {
                    --$parentKey;
                    $prevGroupData = $projectGroupsList[$parentKey];
                }
                $groupIds = $this->getGroupParents($parentKey, $projectGroupsList, $minLevel);
            }
            $groupIds[] = $projectGroupsList[$groupKey][0];
        }

        return $groupIds;
    }

    /** Получение списка групп по персонажу или по заявке */
    public function getListOfGroupsByCharacterOrApplication(int $objId, string $objType): array
    {
        $addGroupsList = [];

        if ($objId > 0) {
            if ($objType === 'application') {
                /** @var ApplicationService */
                $applicationService = CMSVCHelper::getService('application');

                $projectApplicationData = $applicationService->get($objId);
                $addGroupsList = $projectApplicationData->project_group_ids->get();
            } else {
                /** @var CharacterService */
                $characterService = CMSVCHelper::getService('character');

                $projectCharacterData = $characterService->get($objId);
                $addGroupsList = $projectCharacterData->project_group_ids->get();
            }
        }

        return [
            'response' => 'success',
            'response_data' => [
                'add' => $addGroupsList,
            ],
        ];
    }

    /** Получение наследующих групп */
    public function getChildGroups(?int $objId, int $groupId): array
    {
        $LOCALE = LocaleHelper::getLocale(['group', 'global']);

        $responseDataSelected = -1;
        $groupData = [];

        if ($groupId > 0) {
            // это уже созданная группа, нам бы определить ее код и передать сразу, какой пункт должен быть выбран
            $groupData = DB->findObjectById($groupId, 'project_group');
            $responseDataSelected = $groupData['code'];
        }

        $groupsList = [];
        $groupsList[-1] = $LOCALE['at_the_end'];

        $responseDataSelectedFound = false;
        $projectGroupChilds = DB->query(
            'SELECT * FROM project_group WHERE (parent=:parent' . (is_null($objId) ? ' OR parent IS NULL' : '') . ')' .
                ($groupId > 0 ? ' AND id != :group_id' : '') . ' AND project_id = :project_id',
            [
                ['parent', is_null($objId) ? 0 : $objId],
                ['group_id', $groupId],
                ['project_id', $this->getActivatedProjectId()],
            ],
        );

        foreach ($projectGroupChilds as $projectGroupChild) {
            $groupsList[$projectGroupChild['code'] - 1] = $LOCALE['before'] . DataHelper::escapeOutput(
                $projectGroupChild['name'],
            );

            if ($responseDataSelected === $projectGroupChild['code'] - 1) {
                $responseDataSelectedFound = true;
            }
        }

        if ($objId !== ($groupData['parent'] ?? false) || !$responseDataSelectedFound) {
            $responseDataSelected = -1;
        }

        return [
            'response' => 'success',
            'response_data' => $groupsList,
            'response_data_selected' => $responseDataSelected,
        ];
    }

    /** Получение ответственного мастера по id группы */
    public function getResponsibleGamemaster(?int $objId): array
    {
        if (!is_null($objId)) {
            $groupData = DB->findObjectById($objId, 'project_group');
            $returnArr = [
                'response' => 'success',
                'response_data' => $groupData['responsible_gamemaster_id'],
            ];
        } else {
            $returnArr = [
                'response' => 'success',
            ];
        }

        return $returnArr;
    }

    /** Формирование хлебных крошек для групп из массива групп */
    public function createGroupPath(int $groupKey, array $projectGroupsList, int $minLevel = 0): string
    {
        $path = '';

        if (isset($projectGroupsList[$groupKey])) {
            $theLevel = $projectGroupsList[$groupKey][2];

            if ($theLevel > $minLevel) {
                $parentKey = $groupKey - 1;
                $prevGroupData = $projectGroupsList[$parentKey] ?? [];

                while (($prevGroupData[2] ?? false) && $prevGroupData[2] !== $theLevel - 1) {
                    --$parentKey;
                    $prevGroupData = $projectGroupsList[$parentKey] ?? null;
                }

                if ($prevGroupData) {
                    $path = $prevGroupData[1] . ' &rarr; ';
                }
            }
            $path .= $projectGroupsList[$groupKey][1];
        }

        return $path;
    }

    /** Определение самой низкой по уровню группы */
    public static function getLowestGroup(array $projectGroupsDataById, array $projectGroupIds): int
    {
        $projectGroupId = 0;
        $highestLevel = -1;

        if (count($projectGroupIds) > 0) {
            foreach ($projectGroupIds as $projectGroupPossibleId) {
                if ($projectGroupsDataById[$projectGroupPossibleId] ?? false) {
                    $data = $projectGroupsDataById[$projectGroupPossibleId];

                    if ($data[1] > $highestLevel && (!isset($data[2]['rights']) || $data[2]['rights'] < 2)) {
                        $projectGroupId = (int) $projectGroupPossibleId;
                        $highestLevel = $projectGroupsDataById[$projectGroupPossibleId][1];
                    }
                }
            }
        }

        return $projectGroupId;
    }

    public function updateCode(array $successfulResultsIds): void
    {
        /** @var CharacterService $characterService */
        $characterService = CMSVCHelper::getService('character');

        $savedGroupData = $this->savedGroupData;

        $key = 0;

        foreach ($successfulResultsIds as $successfulResultsId) {
            $parent = (int) $_REQUEST['parent'][$key];

            if ($_REQUEST['code'][$key] === -1) {
                $highestCode = DB->query(
                    'SELECT code FROM project_group WHERE (parent=' . ($parent === 0 ? $parent . ' OR parent IS NULL' : $parent) . ') ORDER BY code DESC LIMIT 1',
                    [],
                    true,
                );
                DB->update(
                    'project_group',
                    [
                        'code' => (int) $highestCode['code'] + 1,
                    ],
                    [
                        'id' => $successfulResultsId,
                    ],
                );
            }

            $code = 1;
            $projectGroupsData = DB->query(
                'SELECT * FROM project_group WHERE project_id=:project_id AND (parent=:parent' . ($parent === 0 ? ' OR parent IS NULL' : '') . ') ORDER BY code, updated_at',
                [
                    ['project_id', $this->getActivatedProjectId()],
                    ['parent', $parent],
                ],
            );

            foreach ($projectGroupsData as $projectGroupData) {
                DB->update(
                    'project_group',
                    [
                        'code' => $code,
                    ],
                    [
                        'id' => $projectGroupData['id'],
                    ],
                );
                ++$code;
            }

            /** Если изменился родитель, меняем у всех персонажей родительские группы данной группы */
            if (!is_null($savedGroupData) && !is_null($savedGroupData->parent->get()) && $savedGroupData->parent->get() !== $parent) {
                $charactersId = [];
                $charactersData = DB->select(
                    'project_character',
                    [
                        'project_id' => $this->getActivatedProjectId(),
                        ['project_group_ids', '-' . $savedGroupData->id->getAsInt() . '-', [OperandEnum::LIKE]],
                    ],
                    false,
                    null,
                    null,
                    null,
                    false,
                    [
                        'id',
                    ],
                );

                foreach ($charactersData as $characterData) {
                    $charactersId[] = $characterData['id'];
                }
                $characterService->changeCharacterGroupParents($charactersId, (int) $savedGroupData['parent'], $parent);
            }

            /** Если изменился ответственный мастер, меняем у всех наследующих групп и заявок в них предыдущего ответственного мастера на нового */
            if (ACTION === ActionEnum::change) {
                $newResponsibleGamemasterId = (int) $_REQUEST['responsible_gamemaster_id'][$key];

                if ($savedGroupData->responsible_gamemaster_id->get() !== $newResponsibleGamemasterId) {
                    $this->changeResponsibleGamemasterId(
                        $savedGroupData->id->getAsInt(),
                        $savedGroupData->responsible_gamemaster_id->get(),
                        $newResponsibleGamemasterId,
                    );
                }
            }

            /** Изменение раздатки */
            $savedGroupData = $this->get($successfulResultsId, null, null, true);
            $distributedItemAutoset = $savedGroupData->distributed_item_autoset->get();

            if (count($distributedItemAutoset) > 0) {
                foreach ($distributedItemAutoset as $distributedItemAutosetId) {
                    DB->query(
                        "UPDATE project_application SET distributed_item_ids = CONCAT(distributed_item_ids, '" . $distributedItemAutosetId . "-') WHERE project_group_ids LIKE :project_group_ids AND project_id=:project_id AND distributed_item_ids NOT LIKE :distributed_item_ids",
                        [
                            ['project_group_ids', '%-' . $savedGroupData->id->getAsInt() . '-%'],
                            ['project_id', $this->getActivatedProjectId()],
                            ['distributed_item_ids', '%-' . $distributedItemAutosetId . '-%'],
                        ],
                    );
                }
            }

            ++$key;
        }
    }

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            DB->update(
                'project_group',
                [
                    'content' => '{menu}',
                ],
                [
                    'id' => $successfulResultsId,
                ],
            );
        }

        $this->updateCode($successfulResultsIds);
    }

    public function preChange(): void
    {
        if (is_null($this->savedGroupData) && DataHelper::getId()) {
            $this->savedGroupData = $this->get(DataHelper::getId());
        }

        $savedGroupData = $this->savedGroupData;

        if (!is_null($savedGroupData) && ($_REQUEST['disable_changes'][0] ?? false) === 'on' && $savedGroupData->disable_changes->get()) {
            $LOCALE = $this->getLOCALE();
            ResponseHelper::responseOneBlock('error', $LOCALE['messages']['disable_changes_active'], ['disable_changes[0]']);
        }
    }

    public function postChange(array $successfulResultsIds): void
    {
        $this->updateCode($successfulResultsIds);
    }

    public function getSortResponsibleGamemasterId(): array
    {
        $gamemastersList = $this->gamemastersList;

        if (is_null($gamemastersList)) {
            $userService = $this->getUserService();

            $gamemasters = RightsHelper::findByRights(
                ['{admin}', '{gamemaster}'],
                '{project}',
                $this->getActivatedProjectId(),
                '{user}',
                false,
            );

            $allusersDataSort = [];

            foreach ($gamemasters as $gamemaster) {
                $gamemastersList[$gamemaster] = [$gamemaster, $userService->showNameWithId($userService->get($gamemaster))];
                $allusersDataSort[$gamemaster] = mb_strtolower($userService->showNameWithId($userService->get($gamemaster)));
            }
            array_multisort($allusersDataSort, SORT_ASC, $gamemastersList);
        }

        if (is_null($gamemastersList)) {
            $gamemastersList = $this->gamemastersList = [];
        }

        return $gamemastersList;
    }

    public function getSortRights(): array
    {
        /** @var Attribute\Multiselect $rightsAttribute */
        $rightsAttribute = $this->getModel()->getElement('rights')->getAttribute();
        $rightsValues = $rightsAttribute->getValues();

        return [
            [0, '<span class="sbi sbi-eye green" title="' . $rightsValues[0][1] . '"></span>'],
            [1, '<span class="sbi sbi-info green" title="' . $rightsValues[1][1] . '"></span>'],
            [2, '<span class="sbi sbi-eye-striked red" title="' . $rightsValues[2][1] . '"></span>'],
            [3, '<span class="sbi sbi-times" title="' . $rightsValues[3][1] . '"></span>'],
        ];
    }

    public function getLinkToRolesDefault(): ?string
    {
        if ($this->getAct() === ActEnum::edit && DataHelper::getId() > 0) {
            $LOCALE = $this->getLOCALE();

            return '<a href="/roles/' . $this->getActivatedProjectId() . '/group/' . DataHelper::getId() . '/" target="_blank"><span class="sbi sbi-list"></span> ' . $LOCALE['link_to_roles_default'] . '</a>';
        }

        return null;
    }

    public function getParentDefault(): ?string
    {
        if ($this->getAct() === ActEnum::add) {
            return $_REQUEST['parent'] ?? null;
        }

        return null;
    }

    public function getParentValues(): array
    {
        return DB->getTreeOfItems(
            false,
            'project_group',
            'parent',
            null,
            (DataHelper::getId() > 0 ? ' AND id!=' . DataHelper::getId() : '') . ' AND project_id=' . $this->getActivatedProjectId(),
            'code, name',
            0,
            'id',
            'name',
            1000000,
        );
    }

    public function getCodeValues(): array
    {
        $LOCALE = $this->getLOCALE();

        return [[-1, $LOCALE['at_the_end']]];
    }

    public function getResponsibleGamemasterIdValues(): array
    {
        return $this->getSortResponsibleGamemasterId();
    }

    public function getDistributedItemAutosetValues(): array
    {
        return array_merge(
            [['hidden', '']],
            DB->getArrayOfItemsAsArray('resource WHERE project_id=' . $this->getActivatedProjectId() . " AND distributed_item='1'", 'id', 'name'),
        );
    }

    public function getDistributedItemAutosetLocked(): array
    {
        return ['hidden'];
    }

    public function getDistributedItemAutosetMultiselectCreatorUpdatedAt(): int|string
    {
        return DateHelper::getNow();
    }

    public function getDistributedItemAutosetMultiselectCreatorCreatedAt(): int|string
    {
        return DateHelper::getNow();
    }

    public function getDistributedItemAutosetMultiselectCreatorProjectId(): int|string
    {
        return $this->getActivatedProjectId();
    }

    public function getDistributedItemAutosetMultiselectCreatorCreatorId(): int|string|null
    {
        return CURRENT_USER->id();
    }

    public function getLinkToCharactersDefault(): string
    {
        $linkToCharacters = '';

        if (DataHelper::getId() > 0) {
            $characters = DB->query(
                "SELECT pc.* FROM project_character AS pc LEFT JOIN relation r ON r.obj_id_from=pc.id WHERE pc.project_id=:project_id  AND r.obj_id_to=:obj_id_to AND r.type='{member}' AND r.obj_type_from='{character}' AND r.obj_type_to='{group}' ORDER BY cast(r.comment AS unsigned), pc.name",
                [
                    ['project_id', $this->getActivatedProjectId()],
                    ['obj_id_to', DataHelper::getId()],
                ],
            );

            $i = 0;

            foreach ($characters as $characterData) {
                $linkToCharacters .= '<a href="/character/' . $characterData['id'] . '/" class="' . ($i % 2 === 0 ? 'string1' : 'string2') . '">' . DataHelper::escapeOutput($characterData['name']) . '</a>';
                ++$i;
            }

            if ($linkToCharacters === '') {
                $linkToCharacters = '<!---->';
            }
        }

        return $linkToCharacters;
    }
}
