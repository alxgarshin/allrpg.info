<?php

declare(strict_types=1);

namespace App\CMSVC\Message;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Community\{CommunityModel, CommunityService};
use App\CMSVC\Conversation\ConversationService;
use App\CMSVC\Event\EventService;
use App\CMSVC\Notion\NotionService;
use App\CMSVC\Project\{ProjectModel, ProjectService};
use App\CMSVC\Publication\PublicationService;
use App\CMSVC\RulingItemEdit\RulingItemEditService;
use App\CMSVC\Task\TaskService;
use App\CMSVC\Trait\UserServiceTrait;
use App\Helper\{DateHelper, FileHelper, MessageHelper, RightsHelper, TextHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Enum\{EscapeModeEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper, ResponseHelper};

#[Controller(MessageController::class)]
class MessageService extends BaseService
{
    use UserServiceTrait;

    private array $messages = [];

    public function init(): static
    {
        $this->LOCALE = ['conversation', 'global'];

        return $this;
    }

    /** Получение массива выведенных пользователю сообщений */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /** Вывод данных стены / диалогов */
    public function loadWall(
        string $objType,
        int|string|null $objId,
        int $lastShownConversationId,
        int $objLimit,
        int $showLimit,
        string $searchString,
        string $subObjType,
    ): array {
        $userService = $this->getUserService();

        $LOCALE = $this->LOCALE;

        $returnArr = [];

        $objLimit = (REQUEST_TYPE->isApiRequest() ? $lastShownConversationId : $objLimit);
        $objType = DataHelper::addBraces($objType);

        if (in_array($objType, ['{main_conversation}', '{main_conversation_on_main_page}']) && CURRENT_USER->isLogged()) {
            $text = '';
            $responseData = [];

            // количество диалогов на странице
            if ($objType === '{main_conversation_on_main_page}') {
                $cCount = 3;
            } else {
                $cCount = (REQUEST_TYPE->isApiRequest() ? $showLimit : 20);
            }

            // строка поиска по фио друга или названию диалога
            if (mb_strlen($searchString) < 3) {
                $searchString = '';
            }

            if ($searchString !== '') {
                // заглушка кол-ва диалогов в случае, если ищем конкретное
                $cCount = 10000000;
                $objLimit = 0;
            }

            // параметр выборки диалогов по типу: все / личные / рабочие
            $subObjType = (REQUEST_TYPE->isApiRequest() ? $subObjType : 'all');
            $allowedSubObjTypesSqls = [
                'all' => '',
                'personal' => ' AND r2.obj_type_to IS NULL',
                'work' => ' AND r2.obj_type_to IS NOT NULL',
            ];

            if (!isset($allowedSubObjTypesSqls[$subObjType])) {
                $subObjType = 'all';
            }

            // выборка диалогов на данную страницу
            $query = "SELECT
			cm.*,
			c.id as c_id,
			c.name as c_name,
			COUNT(DISTINCT cms.id) as new_messages_count
		    FROM conversation c
			LEFT JOIN relation r ON
				r.obj_id_to=c.id AND
				r.type='{member}' AND
				r.obj_type_from='{user}' AND
				r.obj_type_to='{conversation}'
			" . ($subObjType !== 'all' ? "LEFT JOIN relation r2 ON
				r2.obj_id_from=c.id AND
				r2.obj_type_from='{conversation}' AND
				r2.type='{child}'" : '') . '
			' . ($searchString !== '' ? "LEFT JOIN relation r3 ON
				r3.obj_id_to=c.id AND
				r3.obj_type_to='{conversation}' AND
				r3.type='{member}' AND
				r3.obj_type_from='{user}' AND
				r3.obj_id_from!=:user_id1
			LEFT JOIN user u ON
				u.id=r3.obj_id_from
			" : '') . "
			LEFT JOIN conversation_message cm ON
				cm.id = (
					SELECT
						b.id
					FROM conversation_message AS b
					LEFT JOIN conversation_message_status bs ON
						bs.message_id=b.id AND
						bs.user_id=:user_id2
					WHERE
						b.conversation_id = c.id AND (
							bs.message_deleted!='1' OR
							bs.message_deleted IS NULL
						)
					ORDER BY
						bs.message_read,
						b.id DESC
					LIMIT 1
				)
			LEFT JOIN conversation_message cm2 ON
				cm2.conversation_id=c.id
			LEFT JOIN conversation_message_status cms ON
				cms.message_id=cm2.id AND (
					cms.message_id!=0 AND
					cms.message_id IS NOT NULL
				) AND
				cms.user_id=:user_id3 AND
				(
					cms.message_deleted!='1' OR
					cms.message_deleted IS NULL
				) AND
				(
					cms.message_read='0' OR
					cms.message_read IS NULL
				)
			WHERE
				c.obj_id IS NULL AND
				cm.id IS NOT NULL AND
				r.obj_id_from=:user_id4" .
                ($searchString !== '' ? ' AND (u.sid=:search_string_int' .
                    (CURRENT_USER->blockedProfileEdit() ? '' : ' OR LOWER(u.fio) LIKE :search_string1 OR LOWER(u.nick) LIKE :search_string2') .
                    ' OR LOWER(c.name) LIKE :search_string3)' : '') .
                $allowedSubObjTypesSqls[$subObjType] .
                ($objLimit > 0 ? ' AND cm.id < :obj_limit' : '') .
                '
            GROUP BY
				c.id,
				cm.id
			ORDER BY
				cm.id DESC
			LIMIT :count';

            $result = DB->query($query, [
                ['user_id1', CURRENT_USER->id()],
                ['user_id2', CURRENT_USER->id()],
                ['user_id3', CURRENT_USER->id()],
                ['user_id4', CURRENT_USER->id()],
                ['search_string_int', (int) $searchString],
                ['search_string1', '%' . mb_strtolower($searchString) . '%'],
                ['search_string2', '%' . mb_strtolower($searchString) . '%'],
                ['search_string3', '%' . mb_strtolower($searchString) . '%'],
                ['obj_limit', $objLimit],
                ['count', $cCount],
            ]);

            $hasMessages = DB->selectCount() > 0;

            $gotSome = 0;
            $counter = 0;

            foreach ($result as $messageData) {
                if ($messageData['id'] > 0) {
                    ++$counter;
                    $gotSome = $messageData['id'];
                    $text .= '<div class="message" c_id="' . $messageData['c_id'] . '">';

                    if (REQUEST_TYPE->isApiRequest()) {
                        $responseData[$messageData['c_id']] = [
                            'conversation_id' => $messageData['c_id'],
                        ];
                    }

                    $text .= '
<div class="message_photos">';

                    $result2 = DB->query(
                        'SELECT u.* FROM user u LEFT JOIN relation r ON r.obj_id_from=u.id WHERE r.obj_id_to=:obj_id_to AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_type_to="{conversation}" AND r.obj_id_from!=:obj_id_from',
                        [
                            ['obj_id_to', $messageData['c_id']],
                            ['obj_id_from', CURRENT_USER->id()],
                        ],
                    );
                    $userCount = count($result2);

                    if ($userCount === 0) {
                        $result2 = DB->query(
                            'SELECT u.* FROM user u LEFT JOIN relation r ON r.obj_id_from=u.id WHERE r.obj_id_to=:obj_id_to AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_type_to="{conversation}" AND r.obj_id_from=:obj_id_from',
                            [
                                ['obj_id_to', $messageData['c_id']],
                                ['obj_id_from', CURRENT_USER->id()],
                            ],
                        );
                        $userCount = count($result2);
                    }

                    if ($userCount > 1) {
                        $maxUser = 4;
                        $i = 0;
                        $names = [];
                        $apiNames = [];
                        $result2 = $this->getUserService()->arraysToModels($result2);

                        foreach ($result2 as $userData) {
                            $class = '';

                            /*if ($userCount == 2) {
                                $class = 'height100';
                            } elseif ($userCount == 3 && $i == 0) {
                                $class = 'height100';
                            }*/
                            if ($i < $maxUser) {
                                $text .= $userService->photoNameLink($userData, '50%', false, $class, '', false, true);
                            }
                            $names[] = $userService->showName($userData);
                            ++$i;

                            if (REQUEST_TYPE->isApiRequest()) {
                                $responseData[$messageData['c_id']]['dialog_avatars'][] = $userService->photoUrl($userData);
                                $apiNames[] = $userService->showName($userData);
                            }
                        }
                        $text .= '</div>';

                        $text .= '
<div class="message_info">
';

                        $text .= '
<div class="names">';

                        if (!empty($messageData['c_name'])) {
                            $text .= DataHelper::escapeOutput($messageData['c_name']);

                            if (REQUEST_TYPE->isApiRequest()) {
                                $responseData[$messageData['c_id']]['name'][] = DataHelper::escapeOutput($messageData['c_name']);
                            }
                        } else {
                            $text .= implode(', ', $names);

                            if (REQUEST_TYPE->isApiRequest()) {
                                $responseData[$messageData['c_id']]['name'][] = implode(', ', $apiNames);
                            }
                        }
                    } else {
                        $userData = $result2[0] ?? null;
                        $text .= $userService->photoNameLink($userService->arrayToModel($userData), '', false, '', '', false, true) . '</div>';

                        $text .= '
<div class="message_info">';

                        $text .= '
<div class="names">';

                        if (REQUEST_TYPE->isApiRequest()) {
                            $responseData[$messageData['c_id']]['dialog_avatars'][] = $userService->photoUrl(
                                $userService->arrayToModel($userData),
                            );
                        }

                        if (!empty($messageData['c_name'])) {
                            $text .= DataHelper::escapeOutput($messageData['c_name']);

                            if (REQUEST_TYPE->isApiRequest()) {
                                $responseData[$messageData['c_id']]['name'] = DataHelper::escapeOutput($messageData['c_name']);
                            }
                        } else {
                            $text .= $userService->showName($userService->arrayToModel($userData));

                            if (REQUEST_TYPE->isApiRequest()) {
                                $responseData[$messageData['c_id']]['name'] = $userService->showNameExtended(
                                    $userService->arrayToModel($userData),
                                    true,
                                    false,
                                    '',
                                    false,
                                    false,
                                    true,
                                );
                            }
                        }
                    }
                    $text .= '</div>';

                    $text .= '
<div class="time">' . DateHelper::showDateTime($messageData['updated_at'], true) . '</div>';

                    $userData = $userService->get($messageData['creator_id']);

                    if (!REQUEST_TYPE->isApiRequest()) {
                        $otherUsersUnread = false;

                        if ($messageData['new_messages_count'] === 0 && $messageData['creator_id'] === CURRENT_USER->id()) {
                            $otherUsersReadQuery = DB->query(
                                "SELECT
						user_id
					FROM
						conversation_message_status
					WHERE
						message_id = :message_id AND
						user_id != :user_id AND
						(
							message_deleted != '1' OR
							message_deleted IS NULL
						) AND
						message_read = '1'",
                                [
                                    ['message_id', $messageData['id']],
                                    ['user_id', CURRENT_USER->id()],
                                ],
                            );

                            if (count($otherUsersReadQuery) === 0) {
                                $otherUsersUnread = true;
                            }
                        }

                        $text .= '
<div class="content_preview' . ($messageData['new_messages_count'] > 0 ? ' unread counter" data-content="' . $messageData['new_messages_count'] : ($otherUsersUnread ? ' unread' : '')) . '">' . ($messageData['creator_id'] === CURRENT_USER->id() ? '<span class="gray">' . $LOCALE['you'] . ':</span> ' : ($userCount > 1 ? '<span class="gray">' .
                            $userService->showNameExtended(
                                $userData,
                                true,
                                false,
                                '',
                                true,
                                false,
                                true,
                            ) . ':</span> ' : '')) . mb_substr(
                                strip_tags(
                                    preg_replace(
                                        '#<div class="commands">(.*?)</div>#',
                                        '',
                                        str_replace(
                                            ['<br>', '<br />', '<br/>'],
                                            ' ',
                                            MessageHelper::viewActions($messageData['id']),
                                        ),
                                    ),
                                ),
                                0,
                                50,
                            ) . '</div>';
                    }

                    $text .= '
</div>
</div>';

                    if (REQUEST_TYPE->isApiRequest()) {
                        $responseData[$messageData['c_id']]['time'] = DateHelper::showDateTime(
                            $messageData['updated_at'],
                        );
                        $responseData[$messageData['c_id']]['timestamp'] = $messageData['updated_at'];
                        $responseData[$messageData['c_id']]['read'] = ($messageData['new_messages_count'] > 0 ? 'unread' : 'read');
                        $responseData[$messageData['c_id']]['message_content'] = MessageHelper::viewActions(
                            $messageData['id'],
                        );
                        $responseData[$messageData['c_id']]['message_content']['author_id'] = $userData->id->getAsInt();
                        $responseData[$messageData['c_id']]['message_content']['avatar'] = $userService->photoUrl(
                            $userData,
                        );
                    }
                }
            }

            if ($gotSome > 0 && $counter === $cCount && $objType !== '{main_conversation_on_main_page}') {
                $text .= '<a class="load_wall" obj_type="{main_conversation}" obj_limit="' . $gotSome . '">' . $LOCALE['previous'] . ' ' . $cCount . '</a>';

                if (REQUEST_TYPE->isApiRequest()) {
                    $responseData['last_shown_conversation_id'] = (string) $gotSome;
                }
            }

            if (!$hasMessages && $objLimit === 0) {
                $text .= '<div class="conversation_block"><h3>' . $LOCALE['no_conversations_found'] . '</h3></div>';
            }

            if (REQUEST_TYPE->isApiRequest()) {
                if (!$hasMessages && $objLimit === 0) {
                    $returnArr = [
                        'response' => 'error',
                        'response_error_code' => 'no_conversations_found',
                        'response_text' => $LOCALE['no_conversations_found'],
                    ];
                } else {
                    $returnArr = ['response' => 'success', 'response_data' => $responseData];
                }
            } else {
                $returnArr = ['response' => 'success', 'response_text' => $text];
            }
        } else {
            $objParentType = DataHelper::addBraces(str_replace(['_wall', '_comment'], '', $objType));

            if ($objType !== '' && ($objId !== '' || $objParentType === '{main}')) {
                if ($objParentType === '{main}' && CURRENT_USER->isLogged()) {
                    $subObjType = DataHelper::clearBraces($subObjType);

                    if ($subObjType === 'new_messages') {
                        $result = DB->query(
                            "SELECT DISTINCT c.*
						FROM
							conversation c
							LEFT JOIN conversation_message cm ON
							    cm.conversation_id=c.id
							LEFT JOIN conversation_message_status cms ON
							    cms.message_id=cm.id AND
								cms.user_id=:user_id1
							LEFT JOIN relation r ON
							    r.obj_id_to=c.obj_id AND
								r.type NOT IN (:banned_types) AND
								r.obj_type_from='{user}' AND
								r.obj_id_from=:user_id2
							WHERE
								c.obj_id IS NOT NULL AND
								(
									(
										c.obj_type='{project_wall}' AND
										r.obj_type_to='{project}'
									)
									OR
									(
										c.obj_type='{community_wall}' AND
										r.obj_type_to='{community}'
									)
									" . ($_ENV['SEPARATE_TASKS_FROM_WALL'] ? '' : "OR
									(
										c.obj_type='{task_comment}' AND
										r.obj_type_to='{task}'
									)") . "
									OR
									(
										c.obj_type='{event_comment}' AND
										r.obj_type_to='{event}'
									)
								) AND
								cm.id IS NOT NULL AND
								(cms.message_deleted='0' OR cms.message_deleted IS NULL) AND
								(cms.message_read!='1' OR cms.message_read IS NULL)",
                            [
                                ['user_id1', CURRENT_USER->id()],
                                ['user_id2', CURRENT_USER->id()],
                                ['banned_types', RightsHelper::getBannedTypes()],
                            ],
                        );
                    } else {
                        $result = DB->query(
                            "SELECT DISTINCT c.*
						FROM
							conversation c
							LEFT JOIN conversation_message cm ON
							    cm.conversation_id=c.id
							LEFT JOIN relation r ON
							    r.obj_id_to=c.obj_id AND
								r.type NOT IN (:banned_types) AND
								r.obj_type_from='{user}' AND
								r.obj_id_from=:user_id
						WHERE
							c.obj_id IS NOT NULL AND
							(
								(
									c.obj_type='{project_wall}' AND
									r.obj_type_to='{project}'
								)
								OR
								(
									c.obj_type='{community_wall}' AND
									r.obj_type_to='{community}'
								)
								" . ($_ENV['SEPARATE_TASKS_FROM_WALL'] ? '' : "OR
								(
									c.obj_type='{task_comment}' AND
									r.obj_type_to='{task}'
								)") . "
								OR
								(
									c.obj_type='{event_comment}' AND
									r.obj_type_to='{event}'
								)
							) AND
							cm.id IS NOT NULL
							" . ($objLimit > 0 ? ' AND c.id<:obj_limit' : '') . '
						ORDER BY
							c.created_at DESC
						LIMIT 10',
                            [
                                ['user_id', CURRENT_USER->id()],
                                ['banned_types', RightsHelper::getBannedTypes()],
                                ['obj_limit', $objLimit],
                            ],
                        );
                    }

                    $wallCount = DB->selectCount();
                    $text = '';
                    $responseData = [];
                    $gotSome = 0;
                    $counter = 0;

                    foreach ($result as $conversationData) {
                        ++$counter;
                        $gotSome = $conversationData['id'];

                        unset($parentObjData);
                        $parentObjType = '';
                        $parentObjTable = '';
                        $parentObjService = null;

                        switch ($conversationData['obj_type']) {
                            case '{community_wall}':
                                $parentObjType = 'community';
                                $parentObjTable = 'community';
                                /** @var CommunityService */
                                $parentObjService = CMSVCHelper::getService('community');
                                break;
                            case '{project_wall}':
                                $parentObjType = 'project';
                                $parentObjTable = 'project';
                                /** @var ProjectService */
                                $parentObjService = CMSVCHelper::getService('project');
                                break;
                            case '{task_comment}':
                                $parentObjType = 'task';
                                $parentObjTable = 'task_and_event';
                                /** @var TaskService */
                                $parentObjService = CMSVCHelper::getService('task');
                                break;
                            case '{event_comment}':
                                $parentObjType = 'event';
                                $parentObjTable = 'task_and_event';
                                /** @var EventService */
                                $parentObjService = CMSVCHelper::getService('event');
                                break;
                            case '{publication_wall}':
                                $parentObjType = 'publication';
                                $parentObjTable = 'publication';
                                /** @var PublicationService */
                                $parentObjService = CMSVCHelper::getService('publication');
                                break;
                            case '{ruling_item_wall}':
                                $parentObjType = 'ruling_item';
                                $parentObjTable = 'ruling_item';
                                /** @var RulingItemEditService */
                                $parentObjService = CMSVCHelper::getService('ruling_item');
                                break;
                        }
                        $objId = $conversationData['obj_id'];

                        if (!is_null($parentObjService) && $parentObjTable !== '') {
                            $parentObjData = DB->select($parentObjTable, ['id' => $objId], true);

                            $id = $objId;

                            if (!$_ENV['SEPARATE_TASKS_FROM_WALL'] && $parentObjType === 'task') {
                                require_once INNER_PATH . 'kinds/models/task.php';
                            }
                            unset($id);

                            if (REQUEST_TYPE->isApiRequest()) {
                                $responseData[$conversationData['id']] = MessageHelper::conversationWall(
                                    $conversationData,
                                    $parentObjType,
                                    $parentObjService->arrayToModel($parentObjData),
                                    true,
                                );
                            } else {
                                $text .= MessageHelper::conversationWall(
                                    $conversationData,
                                    $parentObjType,
                                    $parentObjService->arrayToModel($parentObjData),
                                    true,
                                );
                            }
                        }
                    }

                    if (count($this->getMessages()) > 0) {
                        MessageHelper::createRead($this->getMessages());
                    }

                    $LOCALE_WALL = LocaleHelper::getLocale(['wall', 'global']);

                    if (REQUEST_TYPE->isApiRequest()) {
                        if ($gotSome > 0 && $counter === 10 && $subObjType !== 'new_messages') {
                            $responseData['last_shown_conversation_id'] = $gotSome;
                            $returnArr = ['response' => 'success', 'response_data' => $responseData];
                        } elseif ($wallCount === 0 && $objLimit === 0) {
                            if ($subObjType === 'new_messages') {
                                $returnArr = [
                                    'response' => 'error',
                                    'response_error_code' => 'no_new_threads',
                                    'response_text' => $LOCALE_WALL['no_new_threads'],
                                ];
                            } else {
                                $returnArr = [
                                    'response' => 'error',
                                    'response_error_code' => 'no_projects_or_communities',
                                    'response_text' => $LOCALE_WALL['no_projects_or_communities'],
                                ];
                            }
                        }
                    } else {
                        if ($gotSome > 0 && $counter === 10 && $subObjType !== 'new_messages') {
                            $text .= '<a class="load_wall" obj_type="' . $objType . '" obj_limit="' . $gotSome . '">' . $LOCALE['show_previous_items'] . '</a>';
                        } elseif ($wallCount === 0 && $objLimit === 0) {
                            if ($subObjType === 'new_messages') {
                                $text .= '<span>' . $LOCALE_WALL['no_new_threads'] . '</span>';
                            } else {
                                $text .= '<span>' . $LOCALE_WALL['no_groups'] . '</span>';
                            }
                        }
                        $returnArr = ['response' => 'success', 'response_text' => $text];
                    }
                } elseif (RightsHelper::checkAnyRights($objParentType, $objId) || in_array($objParentType, ['{publication}', '{ruling_item}'])) {
                    $parentObjData = null;

                    if ($objParentType === '{project}') {
                        /** @var ProjectService */
                        $projectService = CMSVCHelper::getService('project');
                        $parentObjData = $projectService->get($objId);
                    } elseif ($objParentType === '{community}') {
                        /** @var CommunityService */
                        $communityService = CMSVCHelper::getService('community');
                        $parentObjData = $communityService->get($objId);
                    } elseif ($objParentType === '{publication}') {
                        /** @var PublicationService */
                        $publicationService = CMSVCHelper::getService('publicationsEdit');
                        $parentObjData = $publicationService->get($objId);
                    } elseif ($objParentType === '{ruling_item}') {
                        /** @var RulingItemEditService */
                        $rulingItemEditService = CMSVCHelper::getService('rulingItemEdit');
                        $parentObjData = $rulingItemEditService->get($objId);
                    } elseif ($objParentType === '{task}') {
                        /** @var TaskService */
                        $taskService = CMSVCHelper::getService('task');
                        $parentObjData = $taskService->get($objId);
                    } elseif ($objParentType === '{event}') {
                        /** @var EventService */
                        $eventService = CMSVCHelper::getService('event');
                        $parentObjData = $eventService->get($objId);
                    }

                    $text = '';
                    $responseData = [];
                    $result = DB->query(
                        'SELECT DISTINCT c.*
			FROM
				conversation c LEFT JOIN
				conversation_message cm ON cm.conversation_id=c.id
			WHERE
				c.obj_id IS NOT NULL AND
				c.obj_type=:obj_type AND
				c.obj_id=:obj_id AND
				cm.id IS NOT NULL
				' . ($objLimit > 0 ? ' AND c.id<:obj_limit' : '') . '
			ORDER BY
				c.created_at DESC
			LIMIT 10',
                        [
                            ['obj_type', $objType],
                            ['obj_id', $objId],
                            ['obj_limit', $objLimit],
                        ],
                    );
                    $gotSome = 0;
                    $counter = 0;

                    if (isset($parentObjData)) {
                        foreach ($result as $conversationData) {
                            ++$counter;

                            if (REQUEST_TYPE->isApiRequest()) {
                                $responseData[$conversationData['id']] = MessageHelper::conversationWall(
                                    $conversationData,
                                    $objParentType,
                                    $parentObjData,
                                );
                            } else {
                                $text .= MessageHelper::conversationWall(
                                    $conversationData,
                                    $objParentType,
                                    $parentObjData,
                                );
                            }
                            $gotSome = $conversationData['id'];
                        }
                    }

                    if (count($this->getMessages()) > 0) {
                        MessageHelper::createRead($this->getMessages());
                    }

                    if ($gotSome > 0 && $counter === 10) {
                        $text .= '<a class="load_wall" obj_type="' . $objType . '" obj_id="' . $objId . '" obj_limit="' . $gotSome . '">' . $LOCALE['show_previous_items'] . '</a>';
                        $responseData['last_shown_conversation_id'] = $gotSome;
                    }

                    if (REQUEST_TYPE->isApiRequest()) {
                        $returnArr = ['response' => 'success', 'response_data' => $responseData];
                    } else {
                        $returnArr = ['response' => 'success', 'response_text' => $text];
                    }
                } elseif (REQUEST_TYPE->isApiRequest()) {
                    $returnArr = ['response' => 'success', 'response_data' => []];
                } else {
                    $returnArr = ['response' => 'success', 'response_text' => ''];
                }
            }
        }

        return $returnArr;
    }

    /** Добавление сообщения */
    public function addComment(
        string $objType,
        string $subObjType,
        ?int $objId,
        string $name,
        string $content,
        int $rating,
        ?int $cId,
        bool $useGroupName = false,
        int|string $parent = '',
        ?string $voteName = null,
        ?array $voteAnswers = null,
        ?array $attachments = [],
        ?string $parentObjType = null,
        ?int $parentObjId = null,
        ?string $status = null,
        ?int $priority = null,
        ?string $dateTo = null,
        ?int $responsible = null,
    ): array {
        $LOCALE = $this->LOCALE;

        $clearedObjType = DataHelper::clearBraces($objType);

        if (in_array($clearedObjType, ['calendar_event_notion', 'user_notion']) && $objId > 0) {
            $dataField = str_replace('_notion', '', $clearedObjType) . '_id';
            $checkForData = DB->select('notion', [
                'creator_id' => CURRENT_USER->id(),
                $dataField => $objId,
            ], true);

            if (!($checkForData['id'] ?? false)) {
                DB->insert(
                    'notion',
                    [
                        'creator_id' => CURRENT_USER->id(),
                        $dataField => $objId,
                        'content' => $content,
                        'rating' => $rating,
                        'active' => 1,
                        'created_at' => DateHelper::getNow(),
                        'updated_at' => DateHelper::getNow(),
                    ],
                );

                $notionId = DB->lastInsertId();
                $notionHtml = '';

                if ($notionId > 0) {
                    $notionData = DB->findObjectById($notionId, 'notion');

                    if ($notionData['id'] ?? false) {
                        /** @var NotionService $notionService */
                        $notionService = CMSVCHelper::getService('notion');

                        $notionHtml = $notionService->conversationNotion(
                            $notionData,
                            $clearedObjType === 'calendar_event_notion',
                        );
                    }
                }

                $LOCALE_NOTION = LocaleHelper::getLocale(['notion', 'global']);

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE_NOTION['messages']['notion_add_success'],
                    'html' => $notionHtml,
                ];
            } else {
                $returnArr = [
                    'response' => 'error',
                    'response_error_code' => 'message_fail',
                    'response_text' => $LOCALE['messages']['fail'],
                ];
            }
        } else {
            if ($clearedObjType === 'project_application_conversation' && $objId === null && $subObjType === '{filter}') {
                $objType = '{project_application_conversation_filter}';
                $clearedObjType = 'project_application_conversation_filter';
            }

            $alternativeCheckOfRights = false;

            switch ($clearedObjType) {
                case 'publication_wall':
                    $parentObjectType = 'publication';
                    break;
                case 'ruling_item_wall':
                    $parentObjectType = 'ruling_item';
                    $alternativeCheckOfRights = true;
                    break;
                case 'task_comment':
                    $parentObjectType = 'task';
                    break;
                case 'event_comment':
                    $parentObjectType = 'event';
                    break;
                case 'project_conversation':
                case 'project_wall':
                    $parentObjectType = 'project';
                    break;
                case 'community_conversation':
                case 'community_wall':
                    $parentObjectType = 'community';
                    break;
                case 'project_application_conversation':
                    $parentObjectType = 'project_application';

                    if ($objId > 0) {
                        $applicationData = DB->select($parentObjectType, ['id' => $objId], true);

                        if (RightsHelper::checkRights(['{admin}', '{gamemaster}'], '{project}', $applicationData['project_id'])) {
                            $alternativeCheckOfRights = true;
                        }
                    }
                    break;
                case 'project_application_conversation_filter':
                    $parentObjectType = 'project_application';
                    $cookieProjectId = (int) CookieHelper::getCookie('project_id');

                    if ($cookieProjectId > 0) {
                        if (RightsHelper::checkRights(['{admin}', '{gamemaster}'], '{project}', $cookieProjectId)) {
                            $alternativeCheckOfRights = true;
                        }
                    }

                    $objType = '{project_application_conversation}';
                    $subObjType = '{to_player}';
                    break;
                case 'conversation_message':
                    $parentObjectType = 'conversation';
                    break;
                default:
                    $parentObjectType = '';
                    break;
            }

            if (
                ($objId === null || RightsHelper::checkAnyRights($parentObjectType, $objId) || CURRENT_USER->isAdmin() || $alternativeCheckOfRights)
                && $parentObjectType
            ) {
                if ($clearedObjType === 'project_application_conversation_filter') {
                    // parent - доп.фильтры в себе несет
                    $listOfIds = [];
                    $returnArr = [];

                    $filter = $parent;

                    $deletedView = $filter === 'deleted_view';
                    $noreplyView = $filter === 'noreply_view';
                    $needresponseView = $filter === 'needresponse_view';
                    $paymentApproveView = $filter === 'payment_approve_view';
                    $nofillobligView = $filter === 'nofilloblig_view';
                    $nonsettledView = $filter === 'nonsettled_view';
                    $noreplyIds = [];
                    $needresponseIds = [];
                    $nofillobligIds = [];
                    $nonsettledIds = [];

                    if ($noreplyView) {
                        /* собираем id всех заявок, в которых последним комментом был коммент от создателя заявки */
                        $noreplyApplicationsData = DB->query(
                            "SELECT pa.id FROM project_application AS pa LEFT JOIN conversation_message AS cm ON cm.id = (SELECT cm2.id FROM conversation AS c LEFT JOIN conversation_message AS cm2 ON cm2.conversation_id=c.id WHERE c.obj_id=pa.id AND c.obj_type='{project_application_conversation}' AND cm2.message_action!='{fee_payment}' ORDER BY cm2.id DESC LIMIT 1) WHERE pa.project_id=:project_id AND cm.creator_id = pa.creator_id",
                            [
                                ['project_id', CookieHelper::getCookie('project_id')],
                            ],
                        );

                        foreach ($noreplyApplicationsData as $noreplyApplicationData) {
                            $noreplyIds[] = $noreplyApplicationData['id'];
                        }
                    }

                    if ($needresponseView) {
                        /* собираем id всех заявок, в которых есть коммент с need_response */
                        $needresponseApplicationsData = DB->query(
                            "SELECT pa.id FROM project_application AS pa LEFT JOIN conversation AS c ON c.obj_id=pa.id LEFT JOIN conversation_message AS cm ON cm.conversation_id=c.id WHERE c.obj_type='{project_application_conversation}' AND cm.icon LIKE '%need_response%' AND pa.project_id=:project_id AND pa.deleted_by_gamemaster='0' AND pa.deleted_by_player='0' AND pa.status!=4",
                            [
                                ['project_id', CookieHelper::getCookie('project_id')],
                            ],
                        );

                        foreach ($needresponseApplicationsData as $needresponseApplicationData) {
                            $needresponseIds[] = $needresponseApplicationData['id'];
                        }
                    }

                    if ($nofillobligView) {
                        $applicationFields = DataHelper::virtualStructure(
                            "SELECT * FROM project_application_field WHERE project_id=:project_id AND application_type='0' ORDER BY field_code",
                            [
                                ['project_id', CookieHelper::getCookie('project_id')],
                            ],
                            'field_',
                        );

                        /* собираем id всех заявок, в которых не заполнено хотя бы одно обязательное поле (т.е. его нет в allinfo) */
                        $nofillobligViewScriptParams = [];

                        foreach ($applicationFields as $applicationFieldId => $applicationField) {
                            if ($applicationField->getObligatory()) {
                                $nofillobligViewScriptParams[] = ['allinfo', "%[virtual'.$applicationFieldId.']%", [OperandEnum::NOT_LIKE]];
                            }
                        }

                        $nofillobligApplicationsData = DB->select(
                            'project_application',
                            array_merge([
                                ['project_id' => CookieHelper::getCookie('project_id')],
                            ], $nofillobligViewScriptParams),
                        );

                        foreach ($nofillobligApplicationsData as $nofillobligApplicationData) {
                            $nofillobligIds[] = $nofillobligApplicationData['id'];
                        }
                    }

                    if ($nonsettledView) {
                        /* собираем id всех заявок, в которых не заполнено поселение */
                        $applicationsWithNoRoom = DB->query(
                            "SELECT pa.id FROM project_application AS pa LEFT JOIN relation AS r ON pa.id=r.obj_id_from AND r.type='{member}' AND r.obj_type_to='{room}' AND r.obj_type_from='{application}' WHERE pa.project_id=:project_id AND r.obj_id_to IS NULL",
                            [
                                ['project_id', CookieHelper::getCookie('project_id')],
                            ],
                        );

                        foreach ($applicationsWithNoRoom as $applicationWithNoRoom) {
                            $nonsettledIds[] = $applicationWithNoRoom['id'];
                        }
                    }

                    $applicationService = CMSVCHelper::getService('application');

                    $searchQuerySql = $applicationService->entity->filters->getPreparedSearchQuerySql();

                    $applicationsData = DB->query(
                        'SELECT t1.* FROM project_application t1 WHERE t1.project_id=:project_id' .
                            ($deletedView ? ' AND (t1.deleted_by_gamemaster="1" OR t1.deleted_by_player="1")' : ' AND t1.deleted_by_gamemaster="0" AND t1.deleted_by_player="0"') .
                            ($paymentApproveView ? ' AND t1.money_need_approve="1"' : '') .
                            ($noreplyView ? ' AND t1.id IN (:noreply_ids)' : '') .
                            ($needresponseView ? ' AND id IN (:needresponse_ids)' : '') .
                            ($nofillobligView ? ' AND t1.id IN (:nofilloblig_ids)' : '') .
                            ($nonsettledView ? ' AND t1.id IN (:nonsettled_ids)' : '') .
                            ($searchQuerySql ? ' AND' . $searchQuerySql : ''),
                        [
                            ['project_id', CookieHelper::getCookie('project_id')],
                            ['noreply_ids', count($noreplyIds) > 0 ? $noreplyIds : '0'],
                            ['needresponse_ids', count($needresponseIds) > 0 ? $needresponseIds : '0'],
                            ['nofilloblig_ids', count($nofillobligIds) > 0 ? $nofillobligIds : '0'],
                            ['nonsettled_ids', count($nonsettledIds) > 0 ? $nonsettledIds : '0'],
                            ...$applicationService->entity->filters->getPreparedSearchQueryParams(),
                        ],
                    );

                    foreach ($applicationsData as $applicationData) {
                        $listOfIds[] = $applicationData['id'];
                    }

                    foreach ($listOfIds as $applicationId) {
                        $returnArr = $this->conversationFormSave(
                            $name,
                            $objType,
                            $subObjType,
                            $content,
                            $applicationId,
                            $cId,
                            $useGroupName,
                            (int) $parent,
                            $voteName,
                            $voteAnswers,
                            $attachments,
                            $status,
                            $priority,
                            $dateTo,
                            $responsible,
                        );
                    }

                    if ($returnArr['cm_id']) {
                        $returnArr = [
                            'response' => 'success',
                        ];

                        $LOCALE_APPLICATION = LocaleHelper::getLocale(['application', 'global']);
                        ResponseHelper::success($LOCALE_APPLICATION['messages']['conversation_sub_obj_type_filter_success']);
                    } else {
                        $returnArr = [
                            'response' => 'error',
                            'response_error_code' => 'message_fail',
                            'response_text' => $LOCALE['messages']['fail'],
                        ];
                    }
                } else {
                    $returnArr = $this->conversationFormSave(
                        $name,
                        $objType,
                        $subObjType,
                        $content,
                        $objId,
                        $cId,
                        $useGroupName,
                        (int) $parent,
                        $voteName,
                        $voteAnswers,
                        $attachments,
                        $status,
                        $priority,
                        $dateTo,
                        $responsible,
                    );

                    if ($returnArr['cm_id'] ?? false) {
                        // $returnArr['cm_id'] = id добавленного сообщения. Если она есть, запись успешно создалась, рендерим ответ.
                        if ($objId === null) {
                            $objId = $returnArr['c_id'];
                        }

                        // добавляем привязку к родительскому объекту диалога, если есть
                        if ($clearedObjType === 'conversation_message' && $parentObjType !== '' && $parentObjId > 0) {
                            if (
                                !RightsHelper::checkRights('{child}', DataHelper::addBraces($parentObjType), $parentObjId, '{conversation}')
                                && RightsHelper::checkRights('{admin}', DataHelper::addBraces($parentObjType), $parentObjId)
                            ) {
                                RightsHelper::addRights('{child}', DataHelper::addBraces($parentObjType), $parentObjId, '{conversation}', $objId);
                            }
                        }

                        if (!REQUEST_TYPE->isApiRequest()) {
                            $returnArr['html'] = '';

                            if (in_array($objType, ['{project_wall}', '{community_wall}', '{ruling_item_wall}'])) {
                                $parentObjData = null;

                                if ($objType === '{project_wall}') {
                                    /** @var ProjectService */
                                    $projectService = CMSVCHelper::getService('project');
                                    $parentObjData = $projectService->get($objId);
                                } elseif ($objType === '{community_wall}') {
                                    /** @var CommunityService */
                                    $communityService = CMSVCHelper::getService('community');
                                    $parentObjData = $communityService->get($objId);
                                } elseif ($objType === '{ruling_item_wall}') {
                                    /** @var RulingItemEditService */
                                    $rulingItemEditService = CMSVCHelper::getService('rulingItemEdit');
                                    $parentObjData = $rulingItemEditService->get($objId);
                                }

                                if ($parentObjData) {
                                    $parentValue = (int) $parent;

                                    if ($parentValue > 0) {
                                        $commentData = DB->query(
                                            'SELECT cm.*, cms.message_read as cms_read FROM conversation_message cm LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND (cms.user_id=:user_id OR cms.user_id IS NULL) WHERE cm.id=:id',
                                            [
                                                ['user_id', CURRENT_USER->id()],
                                                ['id', $returnArr['cm_id']],
                                            ],
                                            true,
                                        );
                                        $pathToObj = '/' . $parentObjectType . '/' . $parentObjData->id->getAsInt() . '/';
                                        $uploadNum = FileHelper::getUploadNumByType($parentObjectType);
                                        $groupName = $parentObjData->name->get();
                                        $returnArr['html'] .= MessageHelper::conversationWallComment(
                                            $commentData,
                                            $pathToObj,
                                            $groupName,
                                            $uploadNum,
                                            ($commentData['use_group_name'] ?? false) === 1,
                                            $parentObjectType,
                                            $parentObjData,
                                        );
                                    } else {
                                        $conversationData = DB->findObjectById($returnArr['c_id'], 'conversation');
                                        $returnArr['html'] .= MessageHelper::conversationWall(
                                            $conversationData,
                                            $parentObjectType,
                                            $parentObjData,
                                        );
                                    }
                                }
                            } elseif (in_array($objType, ['{project_conversation}', '{community_conversation}', '{project_application_conversation}'])) {
                                $parentObjData = null;

                                if ($objType === '{project_conversation}') {
                                    /** @var ProjectService */
                                    $projectService = CMSVCHelper::getService('project');
                                    $parentObjData = $projectService->get($objId);
                                } elseif ($objType === '{community_conversation}') {
                                    /** @var CommunityService */
                                    $communityService = CMSVCHelper::getService('community');
                                    $parentObjData = $communityService->get($objId);
                                } elseif ($objType === '{project_application_conversation}') {
                                    /** @var ApplicationService */
                                    $applicationService = CMSVCHelper::getService('application');
                                    $parentObjData = $applicationService->get($objId);
                                }

                                if ($parentObjData) {
                                    $parentValue = (int) $parent;

                                    if ($parentValue > 0) {
                                        $commentData = DB->query(
                                            'SELECT cm.*, cms.message_read as cms_read FROM conversation_message cm LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND (cms.user_id=:user_id OR cms.user_id IS NULL) WHERE cm.id=:id',
                                            [
                                                ['user_id', CURRENT_USER->id()],
                                                ['id', $returnArr['cm_id']],
                                            ],
                                            true,
                                        );
                                        $uploadNum = FileHelper::getUploadNumByType($parentObjectType);
                                        $groupName = $parentObjData instanceof ProjectModel || $parentObjData instanceof CommunityModel ? $parentObjData->name->get() : null;
                                        $returnArr['html'] .= MessageHelper::conversationTreeComment(
                                            $commentData,
                                            1,
                                            $groupName,
                                            $uploadNum,
                                            $parentObjectType,
                                            $parentObjData,
                                        );
                                    } elseif ($objType === '{project_application_conversation}' && $parentValue === 0) {
                                        $conversationData = DB->findObjectById($returnArr['c_id'], 'conversation');

                                        $subObjType = DataHelper::clearBraces($conversationData['sub_obj_type']);

                                        $LOCALE_APPLICATION = LocaleHelper::getLocale(
                                            [($parentObjData->creator_id->getAsInt() === CURRENT_USER->id() && !($subObjType === 'gamemaster') ? 'my' : '') . 'application', 'global'],
                                        );

                                        $returnArr['html'] .= MessageHelper::conversationTree(
                                            $conversationData['id'],
                                            0,
                                            1,
                                            $parentObjectType,
                                            $parentObjData,
                                            $subObjType,
                                            $LOCALE_APPLICATION['titles_conversation_sub_obj_types'][$subObjType],
                                        );
                                    } else {
                                        $conversationData = DB->query(
                                            'SELECT c.id AS c_id, c.name AS c_name FROM conversation c WHERE c.id=:id',
                                            [
                                                ['id', $returnArr['c_id']],
                                            ],
                                            true,
                                        );
                                        $returnArr['html'] .= MessageHelper::conversationTreePreview(
                                            $conversationData,
                                            $parentObjectType,
                                            $objId,
                                        );
                                    }
                                }
                            } elseif ($objType === '{conversation_message}') {
                                $messageData = DB->query(
                                    'SELECT cm.*, cms.message_read as cms_read FROM conversation_message cm LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND cms.user_id != :user_id AND cms.message_read = "1" WHERE cm.id=:id',
                                    [
                                        ['user_id', CURRENT_USER->id()],
                                        ['id', $returnArr['cm_id']],
                                    ],
                                    true,
                                );
                                $returnArr['html'] .= MessageHelper::conversationConversationComment($messageData);
                            }

                            if ($returnArr['html'] === '') {
                                $returnArr['html'] = 'reload';
                            }
                        }

                        if (count($this->getMessages()) > 0) {
                            MessageHelper::createRead($this->getMessages());
                        }
                    }
                }
            } else {
                $returnArr = [
                    'response' => 'error',
                    'response_error_code' => 'message_fail',
                    'response_text' => $LOCALE['messages']['fail'],
                ];
            }

            $returnArr['response_updated_at'] = DateHelper::getNow();
        }

        return $returnArr;
    }

    /** Запись сообшения на стену / в обсуждение */
    public function conversationFormSave(
        string $name,
        string $objType,
        string $subObjType,
        string $content,
        ?int $objId,
        ?int $cId, // conversation_id
        bool $useGroupName = false,
        int $parent = 0,
        ?string $voteName = null,
        ?array $voteAnswers = null,
        ?array $attachments = [],
        ?string $status = null,
        ?int $priority = null,
        ?string $dateTo = null,
        ?int $responsible = null,
    ): array {
        $userService = $this->getUserService();

        $LOCALE = $this->LOCALE;

        $content = TextHelper::makeATsInactive($content);
        $objType = DataHelper::addBraces($objType);
        $subObjType = DataHelper::addBraces($subObjType);

        if (in_array($objType, ['{project_wall}', '{project_conversation}', '{community_wall}', '{community_conversation}'])) {
            if (
                !RightsHelper::checkRights(
                    ['{admin}', '{moderator}', '{newsmaker}', '{gamemaster}'],
                    str_replace(['_conversation', '_wall'], '', DataHelper::clearBraces($objType)),
                    $objId,
                )
            ) {
                $useGroupName = false;
            }
        } else {
            $useGroupName = false;
        }

        if (is_array($voteAnswers)) {
            foreach ($voteAnswers as $key => $value) {
                if (in_array($value, ['', $LOCALE['placeholders']['vote_choice']])) {
                    unset($voteAnswers[$key]);
                }
            }
        } else {
            $voteAnswers = [];
        }

        $allowedProjectSubObjTypes = [
            '{admin}',
            '{management}',
            '{business}',
            '{technology}',
        ];

        $allowedApplicationSubObjTypes = [
            '{to_player}',
            '{gamemaster}',
            '{from_player}',
            '{filter}',
        ];

        if ($objType === '{project_conversation}' && !in_array($subObjType, $allowedProjectSubObjTypes)) {
            return [
                'response' => 'error',
                'response_text' => $LOCALE['messages']['no_conversation_type_selected'],
            ];
        } elseif ($objType === '{project_application_conversation}' && !in_array($subObjType, $allowedApplicationSubObjTypes)) {
            return [
                'response' => 'error',
                'response_text' => $LOCALE['messages']['no_conversation_type_selected'],
            ];
        } elseif (!in_array($objType, ['{project_conversation}', '{project_application_conversation}']) && $subObjType !== '') {
            $subObjType = '';
        }

        if (
            in_array($name, ['', $LOCALE['placeholders']['topic_name']]) && in_array($objType, ['{project_conversation}', '{community_conversation}'])
            && $parent === 0
        ) {
            return ['response' => 'error', 'response_text' => $LOCALE['messages']['no_topic_set']];
        }

        if (
            ($content === '' && $objType !== '{task_comment}')
            || ($objType === '' && $objId <= 0 && $content === $LOCALE['placeholders']['enter_your_message'])
            || (
                in_array($objType, [
                    '{task_comment}',
                    '{event_comment}',
                    '{publication_wall}',
                    '{ruling_item_wall}',
                    '{project_wall}',
                    '{community_wall}',
                ])
                && $content === $LOCALE['placeholders']['write_a_message']
            )
            || (in_array($objType, [
                '{project_conversation}',
                '{community_conversation}',
            ])
                && in_array($content, [
                    $LOCALE['placeholders']['conversation_text'],
                    $LOCALE['placeholders']['comment'],
                ]))
        ) {
            return ['response' => 'error', 'response_text' => $LOCALE['messages']['no_text_set']];
        }

        if ((!$voteName || $voteName === $LOCALE['placeholders']['vote_topic_name']) && $voteAnswers) {
            return [
                'response' => 'error',
                'response_text' => $LOCALE['messages']['no_vote_topic_set'],
            ];
        } elseif ($voteName && $voteName !== $LOCALE['placeholders']['vote_topic_name'] && count($voteAnswers) <= 1) {
            return [
                'response' => 'error',
                'response_text' => $LOCALE['messages']['vote_choices_not_set'],
            ];
        }

        /* проверка на возможность писать человеку в личку (не в групповой чат) */
        if (!CURRENT_USER->isAdmin()) {
            if ($objType === '{conversation_message}' && $cId > 0) {
                $contactData = RightsHelper::findByRights('{member}', '{conversation}', $cId, '{user}', false);

                if (count($contactData) === 2) {
                    $isContact = false;

                    foreach ($contactData as $contactData_1) {
                        if ($contactData_1 !== CURRENT_USER->id()) {
                            $isContact = RightsHelper::checkRights(
                                '{friend}',
                                '{user}',
                                CURRENT_USER->id(),
                                '{user}',
                                $contactData_1,
                            );

                            if (!$isContact) {
                                $checkSupport = $userService->get($contactData_1);
                                $isContact = in_array('help', $checkSupport->rights->get());
                            }
                            break;
                        }
                    }

                    if (!$isContact) {
                        /* или же должен быть неотрезолвленный запрос на добавление в контакты */
                        $checkUnresolvedBecomeFriends = DB->select(
                            'conversation_message',
                            [
                                'conversation_id' => $cId,
                                'message_action' => '{become_friends}',
                                ['message_action_data', '%resolved%', [OperandEnum::NOT_LIKE]],
                            ],
                            true,
                        );

                        if (!isset($checkUnresolvedBecomeFriends['id'])) {
                            return [
                                'response' => 'error',
                                'response_text' => $LOCALE['messages']['recipient_is_not_contact_blocked'],
                            ];
                        }
                    }
                }
            }
        }

        $messageAction = '';
        $messageActionData = '';

        if ($objType === '{task_comment}') {
            if (isset($_REQUEST['status']) && isset($_REQUEST['priority']) && isset($_REQUEST['responsible'])) {
                $messageAction = '{change_task}';
                $messageActionData = '{status:' . $_REQUEST['status'] . ',priority:' .
                    $_REQUEST['priority']
                    . ',responsible:' . $_REQUEST['responsible'] . '}';
                DB->update(
                    'task_and_event',
                    [
                        'status' => $status,
                        'priority' => $priority,
                        'date_to' => ($dateTo ? date('Y-m-d H:i', strtotime($dateTo)) : null),
                    ],
                    ['id' => $objId],
                );

                $newResponsible = $responsible;
                RightsHelper::deleteRights('{responsible}', '{task}', $objId, '{user}', 0);
                RightsHelper::addRights('{responsible}', '{task}', $objId, '{user}', $newResponsible);
            } else {
                $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

                return [
                    'response' => 'error',
                    'response_text' => $LOCALE_FRAYM['classes']['emails']['obligatory_field_not_filled'],
                ];
            }

            if ($content === '') {
                $content = $LOCALE['messages']['task_changes'];
            }
        } elseif (count($voteAnswers) > 0) {
            $messageAction = '{vote}';
            $messageActionData = '{name:' . $voteName . '}';
            $i = 1;

            foreach ($voteAnswers as $value) {
                $messageActionData .= '{' . $i . ':' . $value . '}';
                ++$i;
            }
        }

        $result = $this->newMessage(
            $cId,
            $content,
            $name,
            [],
            $attachments,
            ['obj_type' => $objType, 'obj_id' => $objId, 'sub_obj_type' => $subObjType],
            $messageAction,
            $messageActionData,
            $parent,
            $useGroupName,
        );

        if ($result) {
            $messageData = DB->select('conversation_message', ['id' => $result], true);

            return [
                'response' => 'success',
                'response_text' => $LOCALE['messages']['success'],
                'c_id' => (string) $messageData['conversation_id'],
                'cm_id' => (string) $result,
            ];
        } else {
            return ['response' => 'error', 'response_text' => $LOCALE['messages']['fail']];
        }
    }

    /** Создание нового сообщения / ветки сообщений */
    public function newMessage(
        ?int $cId,
        string $content,
        string $name = '',
        array $userIds = [],
        ?array $attachments = [],
        array $additionalFields = [],
        string $messageAction = '',
        string $messageActionData = '',
        int $parent = 0,
        bool $useGroupName = false,
        ?string $status = null,
        ?int $priority = null,
        ?string $dateTo = null,
        ?int $responsible = null,
    ): int|bool {
        $userService = $this->getUserService();

        $LOCALE = $this->LOCALE;
        $LOCALE_SUBSCRIPTION = LocaleHelper::getLocale(['global', 'subscription']);

        $newConversation = false;

        if (is_null($cId)) {
            $newConversation = true;

            if (count($additionalFields) === 0 && trim($name) === '') {
                // если это диалог без названия особого, а не запись в проекте/сообществе и т.п., и не указано id обсуждения, то проверяем, нет ли уже диалога с таким составом участников и при этом без отдельного специального названия
                $userIdsString = CURRENT_USER->id();

                foreach ($userIds as $key => $value) {
                    $userIdsString .= ', ' . $key;
                }
                $rData = DB->query(
                    'SELECT r.obj_id_to FROM relation r LEFT JOIN conversation c ON c.id=r.obj_id_to WHERE r.obj_type_to="{conversation}" AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_id_from IN (:obj_id_froms) AND (c.name IS NULL OR c.name="") AND EXISTS (SELECT 1 FROM relation r2 WHERE r.obj_id_to=r2.obj_id_to AND r2.obj_type_to="{conversation}" AND r2.type="{member}" AND r2.obj_type_from="{user}" GROUP BY r2.obj_id_to HAVING COUNT(r2.obj_id_from)=:count_obj_id_from1) GROUP BY r.obj_id_to HAVING COUNT(r.obj_id_from)=:count_obj_id_from2 ORDER BY r.obj_id_to LIMIT 1',
                    [
                        ['obj_id_froms', $userIdsString],
                        ['count_obj_id_from1', count($userIds) + 1],
                        ['count_obj_id_from2', count($userIds) + 1],
                    ],
                    true,
                );

                if (($rData['obj_id_to'] ?? false) > 0) {
                    $cId = $rData['obj_id_to'];
                    $newConversation = false;
                }
            }
        }

        if (!isset($additionalFields['created_at'])) {
            $additionalFields['created_at'] = DateHelper::getNow();
        }

        if (!isset($additionalFields['updated_at'])) {
            $additionalFields['updated_at'] = DateHelper::getNow();
        }

        if ($newConversation) {
            foreach ($additionalFields as $field => $value) {
                if (!((is_int($value) && $value > 0) || $value !== '')) {
                    $additionalFields[$field] = [$field, null];
                }
            }
            DB->insert(
                'conversation',
                array_merge([
                    ['creator_id', CURRENT_USER->id()],
                    ['name', $name === '' ? null : $name],
                ], $additionalFields),
            );
            $cId = DB->lastInsertId();

            if ($cId > 0) {
                RightsHelper::addRights('{member}', '{conversation}', $cId);
            }
        }

        $attachmentsList = '';

        if (is_array($attachments)) {
            $attachmentsList = implode('', $attachments);
        }

        DB->insert(
            'conversation_message',
            [
                ['creator_id', CURRENT_USER->id()],
                ['conversation_id', $cId],
                ['parent', $parent],
                ['use_group_name', $useGroupName ? '1' : '0'],
                ['content', $content],
                ['attachments', $attachmentsList],
                ['message_action', $messageAction],
                ['message_action_data', $messageActionData],
                ['created_at', $additionalFields['created_at']],
                ['updated_at', $additionalFields['updated_at']],
            ],
        );
        $cmId = DB->lastInsertId();

        if ($newConversation) {
            $userIdsChange = $userIds;
            $userIds = [];

            if (count($userIdsChange) > 0) {
                foreach ($userIdsChange as $key => $value) {
                    $userIds[] = $key;
                }
            }
        } else {
            $userIds = [];
            $result = RightsHelper::findByRights('{member}', '{conversation}', $cId, '{user}', false);

            if ($result) {
                foreach ($result as $value) {
                    if ($value !== CURRENT_USER->id()) {
                        $userIds[] = $value;
                    }
                }
            }
        }

        if ((int) $cmId > 0 && (int) $cId > 0) {
            DB->insert(
                'conversation_message_status',
                [
                    ['message_id', $cmId],
                    ['user_id', CURRENT_USER->id()],
                    ['message_read', 1],
                    ['created_at', $additionalFields['created_at']],
                    ['updated_at', $additionalFields['updated_at']],
                ],
            );

            foreach ($userIds as $value) {
                if ($newConversation) {
                    RightsHelper::addRights('{member}', '{conversation}', $cId, '{user}', $value);
                }
                DB->insert(
                    'conversation_message_status',
                    [
                        ['message_id', $cmId],
                        ['user_id', $value],
                        ['message_read', 0],
                        ['created_at', $additionalFields['created_at']],
                        ['updated_at', $additionalFields['updated_at']],
                    ],
                );
            }

            DB->update('conversation', ['updated_at' => DateHelper::getNow()], ['id' => $cId]);
        }

        // если это запись в диалоге, а не в объекте, подготавливаем рассылку
        if (
            (
                (isset($additionalFields['obj_id']) && $additionalFields['obj_id'] === '')
                || !isset($additionalFields['obj_id'])
                || $additionalFields['obj_type'] === '{conversation_message}'
            )
            && count($userIds) > 0
        ) {
            $me = $userService->get(CURRENT_USER->id());

            $message = '<a href="' . ABSOLUTE_PATH . '/conversation/' . $cId . '/#bottom"><i>' . $userService->showNameExtended($me, true) . '</i></a>';

            if ($messageAction !== '') {
                $objType = '';
                $objId = 0;
                $obj = [];
                preg_match('#{([^:]+):([^,]+)}#', $messageActionData, $actionData);

                if ($actionData[1]) {
                    $objType = preg_replace('#_id#', '', $actionData[1]);
                }

                if ($actionData[2]) {
                    $objId = (int) $actionData[2];

                    if ($objType !== '') {
                        $obj = DB->findObjectById(
                            $objId,
                            in_array(DataHelper::clearBraces($objType), ['task', 'event']) ? 'task_and_event' : DataHelper::clearBraces($objType),
                        );
                    }
                }

                $linkToKind = $objType;

                if ($linkToKind === 'project_application') {
                    $linkToKind = 'application';
                }

                if ($messageAction === '{send_invitation}') {
                    if ($objType === 'project_room') {
                        $projectData = DB->findObjectById($obj['project_id'], 'project');
                        $message .= ' ' . sprintf(
                            $LOCALE_SUBSCRIPTION['send_invitation_room'],
                            DataHelper::escapeOutput($obj['name']),
                            $projectData['id'],
                            DataHelper::escapeOutput($projectData['name']),
                        );
                    } else {
                        $message .= ' ' . $LOCALE_SUBSCRIPTION['send_invitation'] . ' ' . $LOCALE_SUBSCRIPTION['obj_types'][$objType] . ' «<a href="' . ABSOLUTE_PATH . '/' . $linkToKind . '/' . $objId . '/">' . ($linkToKind === 'application' ? DataHelper::escapeOutput(
                            $obj['sorter'],
                        ) : DataHelper::escapeOutput($obj['name'])) . '</a>».';
                    }
                } elseif ($messageAction === '{get_access}') {
                    $message .= ' ' . $LOCALE_SUBSCRIPTION['get_access'] . ' ' . $LOCALE_SUBSCRIPTION['obj_types'][$objType] . ' «<a href="' . ABSOLUTE_PATH . '/' . $linkToKind . '/' . $objId . '/">' . $obj['name'] . '</a>».';
                } elseif ($messageAction === '{become_friends}') {
                    $message .= ' ' . $LOCALE_SUBSCRIPTION['become_friends'];
                }
            }

            if ($content !== '{action}') {
                $message .= '<br> ' . TextHelper::basePrepareText(DataHelper::escapeOutput($content, EscapeModeEnum::forHTMLforceNewLines));
            }

            MessageHelper::prepareEmails($userIds, [
                'author_name' => $userService->showName($me),
                'author_email' => $me->em->get(),
                'name' => $LOCALE_SUBSCRIPTION['subscription_new_message_name'],
                'content' => sprintf($LOCALE_SUBSCRIPTION['subscription_new_message_text'], $message),
                'obj_type' => 'conversation',
                'obj_id' => $cId,
            ]);

            MessageHelper::preparePushs($userIds, [
                'user_id_from' => $me->id->getAsInt(),
                'message_img' => $userService->photoUrl($me),
                'header' => (
                    $name !== '' ? DataHelper::escapeOutput($name) : $userService->showNameExtended(
                        $me,
                        true,
                        false,
                        '',
                        false,
                        false,
                        true,
                    )
                ),
                'content' => trim(strip_tags($content !== '{action}' ? DataHelper::escapeOutput($content, EscapeModeEnum::forHTMLforceNewLines) : $message)),
                'obj_type' => 'conversation',
                'obj_id' => $cId,
            ]);

            // если это диалог объекта, то добавляем загруженные в него файлы ссылками в библиотеку объекта
            if (is_array($attachments)) {
                $chatRelation = DB->select(
                    'relation',
                    [
                        'type' => '{child}',
                        'obj_type_from' => '{conversation}',
                        'obj_id_from' => $cId,
                    ],
                    true,
                );

                if ($chatRelation && $chatRelation['obj_type_to'] !== '') {
                    $type = $chatRelation['obj_type_to'];
                    $objId = $chatRelation['obj_id_to'];

                    foreach ($attachments as $value) {
                        if ($value !== '') {
                            preg_match('#{([^:]+):([^}:]+)}#', $value, $matches);

                            if ($matches && ($matches[1] ?? false) && ($matches[2] ?? false)) {
                                $path = '{external:' . $matches[1] . ':/' . $_ENV['UPLOADS'][1]['path'] . $matches[2] . '}';
                                DB->insert(
                                    'library',
                                    [
                                        ['creator_id', CURRENT_USER->id()],
                                        ['path', $path],
                                        ['created_at', DateHelper::getNow()],
                                        ['updated_at', DateHelper::getNow()],
                                    ],
                                );
                                $fileId = DB->lastInsertId();
                                RightsHelper::addRights(
                                    '{child}',
                                    DataHelper::addBraces($type),
                                    $objId,
                                    '{file}',
                                    $fileId,
                                );
                            }
                        }
                    }
                }
            }
        } else {
            $type = '';
            $parentObjData = [];
            $typeName = '';
            $typeName2 = '';
            $emailsType = '';
            $modalLinkPath = '';
            $projectData = [];
            $objType = $additionalFields['obj_type'];
            $objId = $additionalFields['obj_id'];

            $sendNotification = false;

            if ($objId > 0) {
                if ($objType === '{task_comment}') {
                    $sendNotification = true;
                    $type = 'task';
                    $emailsType = 'task';
                    $typeName = $LOCALE['obj_types']['task'];
                    $typeName2 = $LOCALE['obj_types']['task3'];
                    $parentObjData = DB->select('task_and_event', ['id' => $objId], true);
                } elseif ($objType === '{event_comment}') {
                    $sendNotification = true;
                    $type = 'event';
                    $emailsType = 'event';
                    $typeName = $LOCALE['obj_types']['event'];
                    $typeName2 = $LOCALE['obj_types']['event3'];
                    $parentObjData = DB->select('task_and_event', ['id' => $objId], true);
                } elseif ($objType === '{project_wall}' || $objType === '{project_conversation}') {
                    $sendNotification = true;
                    $type = 'project';
                    $emailsType = DataHelper::clearBraces($objType);
                    $typeName = $LOCALE['obj_types']['project'];
                    $typeName2 = $LOCALE['obj_types']['project3'];
                    $parentObjData = DB->findObjectById($objId, '{project}');
                    $modalLinkPath = '#' . ($objType === '{project_wall}' ? 'wall' : 'conversation') . '_' . $cId;
                } elseif ($objType === '{community_wall}' || $objType === '{community_conversation}') {
                    $sendNotification = true;
                    $type = 'community';
                    $emailsType = DataHelper::clearBraces($objType);
                    $typeName = $LOCALE['obj_types']['community'];
                    $typeName2 = $LOCALE['obj_types']['community3'];
                    $parentObjData = DB->findObjectById($objId, '{community}');
                    $modalLinkPath = '#' . ($objType === '{community_wall}' ? 'wall' : 'conversation') . '_' . $cId;
                } elseif ($objType === '{project_application_conversation}') {
                    $sendNotification = true;
                    $type = 'project_application';
                    $emailsType = DataHelper::clearBraces($objType);
                    $typeName = $LOCALE['obj_types']['project_application'];
                    $typeName2 = $LOCALE['obj_types']['project_application3'];
                    $parentObjData = DB->select('project_application', ['id' => $objId], true);

                    if ($parentObjData) {
                        $projectData = DB->findObjectById($parentObjData['project_id'], 'project');
                    }
                    $modalLinkPath = '#wmc_' . $cmId;
                } elseif ($objType === '{ruling_item_wall}') {
                    $sendNotification = true;
                    $type = 'ruling_item';
                    $emailsType = DataHelper::clearBraces($objType);
                    $typeName = $LOCALE['obj_types']['ruling_item'];
                    $typeName2 = $LOCALE['obj_types']['ruling_item3'];
                    $parentObjData = DB->select('ruling_item', ['id' => $objId], true);
                }
            }

            if (is_array($attachments) && $type !== '' && $objId > 0) {
                foreach ($attachments as $value) {
                    if ($value !== '') {
                        preg_match('#{([^:]+):([^}:]+)}#', $value, $matches);

                        if ($matches && ($matches[1] ?? false) && ($matches[2] ?? false)) {
                            $path = '{external:' . $matches[1] . ':/' . $_ENV['UPLOADS'][FileHelper::getUploadNumByType($type)]['path'] . $matches[2] . '}';
                            DB->insert(
                                'library',
                                [
                                    ['creator_id', CURRENT_USER->id()],
                                    ['path', $path],
                                    ['created_at', DateHelper::getNow()],
                                    ['updated_at', DateHelper::getNow()],
                                ],
                            );
                            $fileId = DB->lastInsertId();
                            RightsHelper::addRights('{child}', DataHelper::addBraces($type), $objId, '{file}', $fileId);
                        }
                    }
                }
            }

            if ($sendNotification) {
                $users = [];
                $linksKind = $type;

                // отсылка приглашений в объект упомянутым в сообщении людям
                $usersInvitedDirectly = false;

                if ($type !== '') {
                    preg_match_all('#@([^\[]+)\[(\d+)]#', DataHelper::escapeOutput($content), $matches);

                    foreach ($matches[2] as $match) {
                        if ((int) $match > 0) {
                            /* нужно высылать приглашение на id, а выдается sid */
                            $invitedUserData = $userService->get(null, ['sid' => $match]);

                            if ($invitedUserData->id->getAsInt() > 0) {
                                if ($type === 'project_application') {
                                    $users[] = $invitedUserData->id->getAsInt();
                                } else {
                                    /** @var ConversationService $conversationService */
                                    $conversationService = CMSVCHelper::getService('conversation');

                                    $conversationService->sendInvitation(
                                        DataHelper::addBraces($type),
                                        $objId,
                                        $invitedUserData->id->getAsInt(),
                                    );
                                }
                                $usersInvitedDirectly = true;
                            }
                        }
                    }
                }

                $groupNamedMessage = false;

                if (
                    $useGroupName && RightsHelper::checkRights(
                        ['{admin}', '{moderator}', '{newsmaker}', '{gamemaster}'],
                        str_replace(['_conversation', '_wall'], '', DataHelper::clearBraces($objType)),
                        $objId,
                    )
                ) {
                    $groupNamedMessage = true;
                }

                if (
                    in_array($objType, ['{project_wall}', '{project_conversation}', '{community_wall}', '{community_conversation}', '{ruling_item_wall}'])
                    && !$newConversation
                ) {
                    // ответ на тему на стене / в обсуждениях: высылаем только тем, кто в ней писал
                    $conversationUsers = DB->query(
                        'SELECT DISTINCT creator_id FROM conversation_message WHERE conversation_id=:conversation_id',
                        [
                            ['conversation_id', $cId],
                        ],
                    );

                    foreach ($conversationUsers as $conversationUsersData) {
                        $users[] = $conversationUsersData['creator_id'];
                    }
                } elseif ($objType === '{project_application_conversation}') {
                    // комментарий в заявке - высылаем в зависимости от автора
                    if (CURRENT_USER->id() === $parentObjData['creator_id'] || $additionalFields['sub_obj_type'] === '{gamemaster}') {
                        $linksKind = 'application/application';
                        $modalLinkPath = 'act=edit&project_id=' . $parentObjData['project_id'] . $modalLinkPath;
                    } else {
                        $linksKind = 'myapplication';
                    }

                    if (CURRENT_USER->id() === $parentObjData['creator_id']) {
                        if ($parentObjData['deleted_by_gamemaster'] !== '1') {
                            if ($userService->get($parentObjData['responsible_gamemaster_id'])) {
                                $users[] = $parentObjData['responsible_gamemaster_id'];
                            } else {
                                $users = RightsHelper::findByRights(
                                    ['{admin}', '{gamemaster}'],
                                    '{project}',
                                    $parentObjData['project_id'],
                                    '{user}',
                                    false,
                                );
                            }
                        }
                    } elseif ($additionalFields['sub_obj_type'] === '{gamemaster}' && $newConversation && !$usersInvitedDirectly) {
                        // рассылаем всем мастерам сообщение, только если оно от мастера другим мастерам, при этом это новая ветка обсуждения, при этом в ней нет приглашаемых директно людей
                        $users = RightsHelper::findByRights(
                            ['{admin}', '{gamemaster}'],
                            '{project}',
                            $parentObjData['project_id'],
                            '{user}',
                            false,
                        );
                    } elseif ($additionalFields['sub_obj_type'] === '{to_player}' || $additionalFields['sub_obj_type'] === '{from_player}') {
                        // отправляем изменения автору
                        if ($parentObjData['deleted_by_player'] !== '1') {
                            $users[] = $parentObjData['creator_id'];
                        }
                    }

                    // если это какой-то ответ на сообщение, то добавляем всех, кто участвовал в этой ветке из текущих мастеров + создателя заявки
                    if (!$newConversation) {
                        $checkUsers = RightsHelper::findByRights(
                            ['{admin}', '{gamemaster}'],
                            '{project}',
                            $parentObjData['project_id'],
                            '{user}',
                            false,
                        );
                        $conversationUsers = DB->query(
                            'SELECT DISTINCT creator_id FROM conversation_message WHERE conversation_id=:conversation_id',
                            [
                                ['conversation_id', $cId],
                            ],
                        );

                        foreach ($conversationUsers as $conversationUsersData) {
                            if (
                                $conversationUsersData['creator_id'] === $parentObjData['creator_id'] || ($checkUsers && in_array(
                                    $conversationUsersData['creator_id'],
                                    $checkUsers,
                                ))
                            ) {
                                $users[] = $conversationUsersData['creator_id'];
                            }
                        }
                    }

                    // выставляем заодно у заявки дату изменения и человека
                    // query("UPDATE project_application SET updated_at=" . DateHelper::getNow() . ", last_update_user_id=" . CURRENT_USER->id() . " WHERE id=" . $parentObjData['id']);
                } elseif ($groupNamedMessage || in_array($objType, ['{task_comment}', '{event_comment}'])) {
                    // новая тема на стене / в обсуждениях или оповещение по задаче / событию: высылаем всем (если написано от имени группы)
                    $users = RightsHelper::findByRights(
                        null,
                        DataHelper::addBraces($type),
                        $parentObjData['id'],
                        '{user}',
                        false,
                    );
                } elseif ($objType === '{ruling_item_wall}') {
                    // комментарий к модели в Рулежке
                    $users[] = $parentObjData['creator_id'];
                }

                /* добавляем всех с подпиской на объект */
                $subscriptionUsers = RightsHelper::findByRights(
                    '{subscribe}',
                    DataHelper::addBraces($type),
                    $objId,
                    '{user}',
                    false,
                );

                if (is_array($subscriptionUsers)) {
                    $users = array_merge($users, $subscriptionUsers);
                }

                $users = array_unique($users);

                $user = $userService->get(CURRENT_USER->id());

                if ($groupNamedMessage) {
                    $browserPushName = $messageName = DataHelper::escapeOutput($parentObjData['name']);
                    $browserPushMessage = $message = TextHelper::basePrepareText(DataHelper::escapeOutput($content, EscapeModeEnum::forHTMLforceNewLines)) . '<br><br>
<a href="' . ABSOLUTE_PATH . '/' . $linksKind . '/' . $parentObjData['id'] . '/' . $modalLinkPath . '">' . DataHelper::escapeOutput($parentObjData['name']) . '</a>';
                } else {
                    $messageName = $LOCALE['subscription']['name'] . ' ' . $typeName2 . ' ' . ($objType === '{project_application_conversation}' ? DataHelper::escapeOutput($parentObjData['sorter']) . ' ' . $LOCALE['obj_types']['project2'] . ' ' . DataHelper::escapeOutput($projectData['name'] ?? '') : DataHelper::escapeOutput($parentObjData['name']));

                    $message = sprintf($LOCALE['subscription']['base_text'], $userService->showNameExtended($user, true)) . LocaleHelper::declineVerb($user) . ' ' . $typeName . ' <a href="' . ABSOLUTE_PATH . '/' . $linksKind . '/' . $parentObjData['id'] . '/' . $modalLinkPath . '">' . ($objType === '{project_application_conversation}' ? DataHelper::escapeOutput($parentObjData['sorter']) : DataHelper::escapeOutput($parentObjData['name'])) . '</a>.<br><br><i>' . TextHelper::basePrepareText(DataHelper::escapeOutput($content, EscapeModeEnum::forHTMLforceNewLines)) . '</i>';

                    $browserPushName = ($objType === '{project_application_conversation}' ? DataHelper::escapeOutput(
                        $parentObjData['sorter'],
                    ) . ' (' . DataHelper::escapeOutput($projectData['name']) . ')' : DataHelper::escapeOutput($parentObjData['name']));
                    $browserPushMessage = $userService->showNameExtended($user, true) . '<br>' .
                        TextHelper::basePrepareText(DataHelper::escapeOutput($content, EscapeModeEnum::forHTMLforceNewLines));

                    $LOCALE_TASK = LocaleHelper::getLocale(['task', 'fraym_model']);

                    if ($type === 'task' && !is_null($status)) {
                        $message .= '<br><br><b>' . $LOCALE_TASK['elements']['status']['shownName'] . ':</b> ' .
                            DataHelper::getFlatArrayElement($status, $LOCALE_TASK['elements']['status']['values'])[1] . '.';
                    }

                    if ($type === 'task' && !is_null($priority)) {
                        $message .= '<br><br><b>' . $LOCALE_TASK['elements']['priority']['shownName'] . ':</b> ' .
                            DataHelper::getFlatArrayElement($priority, $LOCALE_TASK['elements']['priority']['values'])[1] . '.';
                    }

                    if ($type === 'task' && !is_null($responsible)) {
                        if ($responsible > 0) {
                            $message .= '<br><b>' . $LOCALE_TASK['elements']['responsible']['shownName'] . ':</b> ' .
                                $userService->showNameExtended($userService->get($responsible), true, true) . '.';
                        }
                    }

                    if ($type === 'task' && !is_null($dateTo)) {
                        $message .= '<br><b>' . $LOCALE_TASK['elements']['date_to']['shownName'] . ':</b> ' . ($dateTo !== '' ? date('d.m.Y H:i', strtotime($dateTo)) : $LOCALE['date_to_not_set']) . '.';
                    }
                    $message .= $LOCALE['subscription']['base_text2'];
                }

                if ($users) {
                    MessageHelper::prepareEmails($users, [
                        'author_name' => $userService->showName($user),
                        'author_email' => ($user->em->get() !== null ? DataHelper::escapeOutput(
                            $user->em->get(),
                        ) : $LOCALE_SUBSCRIPTION['author_email']),
                        'name' => $messageName,
                        'content' => $message,
                        'obj_type' => $emailsType,
                        'obj_id' => $objId,
                    ]);

                    MessageHelper::preparePushs($users, [
                        'user_id_from' => $user->id->getAsInt(),
                        'message_img' => $userService->photoUrl($user),
                        'header' => $browserPushName,
                        'content' => trim(strip_tags(str_replace('<br>', "\n", $browserPushMessage))),
                        'obj_type' => $linksKind,
                        'obj_id' => $parentObjData['id'],
                    ]);
                }
            }
        }

        if ((int) $cmId > 0 && (int) $cId > 0) {
            return (int) $cmId;
        }

        return false;
    }

    /** Голосование */
    public function vote(?int $messageId, ?int $value, string $type): array
    {
        $LOCALE = LocaleHelper::getLocale(['conversation', 'global', 'messages']);

        if ($messageId > 0 && $value > 0 && $type !== '') {
            $commentContent = '';

            $commentData = DB->select('conversation_message', ['id' => $messageId], true);

            if ($commentData['message_action'] === '{vote}') {
                RightsHelper::addRights(
                    '{voted}',
                    '{message}',
                    $commentData['id'],
                    '{user}',
                    CURRENT_USER->id(),
                    (string) $value,
                );

                $voteResults = [];
                // определяем общее количество голосов
                $result = DB->select(
                    'relation',
                    [
                        'type' => '{voted}',
                        'obj_type_from' => '{user}',
                        'obj_type_to' => '{message}',
                        'obj_id_to' => $commentData['id'],
                    ],
                    false,
                    null,
                    null,
                    null,
                    true,
                );
                $totalVotesCount = $result[0];

                $highestVote = 1;
                preg_match_all('#{([^:]+):([^}]+)}#', $commentData['message_action_data'], $matches);

                foreach ($matches[0] as $key => $value) {
                    if ($matches[1][$key] !== 'name') {
                        // считаем результаты по данному пункту
                        $result = DB->select(
                            'relation',
                            [
                                'type' => '{voted}',
                                'obj_type_from' => '{user}',
                                'obj_type_to' => '{message}',
                                'obj_id_to' => $commentData['id'],
                                'comment' => $matches[1][$key],
                            ],
                            false,
                            null,
                            null,
                            null,
                            true,
                        );
                        $votesCount = $result[0];

                        $voteResults[$matches[1][$key]] = $votesCount;

                        if ($votesCount > $highestVote) {
                            $highestVote = $votesCount;
                        }

                        $commentContent .= '<div class="' . $type . '_message_vote_choice_made" obj_id="' . $commentData['id'] . '" value="' .
                            DataHelper::escapeOutput($matches[1][$key]) . '">' . DataHelper::escapeOutput($matches[2][$key]) . '<br>
<div class="' . $type . '_message_vote_choice_made_percent">' . ($votesCount / $totalVotesCount * 100) . '%</div><div class="' . $type . '_message_vote_choice_made_bar" style="width:<!--result_' . $matches[1][$key] . '_percent-->%"></div><div class="' . $type . '_message_vote_choice_made_bar_count">' . $votesCount . '</div>';
                        $commentContent .= '</div>';
                    }
                }
                $voteResultsPercent = [];

                foreach ($voteResults as $key => $value) {
                    $voteResultsPercent[$key] = $value / $highestVote * 94;
                }

                foreach ($voteResultsPercent as $key => $value) {
                    $commentContent = str_replace('<!--result_' . $key . '_percent-->', (string) $value, $commentContent);
                }
            }

            if ($commentContent !== '') {
                return ['response' => 'success', 'response_text' => $commentContent];
            } else {
                return [
                    'response' => 'error',
                    'response_text' => $LOCALE['vote_parameters_error'],
                ];
            }
        } else {
            return [
                'response' => 'error',
                'response_text' => $LOCALE['vote_parameters_error'],
            ];
        }
    }
}
