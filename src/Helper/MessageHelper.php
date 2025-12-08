<?php

declare(strict_types=1);

namespace App\Helper;

use App\CMSVC\Application\ApplicationModel;
use App\CMSVC\Community\CommunityModel;
use App\CMSVC\Event\EventModel;
use App\CMSVC\Project\ProjectModel;
use App\CMSVC\PublicationsEdit\PublicationsEditModel;
use App\CMSVC\RulingItemEdit\RulingItemEditModel;
use App\CMSVC\Task\TaskModel;
use App\CMSVC\User\UserService;
use Fraym\Element\Item;
use Fraym\Enum\EscapeModeEnum;
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Helper;

abstract class MessageHelper implements Helper
{
    /** Вывод комментариев задач */
    public static function conversationTask(
        array $conversationData,
        int $i = 1,
        ?bool $API_REQUEST = null,
    ): array|string {
        $API_REQUEST = $API_REQUEST ?? REQUEST_TYPE->isApiRequest();

        $API_CONVERSATIONS_DATA = [];
        $messages = [];

        $commentContent = '<div class="task_message_container">';
        $result = DB->query(
            'SELECT cm.*, cms.message_read as cms_read FROM conversation_message cm LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND (cms.user_id=:user_id OR cms.user_id IS NULL) WHERE cm.conversation_id=:conversation_id ORDER BY (cm.parent=0) DESC, cm.updated_at',
            [
                ['user_id', CURRENT_USER->id()],
                ['conversation_id', $conversationData['id']],
            ],
        );
        $commentData = $result[0];

        if ($commentData['id'] !== '') {
            $commentContent .= '<div class="task_message">';

            $commentContent .= MessageHelper::conversationTaskComment($commentData, $i);

            $commentContent .= '</div>';

            if ($commentData['cms_read'] !== '1') {
                $messages[] = $commentData['id'];
            }
        }

        $commentContent .= '
</div>';

        self::createRead($messages);

        if ($API_REQUEST) {
            return $API_CONVERSATIONS_DATA;
        } else {
            return $commentContent;
        }
    }

    /** Вывод сообщения в комментариях задач */
    public static function conversationTaskComment(
        array $commentData,
        int $i = 1,
        ?bool $API_REQUEST = null,
    ): array|string {
        $API_REQUEST = $API_REQUEST ?? REQUEST_TYPE->isApiRequest();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = LocaleHelper::getLocale(['task', 'fraym_model', 'elements']);

        $API_CONVERSATION_DATA = [];

        $commentCreator = $userService->get($commentData['creator_id']);
        $commentContent = '<div class="task_message_photo">' . $userService->photoNameLink($commentCreator, '', false) . '</div>
<div class="task_message_data" obj_id="' . $commentData['id'] . '">
<div class="task_id"><a id="wmc_' . $commentData['id'] . '" href="' . ABSOLUTE_PATH . '/' . KIND . '/' . DataHelper::getId() . '/#wmc_' . $commentData['id'] . '">#' . $i . '</a></div>';

        if ($commentData['message_action_data'] !== '') {
            $commentContent .= '<div class="task_changes">';
            preg_match_all('#([a-z]+):([^,]+)#', DataHelper::clearBraces($commentData['message_action_data']), $matches);
            $taskChangesContent = '';

            foreach ($matches[1] as $key => $match) {
                if ($match === 'responsible') {
                    $taskChangesContent = $userService->photoNameLink($userService->get($matches[2][$key]), '', false, 'tooltipBottomRight') . $taskChangesContent;
                } elseif ($LOCALE[$match] ?? false) {
                    $taskChangesContent .= $LOCALE[$match]['shownName'] . ': ' .
                        DataHelper::getFlatArrayElement($matches[2][$key], $LOCALE[$match]['values'])[1];
                }

                if ($key !== count($matches[1]) - 1) {
                    $taskChangesContent .= '<br>';
                }
            }
            $commentContent .= $taskChangesContent . '</div>';
        }
        $commentContent .= '
<div class="task_message_creator">' . $userService->showNameExtended($commentCreator, true, true, '', false, false, true) . '</div>
<div class="task_message_content' . ($commentData['cms_read'] === '1' ? '' : ' unread') . '">' . self::viewActions($commentData['id'], 3) . '</div>
<div class="task_message_time">' . DateHelper::showDateTime($commentData['updated_at']) . '</div>
</div>
<div class="clear"></div>
';

        if ($API_REQUEST) {
            return $API_CONVERSATION_DATA;
        } else {
            return $commentContent;
        }
    }

    /** Вывод стены сообщений */
    public static function conversationWall(
        array $conversationData,
        string $parentObjType,
        CommunityModel|ProjectModel|TaskModel|EventModel|PublicationsEditModel|RulingItemEditModel|null $parentObjData,
        bool $addGroupName = false,
        ?bool $API_REQUEST = null,
    ): array|string {
        $API_REQUEST = $API_REQUEST ?? REQUEST_TYPE->isApiRequest();

        $LOCALE = LocaleHelper::getLocale(['global']);
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $messages = [];
        $objType = '';
        $parentObjType = DataHelper::clearBraces($parentObjType);

        $objId = $parentObjData->id->getAsInt();
        $groupName = $parentObjData->name->get();
        $pathToObj = '/' . $parentObjType . '/' . $objId . '/';
        $uploadNum = FileHelper::getUploadNumByType($parentObjType);

        if ($parentObjType === 'event') {
            $objType = '{event_comment}';
        } elseif ($parentObjType === 'task') {
            $objType = '{task_comment}';
        } elseif ($parentObjType === 'project') {
            $objType = '{project_wall}';
        } elseif ($parentObjType === 'community') {
            $objType = '{community_wall}';
        } elseif ($parentObjType === 'publication') {
            $objType = '{publication_wall}';
        } elseif ($parentObjType === 'ruling_item') {
            $objType = '{ruling_item_wall}';
        }
        $parentOfTopic = 0;

        $commentContent = '<div class="wall_message_container">';

        if (CURRENT_USER->isLogged()) {
            $result = DB->query(
                'SELECT cm.*, cms.message_read as cms_read FROM conversation_message cm LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND (cms.user_id=:user_id OR cms.user_id IS NULL) WHERE cm.conversation_id=:conversation_id ORDER BY (cm.parent=0) DESC, cm.updated_at',
                [
                    ['user_id', CURRENT_USER->id()],
                    ['conversation_id', $conversationData['id']],
                ],
            );
        } else {
            $result = DB->query(
                'SELECT cm.* FROM conversation_message cm WHERE cm.conversation_id=:conversation_id ORDER BY (cm.parent=0) DESC, cm.updated_at',
                [
                    ['conversation_id', $conversationData['id']],
                ],
            );
        }
        $commentsCount = count($result) - 1;
        $i = -1;
        $stopHidingComments = true;

        $API_COMMENTS_DATA = [];

        foreach ($result as $commentData) {
            ++$i;

            if ($commentsCount > 4 && $i === 1 && $commentData['cms_read'] === '1') {
                $commentContent .= '<a class="show_hidden child">' . $LOCALE['show_hidden'] . '</a><div class="hidden">';
                $stopHidingComments = false;
            }

            if ($parentOfTopic === 0) {
                $parentOfTopic = $commentData['id'];
            }

            if ($commentData['cms_read'] !== '1') {
                $messages[] = $commentData['id'];

                if (!$stopHidingComments) {
                    $stopHidingComments = true;
                    $commentContent .= '</div>';
                }
            }

            if ($API_REQUEST) {
                $API_COMMENTS_DATA[$commentData['id']] = MessageHelper::conversationWallComment(
                    $commentData,
                    $pathToObj,
                    $groupName,
                    $uploadNum,
                    $i === 0 && $addGroupName,
                    $parentObjType,
                    $parentObjData,
                );
            } else {
                $commentContent .= MessageHelper::conversationWallComment(
                    $commentData,
                    $pathToObj,
                    $groupName,
                    $uploadNum,
                    $i === 0 && $addGroupName,
                    $parentObjType,
                    $parentObjData,
                );
            }

            if ($commentsCount > 4 && $i === $commentsCount - 4 && !$stopHidingComments) {
                $commentContent .= '</div>';
            }
        }
        $commentContent .= MessageHelper::conversationForm(
            $conversationData['id'],
            $objType,
            $objId,
            $LOCALE_CONVERSATION['placeholders']['comment'],
            $parentOfTopic,
            count($result) > 1,
        );
        $commentContent .= '<hr>
</div>';

        self::createRead($messages);

        if ($API_REQUEST) {
            return $API_COMMENTS_DATA;
        } else {
            return $commentContent;
        }
    }

    /** Вывод сообщения на стене сообщений */
    public static function conversationWallComment(
        array $commentData,
        string $pathToObj,
        string $groupName,
        ?int $uploadNum,
        bool $addGroupName,
        string $parentObjType = '',
        CommunityModel|ProjectModel|TaskModel|EventModel|PublicationsEditModel|RulingItemEditModel|null $parentObjData = null,
        ?bool $API_REQUEST = null,
    ): array|string {
        $API_REQUEST = $API_REQUEST ?? REQUEST_TYPE->isApiRequest();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

        $commentCreator = $userService->get($commentData['creator_id']);

        $parentObjDataId = $parentObjData->id->getAsInt();

        if ($API_REQUEST) {
            $objType = '';

            if ($parentObjType === 'event') {
                $objType = '{event_comment}';
            } elseif ($parentObjType === 'task') {
                $objType = '{task_comment}';
            } elseif ($parentObjType === 'project') {
                $objType = '{project_wall}';
            } elseif ($parentObjType === 'community') {
                $objType = '{community_wall}';
            } elseif ($parentObjType === 'publication') {
                $objType = '{publication_wall}';
            } elseif ($parentObjType === 'ruling_item') {
                $objType = '{ruling_item_wall}';
            }

            $commentReply = 'false';
            $commentEdit = 'false';
            $commentDelete = 'false';

            if (CURRENT_USER->isLogged()) {
                if (!($addGroupName || ($parentObjType === 'task' || $parentObjType === 'event'))) {
                    $commentReply = 'true';

                    if ($commentCreator->id->getAsInt() === CURRENT_USER->id() && DateHelper::getNow() <= $commentData['created_at'] + 3600) {
                        $commentEdit = 'true';
                    }

                    if (
                        (
                            ($parentObjType === 'project' || $parentObjType === 'community')
                            && RightsHelper::checkRights(['{admin}', '{moderator}'], DataHelper::addBraces($parentObjType), $parentObjDataId)
                        )
                        || $commentCreator->id->getAsInt() === CURRENT_USER->id() || CURRENT_USER->isAdmin() || $userService->isModerator()
                    ) {
                        $commentDelete = 'true';
                    }
                }
            }

            $API_COMMENT_DATA = [
                'message_id' => $commentData['id'],
                'parent_or_child' => ($commentData['parent'] > 0 ? 'child' : 'parent'),
                'author_id' => $commentData['creator_id'],
                'author_avatar' => $userService->photoUrl($commentCreator),
                'author_name' => $userService->showName($commentCreator),
                'time' => DateHelper::showDateTime($commentData['updated_at']),
                'timestamp' => $commentData['updated_at'],
                'read' => ($commentData['cms_read'] === '1' ? 'read' : 'unread'),
                'use_group_name' => ($commentData['use_group_name'] === '1' ? 'true' : 'false'),
                'parent' => [
                    'add_group_name' => $addGroupName,
                    'name' => $groupName,
                    'href' => $pathToObj,
                    'obj_type' => $parentObjType,
                    'save_obj_type' => $objType,
                    'obj_id' => $parentObjDataId,
                ],
                'commands' => [
                    'reply' => $commentReply,
                    'edit' => $commentEdit,
                    'delete' => $commentDelete,
                ],
                'important' => UniversalHelper::drawImportant(
                    'conversation_message',
                    $commentData['id'],
                ),
            ];

            return array_merge(
                $API_COMMENT_DATA,
                self::viewActions(
                    $commentData['id'],
                    $uploadNum,
                    API_REQUEST: $API_REQUEST,
                ),
            );
        }

        $commentContent = '<div class="wall_message' . ($commentData['parent'] > 0 ? ' child' : '') . '" message_id="' . $commentData['id'] . '">';

        $avatarUploadNum = ($parentObjType === 'project' || $parentObjType === 'community') ? 'projects_and_communities_avatars' : $parentObjType;

        $commentContent .= '<div class="wall_message_photo">' . (($commentData['use_group_name'] === '1' && FileHelper::getUploadNumByType($avatarUploadNum) && $pathToObj) ? '<div class="photoName"><a href="' . $pathToObj . '"><div class="photoName_photo_wrapper"><div class="photoName_photo" style="' . DesignHelper::getCssBackgroundImage(FileHelper::getImagePath($parentObjData->attachments->get(), FileHelper::getUploadNumByType($avatarUploadNum)) ?? ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_' . $parentObjType . '.svg') . '" title="' . $groupName . '"></div></div></a></div>' : $userService->photoNameLink($commentCreator, '', false)) . '</div>
<div class="wall_message_data">';

        if (CURRENT_USER->isLogged()) {
            $commentContent .= '<div class="wall_message_more"></div><div class="wall_message_more_list">';

            if ($addGroupName && ($parentObjType === 'task' || $parentObjType === 'event')) {
                $commentContent .= '<a class="wall_message_more_function wall_message_more_goto" href="' . $pathToObj . '">' . $LOCALE['placeholders']['goto'] . '</a>';
            } else {
                $commentContent .= '<a class="wall_message_more_function wall_message_more_reply">' . ($commentData['parent'] > 0 ? $LOCALE['placeholders']['reply'] : $LOCALE['placeholders']['comment']) . '</a>';

                if ($commentCreator->id->getAsInt() === CURRENT_USER->id() && DateHelper::getNow() <= $commentData['created_at'] + 3600) {
                    $commentContent .= '<a class="wall_message_more_function wall_message_more_edit">' . $LOCALE['placeholders']['edit'] . '</a>';
                }

                if ((($parentObjType === 'project' || $parentObjType === 'community') && RightsHelper::checkRights(
                    ['{admin}', '{moderator}'],
                    DataHelper::addBraces($parentObjType),
                    $parentObjDataId,
                )) || ($commentCreator->id->getAsInt() === CURRENT_USER->id() && DateHelper::getNow() <= $commentData['created_at'] + 3600) || CURRENT_USER->isAdmin() || $userService->isModerator()) {
                    $commentContent .= '<a class="wall_message_more_function wall_message_more_delete">' . $LOCALE['placeholders']['delete'] . '</a>';
                }
                /*if ($commentCreator->id->getAsInt() != CURRENT_USER->id()) {
                    $commentContent .= '<a class="wall_message_more_function wall_message_more_spam">' . $LOCALE['placeholders']['functions_spam_2'] . '</a>';
                }*/

                if ($parentObjType === 'project' || $parentObjType === 'community') {
                    $checkTask = RightsHelper::findOneByRights(
                        '{child}',
                        '{conversation_message}',
                        $commentData['id'],
                        '{task}',
                        null,
                    );
                    $LOCALE_PARENT = LocaleHelper::getLocale([$parentObjType, 'global']);

                    if ($checkTask > 0) {
                        $taskData = DB->select('task_and_event', ['id' => $checkTask], true);

                        if ($taskData['id']) {
                            $commentContent .= '<a class="wall_message_more_function wall_message_more_add_task" href="' .
                                ABSOLUTE_PATH . '/task/' . $taskData['id'] . '/">' . DataHelper::escapeOutput($taskData['name']) . '</a>';
                        } else {
                            $commentContent .= '<a class="wall_message_more_function wall_message_more_add_task" href="' . ABSOLUTE_PATH . '/task/task/act=add&obj_id=' . $parentObjDataId . '&obj_type=' . $parentObjType . '&message_id=' . $commentData['id'] . '">' . $LOCALE_PARENT['add_task'] . '</a>';
                        }
                    } else {
                        $commentContent .= '<a class="wall_message_more_function wall_message_more_add_task" href="' . ABSOLUTE_PATH . '/task/task/act=add&obj_id=' . $parentObjDataId . '&obj_type=' . $parentObjType . '&message_id=' . $commentData['id'] . '">' . $LOCALE_PARENT['add_task'] . '</a>';
                    }

                    $checkEvent = RightsHelper::findOneByRights(
                        '{child}',
                        '{conversation_message}',
                        $commentData['id'],
                        '{event}',
                        null,
                    );

                    if ($checkEvent > 0) {
                        $eventData = DB->select('task_and_event', ['id' => $checkEvent], true);

                        if ($eventData['id']) {
                            $commentContent .= '<a class="wall_message_more_function wall_message_more_add_event" href="' .
                                ABSOLUTE_PATH . '/event/' . $eventData['id'] . '/">' . DataHelper::escapeOutput($eventData['name']) . '</a>';
                        } else {
                            $commentContent .= '<a class="wall_message_more_function wall_message_more_add_event" href="' . ABSOLUTE_PATH . '/event/event/act=add&obj_id=' . $parentObjDataId . '&obj_type=' . $parentObjType . '&message_id=' . $commentData['id'] . '">' . $LOCALE_PARENT['add_event'] . '</a>';
                        }
                    } else {
                        $commentContent .= '<a class="wall_message_more_function wall_message_more_add_event" href="' . ABSOLUTE_PATH . '/event/event/act=add&obj_id=' . $parentObjDataId . '&obj_type=' . $parentObjType . '&message_id=' . $commentData['id'] . '">' . $LOCALE_PARENT['add_event'] . '</a>';
                    }
                }
            }
            $commentContent .= '</div>';
        }

        $commentContent .= '<div class="wall_message_creator">' . ($commentData['use_group_name'] === '1' ? '<a href="' . $pathToObj . '">' . $groupName . '</a>' :
            $userService->showNameExtended(
                $commentCreator,
                true,
                true,
                '',
                false,
                false,
                true,
            ) . ($addGroupName ? ' (<a href="' . $pathToObj . '">' . $groupName . '</a>)' : '')) . '<div class="hidden">' .
            DateHelper::showDateTime($commentData['updated_at']) . '</div></div>
<div class="wall_message_content' . ($commentData['cms_read'] === '1' || !CURRENT_USER->isLogged() ? '' : ' unread') . '">' .
            self::viewActions($commentData['id'], $uploadNum);

        if ($commentData['message_action'] === '{vote}') {
            preg_match_all('#{([^:]+):([^}]+)}#', $commentData['message_action_data'], $matches);

            // проверяем, голосовал ли уже пользователь
            $voteMade = RightsHelper::checkRights('{voted}', '{message}', $commentData['id']);

            $totalVotesCount = 0;
            $voteResults = [];
            $highestVote = 1;

            if ($voteMade) {
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
            }

            foreach ($matches[0] as $key => $value) {
                if ($matches[1][$key] === 'name') {
                    $commentContent .= '<div class="wall_message_vote">' . DataHelper::escapeOutput($matches[2][$key]) . '</div><hr>';
                } elseif ($voteMade) {
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

                    $commentContent .= '<div class="wall_message_vote_choice_made" obj_id="' . $commentData['id'] . '" value="' . DataHelper::escapeOutput(
                        $matches[1][$key],
                    ) . '">' . DataHelper::escapeOutput($matches[2][$key]) . '<br>
<div class="wall_message_vote_choice_made_percent">' . round(
                        $votesCount / $totalVotesCount * 100,
                        2,
                    ) . '%</div><div class="wall_message_vote_choice_made_bar" style="width:<!--result_' . $matches[1][$key] . '_percent-->%"></div><div class="wall_message_vote_choice_made_bar_count">' . $votesCount . '</div>';
                    $commentContent .= '</div>';
                } else {
                    $commentContent .= '<div class="wall_message_vote_choice"><input name="vote[' . $commentData['id'] . ']" type="radio" class="inputradio" value="' . DataHelper::escapeOutput(
                        $matches[1][$key],
                    ) . '" m_id="' . $commentData['id'] . '" id="vote_choice[' . $commentData['id'] . '][' . DataHelper::escapeOutput(
                        $matches[1][$key],
                    ) . ']"> <label for="vote_choice[' . $commentData['id'] . '][' . DataHelper::escapeOutput(
                        $matches[1][$key],
                    ) . ']">' . DataHelper::escapeOutput($matches[2][$key]) . '</label></div>';
                }
            }

            if ($voteMade) {
                $voteResultsPercent = [];

                foreach ($voteResults as $key => $value) {
                    $voteResultsPercent[$key] = $value / $highestVote * 94;
                }

                foreach ($voteResultsPercent as $key => $value) {
                    $commentContent = str_replace('<!--result_' . $key . '_percent-->', (string) $value, $commentContent);
                }
            }
        }
        $commentContent .= '</div>
<div class="wall_message_time">' . UniversalHelper::drawImportant(
            'conversation_message',
            $commentData['id'],
        ) . ($commentData['use_group_name'] === '1' ? $userService->showNameExtended(
            $commentCreator,
            true,
            true,
            'use_group_name',
        ) : '') . '<span class="mobile_hidden">' . DateHelper::showDateTime($commentData['updated_at']) . '</span>';

        if ($addGroupName && ($parentObjType === 'task' || $parentObjType === 'event')) {
            $commentContent .= '<a class="wall_message_goto" href="' . $pathToObj . '">' . $LOCALE['placeholders']['goto'] . '</a>';
        } elseif (CURRENT_USER->isLogged()) {
            $commentContent .= '<a class="wall_message_reply" message_id="' . $commentData['id'] . '" respond_to="' .
                $userService->showNameExtended($commentCreator, true, false, '', true, false, true) .
                '" respond_to_id="' . $commentCreator->sid->get() . '">' . ($commentData['parent'] > 0 ? $LOCALE['placeholders']['reply'] : $LOCALE['placeholders']['comment']) . '</a>';

            if ($commentCreator->id->getAsInt() === CURRENT_USER->id() && DateHelper::getNow() <= $commentData['created_at'] + 3600) {
                $commentContent .= '<a class="wall_message_edit" message_id="' . $commentData['id'] . '">' . $LOCALE['placeholders']['edit'] . '</a>';
            }
        }
        $commentContent .= '</div>
</div>
</div>';

        return $commentContent;
    }

    /** Подготовка выборки для краткого вида обсуждений */
    public static function prepareConversationTreePreviewData(string $objType, int $objId): array
    {
        $counters = DB->query(
            "SELECT
                        c.id AS c_id,
                        COUNT(cm.id) AS cm_count,
                        SUM(cms.user_id IS NOT NULL) AS cms_count
                    FROM
                        conversation AS c
                        LEFT JOIN conversation_message AS cm ON cm.conversation_id = c.id
                        LEFT JOIN conversation_message_status AS cms ON cms.message_id = cm.id AND cms.user_id=:user_id AND cms.message_read='1'
                    WHERE
                        c.obj_type = '" . DataHelper::addBraces($objType) . "'
                        AND c.obj_id = :obj_id
                        AND (c.sub_obj_type = '{admin}' OR c.sub_obj_type IS NULL)
                    GROUP BY
                        c.id",
            [
                ['obj_id', $objId],
                ['user_id', CURRENT_USER->id()],
            ],
        );

        $countersById = [];

        foreach ($counters as $counter) {
            $countersById[$counter['c_id']] = $counter;
        }

        unset($counters);

        $lastMessages = DB->query(
            "SELECT
                c.id as c_id,
                c.name AS c_name,
                cm.*
            FROM
                conversation AS c
                LEFT JOIN conversation_message AS cm ON cm.conversation_id=c.id AND cm.updated_at=(
                    SELECT
                        MAX(cm2.updated_at)
                    FROM
                        conversation_message AS cm2
                    WHERE
                        cm2.conversation_id=c.id
                    )
                WHERE
                    c.obj_type='" . DataHelper::addBraces($objType) . "'
                    AND c.obj_id=:obj_id
                    AND (c.sub_obj_type='{admin}' OR c.sub_obj_type IS NULL)
                ORDER BY
                    cm.updated_at DESC",
            [
                ['obj_id', $objId],
            ],
        );

        $conversationsData = [];

        foreach ($lastMessages as $lastMessage) {
            $conversationData = $lastMessage;

            $conversationData = array_merge($conversationData, $countersById[$conversationData['c_id']]);

            $conversationsData[] = $conversationData;
        }

        unset($countersById);

        return $conversationsData;
    }

    /** Вывод краткого вида для обсуждений */
    public static function conversationTreePreview(
        array $conversationData,
        string $type,
        int $objId,
        string $class = '',
        ?array $parentData = null,
        ?bool $API_REQUEST = null,
    ): array|string {
        $API_REQUEST = $API_REQUEST ?? REQUEST_TYPE->isApiRequest();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

        $type = DataHelper::clearBraces($type);
        $API_CONVERSATIONS_DATA = [];

        if (($conversationData['id'] ?? 0) > 0) {
            $lastMessage = $conversationData;
        } else {
            $lastMessage = DB->select('conversation_message', ['conversation_id' => $conversationData['c_id']], true, ['updated_at DESC'], 1);
        }

        if (($conversationData['cm_count'] ?? 0) > 0) {
            $conversationMessageCount = $conversationData['cm_count'];
        } else {
            $conversationMessageCount = DB->count('conversation_message', ['conversation_id' => $conversationData['c_id']]);
        }

        if (CURRENT_USER->isLogged()) {
            if (($conversationData['cms_count'] ?? 0) > 0) {
                $conversationReadMessageCount = $conversationData['cms_count'];
            } else {
                $result = DB->query(
                    "SELECT cms.id FROM conversation_message_status cms INNER JOIN conversation_message cm ON cms.message_id=cm.id AND cm.conversation_id=:conversation_id WHERE cms.user_id=:user_id AND cms.message_read='1'",
                    [
                        ['conversation_id', $conversationData['c_id']],
                        ['user_id', CURRENT_USER->id()],
                    ],
                );
                $conversationReadMessageCount = count($result);
            }
            $unreadCount = $conversationMessageCount - $conversationReadMessageCount;
        } else {
            $unreadCount = 0;
        }

        $result = '';

        if ($API_REQUEST) {
            $API_CONVERSATIONS_DATA = [
                'conversation_id' => $conversationData['c_id'],
                'total_count' => $conversationMessageCount,
                'total_count_word' => $LOCALE['message'] . LocaleHelper::declineNeuter(
                    $conversationMessageCount,
                ),
                'unread_count' => (string) $unreadCount,
                'name' => DataHelper::escapeOutput($conversationData['c_name']),
                'last_message_data' => [
                    'message_id' => $lastMessage['id'],
                    'author_id' => $lastMessage['creator_id'],
                    'author_name' => $userService->showNameExtended(
                        $userService->get($lastMessage['creator_id']),
                        true,
                        false,
                        '',
                        false,
                        false,
                        true,
                    ),
                    'time' => DateHelper::showDateTime($lastMessage['updated_at']),
                    'timestamp' => $lastMessage['updated_at'],
                ],
            ];
        } else {
            $objData = [];

            if (($type === 'project' || $type === 'community') && $objId > 0) {
                if (!is_null($parentData)) {
                    $objData = $parentData;
                } else {
                    $objData = DB->select($type, ['id' => $objId], true);
                }
            }

            $result = '<div class="' . $type . '_conversation_message' . ($class !== '' ? ' ' . $class : '') . '" unread_count="' . $unreadCount . '"><div class="' . $type . '_conversation_count">' . $conversationMessageCount . '<span class="sbi sbi-conversation-count"></span></div><div class="' . $type . '_conversation_name"><a class="fraymmodal-window" href="' . ABSOLUTE_PATH . '/' . $type . '/' . $objId . '/show=conversation&bid=' . $conversationData['c_id'] . '" hash="conversation_' . $conversationData['c_id'] . '">' . DataHelper::escapeOutput($conversationData['c_name']) . '</a></div><div class="' . $type . '_conversation_additional">' . ($unreadCount > 0 ? '<span class="red">' . $unreadCount . ' ' . $LOCALE['new_message'] . LocaleHelper::declineNeuterAdjective($unreadCount) . ' ' . $LOCALE['message'] . LocaleHelper::declineNeuter($unreadCount) . '</span>' : $conversationMessageCount . ' ' . $LOCALE['message'] . LocaleHelper::declineNeuter($conversationMessageCount) . '. ') . $LOCALE['last_message'] . ': ' . ($lastMessage['use_group_name'] === '1' && isset($objData['name']) ? '<a href="' . ABSOLUTE_PATH . '/' . $type . '/' . $objId . '/">' . DataHelper::escapeOutput($objData['name']) . '</a>' : $userService->showName($userService->get($lastMessage['creator_id']), true)) . ', ' . DateHelper::showDateTime($lastMessage['updated_at']) . ' <a class="fraymmodal-window" href="' . ABSOLUTE_PATH . '/' . $type . '/' . $objId . '/show=conversation&bid=' . $conversationData['c_id'] . '">&rarr;</a></div></div>';
        }

        if ($API_REQUEST) {
            return $API_CONVERSATIONS_DATA;
        } else {
            return $result;
        }
    }

    /**  Вывод дерева сообщений для обсуждений */
    public static function conversationTree(
        int $cId,
        int $parent,
        int $level,
        string $parentObjType,
        CommunityModel|ProjectModel|ApplicationModel $parentObjData,
        string $cssClass = '',
        string $nameFixedTitle = '',
        ?bool $API_REQUEST = null,
    ): array|string {
        $API_REQUEST = $API_REQUEST ?? REQUEST_TYPE->isApiRequest();

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

        $messages = [];
        $parentObjType = DataHelper::clearBraces($parentObjType);
        $groupName = $parentObjData instanceof ApplicationModel ? $parentObjData->sorter->get() : $parentObjData->name->get();
        $parentObjDataId = $parentObjData->id->getAsInt();
        $uploadNum = FileHelper::getUploadNumByType($parentObjType);
        $type = '{' . $parentObjType . '_conversation}';
        $parentOfTopic = 0;
        $commentContent = '';
        $commentContentHere = '';

        $API_COMMENTS_DATA = [];

        $result = DB->query(
            'SELECT cm.*, cms.message_read as cms_read, IFNULL(r.id, 0) as marked_important, COUNT(r2.id) as marked_important_count FROM conversation_message cm LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND cms.user_id=:user_id_1 LEFT JOIN relation AS r ON r.obj_type_to="{conversation_message}" AND r.obj_id_to=cm.id AND r.type="{important}" AND r.obj_type_from="{user}" AND r.obj_id_from=:user_id_2 LEFT JOIN relation AS r2 ON r2.obj_type_to="{conversation_message}" AND r2.obj_id_to=cm.id AND r2.type="{important}" AND r2.obj_type_from="{user}" WHERE cm.conversation_id=:conversation_id AND cm.parent=:parent GROUP BY cm.id, cms.message_read, r.id ORDER BY cm.updated_at',
            [
                ['user_id_1', CURRENT_USER->id()],
                ['user_id_2', CURRENT_USER->id()],
                ['conversation_id', $cId],
                ['parent', $parent],
            ],
        );

        foreach ($result as $commentData) {
            if ($level === 1) {
                if ($parentOfTopic === 0) {
                    $parentOfTopic = $commentData['id'];
                }
            }

            if ($API_REQUEST) {
                $API_COMMENTS_DATA[$commentData['id']] = MessageHelper::conversationTreeComment(
                    $commentData,
                    $level,
                    $groupName,
                    $uploadNum,
                    $parentObjType,
                    $parentObjData,
                    ($level === 1 ? $nameFixedTitle : ''),
                    API_REQUEST: $API_REQUEST,
                );
            } else {
                $commentContentHere .= MessageHelper::conversationTreeComment(
                    $commentData,
                    $level,
                    $groupName,
                    $uploadNum,
                    $parentObjType,
                    $parentObjData,
                    ($level === 1 ? $nameFixedTitle : ''),
                    API_REQUEST: $API_REQUEST,
                );
            }

            if ($commentData['cms_read'] !== '1') {
                $messages[] = $commentData['id'];
            }

            if ($API_REQUEST) {
                $API_COMMENTS_DATA = array_replace(
                    $API_COMMENTS_DATA,
                    MessageHelper::conversationTree(
                        $cId,
                        $commentData['id'],
                        $level + 1,
                        $parentObjType,
                        $parentObjData,
                        $cssClass,
                        $nameFixedTitle,
                        API_REQUEST: $API_REQUEST,
                    ),
                );
            } else {
                $commentContentHere .= MessageHelper::conversationTree(
                    $cId,
                    $commentData['id'],
                    $level + 1,
                    $parentObjType,
                    $parentObjData,
                    $cssClass,
                    $nameFixedTitle,
                    API_REQUEST: $API_REQUEST,
                );
            }
        }

        if ($level === 1) {
            $API_COMMENTS_DATA['parent'] = [
                'name' => $groupName,
                'href' => '/' . DataHelper::clearBraces($parentObjType) . '/' . $parentObjDataId . '/',
                'obj_type' => $parentObjType,
                'obj_id' => $parentObjDataId,
                'save_obj_type' => $type,
            ];

            $conversationSubType = '';

            if ($type === '{project_conversation}' || $type === '{project_application_conversation}') {
                $conversationData = DB->findObjectById($cId, 'conversation');

                if ($conversationData['sub_obj_type']) {
                    $conversationSubType = $conversationData['sub_obj_type'];
                    $API_COMMENTS_DATA['parent']['save_sub_obj_type'] = $conversationData['sub_obj_type'];
                } else {
                    $conversationSubType = '{admin}';
                    $API_COMMENTS_DATA['parent']['save_sub_obj_type'] = '{admin}';
                }
            }

            $commentContent .= '<div class="conversation_message_container' . ($cssClass !== '' ? ' ' . $cssClass : '') . '">';
            $commentContent .= $commentContentHere;
            $commentContent .= MessageHelper::conversationForm(
                $cId,
                $type,
                $parentObjDataId,
                $LOCALE['placeholders']['comment'],
                $parentOfTopic,
                !($type === '{project_application_conversation}'),
                false,
                $conversationSubType,
            );
            $commentContent .= '</div>';
        } elseif ($commentContentHere !== '') {
            $commentContent .= '<div class="conversation_message_children_container">' . $commentContentHere . '</div>';
        }

        self::createRead($messages);

        if ($API_REQUEST) {
            return $API_COMMENTS_DATA;
        } else {
            return $commentContent;
        }
    }

    /** Вывод одного сообщения в дереве сообщений (для обсуждений) */
    public static function conversationTreeComment(
        array $commentData,
        int $level,
        ?string $groupName = null,
        ?int $uploadNum = null,
        string $parentObjType = '',
        CommunityModel|ProjectModel|ApplicationModel|null $parentObjData = null,
        string $nameFixedTitle = '',
        ?int $id = null,
        ?bool $API_REQUEST = null,
    ): array|string {
        if (is_null($id)) {
            $id = DataHelper::getId();
        }

        $API_REQUEST = $API_REQUEST ?? REQUEST_TYPE->isApiRequest();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

        $commentCreator = $userService->get($commentData['creator_id']);
        $parentObjDataId = $parentObjData->id->getAsInt();

        if ($API_REQUEST) {
            $commentReply = 'false';
            $commentEdit = 'false';
            $commentDelete = 'false';

            if (CURRENT_USER->isLogged()) {
                $commentReply = 'true';

                if ($commentCreator->id->getAsInt() === CURRENT_USER->id() && DateHelper::getNow() <= $commentData['created_at'] + 3600) {
                    $commentEdit = 'true';
                }

                if ((($parentObjType === 'project' || $parentObjType === 'community') && RightsHelper::checkRights(
                    ['{admin}', '{moderator}'],
                    DataHelper::addBraces($parentObjType),
                    $parentObjDataId,
                )) || $commentCreator->id->getAsInt() === CURRENT_USER->id() || CURRENT_USER->isAdmin() || $userService->isModerator()) {
                    $commentDelete = 'true';
                }
            }

            $API_COMMENT_DATA = [
                'message_id' => $commentData['id'],
                'level' => (string) $level,
                'parent_message_id' => (string) $commentData['parent'],
                'author_id' => $commentData['creator_id'],
                'author_avatar' => $userService->photoUrl($commentCreator),
                'author_name' => $userService->showName($commentCreator),
                'time' => DateHelper::showDateTime($commentData['updated_at']),
                'timestamp' => $commentData['updated_at'],
                'read' => ($commentData['cms_read'] === '1' ? 'read' : 'unread'),
                'use_group_name' => ($commentData['use_group_name'] === '1' ? 'true' : 'false'),
                'commands' => [
                    'reply' => $commentReply,
                    'edit' => $commentEdit,
                    'delete' => $commentDelete,
                ],
                'important' => UniversalHelper::drawImportant(
                    'conversation_message',
                    $commentData['id'],
                    ($commentData['marked_important'] ?? 0) > 0,
                    $commentData['marked_important_count'] ?? 0,
                ),
            ];

            return array_merge(
                $API_COMMENT_DATA,
                self::viewActions(
                    $commentData['id'],
                    $uploadNum,
                    $commentData,
                    API_REQUEST: $API_REQUEST,
                ),
            );
        }

        if ($level > 5) {
            $level = 5;
        }

        $pathToObj = '';

        if ($parentObjType && $parentObjData) {
            $pathToObj = '/' . $parentObjType . '/' . $parentObjDataId . '/';
        }

        $commentContent = '<div class="conversation_message' . ($commentData['parent'] > 0 ? ' child' : '') . (($commentData['icon'] ?? false) && str_contains($commentData['icon'], 'need_response') ? ' need_response' : '') . '" message_id="' . $commentData['id'] . '" obj_id="' . $commentData['id'] . '" level="' . $level . '">';

        $imagePath = null;
        $commentFromParentObj = false;
        $uploadNumByType = FileHelper::getUploadNumByType($parentObjType);

        if (in_array($parentObjType, ['project', 'community'])) {
            $uploadNumByType = 9;
        }

        if ($commentData['use_group_name'] === '1' && $uploadNumByType && $pathToObj) {
            $commentFromParentObj = true;

            $imagePath = FileHelper::getImagePath($parentObjData->attachments->get(), $uploadNumByType) ?? ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_' . $parentObjType . '.svg';
        }

        $commentContent .= '<div class="conversation_message_photo">' . ($commentFromParentObj ? '<div class="photoName"><a href="' . $pathToObj . '"><div class="photoName_photo_wrapper"><div class="photoName_photo" style="' . DesignHelper::getCssBackgroundImage($imagePath) . '" title="' . $groupName . '"></div></div></a></div>' : $userService->photoNameLink($commentCreator, '', false)) . '</div>
<div class="conversation_message_data">
    <div class="conversation_message_more"></div><div class="conversation_message_more_list">';

        $commentContent .= '<a class="conversation_message_more_function conversation_message_more_reply">' . ($commentData['parent'] > 0 || DataHelper::clearBraces($parentObjType) === 'project_application' ? $LOCALE['placeholders']['reply'] : $LOCALE['placeholders']['comment']) . '</a>';

        if (CURRENT_USER->isLogged()) {
            if ($commentCreator->id->getAsInt() === CURRENT_USER->id() && DateHelper::getNow() <= $commentData['created_at'] + 3600) {
                $commentContent .= '<a class="conversation_message_more_function conversation_message_more_edit">' . $LOCALE['placeholders']['edit'] . '</a>';
            }
            $trueParentObjType = $parentObjType;
            $trueParentObjDataId = $parentObjDataId;

            if ($parentObjType === 'project_application') {
                $trueParentObjType = 'project';
                $trueParentObjDataId = $parentObjData->project_id->get();
            }

            /** Кешируем доступ к объекту, т.к. этих запросов может быть крайне много, если комментариев много */
            $trueParentObjAccess = CACHE->getFromCache('conversationTreeCommentTrueParentObjAccess' . DataHelper::addBraces($trueParentObjType), $trueParentObjDataId);

            if (is_null($trueParentObjAccess)) {
                $trueParentObjAccess = !in_array($trueParentObjType, ['project', 'community']) && RightsHelper::checkRights(['{admin}', '{moderator}', '{gamemaster}'], DataHelper::addBraces($trueParentObjType), $trueParentObjDataId);

                CACHE->setToCache(
                    'conversationTreeCommentTrueParentObjAccess' . DataHelper::addBraces($trueParentObjType),
                    $trueParentObjDataId,
                    $trueParentObjAccess,
                );
            }

            if (
                CURRENT_USER->isAdmin()
                || $userService->isModerator()
                || $trueParentObjAccess
                || (
                    $commentCreator->id->getAsInt() === CURRENT_USER->id() && DateHelper::getNow() <= $commentData['created_at'] + 3600
                )
            ) {
                $commentContent .= '<a class="conversation_message_more_function conversation_message_more_delete">' . $LOCALE['placeholders']['delete'] . '</a>';

                if (!($commentData['icon'] ?? false) || !str_contains($commentData['icon'], 'mark_read')) {
                    $commentContent .= '<a class="conversation_message_more_function conversation_message_more_mark_read">' . $LOCALE['placeholders']['mark_read'] . '</a>';
                }
                $commentContent .= '<a class="conversation_message_more_function conversation_message_more_need_response' .
                    (!($commentData['icon'] ?? false) || !str_contains($commentData['icon'], 'need_response') ? '' : ' hidden') .
                    '">' . $LOCALE['placeholders']['mark_need_response'] . '</a>';
                $commentContent .= '<a class="conversation_message_more_function conversation_message_more_has_response' .
                    (!($commentData['icon'] ?? false) || !str_contains($commentData['icon'], 'need_response') ? ' hidden' : '') .
                    '">' . $LOCALE['placeholders']['mark_has_response'] . '</a>';
            }

            if (in_array($parentObjType, ['project', 'community'])) {
                $checkTask = RightsHelper::findOneByRights(
                    '{child}',
                    '{conversation_message}',
                    $commentData['id'],
                    '{task}',
                    null,
                );

                $LOCALE_PARENT = LocaleHelper::getLocale([$parentObjType, 'global']);

                if ($checkTask > 0) {
                    $taskData = DB->select('task_and_event', ['id' => $checkTask], true);

                    if ($taskData['id']) {
                        $commentContent .= '<a class="conversation_message_more_function conversation_message_more_add_task" href="' . ABSOLUTE_PATH . '/task/' . $taskData['id'] . '/">' . DataHelper::escapeOutput(
                            $taskData['name'],
                        ) . '</a>';
                    } else {
                        $commentContent .= '<a class="conversation_message_more_function conversation_message_more_add_task" href="' . ABSOLUTE_PATH . '/task/task/act=add&obj_id=' . $parentObjDataId . '&obj_type=' . $parentObjType . '&message_id=' . $commentData['id'] . '">' . $LOCALE_PARENT['add_task'] . '</a>';
                    }
                } else {
                    $commentContent .= '<a class="conversation_message_more_function conversation_message_more_add_task" href="' . ABSOLUTE_PATH . '/task/task/act=add&obj_id=' . $parentObjDataId . '&obj_type=' . $parentObjType . '&message_id=' . $commentData['id'] . '">' . $LOCALE_PARENT['add_task'] . '</a>';
                }

                $checkEvent = RightsHelper::findOneByRights(
                    '{child}',
                    '{conversation_message}',
                    $commentData['id'],
                    '{event}',
                    null,
                );

                if ($checkEvent > 0) {
                    $eventData = DB->select('task_and_event', ['id' => $checkEvent], true);

                    if ($eventData['id']) {
                        $commentContent .= '<a class="conversation_message_more_function conversation_message_more_add_event" href="' . ABSOLUTE_PATH . '/event/' . $eventData['id'] . '/">' . DataHelper::escapeOutput(
                            $eventData['name'],
                        ) . '</a>';
                    } else {
                        $commentContent .= '<a class="conversation_message_more_function conversation_message_more_add_event" href="' . ABSOLUTE_PATH . '/event/event/act=add&obj_id=' . $parentObjDataId . '&obj_type=' . $parentObjType . '&message_id=' . $commentData['id'] . '">' . $LOCALE_PARENT['add_event'] . '</a>';
                    }
                } else {
                    $commentContent .= '<a class="conversation_message_more_function conversation_message_more_add_event" href="' . ABSOLUTE_PATH . '/event/event/act=add&obj_id=' . $parentObjDataId . '&obj_type=' . $parentObjType . '&message_id=' . $commentData['id'] . '">' . $LOCALE_PARENT['add_event'] . '</a>';
                }
            }
            /*if ($commentCreator->id->getAsInt() != CURRENT_USER->id()) {
                $commentContent .= '<a class="conversation_message_more_function conversation_message_more_spam">' . $LOCALE['placeholders']['functions_spam_2'] . '</a>';
            }*/
        }
        $commentContent .= '
    </div>
    ' .
            (
                $parentObjType === 'project_application' ? '<div class="conversation_message_id"><a id="wmc_' . $commentData['id'] . '" href="' . ABSOLUTE_PATH . '/' . KIND . '/' . ($id ? $id . '/' : '') . '#wmc_' . $commentData['id'] . '">#</a></div> ' : ''
            ) .
            '<div class="conversation_message_creator">' .
            (
                $commentData['use_group_name'] === '1' ? '<a href="' . $pathToObj . '" ' . ($nameFixedTitle !== '' ? $nameFixedTitle : '') . ' >' . $groupName . '</a>' : str_replace('>', ($nameFixedTitle !== '' ? ' title="' . $nameFixedTitle . '"' : '') . '>', $userService->showNameExtended($commentCreator, true, true, 'tooltipBottomRight', false, false, true))
            ) .
            '</div>
<div class="conversation_message_content' . ($commentData['cms_read'] === '1' ? '' : ' unread') . '"><div class="conversation_message_content_text">' .
            self::viewActions($commentData['id'], $uploadNum, $commentData) .
            '</div>';

        if ($commentData['message_action'] === '{vote}') {
            preg_match_all('#{([^:]+):([^}]+)}#', $commentData['message_action_data'], $matches);

            // проверяем, голосовал ли уже пользователь
            $voteMade = RightsHelper::checkRights('{voted}', '{message}', $commentData['id']);

            $totalVotesCount = 0;
            $voteResults = [];

            if ($voteMade) {
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
            }

            foreach ($matches[0] as $key => $value) {
                if ($matches[1][$key] === 'name') {
                    $commentContent .= '<div class="conversation_message_vote">' . DataHelper::escapeOutput($matches[2][$key]) . '</div><hr>';
                } elseif ($voteMade) {
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

                    $commentContent .= '<div class="conversation_message_vote_choice_made" obj_id="' . $commentData['id'] . '" value="' . DataHelper::escapeOutput($matches[1][$key]) . '">' . DataHelper::escapeOutput($matches[2][$key]) . '<br>
<div class="conversation_message_vote_choice_made_percent">' . round($votesCount / $totalVotesCount * 100) . '%</div><div class="conversation_message_vote_choice_made_bar" style="width:<!--result_' . $matches[1][$key] . '_percent-->%"></div><div class="conversation_message_vote_choice_made_bar_count">' . $votesCount . '</div>';
                    $commentContent .= '</div>';
                } else {
                    $commentContent .= '<div class="conversation_message_vote_choice"><input name="vote[' . $commentData['id'] . ']" type="radio" class="inputradio" value="' . DataHelper::escapeOutput($matches[1][$key]) . '" m_id="' . $commentData['id'] . '" id="vote_choice[' . $commentData['id'] . '][' . DataHelper::escapeOutput($matches[1][$key]) . ']"> <label for="vote_choice[' . $commentData['id'] . '][' . DataHelper::escapeOutput($matches[1][$key]) . ']">' . DataHelper::escapeOutput($matches[2][$key]) . '</label></div>';
                }
            }

            if ($voteMade) {
                arsort($voteResults);
                $voteResultsPercent = [];
                $highestVote = false;

                foreach ($voteResults as $key => $value) {
                    if (!$highestVote) {
                        $highestVote = $value;
                    }
                    $voteResultsPercent[$key] = $value / $highestVote * 94;
                }

                foreach ($voteResultsPercent as $key => $value) {
                    $commentContent = str_replace('<!--result_' . $key . '_percent-->', (string) round($value), $commentContent);
                }
            }
        }
        $commentContent .= '</div>
<div class="conversation_message_time">' .
            UniversalHelper::drawImportant(
                'conversation_message',
                $commentData['id'],
                ($commentData['marked_important'] ?? 0) > 0,
                $commentData['marked_important_count'] ?? null,
            ) .
            (
                $commentData['use_group_name'] === '1' ? $userService->showNameExtended($commentCreator, true, true, 'use_group_name') : ''
            ) .
            DateHelper::showDateTime($commentData['updated_at']);

        if (CURRENT_USER->isLogged()) {
            $commentContent .= '<a class="conversation_message_reply" message_id="' . $commentData['id'] . '" respond_to="' .
                $userService->showNameExtended(
                    $commentCreator,
                    true,
                    false,
                    '',
                    true,
                    false,
                    true,
                ) . '" respond_to_id="' . $commentCreator->sid->get() . '">' .
                ($commentData['parent'] > 0 || DataHelper::clearBraces($parentObjType) === 'project_application' ? $LOCALE['placeholders']['reply'] : $LOCALE['placeholders']['comment']) .
                '</a>';

            if ($commentCreator->id->getAsInt() === CURRENT_USER->id() && DateHelper::getNow() <= $commentData['created_at'] + 3600) {
                $commentContent .= '<a class="conversation_message_edit" message_id="' . $commentData['id'] . '">' . $LOCALE['placeholders']['edit'] . '</a>';
            }
        }
        $commentContent .= '</div>
</div>
</div>';

        return $commentContent;
    }

    /** Вывод одного сообщения в диалоге */
    public static function conversationConversationComment(array $messageData): string
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

        $commentContent = '<div class="message inner' . ($messageData['cms_read'] === '1' ? '' : ' unread') . '" obj_id="' . $messageData['id'] . '">
<div class="message_delete"><a action_request="conversation/conversation_message_delete" obj_id="' . $messageData['id'] . '" title="' . $LOCALE['delete_message'] . '" class="tooltipBottomRight"></a></div>
<div class="message_photos">';
        $userData = $userService->get($messageData['creator_id']);

        // если у диалога выставлено альтернативное отображение имен
        if (($messageData['use_names_type'] ?? false) === 1) {
            // проверяем связь с объектом
            if (is_null(CACHE->getFromCache('conversation_parent', $messageData['c_id']))) {
                CACHE->setToCache(
                    'conversation_parent',
                    $messageData['c_id'],
                    RightsHelper::findOneByRights(
                        '{child}',
                        '{project}',
                        null,
                        '{conversation}',
                        $messageData['c_id'],
                    ),
                );
            }
            $projectId = CACHE->getFromCache('conversation_parent', $messageData['c_id']);

            if ($projectId > 0) {
                // проверяем, мастер ли этот человек
                if (is_null(CACHE->getFromCache('project_rights_by_user_id', $messageData['creator_id']))) {
                    if (RightsHelper::checkRights(
                        '{admin}',
                        '{project}',
                        $projectId,
                        '{user}',
                        $messageData['creator_id'],
                    )) {
                        CACHE->setToCache('project_rights_by_user_id', $messageData['creator_id'], 'admin');
                    } elseif (RightsHelper::checkRights(
                        '{gamemaster}',
                        '{project}',
                        $projectId,
                        '{user}',
                        $messageData['creator_id'],
                    )) {
                        CACHE->setToCache('project_rights_by_user_id', $messageData['creator_id'], 'gamemaster');
                    } else {
                        CACHE->setToCache('project_rights_by_user_id', $messageData['creator_id'], 'member');
                    }
                }

                $LOCALE_PROJECT = LocaleHelper::getLocale(['project', 'global']);

                $userRights = CACHE->getFromCache('project_rights_by_user_id', $messageData['creator_id']);

                if (in_array($userRights, ['admin', 'gamemaster'])) {
                    $authorName = $userService->showNameExtended(
                        $userData,
                        true,
                        true,
                        '',
                        false,
                        false,
                        true,
                    ) . ' <span class="sbi sbi-star achievement type_' . ($userRights === 'admin' ? '1' : '2') . '" title="' . $LOCALE_PROJECT[$userRights === 'admin' ? 'modal_group_admin' : 'modal_group_management'] . '"></span>';
                } else {
                    if (is_null(CACHE->getFromCache('applications_by_creator_id', $messageData['creator_id']))) {
                        CACHE->setToCache(
                            'applications_by_creator_id',
                            $messageData['creator_id'],
                            DB->select('project_application', ['project_id' => $projectId, 'creator_id' => $messageData['creator_id']], true),
                        );
                    }
                    $applicationData = CACHE->getFromCache('applications_by_creator_id', $messageData['creator_id']);

                    if (trim(DataHelper::escapeOutput($applicationData['sorter'] ?? '')) !== '') {
                        $userData->fio->set(trim(DataHelper::escapeOutput($applicationData['sorter'])));
                        $userData->hidesome->set('');
                        $authorName = $userService->showName($userData, true);
                    }
                }
            }
        }

        if (!isset($authorName)) {
            $authorName = $userService->showName($userData, true);
        }

        $commentContent .= $userService->photoNameLink($userData, '', false, '', false) . '</div>
<div class="message_info">
<div class="names">' . $authorName . '</div>
<div class="time">' . DateHelper::showDateTime($messageData['updated_at']) . '</div>
<div class="message_content">
<div class="message_content_data">' . self::viewActions($messageData['id']) . '</div>
</div>
</div>
</div>';

        return $commentContent;
    }

    /** Вывод формы написания сообщения на стену, в обсуждение или в диалогах */
    public static function conversationForm(
        ?int $cId,
        string $objType = '',
        string|int|null $objId = null,
        string $placehold = '',
        string|int $parent = 0,
        bool $show = true,
        bool $showName = false,
        array|string $subObjType = '',
        bool $doNotHide = false,
    ): string {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

        $commentContent = '';

        if ($doNotHide) {
            $show = true;
        }

        if (CURRENT_USER->isLogged()) {
            $id = is_null($objId) ? DataHelper::getId() : $objId;

            $userData = $userService->get(CURRENT_USER->id());

            // разрешать ли опрос
            $allowVote = false;

            // определение пути для загрузки вложений в зависимости от типа объекта
            $uploadNum = false;

            if ($objType === '{project_wall}' || $objType === '{project_conversation}') {
                $allowVote = true;
            } elseif ($objType === '{community_wall}' || $objType === '{community_conversation}') {
                $allowVote = true;
            } elseif ($objType === '{conversation_message}') {
                /* если в диалоге участвует пользователь с возможностью отправлять или принимать картинки, то даем такую возможность */
                $canUseImagesInDialogs = false;
                $result = RightsHelper::findByRights('{member}', '{conversation}', $cId, '{user}', false);

                if ($result) {
                    foreach ($result as $value) {
                        if ($userService->canUseImagesInDialogs($value)) {
                            $canUseImagesInDialogs = true;
                            break;
                        }
                    }
                }

                if ($canUseImagesInDialogs) {
                    $uploadNum = FileHelper::getUploadNumByType('conversation');
                }
            }

            $commentContent = '<!-- start comment object -->
<div class="conversation_form' . ($doNotHide ? ' do_not_hide' : '') . ($show ? ' shown' : '') . '">
<form action="message/" method="POST" enctype="multipart/form-data" action_request_form>
<input type="hidden" name="action" value="add_comment" />
<input type="hidden" name="conversation_id" value="' . $cId . '" />
<input type="hidden" name="parent" value="' . $parent . '" />
<input type="hidden" name="obj_type" value="' . $objType . '" />' .
                ($subObjType !== '' && !is_array($subObjType) ? '<input type="hidden" name="sub_obj_type" value="' . $subObjType . '" />' : '') . '
<input type="hidden" name="obj_id" value="' . ($objType !== '' ? $id : '') . '" />
<div class="conversation_form_photo">' . $userService->photoNameLink($userData, '', false) . '</div>
<div class="conversation_form_data">
<div class="conversation_form_main_fields">' . ($showName ? '<input name="name" type="text" placehold="' . $LOCALE['placeholders']['topic_name'] . '" tabIndex="1">' : '') . '
<div id="help_conversation_form_data">' . $LOCALE['placeholders']['help'] . '</div>
<textarea name="content" placehold="' . $placehold . '" tabIndex="' . ($showName ? '2' : '1') . '"></textarea>
' . ($uploadNum ? '<div class="conversation_form_attachments" id="div_attachments"><input type="file" id="attachments" name="attachments[]" class="inputfile" data-upload-path="/uploads/" data-upload-num="' . $uploadNum . '" data-upload-name="attachments" accept="image/*" multiple></div>' : '') . '
' . ($allowVote ? '<div class="conversation_form_vote" id="div_vote"><hr><input name="vote_name" type="text" placehold="' . $LOCALE['placeholders']['vote_topic_name'] . '" tabIndex="2"><br><br>
<input name="vote_answer[0]" type="text" placehold="' . $LOCALE['placeholders']['vote_choice'] . '" tabIndex="3"><br>
<input name="vote_answer_add" type="text" placehold="' . $LOCALE['placeholders']['vote_add_choice'] . '"></div>' : '') . '
</div>
<div class="conversation_form_controls"' . ($doNotHide ? ' style="display: block;"' : '') . '>
' . ($uploadNum ? '<a class="attach">' . $LOCALE['placeholders']['attach'] . '</a> ' : '') . ($allowVote ? '<a class="vote">' . $LOCALE['placeholders']['vote'] . '</a> ' : '') . '<button class="main">' . $LOCALE['placeholders']['send'] . '</button>';

            if ($objType === '{project_application_conversation}' && is_array($subObjType)) {
                $commentContent .= '<span class="sub_obj_type">' . $LOCALE['placeholders']['whom_to'] . '</span>';

                foreach ($subObjType as $key => $subObjTypeItem) {
                    $commentContent .= '
<input type="radio" name="sub_obj_type" id="sub_obj_type[' . $subObjTypeItem[0] . ']" value="' . $subObjTypeItem[0] . '" class="inputradio"' . ($key === 0 ? ' checked' : '') . '><label for="sub_obj_type[' . $subObjTypeItem[0] . ']">' . $subObjTypeItem[1] . '</label>';
                }
            } elseif ($objType === '{calendar_event_notion}') {
                $commentContent .= '<span class="rating">' . $LOCALE['placeholders']['rating'] . '</span>
<select name="rating">
	<option value="+1">+1</option>
	<option value="0">0</option>
	<option value="-1">-1</option>
</select>';
            } elseif ($objType === '{task_comment}') {
                $taskMembers = RightsHelper::findByRights(null, '{task}', DataHelper::getId(), '{user}', false);
                $responsibleId = RightsHelper::findOneByRights(
                    '{responsible}',
                    '{task}',
                    DataHelper::getId(),
                    '{user}',
                    false,
                );
                $taskMembersData = [];
                $taskMembersDataSort = [];

                foreach ($taskMembers as $memberId) {
                    $taskMembersData[] = [
                        $memberId,
                        $userService->showName($userService->get($memberId)),
                    ];
                }

                foreach ($taskMembersData as $key => $row) {
                    $taskMembersDataSort[$key] = $row[1];
                }
                array_multisort($taskMembersData, SORT_ASC, $taskMembersDataSort);

                $LOCALE_TASK = LocaleHelper::getLocale(['task', 'fraym_model', 'elements']);

                $TASK_MODEL = new TaskModel();
                $TASK_MODEL->construct();

                /** @var Item\Select $responsible */
                $responsible = $TASK_MODEL->getElement('responsible');
                $responsible->getAttribute()->defaultValue = $responsibleId;
                $responsible->getAttribute()->values = $taskMembersData;
                $commentContent .= str_replace(
                    '<select',
                    '<select title="' . $LOCALE_TASK['responsible']['shownName'] . '"',
                    $responsible->asHtml(true),
                );

                /** @var Item\Select $status */
                $status = $TASK_MODEL->getElement('status');
                /** @var Item\Calendar $dateTo */
                $dateTo = $TASK_MODEL->getElement('date_to');
                $commentContent .= '<span>' . str_replace(
                    '<select',
                    '<select title="' . $LOCALE_TASK['status']['shownName'] . '"',
                    $status->asHtml(true),
                ) . ($status->get() === '{delayed}' ? str_replace(
                    'dpkr_time',
                    'dpkr_time shown',
                    $dateTo->asHtml(true),
                ) : $dateTo->asHtml(true)) . '</span>';

                /** @var Item\Select $priority */
                $priority = $TASK_MODEL->getElement('priority');
                $commentContent .= str_replace(
                    '<select',
                    '<select title="' . $LOCALE_TASK['priority']['shownName'] . '"',
                    $priority->asHtml(true),
                );
            } elseif ($objType === '{project_wall}' || $objType === '{project_conversation}' || $objType === '{community_wall}' || $objType === '{community_conversation}') {
                if (RightsHelper::checkRights(
                    ['{admin}', '{moderator}', '{newsmaker}', '{gamemaster}'],
                    str_replace(['_conversation', '_wall'], '', DataHelper::clearBraces($objType)),
                    $objId,
                )) {
                    $randId = rand(0, 500000);
                    $commentContent .= '<input type="checkbox" class="inputcheckbox" name="use_group_name" id="use_group_name_' . $randId . '"> <label for="use_group_name_' . $randId . '">' . $LOCALE['placeholders']['use_group_name'] . ' ';

                    if ($objType === '{project_wall}' || $objType === '{project_conversation}') {
                        $commentContent .= $LOCALE['obj_types']['project2'];
                    } else {
                        $commentContent .= $LOCALE['obj_types']['community2'];
                    }
                    $commentContent .= '</label>';
                }
            }
            $commentContent .= '
</div>
</div>
</form>
</div>
<!-- end comment object -->';
        }

        return $commentContent;
    }

    /** Уточнение, является ли сообщение техническим, и его авто-преобразования в положительном случае */
    public static function viewActions(
        int $messageId,
        ?int $uploadNum = 1,
        ?array $conversationMessageData = null,
        ?bool $API_REQUEST = null,
    ): array|string {
        $API_REQUEST = $API_REQUEST ?? REQUEST_TYPE->isApiRequest();

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

        $responseContent = [];

        if (is_null($conversationMessageData)) {
            $conversationMessageData = DB->select('conversation_message', ['id' => $messageId], true);
        }

        $content = strip_tags(DataHelper::escapeOutput($conversationMessageData['content'], EscapeModeEnum::plainHTMLforceNewLines), '<br><i><b><u>');
        $messageAction = DataHelper::escapeOutput($conversationMessageData['message_action']);
        $messageActionData = (string) DataHelper::escapeOutput($conversationMessageData['message_action_data']);
        $attachments = DataHelper::escapeOutput($conversationMessageData['attachments']);

        $responseContent['content'] = $html = '';

        if ($messageAction !== '' && $conversationMessageData['id'] !== '' && (KIND === 'application' || (!in_array($messageAction, ['{fee_payment}', '{request_group}'])))) {
            preg_match('#{([^:]+):([^,]+),\s*resolved:(.*)}#', $messageActionData, $actionData);

            if (!isset($actionData[3])) {
                preg_match('#{([^:]+):([^,]+)}#', $messageActionData, $actionData);
            }
            $actionData[2] = (int) $actionData[2];

            $obj = [];

            if ($actionData[1] === 'project_id' && $actionData[2] > 0) {
                $obj = DB->findObjectById($actionData[2], 'project');
                $obj['kind'] = 'project';
                $obj['type'] = $LOCALE['obj_types']['project'];
            } elseif ($actionData[1] === 'community_id' && $actionData[2] > 0) {
                $obj = DB->findObjectById($actionData[2], 'community');
                $obj['kind'] = 'community';
                $obj['type'] = $LOCALE['obj_types']['community'];
            } elseif ($actionData[1] === 'task_id' && $actionData[2] > 0) {
                $obj = DB->findObjectById($actionData[2], 'task_and_event');
                $obj['kind'] = 'task';
                $obj['type'] = $LOCALE['obj_types']['task'];
            } elseif ($actionData[1] === 'event_id' && $actionData[2] > 0) {
                $obj = DB->findObjectById($actionData[2], 'task_and_event');
                $obj['kind'] = 'event';
                $obj['type'] = $LOCALE['obj_types']['event'];
            } elseif ($actionData[1] === 'project_application_id' && $actionData[2] > 0) {
                $obj = DB->findObjectById($actionData[2], 'project_application');
                $obj['kind'] = 'application';
                $obj['type'] = $LOCALE['obj_types']['project_application'];
            } elseif ($actionData[1] === 'project_transaction_id' && $actionData[2] > 0) {
                $obj = DB->findObjectById($actionData[2], 'project_transaction');
                $obj['kind'] = 'application';
                $obj['type'] = $LOCALE['obj_types']['project_application'];
            } elseif ($actionData[1] === 'project_room_id' && $actionData[2] > 0) {
                $obj = DB->findObjectById($actionData[2], 'project_room');
                $obj['kind'] = 'project_room';
                $obj['type'] = $LOCALE['obj_types']['project_room'];
            } elseif ($actionData[1] === 'project_group_id' && $actionData[2] > 0) {
                $obj = DB->findObjectById($actionData[2], 'project_group');
                $obj['kind'] = 'application';
                $obj['type'] = $LOCALE['obj_types']['project_application'];
            }

            if ($messageAction === '{request_group}') {
                if (isset($actionData[3])) {
                    $html .= '<div class="commands"><div class="done">' . $LOCALE['actions']['request_done'] . '</div></div>';
                    $responseContent['commands']['done'] = $LOCALE['actions']['request_done'];
                } else {
                    $projectRights = RightsHelper::checkProjectRights();

                    if (is_array($projectRights)) {
                        if (in_array('{admin}', $projectRights) || in_array('{gamemaster}', $projectRights)) {
                            $html .= '<div class="commands"><a action_request="group/confirm_group_request" obj_id="' . $conversationMessageData['id'] . '" class="bold">' . $LOCALE['actions']['confirm_group_request'] . '</a><a action_request="group/decline_group_request" obj_id="' . $conversationMessageData['id'] . '">' . $LOCALE['actions']['decline_group_request'] . '</a></div>';
                            $responseContent['commands']['confirm_group_request'] = $LOCALE['actions']['confirm_group_request'];
                            $responseContent['commands']['decline_group_request'] = $LOCALE['actions']['decline_group_request'];
                        }
                    }
                }

                $html .= TextHelper::basePrepareText($content);

                $responseContent['content'] = $html;
                $responseContent['link'] = ABSOLUTE_PATH . '/' . $obj['kind'] . '/' . $obj['id'] . '/';
            } elseif ($messageAction === '{fee_payment}') {
                if (isset($actionData[3])) {
                    $html .= '<div class="commands"><div class="done">' . $LOCALE['actions']['request_done'] . '</div></div>';
                    $responseContent['commands']['done'] = $LOCALE['actions']['request_done'];
                } else {
                    $projectRights = RightsHelper::checkProjectRights();

                    if (is_array($projectRights)) {
                        if (in_array('{admin}', $projectRights) || in_array('{fee}', $projectRights)) {
                            $html .= '<div class="commands"><a action_request="transaction/confirm_payment" obj_id="' . $conversationMessageData['id'] . '" class="bold">' . $LOCALE['actions']['confirm_payment'] . '</a><a action_request="transaction/decline_payment" obj_id="' . $conversationMessageData['id'] . '">' . $LOCALE['actions']['decline_payment'] . '</a></div>';
                            $responseContent['commands']['confirm_payment'] = $LOCALE['actions']['confirm_payment'];
                            $responseContent['commands']['decline_payment'] = $LOCALE['actions']['decline_payment'];
                        }
                    }
                }

                $html .= TextHelper::basePrepareText($content);

                $responseContent['content'] = $html;

                if ($obj['id'] ?? false) {
                    $responseContent['link'] = ABSOLUTE_PATH . '/transaction/' . $obj['id'] . '/';
                }
            } elseif ($messageAction === '{get_access}') {
                if (RightsHelper::checkRights('{admin}', '{' . $obj['kind'] . '}', (int) $actionData[2])) {
                    if (isset($actionData[3]) || !isset($obj['id'])) {
                        $html .= '<div class="commands"><div class="done">' . $LOCALE['actions']['request_done'] . '</div></div>';
                        $responseContent['commands']['done'] = $LOCALE['actions']['request_done'];
                    } else {
                        $html .= '<div class="commands"><a action_request="conversation/grant_access" obj_id="' . $conversationMessageData['id'] . '" class="bold">'
                            . $LOCALE['actions']['grant_access'] . '</a><a action_request="conversation/deny_access" obj_id="' . $conversationMessageData['id'] . '">' . $LOCALE['actions']['deny_access'] . '</a></div>';
                        $responseContent['commands']['grant_access'] = $LOCALE['actions']['grant_access'];
                        $responseContent['commands']['deny_access'] = $LOCALE['actions']['deny_access'];
                    }
                } else {
                    $html .= '<div class="commands"><div class="done">' . $LOCALE['actions']['request_sent'] . '</div></div>';
                }
                $html .= '<div class="additional">' . $LOCALE['actions']['request_access_to'] . ' ' . $obj['type'] . ($obj['id'] > 0 ? ' «<a href="' . ABSOLUTE_PATH . '/' . $obj['kind'] . '/' . $obj['id'] . '/" target="_blank">' . (($obj['sorter'] ?? false) ? DataHelper::escapeOutput($obj['sorter']) : DataHelper::escapeOutput($obj['name'])) . '</a>»' : '') . '</div>';
                $responseContent['content'] = $LOCALE['actions']['request_access_to'] . ' ' . $obj['type'] . ($obj['id'] > 0 ? ' «' . (($obj['sorter'] ?? false) ? DataHelper::escapeOutput($obj['sorter']) : DataHelper::escapeOutput($obj['name'])) . '»' : '');
                $responseContent['link'] = ($obj['id'] > 0 ? ABSOLUTE_PATH . '/' . $obj['kind'] . '/' . $obj['id'] . '/' : null);
            } elseif ($messageAction === '{send_invitation}') {
                if (isset($actionData[3]) || !isset($obj['id'])) {
                    $html .= '<div class="commands"><div class="done">' . $LOCALE['actions']['request_done'] . '</div></div>';
                    $responseContent['commands']['done'] = $LOCALE['actions']['request_done'];
                } elseif ($obj['kind'] !== 'application') {
                    if (CURRENT_USER->id() !== $conversationMessageData['creator_id']) {
                        $html .= '<div class="commands"><a action_request="conversation/accept_invitation" obj_id="' . $conversationMessageData['id'] . '" class="bold">' . $LOCALE['actions']['accept_invitation'] . '</a><a action_request="conversation/decline_invitation" obj_id="' . $conversationMessageData['id'] . '">' . $LOCALE['actions']['decline_invitation'] . '</a></div>';
                        $responseContent['commands']['accept_invitation'] = $LOCALE['actions']['accept_invitation'];
                        $responseContent['commands']['decline_invitation'] = $LOCALE['actions']['decline_invitation'];
                    } else {
                        $html .= '<div class="commands"><div class="done">' . $LOCALE['actions']['request_sent'] . '</div></div>';
                    }
                }

                if ($obj['kind'] === 'project_room') {
                    $projectData = DB->findObjectById($obj['project_id'], 'project');
                    $html .= '<div class="additional">' . sprintf(
                        $LOCALE['actions']['invitation_to_room'],
                        DataHelper::escapeOutput($obj['name']),
                        $projectData['id'],
                        DataHelper::escapeOutput($projectData['name']),
                    ) . '</div>';
                    $responseContent['content'] = strip_tags(
                        sprintf(
                            $LOCALE['actions']['invitation_to_room'],
                            DataHelper::escapeOutput($obj['name']),
                            $projectData['id'],
                            DataHelper::escapeOutput($projectData['name']),
                        ),
                    );
                    $responseContent['link'] = null;
                } else {
                    $html .= '<div class="additional">' . $LOCALE['actions']['invitation_to'] . ' ' . $obj['type'] . ($obj['id'] > 0 ? ' «<a href="' . ABSOLUTE_PATH . '/' . $obj['kind'] . '/' . $obj['id'] . '/" target="_blank">' .
                        ($obj['kind'] === 'application' ? DataHelper::escapeOutput($obj['sorter']) : DataHelper::escapeOutput($obj['name'])) . '</a>»' : '') . '</div>';
                    $responseContent['content'] = $LOCALE['actions']['invitation_to'] . ' ' . $obj['type'] .
                        ($obj['id'] > 0 ?
                            ' «' . (($obj['sorter'] ?? false) ?
                                DataHelper::escapeOutput($obj['sorter']) : DataHelper::escapeOutput($obj['name'])) . '»' :
                            '');
                    $responseContent['link'] = ($obj['id'] > 0 ? ABSOLUTE_PATH . '/' . $obj['kind'] . '/' . $obj['id'] . '/' : null);
                }
            } elseif ($messageAction === '{become_friends}') {
                if (isset($actionData[3])) {
                    $html .= '<div class="commands"><div class="done">' . $LOCALE['actions']['request_done'] . '</div></div>';
                    $responseContent['commands']['done'] = $LOCALE['actions']['request_done'];
                } elseif (CURRENT_USER->id() !== $conversationMessageData['creator_id']) {
                    $html .= '<div class="commands"><a action_request="conversation/accept_friend" obj_id="' . $conversationMessageData['id'] . '" class="bold">' . $LOCALE['actions']['accept_friend'] . '</a><a action_request="conversation/decline_friend" obj_id="' . $conversationMessageData['id'] . '">' . $LOCALE['actions']['decline_friend'] . '</a></div>';
                    $responseContent['commands']['accept_friend'] = $LOCALE['actions']['accept_friend'];
                    $responseContent['commands']['decline_friend'] = $LOCALE['actions']['decline_friend'];
                } else {
                    $html .= '<div class="commands"><div class="done">' . $LOCALE['actions']['request_sent'] . '</div></div>';
                }

                if ($content !== '' && $content !== '{action}') {
                    $html .= '<div class="additional">' . TextHelper::basePrepareText($content) . '</div>';
                    $responseContent['content'] = TextHelper::basePrepareText($content);
                } else {
                    $html .= '<div class="additional">' . $LOCALE['actions']['add_friend_request'] . '</div>';
                    $responseContent['content'] = $LOCALE['actions']['add_friend_request'];
                }
            }
        }

        if ($html === '') {
            $responseContent['content'] = $html = TextHelper::basePrepareText($content);

            if ($attachments && ($_ENV['UPLOADS'][$uploadNum] ?? false)) {
                $upload = $_ENV['UPLOADS'][$uploadNum];

                $html .= '</div><div class="files_list">';

                preg_match_all('#{([^:]+):([^}]+)}#', $attachments, $matches);

                foreach ($matches[0] as $key => $value) {
                    if (file_exists(INNER_PATH . 'public' . $_ENV['UPLOADS_PATH'] . $upload['path'] . $matches[2][$key])) {
                        if ($upload['isimage']) {
                            if ($upload['thumbmake']) {
                                $html .= '<a href="' . $_ENV['UPLOADS_PATH'] . $upload['path'] . $matches[2][$key] . '" target="_blank"><img src="thumbnails/' . $_ENV['UPLOADS_PATH'] . $upload['path'] . $matches[2][$key] . '"></a><br>';
                            } else {
                                $html .= '<img src="' . $_ENV['UPLOADS_PATH'] . $upload['path'] . $matches[2][$key] . '"><br>';
                            }
                        } else {
                            $html .= '<div class="uploaded_file">';

                            if ($conversationMessageData['creator_id'] === CURRENT_USER->id()) {
                                $html .= '<a class="edit_file" title="' . $GLOBALS['LOCALE']['system']['classes']['file']['edit'] . '"></a><a class="trash careful file_delete" title="' . $GLOBALS['LOCALE']['system']['classes']['file']['delete'] . '" href="' . ABSOLUTE_PATH . '/files/?attachments=' . $matches[2][$key] . '&type=' . $uploadNum . '" post_action="file/delete_conversation_file" post_action_id="' . $matches[1][$key] . ':' . $matches[2][$key] . '"></a> ';
                            }
                            $html .= '<a href="' . $_ENV['UPLOADS_PATH'] . $upload['path'] . $matches[2][$key] . '" target="_blank">' . $matches[1][$key] . '</a></div>';
                        }
                        $responseContent['attachments'][FileHelper::getFileTypeByExtension($matches[2][$key])][] = [
                            'path' => $_ENV['UPLOADS_PATH'] . $upload['path'] . $matches[2][$key],
                            'name' => $matches[1][$key],
                        ];
                    }
                }
            }
        }

        if ($API_REQUEST) {
            return $responseContent;
        }

        return $html;
    }

    /** Создание отметки о прочтенности сообщения */
    public static function createRead(array $messages): bool
    {
        if (is_null(CURRENT_USER->getAdminData()) && CURRENT_USER->isLogged()) {
            foreach ($messages as $value) {
                if ($_ENV['DATABASE_TYPE'] === 'pgsql') {
                    $checkRecord = DB->select('conversation_message_status', ['message_id' => $value, 'user_id' => CURRENT_USER->id()], true);

                    if ($checkRecord['id']) {
                        DB->update(
                            'conversation_message_status',
                            [
                                'message_read' => 1,
                                'updated_at' => DateHelper::getNow(),
                            ],
                            [
                                'message_id' => $value,
                                'user_id' => CURRENT_USER->id(),
                            ],
                        );
                    } else {
                        DB->insert(
                            'conversation_message_status',
                            [
                                'message_id' => $value,
                                'user_id' => CURRENT_USER->id(),
                                'message_read' => 1,
                                'created_at' => DateHelper::getNow(),
                                'updated_at' => DateHelper::getNow(),
                            ],
                        );
                    }
                } else {
                    DB->query(
                        "INSERT INTO conversation_message_status (message_id, user_id, message_read, updated_at, created_at) VALUES (:message_id, :user_id, '1', :time_1, :time_2) ON DUPLICATE KEY UPDATE message_read=VALUES(message_read)",
                        [
                            ['message_id', $value],
                            ['user_id', CURRENT_USER->id()],
                            ['time_1', DateHelper::getNow()],
                            ['time_2', DateHelper::getNow()],
                        ],
                    );
                }
            }
        }

        return true;
    }

    /** Подготовка push-уведомлений пользователям */
    public static function preparePushs(array $users, array $params): void
    {
        foreach ($users as $value) {
            self::preparePush($value, $params);
        }
    }

    /** Подготовка push-уведомления пользователю */
    public static function preparePush(int $user, array $params): bool
    {
        if (
            isset($params['content'])
            && isset($params['obj_type'])
            && $user > 0
            && ($user !== CURRENT_USER->id() || $user !== $params['user_id_from'])
            && !(CURRENT_USER->isAdmin() && CookieHelper::getCookie('testMode'))
        ) {
            $params['obj_type'] = DataHelper::addBraces($params['obj_type']);

            // обрезаем сообщение до разрешенных размеров, если оно больше нужного
            $message = TextHelper::cutStringToLimit($params['content'], 250);
            $header = TextHelper::cutStringToLimit($params['header'], 250);
            $params['obj_type'] = str_replace(['_wall', '_conversation'], '', $params['obj_type']);

            DB->insert(
                'subscription_push',
                [
                    ['creator_id', $params['user_id_from'] ? $params['user_id_from'] : CURRENT_USER->id()],
                    ['user_id', $user],
                    ['message_img', $params['message_img'] ? $params['message_img'] : null],
                    ['header', $header ? $header : null],
                    ['content', $message],
                    ['obj_type', DataHelper::addBraces($params['obj_type'])],
                    ['obj_id', $params['obj_id'] > 0 ? $params['obj_id'] : null],
                    ['created_at', DateHelper::getNow()],
                    ['updated_at', DateHelper::getNow()],
                ],
            );

            return true;
        }

        return false;
    }

    /** Подготовка писем-уведомлений пользователям */
    public static function prepareEmails(array $users, array $params): void
    {
        foreach ($users as $value) {
            self::prepareEmail($value, $params);
        }
    }

    /** Подготовка письма-уведомления пользователю */
    public static function prepareEmail(int $user, array $params): bool
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        if (
            isset($params['author_name'])
            && isset($params['author_email'])
            && isset($params['name'])
            && isset($params['content'])
            && isset($params['obj_type'])
            && $user > 0
            && $user !== CURRENT_USER->id()
            && !(CURRENT_USER->isAdmin() && CookieHelper::getCookie('testMode'))
        ) {
            $insertRecord = false;
            $params['obj_type'] = DataHelper::addBraces($params['obj_type']);

            // массив событий, по которым не нужно высылать оповещения, если пользователь онлайн
            $doNotInsertWhenOnlineRecordTypes = [
                '{conversation}',
                '{project_wall}',
                '{project_conversation}',
                '{community_wall}',
                '{community_conversation}',
            ];

            if (in_array(DataHelper::addBraces($params['obj_type']), $doNotInsertWhenOnlineRecordTypes)) {
                if (!$userService->checkUserOnline($user)) {
                    $insertRecord = true;
                }
            } else {
                $insertRecord = true;
            }

            if ($insertRecord) {
                DB->insert(
                    'subscription',
                    [
                        ['creator_id', CURRENT_USER->id()],
                        ['user_id', $user],
                        ['author_name', $params['author_name']],
                        ['author_email', $params['author_email']],
                        ['name', $params['name']],
                        ['content', $params['content']],
                        ['obj_type', DataHelper::addBraces($params['obj_type'])],
                        ['obj_id', $params['obj_id'] > 0 ? $params['obj_id'] : null],
                        ['created_at', DateHelper::getNow()],
                        ['updated_at', DateHelper::getNow()],
                    ],
                );

                return true;
            }
        }

        return false;
    }
}
