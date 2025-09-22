<?php

declare(strict_types=1);

namespace App\Helper;

use App\CMSVC\Message\MessageService;
use App\CMSVC\User\UserService;
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper, ResponseHelper};

abstract class RightsHelper extends \Fraym\Helper\RightsHelper
{
    /** Проверка, вошел ли пользователь в управление проектом */
    public static function hasProjectActivated(): bool
    {
        return (int) CookieHelper::getCookie('project_id') > 0;
    }

    /** Получение id активного проекта пользователя */
    public static function getActivatedProjectId(): ?int
    {
        return self::hasProjectActivated() ? (int) CookieHelper::getCookie('project_id') : (KIND === 'project' ? DataHelper::getId() : null);
    }

    /** Проверка права в проекте на совершение действий / доступ к разделу */
    public static function checkAllowProjectActions(bool|array $userProjectRights, ?array $rightsSetToCheck = ['{gamemaster}']): bool
    {
        if (!$userProjectRights) {
            return false;
        }

        if (in_array('{admin}', $userProjectRights)) {
            return true;
        }

        if (is_array($rightsSetToCheck) && DataHelper::inArrayAll($rightsSetToCheck, $userProjectRights)) {
            return true;
        }

        return false;
    }

    /** Логика редиректа после проверки прав доступа пользователя в проектное действие типа {budget} */
    public static function checkProjectActionAccessBudget(): bool
    {
        return self::checkAllowProjectActions(PROJECT_RIGHTS, ['{budget}']);
    }

    /** Логика редиректа после проверки прав доступа пользователя в проектное действие типа {fee} */
    public static function checkProjectActionAccessFee(): bool
    {
        return self::checkAllowProjectActions(PROJECT_RIGHTS, ['{fee}']);
    }

    /** Логика редиректа после проверки прав доступа пользователя в проектное действие типа {budget} или {fee} */
    public static function checkProjectActionAccessBudgetFee(): bool
    {
        return self::checkAllowProjectActions(PROJECT_RIGHTS, ['{budget}', '{fee}']);
    }

    /** Базовая логика редиректа после проверки прав доступа пользователя в проект */
    public static function checkProjectKindAccessAndRedirect(): bool
    {
        if (!self::checkAllowProjectActions(PROJECT_RIGHTS, ['{gamemaster}', DataHelper::addBraces(KIND)])) {
            /** Если определен id объекта, находим объект и смотрим его project_id */
            if (!is_null(DataHelper::getId())) {
                $possibleView = CMSVCHelper::getView(KIND);
                $possibleTable = $possibleView->getEntity()->getTable();

                if ($possibleTable) {
                    $possibleObj = DB->findObjectById(DataHelper::getId(), $possibleTable);

                    if ($possibleObj && ($possibleObj['project_id'] ?? false) > 0 && self::checkProjectRights(false, $possibleObj['project_id'])) {
                        ResponseHelper::redirect(
                            '/' . KIND .
                                '/' . (CMSVC ? CMSVC . '/' : '') . DataHelper::getId() .
                                '/' . (CMSVC ? 'act=edit' : ''),
                        );
                    }
                }
            }

            self::redirectIfNoProjectRights();
        }

        return true;
    }

    /** Редирект в случае отсутствия доступа к проекту */
    public static function redirectIfNoProjectRights(): void
    {
        if (is_int($_REQUEST['project_id'] ?? false) && $_REQUEST['project_id'] > 0) {
            ResponseHelper::redirect(
                '/project/' . (int) $_REQUEST['project_id'] . '/' .
                    (KIND === 'application' && !(CURRENT_USER->isLogged()) ? 'application_id=' . DataHelper::getId() : ''),
            );
        } else {
            ResponseHelper::redirect('/project/');
        }
    }

    /** Проверка прав проекта и установки переменной project_id. Чаще всего используется так: $projectRights = RightsHelper::checkProjectRights(); */
    public static function checkProjectRights(bool|array|string $type = false, ?int $projectId = null): bool|array
    {
        $requestProjectId = ($_REQUEST['project_id'] ?? false) && !is_array($_REQUEST['project_id']) ? (int) ($_REQUEST['project_id'] ?? false) : 0;

        if (!defined('REQUEST_PROJECT_ID')) {
            define('REQUEST_PROJECT_ID', 'project_id=' . $requestProjectId);
        }

        if (!CURRENT_USER->isLogged()) {
            return false;
        }

        if (is_null($projectId) && KIND === 'project' && DataHelper::getId() > 0) {
            $projectId = DataHelper::getId();
        }

        if (is_null($projectId) && $requestProjectId > 0) {
            $projectId = $requestProjectId;
        } elseif (is_null($projectId) && self::getActivatedProjectId()) {
            $projectId = self::getActivatedProjectId();
        } elseif (is_null($projectId)) {
            return false;
        }

        $orderBy = '';
        $types = [];
        $postgreInjection = '';

        if ($type && $type !== true) {
            if (is_array($type)) {
                if ($_ENV['DATABASE_TYPE'] === 'pgsql') {
                    $countFields = 0;
                    $orderBy = 'CASE';

                    foreach ($type as $value) {
                        ++$countFields;
                        $value = DataHelper::addBraces($value);
                        $types[] = "'" . $value . "'";
                        $orderBy .= " WHEN type='" . $value . "' THEN " . $countFields;
                    }
                    $orderBy .= ' ELSE ' . ($countFields + 1) . ' END';

                    $postgreInjection = '(' . $orderBy . ') as order_type, ';

                    $orderBy = ' ORDER BY ' . $orderBy . ', type ASC';
                } else {
                    $orderBy = ' ORDER BY FIELD (type, ';

                    foreach ($type as $value) {
                        $value = DataHelper::addBraces($value);
                        $types[] = "'" . $value . "'";
                        $orderBy .= "'" . $value . "', ";
                    }
                    $orderBy = mb_substr($orderBy, 0, mb_strlen($orderBy) - 2) . ')';
                }
            } else {
                $type = DataHelper::addBraces($type);
                $types[] = $type;
            }
        }
        $results = [];
        $comments = [];
        $bannedTypes = self::getBannedTypes();
        $result = DB->query(
            'SELECT DISTINCT ' . $postgreInjection . "type AS data, comment FROM relation WHERE obj_type_to='{project}' AND obj_type_from='{user}'" .
                (count(
                    $bannedTypes,
                ) === 0 ? '' : ' AND type NOT IN (:bannedTypes)') . ($type ? ' AND type IN (:types)' : '') . ' AND obj_id_to=:obj_id_to AND obj_id_from=:obj_id_from ' . $orderBy,
            [
                ['bannedTypes', $bannedTypes],
                ['types', $types],
                ['obj_id_to', $projectId],
                ['obj_id_from', CURRENT_USER->id()],
            ],
        );

        foreach ($result as $relationData) {
            $results[] = $relationData['data'];
            $comments[] = $relationData['comment'];
        }

        if (count($results) > 0) {
            if (DataHelper::inArrayAny(['{admin}', '{gamemaster}'], $results)) {
                $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

                CookieHelper::batchSetCookie(['project_id' => (string) $projectId]);

                if ($requestProjectId > 0) {
                    /** Переключение на другой проект, нужно заменить переменные сессии, которые относились к предыдущему проекту */
                    CookieHelper::batchSetCookie(['application_type' => '0']);
                }

                /** Определяем права и видимость разделов доступных для права "мастер" */
                if (in_array('{gamemaster}', $results)) {
                    $addResults = [];

                    foreach ($results as $key => $resultType) {
                        if ($resultType === '{gamemaster}') {
                            if ($comments[$key] !== '') {
                                $allowedSections = DataHelper::multiselectToArray($comments[$key]);

                                foreach ($allowedSections as $allowedSection) {
                                    if (!is_numeric($allowedSection) && in_array($allowedSection, $LOCALE_GLOBAL['project_control_items'])) {
                                        $addResults[] = DataHelper::addBraces($allowedSection);
                                    }
                                }
                            }
                        }
                    }

                    if (count($addResults) > 0) {
                        $results = array_merge($results, array_unique($addResults));

                        if (DataHelper::inArrayAny(['{rooms}', '{document}', '{registration}'], $results)) {
                            $results[] = '{tab3}';
                        }

                        if (DataHelper::inArrayAny(['{qrpg_key}', '{qrpg_code}', '{qrpg_history}', '{geoposition}', '{bank_transaction}', '{bank_currency}'], $results)) {
                            $results[] = '{tab4}';
                        }

                        if (DataHelper::inArrayAny(['{character}', '{group}'], $results)) {
                            $results[] = '{tab5}';
                        }
                    } else {
                        /** Нет ограничения, добавляем всё */
                        $defaultSections = [
                            'roles/{id}/',
                            'budget',
                            'fee',
                        ];

                        $LOCALE = LocaleHelper::getLocale(['global']);

                        foreach ($LOCALE['project_control_items'] as $key => $item) {
                            if (preg_match('#{' . $key . '}#', $item[1]) && !in_array($key, $defaultSections)) {
                                $results[] = DataHelper::addBraces($key);
                            }
                        }
                    }

                    $results = array_unique($results);
                }
            } else {
                CookieHelper::batchDeleteCookie(['project_id', 'application_type', 'project_filterset_id']);
            }

            if (!$type) {
                return $results;
            }

            return true;
        } else {
            return false;
        }
    }

    /** Запрос доступа к объектам */
    public static function getAccess(string $objType, bool|int $objId = false, bool $API_REQUEST = false): bool|array
    {
        $objType = DataHelper::addBraces($objType);

        $LOCALE = LocaleHelper::getLocale([DataHelper::clearBraces($objType), 'global', 'messages']);

        if (!$objId) {
            $objId = DataHelper::getId();
        }

        $returnArray = [];

        if (self::checkAnyRights($objType, $objId)) {
            if ($API_REQUEST) {
                $returnArray = [
                    'response' => 'success',
                ];
            } else {
                return false;
            }
        } elseif (CURRENT_USER->isLogged() && $objId > 0) {
            $tableObjType = DataHelper::clearBraces($objType);

            if ($tableObjType === 'event' || $tableObjType === 'task') {
                $tableObjType = 'task_and_event';
            }
            $objData = DB->findObjectById($objId, DataHelper::clearBraces($tableObjType));

            $accessToChilds = false;

            if (in_array(DataHelper::clearBraces($objType), ['task', 'event'])) {
                $parentObjType = false;
                $parentObjId = self::findOneByRights('{child}', '{project}', null, $objType, $objId);

                if ($parentObjId > 0) {
                    $parentObjType = 'project';
                } else {
                    $parentObjId = self::findOneByRights(
                        '{child}',
                        '{community}',
                        null,
                        $objType,
                        $objId,
                    );

                    if ($parentObjId > 0) {
                        $parentObjType = 'community';
                    }
                }

                if ($parentObjType) {
                    $parentObjData = DB->findObjectById($parentObjId, $parentObjType);

                    if ($parentObjData['access_to_childs'] === 1) {
                        $accessToChilds = true;
                    }
                }
            }

            if (
                (DataHelper::clearBraces($objType) === 'project' || DataHelper::clearBraces($objType) === 'community')
                && in_array($objData['type'], ['{open}', '{close}'])
            ) {
                if ($objData['type'] === '{open}') {
                    self::addRights('{member}', $objType, $objId);
                    $conversationId = self::findOneByRights(
                        '{child}',
                        $objType,
                        $objId,
                        '{conversation}',
                    );

                    if ($conversationId > 0) {
                        self::addRights('{member}', '{conversation}', $conversationId);
                    }

                    if ($API_REQUEST) {
                        $returnArray = [
                            'response' => 'success',
                            'response_text' => $LOCALE['successfully_joined'],
                        ];
                    } else {
                        ResponseHelper::success($LOCALE['successfully_joined']);
                        ResponseHelper::redirect(ABSOLUTE_PATH . '/' . DataHelper::clearBraces($objType) . '/' . $objId . '/');
                    }
                } elseif ($API_REQUEST) {
                    $returnArray = [
                        'response' => 'error',
                        'response_text' => $LOCALE['closed_' . DataHelper::clearBraces($objType) . '_join_only_by_invitation'],
                    ];
                } else {
                    ResponseHelper::error(
                        $LOCALE['closed_' . DataHelper::clearBraces($objType) . '_join_only_by_invitation'],
                    );
                }
            } elseif ((DataHelper::clearBraces($objType) === 'task' || DataHelper::clearBraces($objType) === 'event') && $accessToChilds) {
                self::addRights('{member}', $objType, $objId);
                $conversationId = self::findOneByRights('{child}', $objType, $objId, '{conversation}');

                if ($conversationId > 0) {
                    self::addRights('{member}', '{conversation}', $conversationId);
                }

                if ($API_REQUEST) {
                    $returnArray = [
                        'response' => 'success',
                        'response_text' => $LOCALE['successfully_joined'],
                    ];
                } else {
                    ResponseHelper::success($LOCALE['successfully_joined']);
                    ResponseHelper::redirect(ABSOLUTE_PATH . '/' . DataHelper::clearBraces($objType) . '/' . $objId . '/');
                }
            } else {
                $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

                $cId = '';
                $block = false;
                $conversationData = DB->query(
                    'SELECT c.id, cm.updated_at FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id WHERE cm.message_action_data=:message_action_data AND cm.message_action="{get_access}" AND cm.creator_id=:creator_id',
                    [
                        ['message_action_data', '"{' . DataHelper::clearBraces($objType) . '_id:' . $objId . '}"'],
                        ['creator_id', CURRENT_USER->id()],
                    ],
                    true,
                );

                if ($conversationData['id'] !== '') {
                    if (time() - $conversationData['updated_at'] > 600) {
                        $cId = $conversationData['id'];
                    } else {
                        $block = true;

                        if ($API_REQUEST) {
                            $returnArray = [
                                'response' => 'error',
                                'response_code' => 're_request_blocked',
                                'response_text' => $LOCALE_CONVERSATION['messages']['re_request_blocked'],
                            ];
                        } else {
                            ResponseHelper::error($LOCALE_CONVERSATION['messages']['re_request_blocked']);
                        }
                    }
                }

                $users = [];

                if ($cId === '' && !$block) {
                    $result = DB->query(
                        'SELECT u.id FROM user u LEFT JOIN relation r ON r.obj_id_from=u.id WHERE r.obj_type_from="{user}" AND r.type="{admin}" AND r.obj_type_to=:obj_type_to AND r.obj_id_to=:obj_id_to',
                        [
                            ['obj_type_to', $objType],
                            ['obj_id_to', $objId],
                        ],
                    );

                    foreach ($result as $a) {
                        $users[$a['id']] = 'on';
                    }
                }

                if ($cId !== '' || count($users) > 0) {
                    /** @var MessageService $messageService */
                    $messageService = CMSVCHelper::getService('message');

                    $result = $messageService->newMessage(
                        $cId,
                        '{action}',
                        $LOCALE_CONVERSATION['actions']['request_access'],
                        $users,
                        [],
                        [],
                        '{get_access}',
                        '{' . DataHelper::clearBraces($objType) . '_id:' . $objId . '}',
                    );

                    if ($result && $cId === '') {
                        if ($API_REQUEST) {
                            $returnArray = [
                                'response' => 'success',
                                'response_text' => $LOCALE_CONVERSATION['messages']['request_success'],
                            ];
                        } else {
                            ResponseHelper::success($LOCALE_CONVERSATION['messages']['request_success']);
                        }
                    } elseif ($result) {
                        if ($API_REQUEST) {
                            $returnArray = [
                                'response' => 'success',
                                'response_text' => $LOCALE_CONVERSATION['messages']['re_request_success'],
                            ];
                        } else {
                            ResponseHelper::success($LOCALE_CONVERSATION['messages']['re_request_success']);
                        }
                    }
                } elseif (!$block) {
                    if ($API_REQUEST) {
                        $returnArray = [
                            'response' => 'error',
                            'response_code' => 'rights_error',
                            'response_text' => $LOCALE_CONVERSATION['messages']['rights_error'],
                        ];
                    } else {
                        ResponseHelper::error($LOCALE_CONVERSATION['messages']['rights_error']);
                    }
                }
            }
        }

        if ($API_REQUEST) {
            return $returnArray;
        }

        return true;
    }

    /** Выход из объекта */
    public static function removeAccess(string $objType): void
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

        $objType = DataHelper::addBraces($objType);

        if (self::deleteRights(null, $objType, DataHelper::getId())) {
            if (!self::checkAnyRights($objType, DataHelper::getId())) {
                $conversationId = self::findOneByRights(
                    '{child}',
                    $objType,
                    DataHelper::getId(),
                    '{conversation}',
                );

                if (
                    $conversationId > 0 && self::checkRights(
                        '{member}',
                        '{conversation}',
                        $conversationId,
                    )
                ) {
                    /** @var MessageService $messageService */
                    $messageService = CMSVCHelper::getService('message');

                    $user = $userService->get(CURRENT_USER->id());
                    $text =
                        sprintf(
                            $LOCALE['messages']['user_left_the_dialog'],
                            $userService->showNameExtended($user, true),
                            false,
                        );
                    $messageService->newMessage($conversationId, $text);
                    self::deleteRights('{member}', '{conversation}', $conversationId);
                }

                if ($objType === '{community}') {
                    $linkedProjects = DB->select(
                        tableName: 'relation',
                        criteria: [
                            'creator_id' => CURRENT_USER->id(),
                            'obj_type_from' => '{project}',
                            'obj_type_to' => '{community}',
                            'obj_id_to' => DataHelper::getId(),
                        ],
                        fieldsSet: [
                            'obj_id_from',
                        ],
                    );

                    foreach ($linkedProjects as $linkedProjectId) {
                        self::deleteRights(
                            null,
                            $objType,
                            DataHelper::getId(),
                            '{project}',
                            $linkedProjectId['obj_id_from'],
                        );
                    }
                }
            }
            ResponseHelper::success(
                sprintf(
                    $LOCALE['messages']['remove_access_success'],
                    $LOCALE['obj_types'][DataHelper::clearBraces($objType)],
                ),
            );
            ResponseHelper::redirect('/start/');
        } else {
            ResponseHelper::responseOneBlock(
                'error',
                sprintf(
                    $LOCALE['messages']['remove_access_fail'],
                    $LOCALE['obj_types'][DataHelper::clearBraces($objType)],
                ),
            );
        }
    }

    /** Динамическое добавление права к объекту */
    public static function dynamicAddRights(string $objType, string|int $objId, string|int $userId, ?string $rightsType = null): array
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = LocaleHelper::getLocale(['fraym', 'rights', 'messages']);
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $returnArr = [];

        if (!is_null($rightsType) && $objId > 0 && $objType !== '' && $userId > 0) {
            $objData = DB->findObjectById(
                $objId,
                in_array(DataHelper::clearBraces($objType), ['task', 'event']) ? 'task_and_event' : DataHelper::clearBraces($objType),
            );

            if (
                self::checkRights(['{admin}', '{responsible}', '{moderator}'], DataHelper::addBraces($objType), $objId)
                || self::checkRights('{friend}', DataHelper::addBraces($objType), $userId)
                || $objData['creator_id'] === CURRENT_USER->id()
            ) {
                if ($rightsType === 'delete_all') {
                    if (DataHelper::addBraces($objType) === '{user}') {
                        if (self::deleteRights(null, DataHelper::addBraces($objType), $userId)) {
                            $returnArr = [
                                'response' => 'success',
                                'response_text' => $LOCALE['delete_rights_success'],
                            ];
                        }
                    } elseif (
                        $userId !== CURRENT_USER->id() && self::deleteRights(
                            null,
                            DataHelper::addBraces($objType),
                            $objId,
                            '{user}',
                            $userId,
                        )
                    ) {
                        $conversationId = self::findOneByRights(
                            '{child}',
                            DataHelper::addBraces($objType),
                            $objId,
                            '{conversation}',
                        );

                        if (
                            $conversationId > 0 && self::checkRights(
                                '{member}',
                                '{conversation}',
                                $conversationId,
                                '{user}',
                                $userId,
                            )
                        ) {
                            /** @var MessageService $messageService */
                            $messageService = CMSVCHelper::getService('message');

                            $user = $userService->get($userId);
                            $text =
                                sprintf(
                                    $LOCALE_CONVERSATION['messages']['user_left_the_dialog'],
                                    $userService->showNameExtended($user, true),
                                    false,
                                );
                            $messageService->newMessage($conversationId, $text);
                            self::deleteRights(
                                '{member}',
                                '{conversation}',
                                $conversationId,
                                '{user}',
                                $userId,
                            );
                        }

                        if (DataHelper::addBraces($objType) === '{community}') {
                            $linkedProjects = DB->select(
                                tableName: 'relation',
                                criteria: [
                                    'creator_id' => $userId,
                                    'obj_type_from' => '{project}',
                                    'obj_type_to' => '{community}',
                                    'obj_id_to' => $objId,
                                ],
                            );

                            foreach ($linkedProjects as $linkedProject) {
                                self::deleteRights(
                                    null,
                                    DataHelper::addBraces($objType),
                                    $objId,
                                    '{project}',
                                    $linkedProject['id'],
                                );
                            }
                            $linkedCommunities = DB->select(
                                tableName: 'relation',
                                criteria: [
                                    'creator_id' => $userId,
                                    'obj_type_from' => '{community}',
                                    'obj_type_to' => '{community}',
                                    'obj_id_to' => $objId,
                                ],
                            );

                            foreach ($linkedCommunities as $linkedCommunity) {
                                self::deleteRights(
                                    null,
                                    DataHelper::addBraces($objType),
                                    $objId,
                                    '{community}',
                                    $linkedCommunity['id'],
                                );
                            }
                        }

                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $LOCALE['delete_rights_success'],
                        ];
                    } else {
                        $returnArr = [
                            'response' => 'error',
                            'response_code' => 'delete_rights_fail',
                            'response_text' => $LOCALE['delete_rights_fail'],
                        ];
                    }
                } // добавлять можно права только тем, у кого есть уже какие-то права в объекте, т.е. он принял в него приглашение
                elseif (self::checkAnyRights(DataHelper::addBraces($objType), $objId, '{user}', $userId)) {
                    if (DataHelper::addBraces($rightsType) === '{responsible}') {
                        self::deleteRights('{responsible}', DataHelper::addBraces($objType), $objId, '{user}', 0);
                    }

                    if (
                        self::addRights(
                            DataHelper::addBraces($rightsType),
                            DataHelper::addBraces($objType),
                            $objId,
                            '{user}',
                            $userId,
                        )
                    ) {
                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $LOCALE['add_rights_success'],
                        ];
                    } else {
                        $returnArr = [
                            'response' => 'error',
                            'response_code' => 'add_rights_fail',
                            'response_text' => $LOCALE['add_rights_fail'],
                        ];
                    }
                } else {
                    $returnArr = [
                        'response' => 'error',
                        'response_code' => 'add_rights_fail',
                        'response_text' => $LOCALE['add_rights_fail'],
                    ];
                }
            }
        }

        return $returnArr;
    }

    /** Динамическое удаление прав к объекту */
    public static function dynamicRemoveRights(string $objType, string|int $objId, string|int $userId, ?string $rightsType = null): array
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = LocaleHelper::getLocale(['fraym', 'rights', 'messages']);
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $returnArr = [];

        if (!is_null($rightsType) && $objId > 0 && $objType !== '' && $userId > 0) {
            if (
                self::checkRights(
                    ['{admin}', '{responsible}', '{moderator}'],
                    DataHelper::addBraces($objType),
                    $objId,
                )
            ) {
                if (
                    self::deleteRights(
                        DataHelper::addBraces($rightsType),
                        DataHelper::addBraces($objType),
                        $objId,
                        '{user}',
                        $userId,
                    )
                ) {
                    if (!self::checkAnyRights(DataHelper::addBraces($objType), $objId, '{user}', $userId)) {
                        $conversationId = self::findOneByRights(
                            '{child}',
                            DataHelper::addBraces($objType),
                            $objId,
                            '{conversation}',
                        );

                        if (
                            $conversationId > 0 && self::checkRights(
                                '{member}',
                                '{conversation}',
                                $conversationId,
                                '{user}',
                                $userId,
                            )
                        ) {
                            /** @var MessageService $messageService */
                            $messageService = CMSVCHelper::getService('message');

                            $user = $userService->get($userId);
                            $text =
                                sprintf(
                                    $LOCALE_CONVERSATION['messages']['user_left_the_dialog'],
                                    $userService->showNameExtended($user, true),
                                    false,
                                );
                            $messageService->newMessage($conversationId, $text);
                            self::deleteRights(
                                '{member}',
                                '{conversation}',
                                $conversationId,
                                '{user}',
                                $userId,
                            );
                        }

                        if (DataHelper::addBraces($objType) === '{community}') {
                            $linkedProjects = DB->select(
                                tableName: 'relation',
                                criteria: [
                                    'creator_id' => $userId,
                                    'obj_type_from' => '{project}',
                                    'obj_type_to' => '{community}',
                                    'obj_id_to' => $objId,
                                ],
                            );

                            foreach ($linkedProjects as $linkedProject) {
                                self::deleteRights(
                                    null,
                                    DataHelper::addBraces($objType),
                                    $objId,
                                    '{project}',
                                    $linkedProject['id'],
                                );
                            }
                            $linkedCommunities = DB->select(
                                tableName: 'relation',
                                criteria: [
                                    'creator_id' => $userId,
                                    'obj_type_from' => '{community}',
                                    'obj_type_to' => '{community}',
                                    'obj_id_to' => $objId,
                                ],
                            );

                            foreach ($linkedCommunities as $linkedCommunity) {
                                self::deleteRights(
                                    null,
                                    DataHelper::addBraces($objType),
                                    $objId,
                                    '{community}',
                                    $linkedCommunity['id'],
                                );
                            }
                        }
                    }

                    $returnArr = [
                        'response' => 'success',
                        'response_text' => $LOCALE['delete_rights_success'],
                    ];
                } else {
                    $returnArr = [
                        'response' => 'error',
                        'response_code' => 'delete_rights_fail',
                        'response_text' => $LOCALE['delete_rights_fail'],
                    ];
                }
            }
        }

        return $returnArr;
    }
}
