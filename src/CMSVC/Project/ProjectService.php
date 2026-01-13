<?php

declare(strict_types=1);

namespace App\CMSVC\Project;

use App\CMSVC\Application\{ApplicationModel, ApplicationService};
use App\CMSVC\Conversation\ConversationService;
use App\CMSVC\Trait\UserServiceTrait;
use App\Helper\{DesignHelper, FileHelper, TextHelper, UniversalHelper};
use DateTimeImmutable;
use Fraym\BaseObject\{BaseModel, BaseService, Controller};
use Fraym\Entity\{PostChange, PostCreate, PostDelete};
use Fraym\Enum\{ActEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper, ResponseHelper, RightsHelper};
use Generator;

/** @extends BaseService<ProjectModel> */
#[Controller(ProjectController::class)]
#[PostCreate]
#[PostChange]
#[PostDelete]
class ProjectService extends BaseService
{
    use UserServiceTrait;

    private int|string|null $objId = null;
    private ?string $objType = null;

    /** Открытие / закрытие подачи заявок на проект */
    public function switchProjectStatus(int $objId): array
    {
        $LOCALE = LocaleHelper::getLocale(['project', 'global']);

        $returnArr = [];

        $projectId = $objId > 0 ? $objId : CookieHelper::getCookie('project_id');
        $projectData = DB->findObjectById($projectId, 'project');
        $setStatus = '1';

        if ($projectData['status'] === '1') {
            $setStatus = '0';
        }

        if ($projectData['id'] > 0) {
            DB->update(
                tableName: 'project',
                data: [
                    'status' => $setStatus,
                ],
                criteria: [
                    'id' => $projectId,
                ],
            );

            $returnArr = [
                'response' => 'success',
                'response_text' => $LOCALE['messages']['project_status_' . ($setStatus === '1' ? 'on' : 'off')],
                'response_data' => $LOCALE['switch_status_to_' . ($setStatus === '1' ? 'off' : 'on')],
            ];
        }

        return $returnArr;
    }

    /** Получение списка пользователей проекта или группы */
    public function getCommunityOrProjectMembersList(string $objType, ?int $objId): array
    {
        $userService = $this->getUserService();

        $returnArr = [];

        $colleaguesOnly = false;

        if ($objType === '') {
            $colleaguesOnly = true;
            $objId = 0;
        } elseif (preg_match('#project_(\d+)#', $objType, $match)) {
            $objType = 'project';
            $objId = $match[1];
            $colleaguesOnly = true;
        } elseif (preg_match('#community_(\d+)#', $objType, $match)) {
            $objType = 'community';
            $objId = $match[1];
            $colleaguesOnly = true;
        }

        if ($objId > 0) {
            if (RightsHelper::checkAnyRights(DataHelper::addBraces($objType), $objId)) {
                /* для allrpg поиск сделан исключительно по всяким ответственным, а не просто участникам */
                $members = RightsHelper::findByRights(
                    [
                        '{admin}',
                        '{gamemaster}',
                        '{moderator}',
                        '{responsible}',
                        '{budget}',
                        '{fee}',
                        '{newsmaker}',
                    ],
                    DataHelper::addBraces($objType),
                    $objId,
                    '{user}',
                    false,
                );
                $membersData = [];

                if ($colleaguesOnly) {
                    $colleagues = RightsHelper::findByRights('{friend}', '{user}');
                } else {
                    $colleagues = [];
                }

                if ($members) {
                    foreach ($members as $memberId) {
                        if ($colleaguesOnly && is_array($colleagues) && in_array(
                            $memberId,
                            $colleagues,
                        ) && $memberId !== CURRENT_USER->id()) {
                            $membersData[] = [
                                $memberId,
                                $userService->showName($userService->get($memberId)),
                                'other',
                                $userService->photoUrl($userService->get($memberId), true),
                            ];
                        } elseif (!$colleaguesOnly) {
                            $membersData[] = [
                                $memberId,
                                $userService->showName($userService->get($memberId)),
                                $memberId === CURRENT_USER->id() ? 'me' : 'other',
                            ];
                        }
                    }
                    $membersDataSort = [];

                    foreach ($membersData as $key => $row) {
                        $membersDataSort[$key] = $row[1];
                    }
                    array_multisort($membersDataSort, SORT_ASC, $membersData);
                }

                $returnArr = ['response' => 'success', 'response_data' => $membersData];
            }
        } elseif ($colleaguesOnly) {
            $members = RightsHelper::findByRights('{friend}', '{user}');
            $membersData = [];

            if ($members) {
                foreach ($members as $memberId) {
                    if ($memberId !== CURRENT_USER->id()) {
                        $membersData[] = [
                            $memberId,
                            $userService->showName($userService->get($memberId)),
                            'other',
                            $userService->photoUrl($userService->get($memberId), true),
                        ];
                    }
                }
                $membersDataSort = [];

                foreach ($membersData as $key => $row) {
                    $membersDataSort[$key] = $row[1];
                }
                array_multisort($membersDataSort, SORT_ASC, $membersData);
            }

            $returnArr = ['response' => 'success', 'response_data' => $membersData];
        } else {
            $returnArr = [
                'response' => 'success',
                'response_data' => [
                    [
                        CURRENT_USER->id(),
                        $userService->showNameExtended($userService->get(CURRENT_USER->id()), true),
                        'me',
                    ],
                ],
            ];
        }

        return $returnArr;
    }

    /** Получение списка задач проекта или группы */
    public function getCommunityOrProjectTasksList(?int $objId, string $objType, ?int $taskId): array
    {
        $parentTasksData = [];

        $parentTaskId = false;
        $followingTaskId = false;
        $childTaskIds = false;
        $pregoingTaskIds = false;

        if ($taskId > 0) {
            $parentTaskId = RightsHelper::findOneByRights('{child}', '{task}', null, '{task}', $taskId);
            $followingTaskId = RightsHelper::findOneByRights('{following}', '{task}', null, '{task}', $taskId);

            $childTaskIds = RightsHelper::findByRights('{child}', '{task}', $taskId, '{task}', false);
            $pregoingTaskIds = RightsHelper::findByRights('{following}', '{task}', $taskId, '{task}', false);
        }

        if ($objId > 0) {
            if (RightsHelper::checkAnyRights(DataHelper::addBraces($objType), $objId)) {
                $parentTasks = RightsHelper::findByRights('{child}', DataHelper::addBraces($objType), $objId, '{task}');

                if ($parentTasks) {
                    $parentTaskData = DB->getArrayOfItems(
                        "task_and_event WHERE ((status!='{closed}' AND status!='{rejected}' AND id IN(" . implode(
                            ',',
                            $parentTasks,
                        ) . '))' . ($parentTaskId ? ' OR id=' . $parentTaskId : '') . ') ' . ($taskId > 0 ? ' AND id!=' . $taskId : '') . ($childTaskIds ? ' AND id NOT IN (' . implode(
                            ',',
                            $childTaskIds,
                        ) . ')' : '') . ' ORDER BY name',
                        'id',
                        'name',
                    );

                    foreach ($parentTaskData as $key => $value) {
                        $parentTasksData['parent_task'][$key] = [$value[0], DataHelper::escapeOutput($value[1])];
                    }

                    $parentTaskData = DB->getArrayOfItems(
                        "task_and_event WHERE ((status!='{closed}' AND status!='{rejected}' AND id IN(" . implode(
                            ',',
                            $parentTasks,
                        ) . '))' . ($followingTaskId ? ' OR id=' . $followingTaskId : '') . ') ' . ($taskId > 0 ? ' AND id!=' . $taskId : '') . ($pregoingTaskIds ? ' AND id NOT IN (' . implode(
                            ',',
                            $pregoingTaskIds,
                        ) . ')' : '') . ($childTaskIds ? ' AND id NOT IN (' . implode(
                            ',',
                            $childTaskIds,
                        ) . ')' : '') . ' ORDER BY name',
                        'id',
                        'name',
                    );

                    foreach ($parentTaskData as $key => $value) {
                        $parentTasksData['following_task'][$key] = [$value[0], DataHelper::escapeOutput($value[1])];
                    }
                }
            }
        } else {
            $data = DB->query(
                "SELECT DISTINCT te.id, te.name FROM task_and_event te LEFT JOIN relation r ON te.id=r.obj_id_from AND (r.obj_type_to='{project}' OR r.obj_type_to='{community}') AND r.obj_type_from='{task}' AND r.type='{child}' LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from WHERE r.obj_id_to IS NULL AND r2.type IN ('{member}','{admin}','{responsible}') AND ((te.status!='{closed}' AND te.status!='{rejected}')" . ($parentTaskId ? ' OR te.id=:parent_task_id' : '') . ') ' . ($taskId > 0 ? ' AND te.id!=:task_id' : '') . ($childTaskIds ? ' AND te.id NOT IN (:child_task_ids)' : '') . ' ORDER BY te.name',
                [
                    ['obj_id_from', CURRENT_USER->id()],
                    ['parent_task_id', $parentTaskId],
                    ['task_id', $taskId],
                    ['child_task_ids', $childTaskIds],
                ],
            );

            foreach ($data as $taskData) {
                $parentTasksData['parent_task'][$taskData['id']] = [
                    $taskData['id'],
                    DataHelper::escapeOutput($taskData['name']),
                ];
            }

            $data = DB->query(
                "SELECT DISTINCT te.id, te.name FROM task_and_event te LEFT JOIN relation r ON te.id=r.obj_id_from AND (r.obj_type_to='{project}' OR r.obj_type_to='{community}') AND r.obj_type_from='{task}' AND r.type='{child}' LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from WHERE r.obj_id_to IS NULL AND r2.type IN ('{member}','{admin}','{responsible}') AND ((te.status!='{closed}' AND te.status!='{rejected}')" . ($parentTaskId ? ' OR te.id=:parent_task_id' : '') . ') ' . ($taskId > 0 ? ' AND te.id!=:task_id' : '') . ($pregoingTaskIds ? ' AND te.id NOT IN (:pregoing_task_ids)' : '') . ($childTaskIds ? ' AND te.id NOT IN (:child_task_ids)' : '') . ' ORDER BY te.name',
                [
                    ['obj_id_from', CURRENT_USER->id()],
                    ['parent_task_id', $parentTaskId],
                    ['task_id', $taskId],
                    ['pregoing_task_ids', $pregoingTaskIds],
                    ['child_task_ids', $childTaskIds],
                ],
            );

            foreach ($data as $taskData) {
                $parentTasksData['following_task'][$taskData['id']] = [
                    $taskData['id'],
                    DataHelper::escapeOutput($taskData['name']),
                ];
            }
        }

        return ['response' => 'success', 'response_data' => $parentTasksData];
    }

    /** Получение списка проектов и групп */
    public function loadProjectsCommunitiesList(string $objType, int $limit, ?string $searchString): array
    {
        $LOCALE = LocaleHelper::getLocale(['global']);

        $returnArr = [];

        if (DataHelper::clearBraces($objType) === 'project' || DataHelper::clearBraces($objType) === 'community') {
            if (REQUEST_TYPE->isApiRequest()) {
                $responseData = [];

                if (DataHelper::clearBraces($objType) === 'community') {
                    $descriptionField = 'description';
                } else {
                    $descriptionField = 'annotation';
                }
                $responseData['commands']['add'] = (CURRENT_USER->isLogged() ? 'true' : 'false');

                $responseData[DataHelper::clearBraces($objType)] = [
                    'mine' => [],
                    'finished' => [],
                    'all' => [],
                ];

                if (DataHelper::clearBraces($objType) === 'community') {
                    unset($responseData[DataHelper::clearBraces($objType)]['finished']);
                }

                if ($searchString !== '') {
                    $allObjectsData = DB->query(
                        'SELECT * FROM ' . DataHelper::clearBraces($objType) . ' WHERE (LOWER(name) LIKE :search_string OR ' . $descriptionField . ' LIKE :search_string) ORDER BY name',
                        [
                            ['search_string', mb_strtolower('"%' . $searchString . '%"')],
                        ],
                    );
                    unset($responseData[DataHelper::clearBraces($objType)]['mine']);
                    unset($responseData[DataHelper::clearBraces($objType)]['finished']);
                } else {
                    $allObjectsData = DB->select(
                        tableName: DataHelper::clearBraces($objType),
                        order: [
                            'id DESC',
                        ],
                        limit: 12,
                        offset: $limit,
                    );

                    if ($limit === 0) {
                        $myObjects = RightsHelper::findByRights(null, DataHelper::addBraces($objType));

                        if ($myObjects) {
                            $myObjectsDataSort = [];
                            $myObjectsDataSort2 = [];
                            $myObjectsData = iterator_to_array(DB->findObjectsByIds($myObjects, DataHelper::clearBraces($objType)));

                            foreach ($myObjectsData as $key => $myObjectData) {
                                if ($myObjectData['id'] !== '') {
                                    $myObjectEvents = UniversalHelper::checkForUpdates(
                                        DataHelper::addBraces($objType),
                                        (int) $myObjectData['id'],
                                    );
                                    $myObjectsData[$key]['new_count'] = $myObjectEvents;
                                    $myObjectsDataSort[$key] = $myObjectsData[$key]['new_count'];
                                    $myObjectsDataSort2[$key] = DataHelper::escapeOutput($myObjectData['name']);
                                } else {
                                    unset($myObjectsData[$key]);
                                }
                            }

                            if (count($myObjectsData) > 0) {
                                array_multisort(
                                    $myObjectsDataSort,
                                    SORT_DESC,
                                    $myObjectsDataSort2,
                                    SORT_ASC,
                                    $myObjectsData,
                                );
                            }

                            foreach ($myObjectsData as $myObjectData) {
                                $membersCount = count(
                                    array_unique(
                                        RightsHelper::findByRights(
                                            null,
                                            DataHelper::addBraces($objType),
                                            $myObjectData['id'],
                                            '{user}',
                                            false,
                                        ),
                                    ),
                                );

                                $preparedData = [
                                    DataHelper::clearBraces($objType) . '_id' => $myObjectData['id'],
                                    'link' => ABSOLUTE_PATH . '/' . DataHelper::clearBraces($objType) . '/' . $myObjectData['id'] . '/',
                                    'avatar' => (FileHelper::getImagePath($myObjectData['attachments'], 9) ??
                                        ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_' . DataHelper::clearBraces($objType) . '.svg'),
                                    'name' => DataHelper::escapeOutput($myObjectData['name']),
                                    'new_events' => (string) $myObjectData['new_count'],
                                    'description' => TextHelper::cutStringToLimit($myObjectData[$descriptionField], 255, true),
                                    'members_count' => (string) $membersCount,
                                ];

                                if (strtotime($myObjectData['date_to']) >= strtotime('today') || DataHelper::clearBraces($objType) === 'community') {
                                    $responseData[DataHelper::clearBraces($objType)]['mine'][$myObjectData['id']] = $preparedData;
                                } else {
                                    $responseData[DataHelper::clearBraces($objType)]['finished'][$myObjectData['id']] = $preparedData;
                                }
                            }
                        }
                    } else {
                        unset($responseData[DataHelper::clearBraces($objType)]['mine']);
                        unset($responseData[DataHelper::clearBraces($objType)]['finished']);
                    }
                }

                foreach ($allObjectsData as $allObjectData) {
                    $membersCount = count(
                        array_unique(
                            RightsHelper::findByRights(null, DataHelper::addBraces($objType), $allObjectData['id'], '{user}', false),
                        ),
                    );

                    $preparedData = [
                        DataHelper::clearBraces($objType) . '_id' => $allObjectData['id'],
                        'link' => ABSOLUTE_PATH . '/' . DataHelper::clearBraces($objType) . '/' . $allObjectData['id'] . '/',
                        'avatar' => (FileHelper::getImagePath($allObjectData['attachments'], 9) ??
                            ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_' . DataHelper::clearBraces($objType) . '.svg'),
                        'name' => DataHelper::escapeOutput($allObjectData['name']),
                        'description' => TextHelper::cutStringToLimit($allObjectData[$descriptionField], 255, true),
                        'members_count' => (string) $membersCount,
                    ];

                    $responseData[DataHelper::clearBraces($objType)]['all'][$allObjectData['id']] = $preparedData;
                }

                $returnArr = ['response' => 'success', 'response_data' => $responseData];
            } else {
                $text = '';
                $allObjects = DB->select(
                    DataHelper::clearBraces($objType),
                    null,
                    false,
                    ['id DESC'],
                    12,
                    $limit,
                );
                $totalObjects = DB->selectCount();

                foreach ($allObjects as $allObjectsData) {
                    $membersCount = count(
                        array_unique(
                            RightsHelper::findByRights(null, DataHelper::addBraces($objType), $allObjectsData['id'], '{user}', false),
                        ),
                    );

                    $text .= DesignHelper::drawPlate($objType, [
                        'id' => $allObjectsData['id'],
                        'attachments' => $allObjectsData['attachments'],
                        'name' => DataHelper::escapeOutput($allObjectsData['name']),
                        'members_count' => $membersCount,
                    ]);
                }

                if ($totalObjects > $limit + 12) {
                    $text .= '<a class="load_projects_communities_list" obj_type="' . $objType . '" limit="' . ($limit + 12) . '">' . $LOCALE['show_next'] . '</a>';
                }

                $returnArr = ['response' => 'success', 'response_text' => $text];
            }
        }

        return $returnArr;
    }

    public function postCreate(array $successfulResultsIds): void
    {
        $LOCALE_MESSAGES = $this->LOCALE['messages'];
        $LOCALE_FEE = LocaleHelper::getLocale(['fee', 'global']);
        $LOCALE_PAYMENT_TYPE = LocaleHelper::getLocale(['payment_type', 'global']);

        foreach ($successfulResultsIds as $id) {
            DB->update(
                'project',
                [
                    'show_roleslist' => 0,
                    'player_count' => 100,
                    'currency' => 'RUR',
                ],
                [
                    'id' => $id,
                ],
            );

            RightsHelper::addRights('{admin}', '{project}', $id);
            RightsHelper::addRights('{member}', '{project}', $id);

            if ($_ENV['PROJECTS_NEED_COMMUNITY'] && ($_REQUEST['communities'] ?? false)) {
                foreach ($_REQUEST['communities'][0] as $key => $value) {
                    RightsHelper::addRights('{child}', '{community}', $key, '{project}', $id);
                }
            }

            if (isset($_REQUEST['user_id'][0])) {
                /** @var ConversationService $conversationService */
                $conversationService = CMSVCHelper::getService('conversation');

                foreach ($_REQUEST['user_id'][0] as $key => $value) {
                    if ($value === 'on' && $key !== CURRENT_USER->id()) {
                        $conversationService->sendInvitation('{project}', $id, $key);
                    }
                }
            }

            DB->insert(
                'project_application_field',
                [
                    'project_id' => $id,
                    'field_name' => $LOCALE_MESSAGES['character'],
                    'field_type' => 'h1',
                    'field_mustbe' => 0,
                    'field_rights' => 4,
                    'field_code' => 1,
                    'application_type' => 0,
                    'created_at' => time(),
                    'updated_at' => time(),
                ],
            );

            DB->insert(
                'project_application_field',
                [
                    'project_id' => $id,
                    'field_name' => $LOCALE_MESSAGES['character_name'],
                    'field_type' => 'text',
                    'field_mustbe' => 1,
                    'field_rights' => 4,
                    'field_code' => 2,
                    'application_type' => 0,
                    'created_at' => time(),
                    'updated_at' => time(),
                ],
            );

            $fieldId = DB->lastInsertId();

            if ($fieldId > 0) {
                DB->update(
                    'project',
                    [
                        'sorter' => $fieldId,
                    ],
                    [
                        'id' => $id,
                    ],
                );
            }

            /** Проверка на наличие события в календаре и предложение добавить, если ничего не найдено */
            $calendarEventData = DB->select(
                'calendar_event',
                [
                    'name' => $_REQUEST['name'][0],
                    'date_from' => $_REQUEST['date_from'][0],
                    'date_to' => $_REQUEST['date_to'][0],
                ],
                true,
            );

            if (!$calendarEventData) {
                ResponseHelper::error(sprintf($LOCALE_MESSAGES['no_matching_calendar_event_found'], $id));
            }

            /** Добавление взноса и даты взноса в раздел настроек взноса */
            DB->insert(
                'project_fee',
                [
                    'creator_id' => CURRENT_USER->id(),
                    'project_id' => $id,
                    'name' => $LOCALE_FEE['base_name'],
                    'content' => '{menu}',
                    'last_update_user_id' => CURRENT_USER->id(),
                    'created_at' => time(),
                    'updated_at' => time(),
                ],
            );
            $projectFeeId = DB->lastInsertId();

            DB->insert(
                'project_fee',
                [
                    'creator_id' => CURRENT_USER->id(),
                    'project_id' => $id,
                    'parent' => $projectFeeId,
                    'date_from' => date('Y-m-d'),
                    'cost' => 0,
                    'last_update_user_id' => CURRENT_USER->id(),
                    'created_at' => time(),
                    'updated_at' => time(),
                ],
            );

            /** Добавление базового метода оплаты */
            DB->insert(
                'project_payment_type',
                [
                    'creator_id' => CURRENT_USER->id(),
                    'project_id' => $id,
                    'name' => $LOCALE_PAYMENT_TYPE['base_name'],
                    'user_id' => CURRENT_USER->id(),
                    'created_at' => time(),
                    'updated_at' => time(),
                ],
            );
        }
    }

    public function postChange(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $id) {
            if ($_ENV['PROJECTS_NEED_COMMUNITY']) {
                $notdelete = [];

                foreach ($_REQUEST['communities'][0] ?? [] as $key => $value) {
                    RightsHelper::addRights('{child}', '{community}', $key, '{project}', $id);
                    $notdelete[] = $key;
                }
                RightsHelper::deleteRights(null, '{community}', null, '{project}', $id, count($notdelete) > 0 ? ' AND obj_id_to NOT IN (' . implode(',', $notdelete) . ')' : '');
            }
        }
    }

    public function postDelete(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $id) {
            RightsHelper::deleteRights(null, '{project}', $id);
            RightsHelper::deleteRights(null, null, null, '{project}', $id);
        }
    }

    /** Выборка проектов на основе запроса.
     * @return array{0: Generator<int|string, ProjectModel>, 1:int}
     */
    public function getProjects(): array
    {
        if ($_REQUEST['search'] ?? false) {
            $projectsData = $this->arraysToModels(
                DB->query(
                    'SELECT * FROM project WHERE (name LIKE :input1 OR annotation LIKE :input2) ORDER BY name',
                    [
                        ['input1', '%' . $_REQUEST['search'] . '%'],
                        ['input2', '%' . $_REQUEST['search'] . '%'],
                    ],
                ),
            );

            $projectsDataCount = DB->query(
                'SELECT COUNT(id) FROM project WHERE (name LIKE :input1 OR annotation LIKE :input2) ORDER BY name',
                [
                    ['input1', '%' . $_REQUEST['search'] . '%'],
                    ['input2', '%' . $_REQUEST['search'] . '%'],
                ],
                true,
            )[0];
        } else {
            $projectsData = $this->getAll(
                [],
                false,
                ['id DESC'],
                12,
            );

            $projectsDataCount = DB->count('project');
        }

        return [$projectsData, $projectsDataCount];
    }

    public function getProjectRights(): bool|array
    {
        return PROJECT_RIGHTS;
    }

    public function getAllowProjectActions(): bool
    {
        return ALLOW_PROJECT_ACTIONS;
    }

    public function isProjectAdmin(): bool
    {
        return $this->getProjectRights() && in_array('{admin}', $this->getProjectRights());
    }

    public function isProjectGamemaster(): bool
    {
        return $this->isProjectAdmin() || ($this->getProjectRights() && in_array('{gamemaster}', $this->getProjectRights()));
    }

    public function isProjectNewsmaker(): bool
    {
        return $this->isProjectAdmin() || ($this->getProjectRights() && in_array('{newsmaker}', $this->getProjectRights()));
    }

    public function isProjectMember(): bool
    {
        return $this->getProjectRights() && in_array('{member}', $this->getProjectRights());
    }

    public function hasProjectAccess(): bool
    {
        return (bool) $this->getProjectRights();
    }

    public function getProjectInfoData(int $projectId): array
    {
        $data = DB->query(
            "SELECT p.id, paf.id AS individual_field_id, paf2.id AS team_field_id, pg.id AS group_id FROM project p LEFT JOIN project_application_field paf ON paf.project_id=p.id AND paf.application_type='0' LEFT JOIN project_application_field paf2 ON paf2.project_id=p.id AND paf2.application_type='1' LEFT JOIN project_group pg ON pg.project_id=p.id AND (pg.rights=0 OR pg.rights=1) WHERE (paf.id IS NOT NULL OR paf2.id IS NOT NULL) AND p.id=:id GROUP BY p.id, paf.id, paf2.id, pg.id",
            [
                ['id', $projectId],
            ],
            true,
        );

        return $data ? $data : [];
    }

    public function getApplicationData(int $projectId): ?ApplicationModel
    {
        if (CURRENT_USER->isLogged()) {
            /** @var ApplicationService */
            $applicationService = CMSVCHelper::getService('application');

            return $applicationService->get(
                criteria: [
                    'project_id' => $projectId,
                    'creator_id' => CURRENT_USER->id(),
                    ['deleted_by_player', 1, [OperandEnum::NOT_EQUAL]],
                ],
            );
        }

        return null;
    }

    public function getCalendarEventData(ProjectModel $projectData): false|array
    {
        return DB->select(
            'calendar_event',
            [
                'name' => $projectData->name->get(),
                'date_from' => $projectData->date_from->get()->format('Y-m-d'),
                'date_to' => $projectData->date_to->get()->format('Y-m-d'),
            ],
            true,
        );
    }

    public function getObjType(): ?string
    {
        if (is_null($this->objType)) {
            /** Инициируем objId сначала, потому что от нее может напрямую зависеть objType */
            $this->getObjId();
            /** @var ?string */
            $objType = $this->objType;

            if (is_null($objType)) {
                $requestObjType = $_REQUEST['obj_type'] ?? null;
                $objType = is_array($requestObjType) ? $requestObjType[0] ?? $requestObjType : $requestObjType;

                if (DataHelper::getId() > 0 && is_null($objType)) {
                    $objType = 'project';
                }

                $this->objType = $objType;
            }
        }

        return $this->objType;
    }

    public function getObjId(): int|string|null
    {
        if (is_null($this->objId)) {
            $requestObjId = $_REQUEST['obj_id'] ?? null;
            $objId = is_array($requestObjId) ? $requestObjId[0] ?? $requestObjId : $requestObjId;

            if (!is_null($objId) && $objId !== 'all') {
                $objId = (int) $objId;
            }

            if (DataHelper::getId() > 0) {
                if ($this->act === ActEnum::edit || $this->act === ActEnum::view) {
                    $objId = RightsHelper::findOneByRights('{child}', '{project}', null, '{task}', DataHelper::getId());

                    if (!is_null($objId)) {
                        $this->objType = 'project';
                    } else {
                        $objId = RightsHelper::findOneByRights('{child}', '{community}', null, '{task}', DataHelper::getId());

                        if (!is_null($objId)) {
                            $this->objType = 'community';
                        }
                    }
                }

                if (is_null($objId)) {
                    $objId = 'all';
                }
            }

            $this->objId = $objId;
        }

        return $this->objId;
    }

    public function getCommunitiesDefault(): ?array
    {
        $communitiesDefault = [];

        if (DataHelper::getId() > 0) {
            $result = DB->query(
                "SELECT DISTINCT c.id FROM community AS c LEFT JOIN relation AS r ON r.obj_id_to=c.id WHERE r.obj_type_to='{community}' AND r.obj_type_from='{project}' AND r.obj_id_from=:obj_id_from AND r.type='{child}'",
                [
                    ['obj_id_from', DataHelper::getId()],
                ],
            );

            foreach ($result as $communityData) {
                $communitiesDefault[] = $communityData['id'];
            }
        } elseif ($this->act === ActEnum::add && $_ENV['PROJECTS_NEED_COMMUNITY'] && OBJ_ID && OBJ_TYPE === 'community') {
            $communitiesDefault[] = OBJ_ID;
        }

        return $communitiesDefault;
    }

    public function getCommunitiesValues(): ?array
    {
        $id = DataHelper::getId();

        $communitiesValues = [];
        $communitiesFound = [];

        if (CURRENT_USER->isLogged()) {
            $result = DB->query(
                "SELECT DISTINCT c.id, c.name FROM community c LEFT JOIN relation r ON r.obj_id_to=c.id WHERE r.obj_type_to='{community}' AND r.obj_type_from='{user}' AND r.obj_id_from=:obj_id_from AND r.type NOT IN ('" . implode('\',\'', RightsHelper::getBannedTypes()) . "') ORDER BY c.name",
                [
                    ['obj_id_from', CURRENT_USER->id()],
                ],
            );

            foreach ($result as $communityData) {
                if ($communityData['id'] !== $id) {
                    $communitiesValues[] = [$communityData['id'], DataHelper::escapeOutput($communityData['name'])];
                    $communitiesFound[] = $communityData['id'];
                }
            }
        }

        if ($id > 0) {
            $result = DB->query(
                "SELECT DISTINCT c.id, c.name FROM community c LEFT JOIN relation r ON r.obj_id_to=c.id WHERE r.obj_type_to='{community}' AND r.obj_type_from='{project}' AND r.obj_id_from=:obj_id_from AND r.type='{child}' ORDER BY c.name",
                [
                    ['obj_id_from', $id],
                ],
            );

            foreach ($result as $communityData) {
                if (!in_array($communityData['id'], $communitiesFound)) {
                    $communitiesValues[] = [$communityData['id'], DataHelper::escapeOutput($communityData['name'])];
                }
            }
        }

        return $communitiesValues;
    }

    public function getCommunitiesContext(): array
    {
        if ($_ENV['PROJECTS_NEED_COMMUNITY']) {
            return ['project:list', 'project:view', 'project:create', 'project:update'];
        }

        return [];
    }

    public function getUserIdDefault(): array
    {
        return [CURRENT_USER->id()];
    }

    public function getUserIdValues(): array
    {
        $parentMembersData = [];

        if ($_ENV['PROJECTS_NEED_COMMUNITY'] && $this->act === ActEnum::add) {
            $objId = $this->getObjId();
            $objType = $this->getObjType();

            $parentMembersData = [];
            $parentMembersDataBuffer = [];
            $parentMembersDataFoundIds = [];
            $parentSeparator = [['locked', '<hr>']];

            if ($objType === 'community' && $objId > 0) {
                $parentMembers = RightsHelper::findByRights(null, '{community}', $objId, '{user}', false);

                foreach ($parentMembers as $memberId) {
                    if (!in_array($memberId, $parentMembersDataFoundIds)) {
                        $parentMembersDataBuffer[] = [
                            $memberId,
                            $this->getUserService()->showName($this->getUserService()->get($memberId)),
                        ];
                        $parentMembersDataFoundIds[] = $memberId;
                    }
                }

                $parentMembersDataSort = [];

                foreach ($parentMembersDataBuffer as $key => $row) {
                    $parentMembersDataSort[$key] = $row[1];
                }
                array_multisort($parentMembersDataSort, SORT_ASC, $parentMembersDataBuffer);
                $parentMembersData = $parentMembersDataBuffer;
                $parentMembersDataBuffer = [];
            }

            foreach ($this->getCommunitiesValues() as $community) {
                $parentMembers = RightsHelper::findByRights(null, '{community}', $community[0], '{user}', false);

                foreach ($parentMembers as $memberId) {
                    if (!in_array($memberId, $parentMembersDataFoundIds)) {
                        $parentMembersDataBuffer[] = [
                            $memberId,
                            $this->getUserService()->showName($this->getUserService()->get($memberId)),
                        ];
                        $parentMembersDataFoundIds[] = $memberId;
                    }
                }
            }

            if (count($parentMembersDataBuffer) > 0) {
                foreach ($parentMembersDataBuffer as $key => $row) {
                    $parentMembersDataSort[$key] = $row[1];
                }
                array_multisort($parentMembersDataSort, SORT_ASC, $parentMembersDataBuffer);

                if (count($parentMembersData) > 0) {
                    $parentMembersData = array_merge(
                        $parentMembersData,
                        $parentSeparator,
                        $parentMembersDataBuffer,
                    );
                } else {
                    $parentMembersData = $parentMembersDataBuffer;
                }
                $parentMembersDataBuffer = [];
            }

            $colleagues = RightsHelper::findByRights('{friend}', '{user}');

            if ($colleagues) {
                foreach ($colleagues as $memberId) {
                    if (!in_array($memberId, $parentMembersDataFoundIds)) {
                        $parentMembersDataBuffer[] = [
                            $memberId,
                            $this->getUserService()->showName($this->getUserService()->get($memberId)),
                        ];
                        $parentMembersDataFoundIds[] = $memberId;
                    }
                }

                $parentMembersDataSort = [];

                foreach ($parentMembersDataBuffer as $key => $row) {
                    $parentMembersDataSort[$key] = $row[1];
                }
                array_multisort($parentMembersDataSort, SORT_ASC, $parentMembersDataBuffer);

                if (count($parentMembersData) > 0) {
                    $parentMembersData = array_merge(
                        $parentMembersData,
                        $parentSeparator,
                        $parentMembersDataBuffer,
                    );
                } else {
                    $parentMembersData = $parentMembersDataBuffer;
                }
                $parentMembersDataBuffer = [];
            }
        }

        return $parentMembersData;
    }

    public function getUserIdLocked(): array
    {
        return [CURRENT_USER->id(), 'locked'];
    }

    public function getUserIdContext(): array
    {
        if ($_ENV['PROJECTS_NEED_COMMUNITY']) {
            return ['project:view', 'project:create'];
        }

        return [];
    }

    public function getDateFromDefault(): DateTimeImmutable
    {
        return new DateTimeImmutable('+1 hour');
    }

    public function getDateToDefault(): DateTimeImmutable
    {
        return new DateTimeImmutable('+2 hours');
    }

    public function getSorterValues(): Generator|array
    {
        if (DataHelper::getId() > 0) {
            return DB->getArrayOfItems('project_application_field WHERE project_id=' . DataHelper::getId() . " AND field_type='text' AND application_type='0' ORDER BY id ASC", 'id', 'field_name');
        }

        return [];
    }

    public function getSorter2Values(): Generator|array
    {
        if (DataHelper::getId() > 0) {
            return DB->getArrayOfItems('project_application_field WHERE project_id=' . DataHelper::getId() . " AND field_type='text' AND application_type='1' ORDER BY id ASC", 'id', 'field_name');
        }

        return [];
    }

    public function getGotoLinkDefault(): string
    {
        return '<a href="' . ABSOLUTE_PATH . '/go/' . DataHelper::getId() . '/" target="_blank">' . ABSOLUTE_PATH . '/go/' . DataHelper::getId() . '/</a>';
    }

    public function getViewOnNotAddContext(): array
    {
        $context = [];

        if (ACT !== ActEnum::add) {
            $context = ['project:view'];
        }

        return $context;
    }

    public function getEditViewPaymentSystemsContext(): array
    {
        if (count($_ENV['USE_PAYMENT_SYSTEMS']) > 0 && CookieHelper::getCookie('locale') === 'RU') {
            return ['project:update'];
        }

        return [];
    }

    public function getEditViewPaymentSystemsPaykeeperContext(): array
    {
        if (in_array('paykeeper', $_ENV['USE_PAYMENT_SYSTEMS']) && CookieHelper::getCookie('locale') === 'RU') {
            return ['project:update'];
        }

        return [];
    }

    public function getEditViewPaymentSystemsPaymasterContext(): array
    {
        if (in_array('paymaster', $_ENV['USE_PAYMENT_SYSTEMS']) && CookieHelper::getCookie('locale') === 'RU') {
            return ['project:update'];
        }

        return [];
    }

    public function getEditViewPaymentSystemsYandexContext(): array
    {
        if (in_array('yandex', $_ENV['USE_PAYMENT_SYSTEMS']) && CookieHelper::getCookie('locale') === 'RU') {
            return ['project:update'];
        }

        return [];
    }

    public function getEditViewPaymentSystemsPayAnyWayContext(): array
    {
        if (in_array('payanyway', $_ENV['USE_PAYMENT_SYSTEMS']) && CookieHelper::getCookie('locale') === 'RU') {
            return ['project:update'];
        }

        return [];
    }

    public function checkRights(): bool
    {
        return CURRENT_USER->isLogged();
    }

    public function checkRightsRestrict(): string
    {
        $id = DataHelper::getId();

        return (CURRENT_USER->isAdmin() || ($id > 0 && $this->isProjectAdmin())) ? '' : 'creator_id=' . CURRENT_USER->id();
    }

    public function postModelInit(BaseModel $model): BaseModel
    {
        /** @var ProjectModel $model */
        if (DataHelper::getId() > 0 && CookieHelper::getCookie('locale') === 'RU') {
            $projectData = $this->getProjectData();

            if (in_array('paykeeper', $_ENV['USE_PAYMENT_SYSTEMS'])) {
                if (
                    ($projectData['paykeeper_login'] ?? '') === '' ||
                    ($projectData['paykeeper_pass'] ?? '') === '' ||
                    ($projectData['paykeeper_server'] ?? '') === '' ||
                    ($projectData['paykeeper_secret'] ?? '') === ''
                ) {
                    $model->helper_1_pk->getAttribute()->context = ['project:view'];
                }
            } elseif (in_array('paymaster', $_ENV['USE_PAYMENT_SYSTEMS'])) {
                if (($projectData['paymaster_merchant_id'] ?? '') === '' || ($projectData['paymaster_code'] ?? '') === '') {
                    $model->helper_1_pm->getAttribute()->context = ['project:view'];
                }
            } elseif (in_array('yandex', $_ENV['USE_PAYMENT_SYSTEMS'])) {
                if (($projectData['yk_acc_id'] ?? '') === '' || ($projectData['yk_code'] ?? '') === '') {
                    $model->helper_1_yk->getAttribute()->context = ['project:view'];
                }
            } elseif (in_array('payanyway', $_ENV['USE_PAYMENT_SYSTEMS'])) {
                if (!((int) $projectData['paw_mnt_id'] > 0) || ($projectData['paw_code'] ?? '') === '') {
                    $model->helper_1_paw->getAttribute()->context = ['project:view'];
                }
            }
        }

        return $model;
    }

    private function getProjectData(): ?array
    {
        return DB->findObjectById(DataHelper::getId(), 'project');
    }
}
