<?php

declare(strict_types=1);

namespace App\CMSVC\Task;

use App\CMSVC\Conversation\ConversationService;
use App\CMSVC\Message\MessageService;
use App\CMSVC\Trait\UserServiceTrait;
use App\Helper\{DateHelper, MessageHelper, RightsHelper};
use DateTime;
use DateTimeImmutable;
use Fraym\BaseObject\{BaseModel, BaseService, Controller};
use Fraym\Element\Item\Multiselect;
use Fraym\Entity\{PostChange, PostCreate, PostDelete, PreChange};
use Fraym\Enum\{ActEnum, EscapeModeEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Generator;

/** @extends BaseService<TaskModel> */
#[PostCreate]
#[PreChange]
#[PostChange]
#[PostDelete]
#[Controller(TaskController::class)]
class TaskService extends BaseService
{
    use UserServiceTrait;

    private int|string|null $objId = null;
    private ?string $objType = null;
    private ?array $messageData = null;
    private ?array $parentUserIdsValues = null;
    private ?TaskModel $parentTask = null;
    private ?TaskModel $followingTask = null;
    /** @var int[]|null */
    private ?array $parentObjectTasksIds = null;
    /** @var int[]|null */
    private ?array $childTasksIds = null;
    /** @var int[]|null */
    private ?array $pregoingTasksIds = null;
    private bool $skipPostCreate = false;
    private bool $skipPostChange = false;
    private bool $skipPostDelete = false;
    private ?TaskModel $savedTaskData = null;

    /** Добавление задачи */
    public function checkDatesAvailability(
        string $objType,
        ?int $objId,
        ?int $responsibleId,
        ?array $requestedUserIds,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        // проверяем доступность требуемых дат у всех участников задачи / события, выводим тех, кто не может
        $userIds = [];

        if (!is_null($responsibleId)) {
            $userIds[] = $responsibleId;
        }

        if (isset($requestedUserIds)) {
            foreach ($requestedUserIds as $value) {
                $userIds[] = (int) $value;
            }
            $userIds = array_unique($userIds);
        }

        $dateFromQuery = date('Y-m-d H:i', strtotime($dateFrom));
        $dateToQuery = date('Y-m-d H:i', strtotime($dateTo));
        $dateQuery = '';

        if (!is_null($dateFrom) && !is_null($dateTo)) {
            // у нас есть обе даты, значит, проверяем наличие других задач, которые начинаются, заканчиваются или идут в этом промежутке
            $dateQuery .= ' AND ((te.date_from<=:date_from1 AND te.date_to>=:date_to1) OR (te.date_from>=:date_from2 AND te.date_from<=:date_to2) OR (te.date_to>=:date_from3 AND te.date_to<=:date_to3))';
            $dateQueryParams = [
                ['date_from1', $dateFromQuery],
                ['date_to1', $dateToQuery],
                ['date_from2', $dateFromQuery],
                ['date_to2', $dateToQuery],
                ['date_from3', $dateFromQuery],
                ['date_to3', $dateToQuery],
            ];
        } elseif (!is_null($dateFrom)) {
            // у нас есть только дата начала, проверяем наличие других задач, которые начинаются, заканчиваются или идут в это время
            $dateQuery .= ' AND (te.date_from<=:date_from1 AND te.date_to>=:date_from2)';
            $dateQueryParams = [
                ['date_from1', $dateFromQuery],
                ['date_from2', $dateFromQuery],
            ];
        } elseif (!is_null($dateTo)) {
            // у нас есть только дата завершения, проверяем наличие других задач, которые начинаются, заканчиваются или идут в это время
            $dateQuery .= ' AND (te.date_from<=:date_to1 AND te.date_to>=:date_to2)';
            $dateQueryParams = [
                ['date_to1', $dateToQuery],
                ['date_to2', $dateToQuery],
            ];
        }

        if ((($objId > 0 && RightsHelper::checkAnyRights(DataHelper::addBraces($objType), $objId)) || is_null($objId)) && count($userIds) > 0 && $dateQuery !== '') {
            $responseData = [];

            $data = DB->query(
                "SELECT DISTINCT u.* FROM task_and_event te LEFT JOIN relation r2 ON r2.obj_id_to=te.id AND r2.obj_type_from='{user}' AND r2.obj_type_to=:obj_type_to AND r2.obj_id_from IN (:obj_id_froms) AND r2.type IN ('{admin}','{responsible}','{member}') LEFT JOIN user u ON u.id=r2.obj_id_from WHERE r2.obj_id_from IS NOT NULL AND ((te.status!='{closed}' AND te.status!='{rejected}' AND te.status!='{delayed}') OR te.status IS NULL)" . ($objId > 0 ? ' AND te.id!=:id' : '') . $dateQuery . ' AND (te.do_not_count_as_busy!="1" OR te.do_not_count_as_busy IS NULL) ORDER BY u.fio ASC',
                array_merge([
                    ['obj_type_to', DataHelper::addBraces($objType)],
                    ['obj_id_froms', $userIds],
                    ['id', $objId],
                ], $dateQueryParams),
            );

            foreach ($data as $userData) {
                $userModel = $this->getUserService()->arrayToModel($userData);

                $responseData['users'][] = [
                    'id' => $userData['id'],
                    'fio' => $this->getUserService()->showNameExtended($userModel, true),
                    'photo' => $this->getUserService()->photoUrl($userModel),
                    'html' => (REQUEST_TYPE->isApiRequest() ? null : $this->getUserService()->photoNameLink($userModel, '', false)),
                ];
            }

            // находим последнюю занятую дату у всех участников
            $lookingForDateTo = true;
            $diffInSeconds = false;

            if (!is_null($dateTo)) {
                $diffInSeconds = strtotime($dateTo) - strtotime($dateFrom) + (15 * 60);
            }
            $data = DB->query(
                "SELECT DISTINCT te.date_to FROM task_and_event te LEFT JOIN relation r2 ON r2.obj_id_to=te.id AND r2.obj_type_from='{user}' AND r2.obj_type_to=:obj_type_to AND r2.obj_id_from IN (:obj_id_froms) AND r2.type IN ('{admin}','{responsible}','{member}') LEFT JOIN user u ON u.id=r2.obj_id_from WHERE r2.obj_id_from IS NOT NULL AND ((te.status!='{closed}' AND te.status!='{rejected}' AND te.status!='{delayed}') OR te.status IS NULL)" . ($objId > 0 ? ' AND te.id!=:id' : '') . $dateQuery . ' AND (te.do_not_count_as_busy!="1" OR te.do_not_count_as_busy IS NULL) ORDER BY te.date_to DESC LIMIT 1',
                array_merge([
                    ['obj_type_to', DataHelper::addBraces($objType)],
                    ['obj_id_froms', $userIds],
                    ['id', $objId],
                ], $dateQueryParams),
                true,
            );

            while ($lookingForDateTo && $data && !is_null($data['date_to']) && (string) $data['date_to'] !== '') {
                $dateFrom = date('Y-m-d H:i', strtotime((string) $data['date_to'] . ' +15 minutes'));

                if ($diffInSeconds) {
                    $dateTo = date('Y-m-d H:i', strtotime((string) $data['date_to'] . ' +' . $diffInSeconds . ' seconds'));
                }

                $dateFromQuery = date('Y-m-d H:i', strtotime((string) $dateFrom));
                $dateToQuery = date('Y-m-d H:i', strtotime((string) $dateTo));
                $dateQueryParams = [
                    ['date_from', $dateFromQuery],
                    ['date_to', $dateToQuery],
                ];

                $dateQuery = '';

                if (!is_null($dateTo)) {
                    // у нас есть обе даты, значит, проверяем наличие других задач, которые начинаются, заканчиваются или идут в этом промежутке
                    $dateQuery .= " AND ((te.date_from<='" . $dateFromQuery . "' AND te.date_to>='" . $dateToQuery . "') OR (te.date_from>='" . $dateFromQuery . "' AND te.date_from<='" . $dateToQuery . "') OR (te.date_to>='" . $dateFromQuery . "' AND te.date_to<='" . $dateToQuery . "'))";
                } else {
                    // у нас есть только дата начала, проверяем наличие других задач, которые начинаются, заканчиваются или идут в это время
                    $dateQuery .= " AND (te.date_from<='" . $dateFromQuery . "' AND te.date_to>='" . $dateFromQuery . "')";
                }

                $data = DB->query(
                    "SELECT DISTINCT te.date_to FROM task_and_event te LEFT JOIN relation r2 ON r2.obj_id_to=te.id AND r2.obj_type_from='{user}' AND r2.obj_type_to=:obj_type_to AND r2.obj_id_from IN (:obj_id_froms) AND r2.type IN ('{admin}','{responsible}','{member}') LEFT JOIN user u ON u.id=r2.obj_id_from WHERE r2.obj_id_from IS NOT NULL AND ((te.status!='{closed}' AND te.status!='{rejected}' AND te.status!='{delayed}') OR te.status IS NULL)" . ($objId > 0 ? ' AND te.id!=:id' : '') . $dateQuery . ' AND (te.do_not_count_as_busy!="1" OR te.do_not_count_as_busy IS NULL) ORDER BY te.date_to DESC LIMIT 1',
                    array_merge([
                        ['obj_type_to', DataHelper::addBraces($objType)],
                        ['obj_id_froms', $userIds],
                        ['id', $objId],
                    ], $dateQueryParams),
                    true,
                );

                if ($data && $data['date_to'] === '') {
                    $lookingForDateTo = false;
                }
            }

            $responseData['closest_interval']['date_from'] = $dateFrom;

            if (!is_null($dateTo)) {
                $responseData['closest_interval']['date_to'] = $dateTo;
            }

            $returnArr = [
                'response' => 'success',
                'response_data' => $responseData,
            ];
        } elseif (!RightsHelper::checkAnyRights(DataHelper::addBraces($objType), $objId)) {
            $LOCALE = LocaleHelper::getLocale([DataHelper::clearBraces($objType), 'global']);

            $returnArr = [
                'response' => 'error',
                'response_text' => $LOCALE['messages']['have_no_rights_in_this_' . DataHelper::clearBraces($objType)],
            ];
        } else {
            $returnArr = [
                'response' => 'error',
            ];
        }

        return $returnArr;
    }

    /** Добавление задачи */
    public function addTask(?string $name = null): array
    {
        $LOCALE = LocaleHelper::getLocale(['task', 'global']);

        $returnArr = [];

        if (!is_null($name)) {
            DB->insert(
                tableName: 'task_and_event',
                data: [
                    'name' => $name,
                    'creator_id' => CURRENT_USER->id(),
                    'status' => '{new}',
                    'priority' => 4,
                    'created_at' => DateHelper::getNow(),
                    'updated_at' => DateHelper::getNow(),
                ],
            );

            $id = DB->lastInsertId();

            if ($id > 0) {
                RightsHelper::addRights('{admin}', '{task}', $id);
                RightsHelper::addRights('{responsible}', '{task}', $id);
                RightsHelper::addRights('{member}', '{task}', $id);

                /** @var MessageService $messageService */
                $messageService = CMSVCHelper::getService('message');

                $messageService->newMessage(
                    null,
                    $LOCALE['messages']['task_change_message_9'],
                    '',
                    [],
                    [],
                    ['obj_type' => '{task_comment}', 'obj_id' => $id, 'sub_obj_type' => ''],
                );

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['messages']['task_created_successfully'],
                ];
            }
        }

        return $returnArr;
    }

    /** Cмена дат задачи */
    public function changeTaskDates(string $dateFrom, string $dateTo): array
    {
        $LOCALE = LocaleHelper::getLocale([DataHelper::clearBraces(OBJ_TYPE), 'global', 'messages']);

        if (RightsHelper::checkAnyRights(DataHelper::addBraces(OBJ_TYPE), OBJ_ID)) {
            DB->update(
                'task_and_event',
                [
                    'date_from' => date('Y-m-d H:i', strtotime($dateFrom)),
                    'date_to' => date('Y-m-d H:i', strtotime($dateTo)),
                ],
                ['id' => OBJ_ID],
            );

            /** @var MessageService $messageService */
            $messageService = CMSVCHelper::getService('message');

            $message = sprintf(
                $LOCALE['dates_change_message'],
                date('d.m.Y H:i', strtotime($dateFrom)),
                date('d.m.Y H:i', strtotime($dateTo)),
            );

            $messageService->newMessage(
                null,
                $message,
                '',
                [],
                [],
                ['obj_type' => '{' . DataHelper::clearBraces(OBJ_TYPE) . '_comment}', 'obj_id' => OBJ_ID],
            );

            $returnArr = [
                'response' => 'success',
                'response_text' => $LOCALE['dates_changed_successfully'],
            ];
        } else {
            $returnArr = [
                'response' => 'error',
                'response_text' => $LOCALE['have_no_rights_in_this_' . DataHelper::clearBraces(OBJ_TYPE)],
            ];
        }

        return $returnArr;
    }

    /** Отодвижение задачи */
    public function outdentTask(int $parentTaskId): array
    {
        $LOCALE = LocaleHelper::getLocale(['task', 'global', 'messages']);

        $returnArr = [];

        if (RightsHelper::checkAnyRights('{task}', OBJ_ID)) {
            if ($parentTaskId > 0) {
                $taskParent = RightsHelper::findOneByRights('{child}', '{project}', null, '{task}', OBJ_ID);
                $parentTaskParent = RightsHelper::findOneByRights(
                    '{child}',
                    '{project}',
                    null,
                    '{task}',
                    $parentTaskId,
                );
                $taskLooped = RightsHelper::checkRights('{child}', '{task}', OBJ_ID, '{task}', $parentTaskId);

                if (!$taskLooped && $taskParent === $parentTaskParent) {
                    RightsHelper::deleteRights('{child}', '{task}', null, '{task}', OBJ_ID);
                    RightsHelper::addRights('{child}', '{task}', $parentTaskId, '{task}', OBJ_ID);
                    $returnArr = [
                        'response' => 'success',
                    ];
                } elseif ($taskLooped) {
                    $returnArr = [
                        'response' => 'error',
                        'response_text' => $LOCALE['tasks_cant_loop'],
                    ];
                } elseif ($taskParent !== $parentTaskParent) {
                    $returnArr = [
                        'response' => 'error',
                        'response_text' => $LOCALE['tasks_have_different_parents'],
                    ];
                }
            } else {
                RightsHelper::deleteRights('{child}', '{task}', null, '{task}', OBJ_ID);
                $returnArr = [
                    'response' => 'success',
                ];
            }
        }

        return $returnArr;
    }

    /** Cдвижение задачи */
    public function indentTask(int $parentTaskId): array
    {
        $LOCALE = LocaleHelper::getLocale(['task', 'global', 'messages']);

        $returnArr = [];

        if (RightsHelper::checkAnyRights('{task}', OBJ_ID) && $parentTaskId > 0) {
            $taskParent = RightsHelper::findOneByRights('{child}', '{project}', null, '{task}', OBJ_ID);
            $parentTaskParent = RightsHelper::findOneByRights(
                '{child}',
                '{project}',
                null,
                '{task}',
                $parentTaskId,
            );
            $taskLooped = RightsHelper::checkRights('{child}', '{task}', OBJ_ID, '{task}', $parentTaskId);

            if (!$taskLooped && $taskParent === $parentTaskParent) {
                RightsHelper::deleteRights('{child}', '{task}', null, '{task}', OBJ_ID);
                RightsHelper::addRights('{child}', '{task}', $parentTaskId, '{task}', OBJ_ID);
                $returnArr = [
                    'response' => 'success',
                ];
            } elseif ($taskLooped) {
                $returnArr = [
                    'response' => 'error',
                    'response_text' => $LOCALE['tasks_cant_loop'],
                ];
            } elseif ($taskParent !== $parentTaskParent) {
                $returnArr = [
                    'response' => 'error',
                    'response_text' => $LOCALE['tasks_have_different_parents'],
                ];
            }
        }

        return $returnArr;
    }

    /** Вывод списка задач в разных вариациях */
    public function loadTasks(string $objGroup, bool $showList, bool $widgetStyle): array|string
    {
        $returnArr = [];

        if (CURRENT_USER->isLogged()) {
            $objGroup = DataHelper::clearBraces($objGroup);

            if ($objGroup === '' && (int) OBJ_ID > 0 && OBJ_ID !== 'all') {
                $objGroup = 'project';
            }

            if (OBJ_ID === 'all' || RightsHelper::checkRights(['{admin}', '{gamemaster}', '{moderator}'], DataHelper::addBraces($objGroup), OBJ_ID)) {
                if ($widgetStyle && !$showList) {
                    $returnArr = [
                        'response' => 'success',
                        'response_text' => $this->getTasksData('mine', true),
                    ];
                } else {
                    $LOC = LocaleHelper::getLocale(['project', 'global']);
                    $tasksList = [];

                    $myProjects = RightsHelper::findByRights(['{admin}', '{gamemaster}', '{moderator}'], '{project}');
                    $myGroups = [];

                    if ($myProjects) {
                        $myProjectsData = DB->select('project', [['id', $myProjects]]);

                        foreach ($myProjectsData as $myProjectsDataVal) {
                            $myGroups['project'][$myProjectsDataVal['id']] = $myProjectsDataVal;
                        }
                    }

                    $myCommunities = RightsHelper::findByRights(['{admin}', '{gamemaster}', '{moderator}'], '{community}');

                    if ($myCommunities) {
                        $myCommunitiesData = DB->select('community', [
                            ['id', $myCommunities],
                        ]);

                        foreach ($myCommunitiesData as $myCommunitiesDataVal) {
                            $myGroups['community'][$myCommunitiesDataVal['id']] = $myCommunitiesDataVal;
                        }
                    }

                    $presentParents = [];

                    $LOCALE_TASK = LocaleHelper::getLocale(['task', 'fraym_model', 'elements']);

                    $statusesList = [];
                    $statusesTemp = $LOCALE_TASK['status']['values'];

                    foreach ($statusesTemp as $value) {
                        $statusesList[$value[0]] = $value[1];
                    }
                    unset($statusesTemp);

                    $priorityList = [];
                    $priorityTemp = $LOCALE_TASK['priority']['values'];

                    foreach ($priorityTemp as $value) {
                        $priorityList[$value[0]] = $value[1];
                    }
                    unset($priorityTemp);

                    $LOCALE_TASK = LocaleHelper::getLocale(['task', 'global']);

                    $content = '<table class="tasks_table"><thead><tr class="block_data_header">' . (OBJ_TYPE === 'closed' ? '<th data-sort="date" id="task_closed">' . $LOC['task_closed'] . '</th>' : '') . '<th data-sort="date" id="task_dates">' . $LOC['task_dates'] . '</th><th data-sort="string" id="task_name">' . $LOC['task_name'] . '</th><th data-sort="string" id="task_responsible">' . $LOC['task_responsible'] . '</th>' . (OBJ_ID !== 'all' ? '' : '<th data-sort="string" id="task_project">' . $LOCALE_TASK['project'] . '</th>') . (OBJ_TYPE !== 'delayed' && OBJ_TYPE !== 'closed' ? '<th data-sort="string" id="task_status">' . $LOC['task_status'] . '</th>' : '') . (OBJ_TYPE === 'closed' ? '' : '<th data-sort="string" id="task_priority">' . $LOC['task_priority'] . '</th>') . '</tr></thead><tbody>';

                    $taskGroupStep = 0;
                    $taskGroupStepName = [
                        1 => 'overdue',
                        2 => 'today',
                        3 => 'tomorrow',
                        4 => 'later',
                        5 => 'no_date',
                        100 => 'closed',
                    ];

                    /* количество колонок */
                    $colsNum = 6;

                    if (OBJ_TYPE === 'delayed') {
                        --$colsNum;
                    }

                    if (OBJ_ID !== 'all') {
                        --$colsNum;
                    }

                    $data = $this->getTasksData(OBJ_TYPE, false, OBJ_ID, $myProjects ?? []);
                    $stringNum = 0;
                    $responsiblesData = [];

                    foreach ($data as $taskData) {
                        $presentParents[DataHelper::clearBraces($taskData['parent_type'])][$taskData['parent_id']] = true;

                        $responsibleId = RightsHelper::findOneByRights(
                            '{responsible}',
                            '{task}',
                            $taskData['id'],
                            '{user}',
                            false,
                        );
                        $responsiblesData[] = $responsibleId;

                        if (OBJ_TYPE !== 'closed') {
                            if ($taskData['level'] === 0) {
                                if ($taskData['date_to'] === '') {
                                    if ($taskGroupStep < 5) {
                                        $content .= '<tr class="date_header' . ($stringNum > 5 && OBJ_ID !== 'all' ? ' hidden' : '') . '"><td colspan="' . $colsNum . '"><h3>' . $LOC['task_new_status_no_date'] . '</h3></td></tr>';
                                    }
                                    $taskGroupStep = 5;
                                } elseif (strtotime($taskData['date_to'] ?? '') < strtotime('today')) {
                                    if ($taskGroupStep < 1) {
                                        $content .= '<tr class="date_header' . ($stringNum > 5 && OBJ_ID !== 'all' ? ' hidden' : '') . '"><td colspan="' . $colsNum . '"><h3>' . $LOC['task_new_status_overdue'] . '</h3></td></tr>';
                                    }
                                    $taskGroupStep = 1;
                                } elseif (date('Y-m-d', strtotime($taskData['date_to'] ?? '')) === date('Y-m-d')) {
                                    if ($taskGroupStep < 2) {
                                        $content .= '<tr class="date_header' . ($stringNum > 5 && OBJ_ID !== 'all' ? ' hidden' : '') . '"><td colspan="' . $colsNum . '"><h3>' . $LOC['task_new_status_today'] . '</h3></td></tr>';
                                    }
                                    $taskGroupStep = 2;
                                } elseif (date('Y-m-d', strtotime($taskData['date_to'] ?? '')) === date(
                                    'Y-m-d',
                                    strtotime('tomorrow'),
                                )) {
                                    if ($taskGroupStep < 3) {
                                        $content .= '<tr class="date_header' . ($stringNum > 5 && OBJ_ID !== 'all' ? ' hidden' : '') . '"><td colspan="' . $colsNum . '"><h3>' . $LOC['task_new_status_tomorrow'] . '</h3></td></tr>';
                                    }
                                    $taskGroupStep = 3;
                                } else {
                                    if ($taskGroupStep < 4) {
                                        $content .= '<tr class="date_header' . ($stringNum > 5 && OBJ_ID !== 'all' ? ' hidden' : '') . '"><td colspan="' . $colsNum . '"><h3>' . $LOC['task_new_status_later'] . '</h3></td></tr>';
                                    }
                                    $taskGroupStep = 4;
                                }
                            }
                        } else {
                            $taskGroupStep = 100;
                        }

                        $currentTask = [
                            'id' => $taskData['id'],
                            'name' => trim(DataHelper::escapeOutput($taskData['name'])),
                            'link' => ABSOLUTE_PATH . '/task/' . $taskData['id'] . '/',
                            'date_from' => ($taskData['date_from'] ? strtotime($taskData['date_from']) : ''),
                            'date_to' => ($taskData['date_to'] ? strtotime($taskData['date_to']) : ''),
                            'editable' => $taskData['editable'],
                            'level' => $taskData['level'],
                        ];

                        $unreadCount = 0;
                        $conversationMessageCount = 0;
                        $lastMessage = [];

                        if (OBJ_TYPE !== 'notmembered') {
                            $lastMessage = DB->query(
                                "SELECT cm.creator_id, cm.updated_at, cm.conversation_id FROM conversation_message cm INNER JOIN conversation c ON cm.conversation_id=c.id AND c.obj_type='{task_comment}' AND c.obj_id=:obj_id ORDER BY cm.updated_at DESC LIMIT 1",
                                [
                                    ['obj_id', $taskData['id']],
                                ],
                                true,
                            );
                            $conversationMessageCount = DB->selectCount();

                            if ($conversationMessageCount > 0) {
                                $result = DB->query(
                                    "SELECT cms.id FROM conversation_message_status cms INNER JOIN conversation_message cm ON cms.message_id=cm.id INNER JOIN conversation c ON cm.conversation_id=c.id AND c.obj_type='{task_comment}' AND c.obj_id=:obj_id WHERE cms.user_id=:user_id AND cms.message_read='1'",
                                    [
                                        ['obj_id', $taskData['id']],
                                        ['user_id', CURRENT_USER->id()],
                                    ],
                                );
                                $unreadCount = $conversationMessageCount - count($result);
                                $currentTask['unread_count'] = $unreadCount;
                            }
                        }

                        $content .= '<tr obj_type="' . DataHelper::clearBraces($taskData['parent_type']) .
                            '" obj_id="' . $taskData['parent_id'] . '" responsible_id="' . $responsibleId . '" class="tasklist_data' . ($unreadCount > 0 ? ' tasklist_unread' : '') . ($stringNum > 5 && OBJ_ID !== 'all' ? ($unreadCount > 0 ? ' ' : '') . ' hidden' : '') . '">';

                        if (OBJ_TYPE === 'closed') {
                            $content .= '<td><nobr>' . ($taskData['updated_at'] > $lastMessage['updated_at'] ?
                                date('d.m.Y H:i', $taskData['updated_at']) : date('d.m.Y H:i', $lastMessage['updated_at'])) . '</nobr></td>';
                        }

                        $content .= '<td><nobr>' . ($taskData['date_to'] ? date('d.m.Y H:i', strtotime($taskData['date_to'])) : '') . '</nobr></td>';
                        $content .= '<td style="padding-left: ' . (0.2 + ($taskData['level'] * 2)) . 'em;"><a href="' . ABSOLUTE_PATH . '/task/' . $taskData['id'] . '/"><b>' .
                            trim(DataHelper::escapeOutput($taskData['name'])) . '</b></a>';

                        if (OBJ_TYPE !== 'notmembered') {
                            $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

                            $content .= '<div class="task_conversation_additional">' . ($unreadCount > 0 ? '<span class="red">' : '') . $conversationMessageCount . ' ' . $LOCALE['message'] .
                                LocaleHelper::declineNeuter($conversationMessageCount) .
                                ($unreadCount > 0 ? '</span>' : '') . '. ' .
                                ($lastMessage ? $LOCALE['last_message'] . ': ' . $this->getUserService()->showNameWithId($this->getUserService()->get($lastMessage['creator_id']), true) . ', ' . DateHelper::showDateTime($lastMessage['updated_at'] ?? time()) : '') . '.</div>';
                        }

                        $content .= '</td>';

                        $content .= '<td>' . $this->getUserService()->showNameWithId($this->getUserService()->get($responsibleId), true) . '</td>';

                        if (OBJ_ID === 'all') {
                            if ($taskData['parent_id']) {
                                if (!isset(
                                    $myGroups[DataHelper::clearBraces($taskData['parent_type'])][$taskData['parent_id']],
                                )) {
                                    $parentData = DB->select(DataHelper::clearBraces($taskData['parent_type']), [
                                        ['id', $taskData['parent_id']],
                                    ], true);

                                    if ($parentData) {
                                        $myGroups[DataHelper::clearBraces($taskData['parent_type'])][$taskData['parent_id']] = $parentData;
                                    }
                                }

                                $content .= '<td><a href="' . ABSOLUTE_PATH . '/' . DataHelper::clearBraces($taskData['parent_type']) . '/' . $taskData['parent_id'] . '/">' .
                                    DataHelper::escapeOutput(
                                        $myGroups[DataHelper::clearBraces($taskData['parent_type'])][$taskData['parent_id']]['name'],
                                    ) . '</a></td>';
                                $currentTask['project'] = [
                                    'id' => $taskData['parent_id'],
                                    'name' => DataHelper::escapeOutput(
                                        $myGroups[DataHelper::clearBraces($taskData['parent_type'])][$taskData['parent_id']]['name'],
                                    ),
                                    'link' => ABSOLUTE_PATH . '/' . DataHelper::clearBraces($taskData['parent_type']) . '/' . $taskData['parent_id'] . '/',
                                ];
                            } else {
                                $content .= '<td></td>';
                            }
                        }

                        if (OBJ_TYPE !== 'closed' && OBJ_TYPE !== 'delayed') {
                            $content .= '<td>' . ($taskData['status'] === '{new}' || $taskData['status'] === '{working}' ? '<b>' : '') . $statusesList[$taskData['status']] . ($taskData['status'] === '{new}' || $taskData['status'] === '{working}' ? '</b>' : '') . '</td>';
                        }

                        if (OBJ_TYPE !== 'closed') {
                            $content .= '<td>' . $priorityList[$taskData['priority']] . '</td>';
                        }
                        $currentTask['bold'] = ($taskData['status'] === '{new}' || $taskData['status'] === '{working}' ? 'true' : 'false');
                        $currentTask['status'] = [
                            'type' => $taskData['status'],
                            'name' => $statusesList[$taskData['status']],
                        ];
                        $currentTask['priority'] = [
                            'type' => $taskData['priority'],
                            'name' => $priorityList[$taskData['priority']],
                        ];

                        $content .= '</tr>';

                        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

                        if ($stringNum === 5 && count($data) > 6 && OBJ_ID !== 'all') {
                            $content .= '<tr><td colspan=4><a class="show_hidden_table">' . $LOCALE_GLOBAL['show_hidden'] . '</a></td></tr>';
                        }
                        ++$stringNum;

                        $tasksList[$taskGroupStepName[$taskGroupStep]][] = $currentTask;
                    }
                    $content .= '</tbody>
	</table>';

                    if ($widgetStyle || REQUEST_TYPE->isApiRequest()) {
                        unset($content);
                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $this->getTasksData('mine', true),
                            'response_data' => $tasksList,
                        ];
                    } else {
                        $LOC = LocaleHelper::getLocale(['tasklist', 'global']);

                        $content2 = '<div class="tasklist_table">
<div class="tasklist_filter">' . $LOC['filter_tasks_and_events'] . '
<select name="tasklist_filter_obj">';

                        foreach ($LOC['obj_filters'] as $key => $value) {
                            if (OBJ_ID === 'all') {
                                $content2 .= '<option obj_type="' . $key . '"' . ($key === 'all' ? ' selected' : '') . '>' . $value . '</option>';
                            } elseif ($key === 'all' || $key === 'unread') {
                                $content2 .= '<option obj_type="' . $key . '"' . ($key === 'all' ? ' selected' : '') . '>' . $value . '</option>';
                            }
                        }

                        if (OBJ_ID === 'all') {
                            $header = false;

                            if ($myGroups['project'] ?? false) {
                                foreach ($myGroups['project'] as $key => $value) {
                                    if (!$header) {
                                        $content2 .= '<option disabled>' . $LOC['obj_filters_additional']['project'] . '</option>';
                                        $header = true;
                                    }
                                    $content2 .= '<option obj_id="' . $key . '" obj_type="project">&nbsp;&nbsp;' . DataHelper::escapeOutput($value['name']) . '</option>';
                                }
                            }

                            $header = false;

                            if ($myGroups['community'] ?? false) {
                                foreach ($myGroups['community'] as $key => $value) {
                                    if (!$header) {
                                        $content2 .= '<option disabled>' . $LOC['obj_filters_additional']['community'] . '</option>';
                                        $header = true;
                                    }
                                    $content2 .= '<option obj_id="' . $key . '" obj_type="community">&nbsp;&nbsp;' . DataHelper::escapeOutput($value['name']) . '</option>';
                                }
                            }
                        }

                        $header = false;

                        if (count($responsiblesData) > 0) {
                            $responsiblesData = array_unique($responsiblesData);
                            $responsiblesDataNames = [];
                            $responsiblesDataSort = [];

                            foreach ($responsiblesData as $value) {
                                $userData = $this->getUserService()->showNameWithId($this->getUserService()->get($value), false);
                                $responsiblesDataNames[] = [$value, $userData];
                                $responsiblesDataSort[] = mb_strtolower($userData);
                            }
                            array_multisort($responsiblesDataSort, SORT_ASC, $responsiblesDataNames);

                            foreach ($responsiblesDataNames as $value) {
                                if (!$header) {
                                    $content2 .= '<option disabled>' . $LOC['obj_filters_additional']['responsible'] . '</option>';
                                    $header = true;
                                }
                                $content2 .= '<option obj_id="' . $value[0] . '" obj_type="responsible">&nbsp;&nbsp;' . $value[1] . '</option>';
                            }
                        }

                        $content2 .= '</select>
</div>' . $content . '
</div>';
                        $content = $content2;
                        unset($content2);

                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $content,
                        ];
                    }
                }
            }
        }

        return $returnArr;
    }

    /** Подсчет непрочитанных комментариев в задачах */
    public function conversationTaskUnreadCount(array $result): int
    {
        $sum = 0;

        $tasksIds = [];

        foreach ($result as $data) {
            $tasksIds[] = $data['id'];
        }

        if (count($tasksIds) > 0) {
            $result = DB->select(
                'conversation',
                [
                    'obj_type' => '{task_comment}',
                    'obj_id' => $tasksIds,
                ],
            );
            $cIds = [];

            foreach ($result as $data) {
                $cIds[] = $data['id'];
            }

            if (count($cIds) > 0) {
                $result = DB->query(
                    "SELECT
                " . ($_ENV['DATABASE_TYPE'] === 'pgsql' ? "distinct on (c.id) c.id,
                CASE WHEN ((cms.message_deleted='0' OR cms.message_deleted IS NULL) AND (cms.message_read!='1' OR cms.message_read IS NULL)) THEN 1 ELSE 0 END" : "SUM(IF((cms.message_deleted='0' OR cms.message_deleted IS NULL) AND (cms.message_read!='1' OR cms.message_read IS NULL),1,0))") . " as new_messages_count
            FROM conversation c
            LEFT JOIN relation r2 ON
                r2.obj_id_to=c.id AND
                (
                    r2.obj_id_from!=:obj_id_from AND
                    r2.obj_id_from IS NOT NULL AND
                    r2.type='{member}' AND
                    r2.obj_type_from='{user}' AND
                    r2.obj_type_to='{conversation}'
                )
            LEFT JOIN conversation_message cm ON
                cm.conversation_id=c.id
            LEFT JOIN conversation_message_status cms ON
                cms.message_id=cm.id AND
                cms.user_id=:user_id
            WHERE
                cm.id IS NOT NULL AND
                c.id IN (:ids)" . ($_ENV['DATABASE_TYPE'] === 'pgsql' ? "" : "
            GROUP BY
                c.id"),
                    [
                        ['obj_id_from', CURRENT_USER->id()],
                        ['user_id', CURRENT_USER->id()],
                        ['ids', $cIds],
                    ],
                );

                foreach ($result as $data) {
                    $sum += $data['new_messages_count'];
                }
            }
        }

        return $sum;
    }

    /** Подсчет количества того или иного типа задач пользователя или выдачи MYSQL-результата по ним */
    public function getTasksData(string $objType, bool $countOnly = false, string|int $objId = 'all', bool|array $myProjects = false): array|int
    {
        $query = '';
        $params = [];

        $postgreInjection = '';
        $postgreInjection2 = '';
        $editableSql = "IF((te.creator_id=:creator_id_1 OR r4.type IS NOT NULL),'true','false') AS editable";
        $params[] = ['creator_id_1', CURRENT_USER->id()];
        $editableSql_2 = "IF((te.creator_id=:creator_id_2 OR r4.type IS NOT NULL OR r5.type IS NOT NULL),'true','false') AS editable";
        $params[] = ['creator_id_2', CURRENT_USER->id()];

        if ($_ENV['DATABASE_TYPE'] === 'pgsql') {
            if ($objType !== 'closed') {
                $postgreInjection = "LEFT JOIN (SELECT id, (date_to::date < CURRENT_DATE + INTERVAL '1 DAY') AS sel1, (date_to::date = CURRENT_DATE + INTERVAL '1 DAY') AS sel2, ((date_to::date > CURRENT_DATE + INTERVAL '1 DAY' AND date_to::date < CURRENT_DATE + INTERVAL '2 DAYS')) AS sel3, (date_to::date > CURRENT_DATE + INTERVAL '2 DAYS') AS sel4, (date_to::date IS NOT NULL) AS sel5 FROM task_and_event) AS te2 ON te2.id=te.id ";
                $postgreInjection2 = 'te2.sel1, te2.sel2, te2.sel3, te2.sel4, te2.sel5, ';
            }

            $editableSql = "(CASE WHEN (te.creator_id=:creator_id_3 OR r4.type IS NOT NULL) THEN 'true' ELSE 'false' END) AS editable";
            $params[] = ['creator_id_3', CURRENT_USER->id()];
            $editableSql_2 = "(CASE WHEN (te.creator_id=:creator_id_4 OR r4.type IS NOT NULL OR r5.type IS NOT NULL) THEN 'true' ELSE 'false' END) AS editable";
            $params[] = ['creator_id_4', CURRENT_USER->id()];
        }

        $params[] = ['obj_id_from_1', CURRENT_USER->id()];
        $params[] = ['obj_id_from_2', CURRENT_USER->id()];
        $params[] = ['obj_id_from_3', CURRENT_USER->id()];
        $params[] = ['obj_id_to', $objId];
        $params[] = ['creator_id', CURRENT_USER->id()];

        if ($objType === 'mine') {
            $query = 'SELECT DISTINCT ' . $postgreInjection2 . "te.*, r.obj_type_to AS parent_type, r.obj_id_to AS parent_id, 'true' AS editable FROM task_and_event te " . $postgreInjection . "LEFT JOIN relation r ON te.id=r.obj_id_from AND (r.obj_type_to='{project}' OR r.obj_type_to='{community}') AND r.obj_type_from='{task}' AND r.type='{child}' LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from_1 WHERE r2.type='{responsible}'" . ($objId !== 'all' ? ' AND r.obj_id_to=:obj_id_to' : '') . " AND te.status!='{closed}' AND te.status!='{rejected}' AND te.status!='{delayed}'";
        } elseif ($objType === 'mine_full_list') {
            $query = 'SELECT DISTINCT ' . $postgreInjection2 . 'te.*, r.obj_type_to AS parent_type, r.obj_id_to AS parent_id, '
                . $editableSql . ' FROM task_and_event te ' . $postgreInjection . "LEFT JOIN relation r ON te.id=r.obj_id_from AND (r.obj_type_to='{project}' OR r.obj_type_to='{community}') AND r.obj_type_from='{task}' AND r.type='{child}' LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from_1 LEFT JOIN relation r4 ON te.id=r4.obj_id_to AND r4.obj_type_from='{user}' AND r4.obj_type_to='{task}' AND r4.obj_id_from=:obj_id_from_2 AND r4.type='{admin}' WHERE (r2.type='{member}' OR te.creator_id=:creator_id)" . ($objId !== 'all' ? ' AND r.obj_id_to=:obj_id_to' : '') . " AND te.status!='{closed}' AND te.status!='{rejected}'";
        } elseif ($objType === 'membered') {
            $query = 'SELECT DISTINCT ' . $postgreInjection2 . 'te.*, r.obj_type_to AS parent_type, r.obj_id_to AS parent_id, '
                . $editableSql . ' FROM task_and_event te ' . $postgreInjection . "LEFT JOIN relation r ON te.id=r.obj_id_from AND (r.obj_type_to='{project}' OR r.obj_type_to='{community}') AND r.obj_type_from='{task}' AND r.type='{child}' LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from_1 LEFT JOIN relation r3 ON te.id=r3.obj_id_to AND r3.obj_type_from='{user}' AND r3.obj_type_to='{task}' AND r3.obj_id_from=:obj_id_from_2 AND r3.type='{responsible}' LEFT JOIN relation r4 ON te.id=r4.obj_id_to AND r4.obj_type_from='{user}' AND r4.obj_type_to='{task}' AND r4.obj_id_from=:obj_id_from_3 AND r4.type='{admin}' WHERE (r2.type='{member}' OR te.creator_id=:creator_id) AND r3.obj_id_to IS NULL" . ($objId !== 'all' ? ' AND r.obj_id_to=:obj_id_to' : '') . " AND te.status!='{closed}' AND te.status!='{rejected}' AND te.status!='{delayed}'";
        } elseif ($objType === 'notmembered') {
            $query = 'SELECT DISTINCT ' . $postgreInjection2 . "te.*, r.obj_type_to AS parent_type, r.obj_id_to AS parent_id, 'false' AS editable FROM task_and_event te " . $postgreInjection . "LEFT JOIN relation r ON te.id=r.obj_id_from AND (r.obj_type_to='{project}' OR r.obj_type_to='{community}') AND r.obj_type_from='{task}' AND r.type='{child}' LEFT JOIN relation r2 ON r2.obj_id_to=r.obj_id_from AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from_1 AND r2.type='{member}' WHERE r2.obj_id_to IS NULL" . ($objId !== 'all' ? ' AND r.obj_id_to=:obj_id_to' : ($myProjects ? ' AND r.obj_id_to IN (:obj_id_tos)' : ' AND r.obj_id_to=0')) . " AND te.status!='{closed}' AND te.status!='{rejected}' AND te.status!='{delayed}' AND te.creator_id!=:creator_id";
            $params[] = ['obj_id_tos', $myProjects];
        } elseif ($objType === 'delayed') {
            $query = 'SELECT DISTINCT ' . $postgreInjection2 . 'te.*, r.obj_type_to AS parent_type, r.obj_id_to AS parent_id, '
                . $editableSql_2 . ' FROM task_and_event te ' . $postgreInjection . "LEFT JOIN relation r ON te.id=r.obj_id_from AND (r.obj_type_to='{project}' OR r.obj_type_to='{community}') AND r.obj_type_from='{task}' AND r.type='{child}' LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from_1 AND r2.type NOT IN (:types) LEFT JOIN relation r4 ON te.id=r4.obj_id_to AND r4.obj_type_from='{user}' AND r4.obj_type_to='{task}' AND r4.obj_id_from=:obj_id_from_2 AND r4.type='{admin}' LEFT JOIN relation r5 ON te.id=r5.obj_id_to AND r5.obj_type_from='{user}' AND r5.obj_type_to='{task}' AND r5.obj_id_from=:obj_id_from_3 AND r5.type='{responsible}' WHERE (r2.obj_id_to IS NOT NULL OR te.creator_id=:creator_id)" . ($objId !== 'all' ? ' AND r.obj_id_to=:obj_id_to' : '') . " AND te.status='{delayed}'";
            $params[] = ['types', RightsHelper::getBannedTypes()];
        } elseif ($objType === 'closed') {
            $query = 'SELECT DISTINCT ' . $postgreInjection2 . 'te.*, GREATEST(te.updated_at, MAX(c.updated_at)) as maxed_updated_at, r.obj_type_to AS parent_type, r.obj_id_to AS parent_id, ' . $editableSql_2 . ' FROM task_and_event te ' . $postgreInjection . "LEFT JOIN relation r ON te.id=r.obj_id_from AND (r.obj_type_to='{project}' OR r.obj_type_to='{community}') AND r.obj_type_from='{task}' AND r.type='{child}' LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from_1 AND r2.type NOT IN (:types) LEFT JOIN relation r4 ON te.id=r4.obj_id_to AND r4.obj_type_from='{user}' AND r4.obj_type_to='{task}' AND r4.obj_id_from=:obj_id_from_2 AND r4.type='{admin}' LEFT JOIN relation r5 ON te.id=r5.obj_id_to AND r5.obj_type_from='{user}' AND r5.obj_type_to='{task}' AND r5.obj_id_from=:obj_id_from_3 AND r5.type='{responsible}' LEFT JOIN conversation c ON c.obj_id=te.id AND c.obj_type='{task_comment}' WHERE (r2.obj_id_to IS NOT NULL OR te.creator_id=:creator_id)" . ($objId !== 'all' ? ' AND r.obj_id_to=:obj_id_to' : '') . " AND (te.status='{closed}' OR te.status='{rejected}')";
            $params[] = ['types', RightsHelper::getBannedTypes()];
        }

        if ($_ENV['DATABASE_TYPE'] === 'pgsql') {
            if ($objType !== 'closed') {
                $query .= ' ORDER BY te2.sel1 DESC, te2.sel2 DESC, te2.sel3 DESC, te2.sel4 DESC, te2.sel5 DESC, te.date_to ASC';
            } else {
                $query .= ' GROUP BY te.id, parent_type, parent_id, editable, maxed_updated_at ORDER BY maxed_updated_at DESC';
            }
        } elseif ($objType !== 'closed') {
            $query .= ' ORDER BY te.date_to<DATE_ADD(CURDATE(), INTERVAL 1 DAY) DESC, te.date_to=DATE_ADD(CURDATE(), INTERVAL 1 DAY) DESC, (te.date_to>DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND te.date_to<DATE_ADD(CURDATE(), INTERVAL 2 DAY)) DESC, te.date_to>DATE_ADD(CURDATE(), INTERVAL 2 DAY) DESC, te.date_to IS NOT NULL DESC, te.date_to ASC';
        } else {
            $query .= ' GROUP BY te.id, parent_type, parent_id ORDER BY maxed_updated_at DESC';
        }

        $result = DB->query($query, $params);

        if (!$countOnly) {
            $data = [];
            $taskIds = [];

            if ($result) {
                foreach ($result as $taskData) {
                    $data['id_' . $taskData['id']] = array_merge($taskData, ['level' => 0]);
                    $taskIds[] = $taskData['id'];
                }
                $taskIds = array_reverse($taskIds);
            }

            if (count($taskIds) > 0) {
                if ($_ENV['DATABASE_TYPE'] === 'pgsql') {
                    $countFields = 0;
                    $orderBy = 'CASE';

                    foreach ($taskIds as $value) {
                        ++$countFields;
                        $orderBy .= " WHEN te.id='" . $value . "' THEN " . $countFields;
                    }
                    $orderBy .= ' ELSE ' . ($countFields + 1) . ' END';

                    $result = DB->query(
                        "SELECT te.id, r.obj_id_to AS parent_task_id FROM task_and_event AS te LEFT JOIN relation r ON r.obj_id_from=te.id AND r.obj_type_from='{task}' AND r.obj_type_to='{task}' AND r.type='{child}' WHERE te.id IN (:ids) AND r.obj_id_to IS NOT NULL ORDER BY " . $orderBy,
                        [
                            ['ids', $taskIds],
                        ],
                    );
                } else {
                    $result = DB->query(
                        "SELECT te.id, r.obj_id_to AS parent_task_id FROM task_and_event AS te LEFT JOIN relation r ON r.obj_id_from=te.id AND r.obj_type_from='{task}' AND r.obj_type_to='{task}' AND r.type='{child}' WHERE te.id IN (:ids) AND r.obj_id_to IS NOT NULL ORDER BY FIELD(te.id, :task_ids)",
                        [
                            ['ids', $taskIds],
                            ['task_ids', $taskIds],
                        ],
                    );
                }

                foreach ($result as $taskParentRelation) {
                    if (isset($data['id_' . $taskParentRelation['parent_task_id']])) {
                        $p1 = array_splice($data, array_search('id_' . $taskParentRelation['id'], array_keys($data)), 1);
                        $p2 = array_splice($data, 0, array_search('id_' . $taskParentRelation['parent_task_id'], array_keys($data)) + 1);
                        $data = array_merge($p2, $p1, $data);
                        $data['id_' . $taskParentRelation['id']]['level'] = $data['id_' . $taskParentRelation['parent_task_id']]['level'] + 1;
                    }
                }
            }

            return $data;
        } else {
            return count($result);
        }
    }

    /** Авторасчет дат начала и окончания объекта в зависимости от дат начала и окончания родительского объекта.
     *
     * @return int[]
     */
    public function followingObjectDateFromDateTo(
        int|string $followingObjectDateFrom,
        int|string $followingObjectDateTo,
        int|string $precedingObjectDateFrom,
        int|string $precedingObjectDateTo,
    ): array {
        if (!is_numeric($followingObjectDateFrom) && $followingObjectDateFrom !== '') {
            $followingObjectDateFrom = strtotime($followingObjectDateFrom);
        }

        if (!is_numeric($followingObjectDateTo) && $followingObjectDateTo !== '') {
            $followingObjectDateTo = strtotime($followingObjectDateTo);
        }

        if (!is_numeric($precedingObjectDateFrom) && $precedingObjectDateFrom !== '') {
            $precedingObjectDateFrom = strtotime($precedingObjectDateFrom);
        }

        if (!is_numeric($precedingObjectDateTo) && $precedingObjectDateTo !== '') {
            $precedingObjectDateTo = strtotime($precedingObjectDateTo);
        }

        $result = [
            'date_from' => $followingObjectDateFrom,
            'date_to' => $followingObjectDateTo,
        ];

        if ($precedingObjectDateFrom > 0 || $precedingObjectDateTo > 0) {
            $dateFromMin = (int) $precedingObjectDateFrom;

            // проверяем, есть ли дата окончания предыдущего события
            if ((int) $precedingObjectDateTo > 0) {
                $dateFromMin = (int) $precedingObjectDateTo;
            }

            // если указана и дата окончания и дата начала, считаем разницу
            $diffBetweenFromAndTo = 0;

            if ($followingObjectDateTo > 0 && $followingObjectDateFrom > 0) {
                $diffBetweenFromAndTo = $followingObjectDateTo - $followingObjectDateFrom;
            }

            // если есть дата начала и при этом она меньше минимальной даты после предыдущего объекта
            if ($followingObjectDateFrom > 0 && $followingObjectDateFrom < $dateFromMin) {
                $result['date_from'] = $dateFromMin;

                // сдвигаем дату окончания, если она была указана
                if ($followingObjectDateTo > 0) {
                    $result['date_to'] = $dateFromMin + $diffBetweenFromAndTo;
                }
            } elseif ($followingObjectDateTo > 0 && $followingObjectDateTo < $dateFromMin) {
                // если есть только дата окончания и при этом она меньше минимальной даты после предыдущего объекта
                $result['date_to'] = strtotime('+1 hour', $dateFromMin);
            }
        }

        return $result;
    }

    /** @return array{string, string} */
    public function getStateNameAndCss(TaskModel $task): array
    {
        $LOCALE = $this->LOCALE;

        $stateName = '';
        $stateCssClass = '';

        if ($task->status->get() === '{closed}') {
            $stateName = $LOCALE['state_name_closed'];
            $stateCssClass = 'green';
        } elseif (!is_null($task->date_to->get()) && $task->date_to->get() < new DateTime('now')) {
            $stateName = $LOCALE['state_name_hot'];
            $stateCssClass = 'red';
        } elseif ($task->date_from->get() < new DateTime('now') && $task->date_to->get() > new DateTime('now')) {
            $stateName = $LOCALE['state_name_current'];
            $stateCssClass = 'yellow';
        } else {
            $stateName = $LOCALE['state_name_future'];
            $stateCssClass = 'green';
        }

        return [
            $stateName,
            $stateCssClass,
        ];
    }

    public function postCreate(array $successfulResultsIds): void
    {
        $objId = $this->getObjId();
        $objType = $this->getObjType();

        $fullListOfIds[] = $successfulResultsIds[0];

        if (!$this->skipPostCreate) {
            // если нам нужно создать копии задачи и основная из них успешно прошла проверки и при этом обладает датой начала хотя бы
            $repeatMode = trim($_REQUEST['repeat_mode'][0] ?? '');
            $repeatUntil = trim($_REQUEST['repeat_until'][0] ?? '');
            $dateFrom = date('Y-m-d H:i', strtotime($_REQUEST['date_from'][0]));

            if (!in_array($repeatMode, ['single', ''])) {
                // делаем защиту от дурака: если "повторять до" не заполнено, ставим повторения не далее, чем на год. Если режим повторения = "ежегодно", то ставим на 10 лет.
                if ($repeatUntil === '') {
                    if ($repeatMode === 'every_year') {
                        $repeatUntil = date('Y-m-d H:i', strtotime('+10 year', strtotime($dateFrom)));
                    } else {
                        $repeatUntil = date('Y-m-d H:i', strtotime('+1 year', strtotime($dateFrom)));
                    }
                }

                // формируем полный список дат, на которые нужно создать задачи
                $additionalDateFromAndDateTo = [];
                $diffBetweenFromAndTo = strtotime($_REQUEST['date_to'][0]) - strtotime($dateFrom);
                $diffBetweenFromAndFrom = match ($repeatMode) {
                    'once_per_week' => '+1 week',
                    'every_month' => '+1 month',
                    'every_year' => '+1 year',
                    default => '+1 day',
                };

                // если задача следует за какой-то другой, нужно сместить ее в конец указанной по датам автоматически
                if ((int) $_REQUEST['following_task'][0] > 0) {
                    $precedingTaskData = DB->findObjectById((int) $_REQUEST['following_task'][0], 'task_and_event');

                    if ($precedingTaskData['id'] !== '') {
                        $fixedDates = $this->followingObjectDateFromDateTo(
                            strtotime($_REQUEST['date_from'][0]),
                            strtotime($_REQUEST['date_to'][0]),
                            strtotime($precedingTaskData['date_from']),
                            strtotime($precedingTaskData['date_to']),
                        );

                        $dateFrom = date('Y-m-d H:i', $fixedDates['date_from']);
                    }
                }

                $dateFrom = date('Y-m-d H:i', strtotime($diffBetweenFromAndFrom, strtotime($dateFrom)));

                while (strtotime($dateFrom) <= strtotime($repeatUntil)) {
                    if ($repeatMode !== 'every_workday' || date('N', strtotime($dateFrom)) <= 5) {
                        $additionalDateFromAndDateTo[] = [
                            'date_from' => $dateFrom,
                            'date_to' => date('Y-m-d H:i', strtotime($dateFrom) + $diffBetweenFromAndTo),
                        ];
                    }

                    $dateFrom = date('Y-m-d H:i', strtotime($diffBetweenFromAndFrom, strtotime($dateFrom)));
                }

                $this->skipPostCreate = true;

                foreach ($additionalDateFromAndDateTo as $value) {
                    // подменяем даты в $_REQUEST на нужные в зависимости от настроек
                    $_REQUEST['date_from'][0] = $value['date_from'];
                    $_REQUEST['date_to'][0] = $value['date_to'];

                    $this->entity->fraymAction(true, true);
                    $fullListOfIds[] = (int) DB->lastInsertId();
                }

                $this->skipPostCreate = false;
            }

            $LOCALE = $this->LOCALE;

            foreach ($fullListOfIds as $idKey => $idValue) {
                RightsHelper::addRights('{admin}', '{task}', $idValue);
                RightsHelper::addRights('{member}', '{task}', $idValue);

                if ($objType !== '' && $objId > 0) {
                    RightsHelper::addRights('{child}', DataHelper::addBraces($objType), $objId, '{task}', $idValue);
                }

                if ((int) $_REQUEST['parent_task'][0] > 0) {
                    RightsHelper::addRights(
                        '{child}',
                        '{task}',
                        $_REQUEST['parent_task'][0],
                        '{task}',
                        $idValue,
                    );
                }

                if ((int) $_REQUEST['following_task'][0] > 0) {
                    RightsHelper::addRights(
                        '{following}',
                        '{task}',
                        $_REQUEST['following_task'][0],
                        '{task}',
                        $idValue,
                    );

                    // если мы создавали одну задачу и в результате не было доп.обработки дат начала и конца
                    if (count($fullListOfIds) === 1) {
                        $precedingTaskData = DB->findObjectById((int) $_REQUEST['following_task'][0], 'task_and_event');

                        if ($precedingTaskData['id'] !== '') {
                            $fixedDates = TaskService::followingObjectDateFromDateTo(
                                strtotime($_REQUEST['date_from'][0]),
                                strtotime($_REQUEST['date_to'][0]),
                                strtotime($precedingTaskData['date_from']),
                                strtotime($precedingTaskData['date_to']),
                            );

                            DB->update(
                                'task_and_event',
                                [
                                    'date_from' => ($fixedDates['date_from'] > 0 ? "'" . date('Y-m-d H:i', $fixedDates['date_from']) . "'" : null),
                                    'date_to' => ($fixedDates['date_to'] > 0 ? "'" . date('Y-m-d H:i', $fixedDates['date_to']) . "'" : null),
                                ],
                                [
                                    'id' => $idValue,
                                ],
                            );
                        }
                    }
                }

                $newResponsible = CURRENT_USER->id(); // заглушка для отсылки приглашений участникам ниже (на тот случай, если ответственного нет)

                if ($_REQUEST['responsible'][0] !== '') {
                    $newResponsible = $_REQUEST['responsible'][0];
                    RightsHelper::addRights('{responsible}', '{task}', $idValue, '{user}', $newResponsible);
                    RightsHelper::addRights('{member}', '{task}', $idValue, '{user}', $newResponsible);
                }

                if (isset($_REQUEST['user_id'][0])) {
                    /** @var ConversationService $conversationService */
                    $conversationService = CMSVCHelper::getService('conversation');

                    foreach ($_REQUEST['user_id'][0] as $key => $value) {
                        if ($value === 'on' && $key !== CURRENT_USER->id() && $key !== $newResponsible) {
                            $conversationService->sendInvitation('{task}', $idValue, $key);
                        }
                    }
                }

                if ($this->getMessageIdDefault()) {
                    RightsHelper::addRights(
                        '{child}',
                        '{conversation_message}',
                        $this->getMessageIdDefault(),
                        '{task}',
                        $idValue,
                    );
                }

                /** @var MessageService $messageService */
                $messageService = CMSVCHelper::getService('message');
                $messageService->newMessage(
                    null,
                    $LOCALE['messages']['task_change_message_9'],
                    '',
                    [],
                    null,
                    ['obj_type' => '{task_comment}', 'obj_id' => $idValue, 'sub_obj_type' => ''],
                );

                // для всех задач кроме основной делаем ссылку на основную типа {same}, что означает, что это задачи из ряда задач
                if ($idKey > 0) {
                    RightsHelper::addRights('{same}', '{task}', $fullListOfIds[0], '{task}', $idValue);
                }
            }
        }

        $this->entity->fraymActionRedirectPath = '/task/' . $fullListOfIds[0] . '/';
    }

    public function preChange(): void
    {
        $this->savedTaskData = $this->get(DataHelper::getId());
    }

    public function postChange(array $successfulResultsIds): void
    {
        $LOCALE = $this->LOCALE;
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_TASK_ELEMENTS = LocaleHelper::getLocale(['task', 'fraym_model', 'elements']);
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $id = DataHelper::getId();
        $objId = $this->getObjId();
        $objType = $this->getObjType();

        $fullListOfIds[] = $id;
        $bosses = $this->getBosses();
        $members = $this->getMembers();
        $dataBeforeSave = $this->savedTaskData;

        if (!$this->skipPostChange) {
            // если нам нужно менять копии задачи и основная из них успешно прошла проверки и при этом обладает датой начала хотя бы
            $repeatedTasksChange = trim($_REQUEST['repeated_tasks_change'][0] ?? '');
            $dateFrom = date('Y-m-d H:i', strtotime($_REQUEST['date_from'][0]));
            $dateTo = date('Y-m-d H:i', strtotime($_REQUEST['date_to'][0]));

            if ($repeatedTasksChange === 'all') {
                // считаем сдвиг новых date_from и date_to
                $diffBetweenFromAndFrom = strtotime($dataBeforeSave['date_from']) - strtotime($dateFrom);

                if ($dateTo > 0) {
                    $diffBetweenFromAndTo = strtotime($dateTo) - strtotime($dateFrom);
                } else {
                    $diffBetweenFromAndTo = false;
                }

                // находим все связанные задачи (вне зависимости от того, изменили мы основную или задачу из ряда)
                $mainTaskId = RightsHelper::findOneByRights('{same}', '{task}', null, '{task}', $id);

                if (!$mainTaskId) {
                    $mainTaskId = $id;
                }
                $sameTasksIds = RightsHelper::findByRights('{same}', '{task}', $mainTaskId, '{task}', false);

                if (!$sameTasksIds) {
                    $sameTasksIds = [];
                }
                $sameTasksIds[] = $mainTaskId;

                $this->skipPostChange = true;

                foreach ($sameTasksIds as $taskId) {
                    if ($taskId !== $dataBeforeSave['id']) {
                        // находим объект, чтобы с ним работать
                        $subtaskDataBeforeSave = DB->findObjectById($taskId, 'task_and_event');

                        // подменяем даты в $_REQUEST на новые
                        $_REQUEST['date_from'][0] = date('Y-m-d H:i', strtotime($subtaskDataBeforeSave['date_from']) - $diffBetweenFromAndFrom);
                        $_REQUEST['date_to'][0] = ($diffBetweenFromAndTo ? date('Y-m-d H:i', strtotime($subtaskDataBeforeSave['date_from']) - $diffBetweenFromAndFrom + $diffBetweenFromAndTo) : '');
                        $_REQUEST['updated_at'][0] = time();
                        $_REQUEST['id'][0] = $taskId;

                        $this->entity->fraymAction(true, true);

                        $fullListOfIds[] = $taskId;
                    }
                }

                $this->skipPostChange = false;
            }

            foreach ($fullListOfIds as $idValue) {
                $taskData = DB->findObjectById($idValue, 'task_and_event');

                // нельзя deleteRights ко всем объектам, потому как это убьет связь child между задачей и родительской задачей
                RightsHelper::deleteRights('{child}', '{project}', null, '{task}', $idValue);
                RightsHelper::deleteRights('{child}', '{community}', null, '{task}', $idValue);

                if ($objType !== '' && $objId > 0) {
                    RightsHelper::addRights('{child}', DataHelper::addBraces($objType), $objId, '{task}', $idValue);
                }

                $responsibleId = RightsHelper::findByRights(
                    '{responsible}',
                    '{task}',
                    $idValue,
                    '{user}',
                    false,
                );

                if ($_REQUEST['responsible'][0] !== '' && $responsibleId !== $_REQUEST['responsible'][0]) {
                    $newResponsible = $_REQUEST['responsible'][0];
                    RightsHelper::deleteRights('{responsible}', '{task}', $idValue, '{user}', 0);
                    RightsHelper::addRights('{responsible}', '{task}', $idValue, '{user}', $newResponsible);
                    RightsHelper::addRights('{member}', '{task}', $idValue, '{user}', $newResponsible);

                    $userData = $this->getUserService()->get(CURRENT_USER->id());
                    $message = sprintf(
                        $LOCALE['messages']['task_change_message_7'],
                        $this->getUserService()->showName($userData),
                        $userData->gender->get() === 2 ? 'а' : '',
                        ABSOLUTE_PATH,
                        $idValue,
                        DataHelper::escapeOutput($taskData['name']),
                    );
                    MessageHelper::prepareEmail(
                        (int) $newResponsible,
                        [
                            'author_name' => $LOCALE_GLOBAL['sitename'],
                            'author_email' => $LOCALE_GLOBAL['admin_mail'],
                            'name' => $LOCALE['messages']['task_change_message_8'],
                            'content' => $message,
                            'obj_type' => 'task',
                            'obj_id' => $idValue,
                        ],
                    );
                }

                RightsHelper::deleteRights('{child}', '{task}', null, '{task}', $idValue);

                if ((int) $_REQUEST['parent_task'][0] > 0) {
                    RightsHelper::addRights(
                        '{child}',
                        '{task}',
                        $_REQUEST['parent_task'][0],
                        '{task}',
                        $idValue,
                    );
                }

                RightsHelper::deleteRights('{following}', '{task}', null, '{task}', $idValue);

                if ((int) $_REQUEST['following_task'][0] > 0) {
                    RightsHelper::addRights(
                        '{following}',
                        '{task}',
                        $_REQUEST['following_task'][0],
                        '{task}',
                        $idValue,
                    );

                    // если это не задача из ряда таких же, а уникальная и в результате не было доп.обработки дат начала и конца
                    if (count($fullListOfIds) === 1) {
                        $precedingTaskData = DB->findObjectById((int) $_REQUEST['following_task'][0], 'task_and_event');

                        if ($precedingTaskData['id'] !== '') {
                            $fixedDates = $this->followingObjectDateFromDateTo(
                                strtotime($_REQUEST['date_from'][0]),
                                strtotime($_REQUEST['date_to'][0]),
                                strtotime($precedingTaskData['date_from']),
                                strtotime($precedingTaskData['date_to']),
                            );

                            DB->update(
                                'task_and_event',
                                [
                                    'date_from' => ($fixedDates['date_from'] > 0 ? "'" . date('Y-m-d H:i', $fixedDates['date_from']) . "'" : null),
                                    'date_to' => ($fixedDates['date_to'] > 0 ? "'" . date('Y-m-d H:i', $fixedDates['date_to']) . "'" : null),
                                ],
                                [
                                    'id' => $idValue,
                                ],
                            );
                        }
                    }
                }

                if (isset($_REQUEST['user_id'][0])) {
                    /** @var ConversationService $conversationService */
                    $conversationService = CMSVCHelper::getService('conversation');

                    foreach ($_REQUEST['user_id'][0] as $key => $value) {
                        if ($value === 'on' && $key !== CURRENT_USER->id() && !in_array(
                            $key,
                            $bosses,
                        )) {
                            if (in_array($key, $members)) {
                                // do nothing
                            } else {
                                $conversationService->sendInvitation('{task}', $idValue, $key);
                            }
                        }
                    }
                }

                foreach ($members as $key => $value) {
                    if ($_REQUEST['user_id'][0][$value] !== 'on' && $value !== CURRENT_USER->id() && !in_array($value, $bosses)) {
                        RightsHelper::deleteRights('{member}', '{task}', $idValue, '{user}', $value);
                        unset($members[$key]);
                    }
                }

                // защита от дурака: что бы человек ни сделал при изменении задачи, оставить его хотя бы ее участником
                RightsHelper::addRights('{member}', '{task}', $idValue);

                $userData = $this->getUserService()->get(CURRENT_USER->id());
                $message = sprintf(
                    $LOCALE['messages']['task_change_message_1'],
                    $this->getUserService()->showName($userData),
                    $userData->gender->get() === 2 ? 'а' : '',
                    ABSOLUTE_PATH,
                    $taskData['id'],
                    DataHelper::escapeOutput($taskData['name']),
                );

                foreach ($LOCALE_TASK_ELEMENTS['status']['values'] as $status) {
                    if ($status[0] === $_REQUEST['status'][0]) {
                        $message .= ($taskData['status'] !== $_REQUEST['status'][0] ? '<i>' : '') .
                            $status[1] .
                            ($taskData['status'] !== $_REQUEST['status'][0] ? '</i>' : '');
                        break;
                    }
                }
                $message .= '.<br><b>' . $LOCALE['messages']['task_change_message_2'] . ':</b> ';

                foreach ($LOCALE_TASK_ELEMENTS['priority']['values'] as $priority) {
                    if ($priority[0] === $_REQUEST['priority'][0]) {
                        $message .= ($taskData['priority'] !== $_REQUEST['priority'][0] ? '<i>' : '') .
                            $priority[1] .
                            ($taskData['priority'] !== $_REQUEST['priority'][0] ? '</i>' : '');
                        break;
                    }
                }
                $message .= '.<br><b>' . $LOCALE['messages']['task_change_message_3'] . ':</b> ' .
                    ($taskData['date_from'] !== date('Y-m-d H:i:s', strtotime($_REQUEST['date_from'][0])) ? '<i>' : '') .
                    ($_REQUEST['date_from'][0] !== '' ? date('d.m.Y H:i:s', strtotime($_REQUEST['date_from'][0])) : $LOCALE_CONVERSATION['date_to_not_set']) .
                    ($taskData['date_from'] !== date('Y-m-d H:i:s', strtotime($_REQUEST['date_from'][0])) ? '</i>' : '') .
                    '<br><b>' . $LOCALE['messages']['task_change_message_4'] . ':</b> ' .
                    ($taskData['date_to'] !== date('Y-m-d H:i:s', strtotime($_REQUEST['date_to'][0])) ? '<i>' : '') .
                    ($_REQUEST['date_to'][0] !== '' ? date('d.m.Y H:i:s', strtotime($_REQUEST['date_to'][0])) : $LOCALE_CONVERSATION['date_to_not_set']) .
                    ($taskData['date_to'] !== date('Y-m-d H:i:s', strtotime($_REQUEST['date_to'][0])) ? '</i>' : '') .
                    ($_REQUEST['result'][0] !== '' ? '<br><b>' . $LOCALE['messages']['task_change_message_10'] . ':</b><br>' . DataHelper::escapeOutput($_REQUEST['result'][0], EscapeModeEnum::forHTMLforceNewLines) : '') .
                    ($_REQUEST['description'][0] !== '' ? '<br><b>' . $LOCALE['messages']['task_change_message_5'] . ':</b><br>' . DataHelper::escapeOutput($_REQUEST['description'][0], EscapeModeEnum::forHTMLforceNewLines) : '');

                MessageHelper::prepareEmails(
                    $members,
                    [
                        'author_name' => $LOCALE_GLOBAL['sitename'],
                        'author_email' => $LOCALE_GLOBAL['admin_mail'],
                        'name' => $LOCALE['messages']['task_change_message_6'],
                        'content' => $message,
                        'obj_type' => 'task',
                        'obj_id' => $idValue,
                    ],
                );
            }
        }
    }

    public function postDelete(array $successfulResultsIds): void
    {
        $id = DataHelper::getId();
        $objId = $this->getObjId();
        $objType = $this->getObjType();

        $fullListOfIds[] = $id;

        if (!$this->skipPostDelete) {
            if ($_REQUEST['all'] === 'true') {
                // находим все связанные задачи (вне зависимости от того, изменили мы основную или задачу из ряда)
                $deletedTaskId = $id;
                $mainTaskId = RightsHelper::findOneByRights('{same}', '{task}', null, '{task}', $id);

                if (!$mainTaskId) {
                    $mainTaskId = $id;
                }
                $sameTasksIds = RightsHelper::findByRights('{same}', '{task}', $mainTaskId, '{task}');

                if (!$sameTasksIds) {
                    $sameTasksIds = [];
                }
                $sameTasksIds[] = $mainTaskId;

                $this->skipPostDelete = true;

                foreach ($sameTasksIds as $taskId) {
                    if ($taskId > 0) {
                        $taskData = DB->findObjectById($taskId, 'task_and_event', true);

                        if ($taskId !== $deletedTaskId && isset($taskData['id'])) {
                            $_REQUEST['id'][0] = $taskId;
                            $this->entity->fraymAction(true, true);
                        } elseif (!isset($taskData['id'])) {
                            RightsHelper::deleteRights('{same}', '{task}', $mainTaskId, '{task}', $taskId);
                        }
                    }
                }

                $this->skipPostDelete = false;
            }

            foreach ($fullListOfIds as $idFromList) {
                if ($idFromList > 0) {
                    RightsHelper::deleteRights(null, '{task}', $idFromList, '{user}', 0);
                    RightsHelper::deleteRights(null, '{task}', $idFromList, '{task}');
                    RightsHelper::deleteRights(null, '{task}', $idFromList, '{file}');
                    RightsHelper::deleteRights(null, '{task}', $idFromList, '{conversation}');
                    RightsHelper::deleteRights(null, null, null, '{task}', $idFromList);
                }
            }

            if ($objType !== '' && !in_array($objId, ['', 'all'])) {
                $this->entity->fraymActionRedirectPath = ABSOLUTE_PATH . '/' . $objType . '/' . $objId . '/';
            } else {
                $this->entity->fraymActionRedirectPath = ABSOLUTE_PATH . '/tasklist/';
            }
        }
    }

    public function isTaskAdmin(int $taskId): bool
    {
        return RightsHelper::checkRights('{admin}', '{task}', $taskId);
    }

    public function isTaskResponsible(int $taskId): bool
    {
        return RightsHelper::checkRights('{responsible}', '{task}', $taskId);
    }

    public function hasTaskAccess(int $taskId): bool
    {
        return $this->isTaskAdmin($taskId) || RightsHelper::checkAnyRights('{task}', $taskId);
    }

    public function hasTaskParentAccess(): bool
    {
        return RightsHelper::checkRights(['{admin}', '{gamemaster}', '{moderator}'], $this->getObjType(), $this->getObjId());
    }

    public function hasAccessToChilds(): bool
    {
        $parentData = $this->getParentData();

        return ($parentData['access_to_childs'] ?? false) === 1;
    }

    public function getParentData(): ?array
    {
        $objId = $this->getObjId();

        if (is_numeric($objId)) {
            return DB->findObjectById($objId, $this->getObjType());
        }

        return null;
    }

    public function getBosses(): ?array
    {
        return RightsHelper::findByRights(['{admin}', '{responsible}'], '{task}', DataHelper::getId(), '{user}', false);
    }

    public function getMembers(): ?array
    {
        return RightsHelper::findByRights('{member}', '{task}', DataHelper::getId(), '{user}', false);
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

    public function getObjTypeDefault(): ?string
    {
        return is_array(OBJ_TYPE) ? OBJ_TYPE[0] : OBJ_TYPE;
    }

    public function getObjIdDefault(): int|string|null
    {
        return $this->getObjId();
    }

    public function getObjIdValues(): array
    {
        $objType = $this->getObjType();
        $result = [];

        if ($objType === 'project') {
            $projectsArray = RightsHelper::findByRights(['{admin}', '{gamemaster}', '{moderator}'], '{project}');

            if ($projectsArray) {
                $projectsList = [];
                $projectsData = DB->select(
                    'project',
                    ['id' => $projectsArray],
                    false,
                    ['name'],
                );

                foreach ($projectsData as $projectData) {
                    $projectsList[] = [$projectData['id'], DataHelper::escapeOutput($projectData['name'])];
                }
                $result = $projectsList;

                $this->postModelInitVars['objType'] = 'project';
            }
        } elseif ($objType === 'community') {
            $communitiesArray = RightsHelper::findByRights(['{admin}', '{gamemaster}', '{moderator}'], '{community}');

            if ($communitiesArray) {
                $communitiesList = [];
                $communitiesData = DB->select(
                    'community',
                    ['id' => $communitiesArray],
                    false,
                    ['name'],
                );

                foreach ($communitiesData as $communityData) {
                    $communitiesList[] = [$communityData['id'], DataHelper::escapeOutput($communityData['name'])];
                }
                $result = $communitiesList;
            }
        }

        return $result;
    }

    public function getUserIdDefault(): array
    {
        $result = [CURRENT_USER->id()];

        if (DataHelper::getId() > 0) {
            $result = $this->getMembers();
        } else {
            $messageData = $this->getMessageData();

            if ($messageData) {
                $messages = DB->select(
                    'conversation_message',
                    ['conversation_id' => $messageData['conversation_id']],
                );

                foreach ($messages as $message) {
                    $result[] = $message['creator_id'];
                }
                $result = array_unique($result);
            }
        }

        return $result;
    }

    public function getParentUserIdsValues(): array
    {
        $result = [];

        if (is_null($this->parentUserIdsValues)) {
            $objId = $this->getObjId();
            $objType = $this->getObjType();

            if ($objId > 0 && in_array($objType, ['project', 'community'])) {
                /** Проверяем, нет ли пользователей, которые уже добавлены в задачу, но при этом не состоят ни в какой из групп родительского объекта */
                $usersAlreadyPresent = [];

                if (DataHelper::getId() > 0) {
                    $usersAlreadyPresent = RightsHelper::findByRights(null, '{task}', DataHelper::getId(), '{user}', false);
                }

                // $parentMembers = RightsHelper::findByRights(false, addBraces($objType), $objId, '{user}', false);
                /** Для allrpg поиск сделан исключительно по всяким ответственным, а не просто участникам */
                $parentMembers = RightsHelper::findByRights(
                    ['{admin}', '{gamemaster}', '{moderator}', '{responsible}', '{budget}', '{fee}', '{newsmaker}'],
                    $objType,
                    $objId,
                    '{user}',
                    false,
                );

                if (!is_null($parentMembers)) {
                    $parentMembersData = [];

                    foreach ($parentMembers as $memberId) {
                        $parentMembersData[] = [
                            $memberId,
                            $this->getUserService()->showName($this->getUserService()->get($memberId)),
                        ];

                        if (in_array($memberId, $usersAlreadyPresent)) {
                            unset($usersAlreadyPresent[array_search($memberId, $usersAlreadyPresent)]);
                        }
                    }

                    if (count($usersAlreadyPresent) > 0) {
                        foreach ($usersAlreadyPresent as $memberId) {
                            $parentMembersData[] = [
                                $memberId,
                                $this->getUserService()->showName($this->getUserService()->get($memberId)),
                            ];
                        }
                    }
                    $parentMembersDataSort = [];

                    foreach ($parentMembersData as $key => $row) {
                        $parentMembersDataSort[$key] = $row[1];
                    }
                    array_multisort($parentMembersDataSort, SORT_ASC, $parentMembersData);
                    $result = $parentMembersData;
                }
            } elseif ($this->getObjId() === 'all') {
                $result[] = [
                    CURRENT_USER->id(),
                    $this->getUserService()->showName($this->getUserService()->get(CURRENT_USER->id())),
                ];
            }
        } else {
            $result = $this->parentUserIdsValues;
        }

        return $result;
    }

    public function getUserIdValues(): array
    {
        return $this->getParentUserIdsValues();
    }

    public function getUserIdLocked(): array
    {
        $result = [];

        if (DataHelper::getId() > 0) {
            $result = $this->getBosses() ?? [CURRENT_USER->id()];
        } else {
            $result = [CURRENT_USER->id()];
        }

        return $result;
    }

    public function getResponsibleIdDefault(): ?int
    {
        if (DataHelper::getId() > 0) {
            return RightsHelper::findOneByRights(
                '{responsible}',
                '{task}',
                DataHelper::getId(),
                '{user}',
                false,
            );
        }

        return CURRENT_USER->id();
    }

    public function getResponsibleValues(): array
    {
        return $this->getParentUserIdsValues();
    }

    public function getMessageData(): ?array
    {
        if (is_null($this->messageData)) {
            if (($_REQUEST['message_id'] ?? false) && $this->act === ActEnum::add) {
                $messageData = DB->findObjectById($_REQUEST['message_id'][0] ?? $_REQUEST['message_id'], 'conversation_message');

                if ($messageData) {
                    $this->messageData = $messageData;
                }
            }
        }

        return $this->messageData;
    }

    public function getMessageIdDefault(): ?int
    {
        $messageData = $this->getMessageData();

        if ($messageData) {
            return $messageData['id'];
        }

        return null;
    }

    public function getDescriptionDefault(): string
    {
        $messageData = $this->getMessageData();

        if ($messageData) {
            return DataHelper::escapeOutput($messageData['content']);
        }

        return '';
    }

    public function getDateFromDefault(): DateTimeImmutable
    {
        return new DateTimeImmutable('+1 hour');
    }

    public function getDateToDefault(): DateTimeImmutable
    {
        return new DateTimeImmutable('+2 hours');
    }

    public function getRepeatUntilDefault(): DateTimeImmutable
    {
        return new DateTimeImmutable('+2 hours');
    }

    public function getRepeatedTasksChangeContext(): array
    {
        if (DataHelper::getId()) {
            $taskData = $this->getTaskData();

            if ($taskData && !in_array($taskData['repeat_mode'], ['', 'single'])) {
                return ['task:view', 'task:update'];
            }
        }

        return [];
    }

    /** @return int[] */
    public function getParentObjectTasksIds(): array
    {
        if (is_null($this->parentObjectTasksIds)) {
            $this->parentObjectTasksIds = [];

            $parentTasksIds = [];

            if ($this->getObjId() !== 'all' && $this->getObjId() > 0) {
                $parentTasksIds = RightsHelper::findByRights('{child}', DataHelper::addBraces($this->getObjType()), $this->getObjId(), '{task}');
            } else {
                $data = DB->query(
                    "SELECT DISTINCT te.id FROM task_and_event te LEFT JOIN relation r ON te.id=r.obj_id_from AND (r.obj_type_to='{project}' OR r.obj_type_to='{community}') AND r.obj_type_from='{task}' AND r.type='{child}' LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from WHERE r.obj_id_to IS NULL AND r2.type IN ('{member}','{admin}','{responsible}') AND te.status!='{closed}' AND te.status!='{rejected}'",
                    [
                        ['obj_id_from', CURRENT_USER->id()],
                    ],
                );

                foreach ($data as $noprojectTask) {
                    $parentTasksIds[] = $noprojectTask['id'];
                }
            }

            $this->parentObjectTasksIds = $parentTasksIds;
        }

        return $this->parentObjectTasksIds;
    }

    public function getParentTask(): ?TaskModel
    {
        if (is_null($this->parentTask) && DataHelper::getId() > 0) {
            $parentTaskId = RightsHelper::findOneByRights('{child}', '{task}', null, '{task}', DataHelper::getId());

            if ($parentTaskId) {
                $this->parentTask = $this->get($parentTaskId);
            }
        }

        return $this->parentTask;
    }

    public function getFollowingTask(): ?TaskModel
    {
        if (is_null($this->followingTask) && DataHelper::getId() > 0) {
            $followingTaskId = RightsHelper::findOneByRights('{following}', '{task}', null, '{task}', DataHelper::getId());

            if ($followingTaskId) {
                $this->followingTask = $this->get($followingTaskId);
            }
        }

        return $this->followingTask;
    }

    public function getChildTasksIds(): ?array
    {
        if (is_null($this->childTasksIds) && DataHelper::getId() > 0) {
            $this->childTasksIds = RightsHelper::findByRights('{child}', '{task}', DataHelper::getId(), '{task}');
        }

        return $this->childTasksIds;
    }

    public function getPregoingTasksIds(): ?array
    {
        if (is_null($this->pregoingTasksIds) && DataHelper::getId() > 0) {
            $this->pregoingTasksIds = RightsHelper::findByRights('{following}', '{task}', DataHelper::getId(), '{task}');
        }

        return $this->pregoingTasksIds;
    }

    public function getParentTaskValues(): Generator|array
    {
        $parentTasksIds = $this->getParentObjectTasksIds();

        if (count($parentTasksIds) > 0) {
            $parentTaskId = $this->getParentTask()?->id->getAsInt();
            $childTasksIds = $this->getChildTasksIds();

            $parentTasksData = DB->getArrayOfItems(
                "task_and_event WHERE ((status!='{closed}' AND status!='{rejected}' AND id IN (" . implode(',', $parentTasksIds) . '))' . ($parentTaskId ? ' OR id=' . $parentTaskId : '') . ') ' . (DataHelper::getId() > 0 ? ' AND id!=' . DataHelper::getId() : '') . ($childTasksIds ? ' AND id NOT IN (' . implode(',', $childTasksIds) . ')' : '') . ' ORDER BY name',
                'id',
                'name',
            );

            return $parentTasksData;
        }

        return [];
    }

    public function getParentTaskDefault(): string
    {
        return $this->getParentTask()?->id->getAsInt() ?? '';
    }

    public function getFollowingTaskValues(): Generator|array
    {
        $parentTasksIds = $this->getParentObjectTasksIds();

        if (count($parentTasksIds) > 0) {
            $followingTaskId = $this->getFollowingTask()?->id->getAsInt();
            $pregoingTasksIds = $this->getPregoingTasksIds();
            $childTasksIds = $this->getChildTasksIds();

            $parentTasksData = DB->getArrayOfItems(
                "task_and_event WHERE ((status!='{closed}' AND status!='{rejected}' AND id IN(" . implode(',', $parentTasksIds) . '))' . ($followingTaskId ? ' OR id=' . $followingTaskId : '') . ') ' . (DataHelper::getId() > 0 ? ' AND id!=' . DataHelper::getId() : '') . ($pregoingTasksIds ? ' AND id NOT IN (' . implode(',', $pregoingTasksIds) . ')' : '') . ($childTasksIds ? ' AND id NOT IN (' . implode(',', $childTasksIds) . ')' : '') . ' ORDER BY name',
                'id',
                'name',
            );

            return $parentTasksData;
        }

        return [];
    }

    public function getFollowingTaskDefault(): string
    {
        return $this->getFollowingTask()?->id->getAsInt() ?? '';
    }

    public function getSortStatus(): array
    {
        /** @var TaskModel */
        $taskModel = $this->model;

        return $taskModel->status->getValues();
    }

    public function checkViewRights(): bool
    {
        return CURRENT_USER->isLogged() && DataHelper::getId() > 0 && $this->hasTaskAccess(DataHelper::getId());
    }

    public function checkAddRights(): bool
    {
        return CURRENT_USER->isLogged() && (is_null($this->objType) || $this->getObjId() === 'all' || RightsHelper::checkAnyRights($this->getObjType(), $this->getObjId()));
    }

    public function checkChangeRights(): bool
    {
        $taskData = $this->getTaskData();

        return CURRENT_USER->isLogged() && (RightsHelper::checkRights(['{admin}', '{responsible}'], '{task}', DataHelper::getId()) || ($taskData['creator_id'] ?? false) === CURRENT_USER->id());
    }

    public function postModelInit(BaseModel $model): BaseModel
    {
        if (($this->postModelInitVars['objType'] ?? false) === 'project') {
            $LOCALE = $this->LOCALE;

            if ($model->getElement('obj_id')) {
                $model->getElement('obj_id')->shownName = $LOCALE['project'];
            }

            /** @var Multiselect|null */
            $objType = $model->getElement('obj_type');

            if ($objType) {
                $objType->getAttribute()->defaultValue = 'project';
            }
        }

        return $model;
    }

    private function getTaskData(): ?array
    {
        return DB->findObjectById(DataHelper::getId(), 'task_and_event');
    }
}
