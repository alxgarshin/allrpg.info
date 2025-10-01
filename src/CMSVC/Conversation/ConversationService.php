<?php

declare(strict_types=1);

namespace App\CMSVC\Conversation;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Message\MessageService;
use App\CMSVC\Trait\UserServiceTrait;
use App\CMSVC\User\UserService;
use App\Helper\{DateHelper, MessageHelper, RightsHelper, TextHelper};
use Fraym\BaseObject\{BaseModel, BaseService, Controller, DependencyInjection};
use Fraym\Entity\PreCreate;
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper, ResponseHelper};

#[PreCreate]
#[Controller(ConversationController::class)]
class ConversationService extends BaseService
{
    use UserServiceTrait;

    #[DependencyInjection]
    public MessageService $messageService;

    private array $messages = [];
    private bool $hasMessages = false;
    private ?array $connectedObjectUsers = null;

    /** Эта функция полностью заменяет собой стандартную методику добавления объектов для данной модели */
    public function PreCreate(): void
    {
        $LOCALE = $this->getLOCALE();

        $objId = OBJ_ID;
        $objType = OBJ_TYPE;

        $cId = $_REQUEST['c_id'] ?? null;

        if (count($_REQUEST['user_id'][0]) >= 2 && is_null($cId) && $_REQUEST['name'][0] === '') {
            ResponseHelper::response([['error', $LOCALE['has_to_fill_topic_on_multidialog']]], '', ['name']);
        }

        if ($objId > 0 && $objType !== '' && $_REQUEST['name'][0] === '') {
            ResponseHelper::response([['error', $LOCALE['has_to_fill_topic_on_object_dialog']]], '', ['name']);
        }

        if ($_REQUEST['user_id'][0][CURRENT_USER->id()] === 'on') {
            ResponseHelper::response([['error', $LOCALE['cant_set_myself_as_address']]], '', ['user_id']);
        }

        if ($_REQUEST['content'][0] === '' || $_REQUEST['content'][0] === $LOCALE['conversation']['type_your_message']) {
            ResponseHelper::response([['error', $LOCALE['text_must_be_set']]], '', ['content']);
        }

        $additionalFields = [];

        if ($objId > 0 && $objType !== '') {
            if (RightsHelper::checkRights('{admin}', $objType, $objId)) {
                $additionalFields['obj_type'] = DataHelper::addBraces($objType);
                $additionalFields['obj_id'] = $objId;
            } elseif (count($_REQUEST['user_id'][0]) === 0 && is_null($cId)) {
                ResponseHelper::response([['error', $LOCALE['no_address_selected']]], '', ['user_id']);
            }
        } elseif (count($_REQUEST['user_id'][0]) === 0 && is_null($cId)) {
            ResponseHelper::response([['error', $LOCALE['no_address_selected']]], '', ['user_id']);
        }

        /* аватарка диалога */
        if ($_REQUEST['avatar'][0][0] !== '') {
            $additionalFields['avatar'] = $_REQUEST['avatar'][0][0];
        }

        $result = $this->messageService->newMessage(
            $cId,
            $_REQUEST['content'][0],
            $_REQUEST['name'][0],
            $_REQUEST['user_id'][0],
            $_REQUEST['attachments'][0],
            $additionalFields,
        );

        if ($result && is_null($cId)) {
            if ($objId > 0 && $objType !== '') {
                if (RightsHelper::checkRights('{admin}', $objType, $objId)) {
                    $messageData = DB->select('conversation_message', ['id' => $result], true);

                    if ($messageData) {
                        DB->update('conversation', ['obj_type' => null, 'obj_id' => null], ['id' => $messageData['conversation_id']]);

                        if (!RightsHelper::checkRights('{child}', DataHelper::addBraces($objType), $objId, '{conversation}')) {
                            RightsHelper::addRights('{child}', DataHelper::addBraces($objType), $objId, '{conversation}', $messageData['conversation_id']);
                        }
                    }
                }
            }
            ResponseHelper::response([['success', $LOCALE['message_send_success']]], ABSOLUTE_PATH . '/conversation/');
        } elseif ($result) {
            ResponseHelper::response([], 'stayhere');
        }
        exit;
    }

    public function getUserIdValues(): array
    {
        return $this->getConnectedObjectUsers()['ids'];
    }

    public function getUserIdImages(): array
    {
        return $this->getConnectedObjectUsers()['images'];
    }

    public function getUserIdDefault(): array
    {
        $selectedUserIds = [];

        if (OBJ_ID > 0 && !empty(OBJ_TYPE) && RightsHelper::checkRights(['{admin}', '{responsible}', '{moderator}'], OBJ_TYPE, OBJ_ID)) {
            $result = RightsHelper::findByRights(null, OBJ_TYPE, OBJ_ID, '{user}', false);
            $selectedUserIds = $result;
        }

        if (count($selectedUserIds) === 0 && ($_REQUEST['user'] ?? false)) {
            $selectedUserIds[] = [$_REQUEST['user'] ?? false];
        }

        if (($key = array_search(CURRENT_USER->getId(), $selectedUserIds)) !== false) {
            unset($selectedUserIds[$key]);
        }

        return $selectedUserIds;
    }

    public function getNameDefault(): string
    {
        $LOCALE_SEARCH = LocaleHelper::getLocale(['search', 'global']);

        $name = '';

        if (OBJ_ID > 0 && !empty(OBJ_TYPE)) {
            if (RightsHelper::checkRights(['{admin}', '{responsible}', '{moderator}'], OBJ_TYPE, OBJ_ID)) {
                $table = OBJ_TYPE;

                if (in_array(DataHelper::clearBraces(OBJ_TYPE), ['task', 'event'])) {
                    $table = 'task_and_event';
                }
                $object = DB->findObjectById(OBJ_ID, $table);
                $name = DataHelper::escapeOutput($object['name']) . ' (' . mb_strtolower($LOCALE_SEARCH['messages'][OBJ_TYPE]) . ')';

                $this->postModelInitVars['changeUserIdShownName'] = true;
            }
        }

        return $name;
    }

    public function getGroupValues(): array
    {
        $LOCALE_EVENTLIST = LocaleHelper::getLocale(['eventlist', 'global']);

        $objId = OBJ_ID;
        $objType = OBJ_TYPE;

        $groupValues = [];

        if (!($objId > 0 && $objType !== '')) {
            $myCommunities = RightsHelper::findByRights(null, '{community}');
            $myProjects = RightsHelper::findByRights(null, '{project}');

            if ($myProjects) {
                $myProjectsList = [];
                $query = DB->select('project', ['id' => $myProjects], false, ['name']);

                foreach ($query as $data) {
                    $myProjectsList[$data['id']] = DataHelper::escapeOutput($data['name']);
                }

                $header = false;

                foreach ($myProjectsList as $key => $value) {
                    if (!$header) {
                        $groupValues[] = ['locked1', $LOCALE_EVENTLIST['obj_filters_additional']['project']];
                        $header = true;
                    }
                    $groupValues[] = ['project_' . $key, $value, 1];
                }
            }

            if ($myCommunities) {
                $myCommunitiesList = [];
                $query = DB->select('community', ['id' => $myCommunities], false, ['name']);

                foreach ($query as $data) {
                    $myCommunitiesList[$data['id']] = DataHelper::escapeOutput($data['name']);
                }

                $header = false;

                foreach ($myCommunitiesList as $key => $value) {
                    if (!$header) {
                        $groupValues[] = ['locked2', $LOCALE_EVENTLIST['obj_filters_additional']['community']];
                        $header = true;
                    }
                    $groupValues[] = ['community_' . $key, $value, 1];
                }
            }
        }

        return $groupValues;
    }

    public function postModelInit(BaseModel $model): BaseModel
    {
        if ($this->postModelInitVars['changeUserIdShownName'] ?? false) {
            $LOCALE = $this->getLOCALE();
            $model->getElement('user_id')?->setShownName($LOCALE[OBJ_TYPE]['members']);
            $model->getElement('user_id')?->setHelpText(null);
        }

        return $model;
    }

    /** Получение массива выведенных пользователю сообщений */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /** Получение факта наличия сообщений */
    public function getHasMessages(): bool
    {
        return $this->hasMessages;
    }

    /** Получение диалога */
    public function getDialog(int|string|null $objId, int|string|null $userId, int $limit, int|string|null $time): ?array
    {
        if ((is_int($objId) && $objId > 0) || ($objId === 'new' && $userId !== '' && $userId !== null)) {
            $LOCALE = $this->getLOCALE();
            $userService = $this->getUserService();

            $keepTime = $time === 'keep';
            $time = (int) $time;

            $userId = explode(',', (string) $userId);
            $contactData = [];

            foreach ($userId as $user) {
                if (!isset($contactData[$user])) {
                    $contactData[$user] = $userService->get($user);
                }
            }

            $conversationData = [];

            if ($objId !== 'new') {
                $conversationData = DB->findObjectById($objId, 'conversation');

                $result = DB->query(
                    'SELECT cm.*, min(cms.message_read) as cms_read, min(cms2.message_read) as cms_read_other FROM conversation c LEFT JOIN relation r ON r.obj_id_to=c.id LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id LEFT JOIN conversation_message_status cms2 ON cms2.message_id=cm.id AND cms2.user_id != :user_id1 AND cms2.message_read = "1" WHERE (r.obj_id_from=:obj_id_from AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_type_to="{conversation}") AND c.obj_id IS NULL AND c.id=:id AND (cms.message_deleted!="1" OR cms.message_deleted IS NULL) AND cms.user_id=:user_id2 ' . ($time > 0 ? 'AND cm.updated_at>:time ' : '') . 'GROUP BY cm.id ORDER BY cm.updated_at DESC' . ($time > 0 ? '' : ' LIMIT 10 OFFSET :limit'),
                    [
                        ['user_id1', CURRENT_USER->id()],
                        ['obj_id_from', CURRENT_USER->id()],
                        ['id', $objId],
                        ['user_id2', CURRENT_USER->id()],
                        ['time', $time],
                        ['limit', $limit],
                    ],
                );
                $hasMessages = count($result) > 0;
            } else {
                $hasMessages = false;
            }

            $name = '';

            $messagesUnread = [];
            $messagesMarkedRead = 0;
            $dialog = '';
            $dialog2 = '';
            $lastMessageTime = false;

            if (isset($result)) {
                foreach ($result as $messagesData) {
                    if (!$lastMessageTime && $limit === 0) {
                        $lastMessageTime = $messagesData['updated_at'];
                    }

                    if ($messagesData['cms_read'] !== '1') {
                        $messagesUnread[] = $messagesData['id'];
                    }
                    $author = ($messagesData['creator_id'] === CURRENT_USER->id() ? 'me' : 'contact');
                    $dialog3 = '<div class="conversations_widget_message conversations_widget_message_' . $author .
                        ($messagesData['cms_read_other'] !== '1' ? ' unread' : '') . '"><div class="conversations_widget_message_content">';

                    if ($author === 'contact') {
                        if (!isset($contactData[$messagesData['creator_id']])) {
                            $contactData[$messagesData['creator_id']] = $userService->get($messagesData['creator_id']);
                        }
                        $dialog3 .= $userService->photoNameLink(
                            $contactData[$messagesData['creator_id']],
                            '',
                            false,
                        );
                    }
                    $dialog3 .= '
' . MessageHelper::viewActions($messagesData['id']) . '
</div>
<div class="conversations_widget_time">' . DateHelper::showDateTimeUsual($messagesData['updated_at']) . '</div>
</div>
';
                    $dialog2 = $dialog3 . $dialog2;
                }

                if ($conversationData['name'] !== '') {
                    $name = DataHelper::escapeOutput($conversationData['name']);
                }
            }

            if (!$lastMessageTime && $limit === 0) {
                $lastMessageTime = 'keep';
            }
            $dialog .= $dialog2;

            if (count($userId) === 1 && !$name) {
                $name = $userService->showName($contactData[$userId[0]]);
            } elseif (!$name && count($userId) > 1 && $objId !== 'new') {
                $name = DataHelper::escapeOutput($conversationData['name']);

                if (!$name) {
                    foreach ($contactData as $userName) {
                        $name .= $userService->showName($userName) . ', ';
                    }
                    $name = mb_substr($name, 0, mb_strlen($name) - 2);
                }
            }

            if (!$hasMessages && $time === 0 && !$keepTime && $limit === 0) {
                $dialog .= '<div class="no_message_yet conversations_widget_message conversations_widget_message_contact">
<div class="conversations_widget_message_content">
' . $LOCALE['messages']['no_message_yet'] . '
</div>
</div>';
            }

            $openedDialogsData = $this->getOpenedDialogsData();
            $openedDialogData = $openedDialogsData[$objId] ?? null;

            if (
                $messagesUnread &&
                $openedDialogData &&
                (
                    !isset($openedDialogData['visible']) ||
                    $openedDialogData['visible'] === 'true'
                )
            ) {
                $this->setRead($messagesUnread);
                $messagesMarkedRead = count($messagesUnread);
            }

            $returnArr = [
                'response' => 'success',
                'response_text' => $hasMessages,
                'dialog' => $dialog,
                'left' => $openedDialogData['left'] ?? null,
                'top' => $openedDialogData['top'] ?? null,
                'visible' => $openedDialogData['visible'] ?? 'notset',
                'sound' => $openedDialogData['sound'] ?? 'on',
                'time' => $lastMessageTime,
                'messages_marked_read' => $messagesMarkedRead,
                'name' => $name,
                'is_group' => (count($contactData) > 1 ? 'true' : 'false'),
            ];

            if (!$time) {
                $returnArr['limit'] = $limit + 10;
            }

            return $returnArr;
        }

        return null;
    }

    /** Получение аватара диалога */
    public function getDialogAvatar(?int $objId, int|string|null $userId): ?array
    {
        $userId = (string) $userId;

        if ($userId !== '') {
            $userService = $this->getUserService();

            if ($objId > 0) {
                $conversationUsers = RightsHelper::findByRights(
                    '{member}',
                    '{conversation}',
                    $objId,
                    '{user}',
                    false,
                );

                $userIds = $conversationUsers;
            } else {
                $userIds = explode(',', $userId);
            }

            $userIds = array_filter($userIds, static fn ($id) => $id !== CURRENT_USER->id());

            $userCount = count($userIds);

            if ($userCount === 1) {
                $contactData = $userService->get($userIds[key($userIds)]);
                $avatarUrl = $userService->photoNameLink($contactData, '', false, '', false, false, false, false);
            } else {
                $contactsData = $userService->getAll(['id' => $userIds]);
                $avatarUrl = $userService->photoNameLinkMulti(iterator_to_array($contactsData), false, false);
            }

            return ['response' => 'success', 'response_data' => $avatarUrl];
        }

        return null;
    }

    /** Установка положения всплывающего диалогового окна  */
    public function setDialogPosition(
        ?int $objId,
        string $left,
        string $top,
        bool $visible,
        ?int $userId,
        ?string $sound,
    ): ?array {
        if ($objId > 0) {
            $openedDialogsData = $this->getOpenedDialogsData();

            if (($openedDialogsData['new']['user_id'] ?? false) === $userId) {
                unset($openedDialogsData['new']['user_id']);
            }

            $openedDialogsData[$objId] = [
                'left' => $left,
                'top' => $top,
                'visible' => $visible,
                'user_id' => $userId,
                'sound' => $sound,
            ];

            $this->setOpenedDialogsData($openedDialogsData);

            return ['response' => 'success'];
        }

        return null;
    }

    /** Удаление положения всплывающего окна */
    public function deleteDialogPosition(?int $objId): ?array
    {
        if ($objId > 0) {
            $openedDialogsData = $this->getOpenedDialogsData();

            if ($openedDialogsData[$objId] ?? false) {
                unset($openedDialogsData[$objId]);
                $this->setOpenedDialogsData($openedDialogsData);
            }

            return ['response' => 'success'];
        }

        return null;
    }

    /** Получение данных об открытых диалогах */
    public function getOpenedDialogsData(): array
    {
        $openedDialogsData = CookieHelper::getCookie('opened_dialogs');

        if ($openedDialogsData) {
            return json_decode($openedDialogsData, true);
        }

        return [];
    }

    /** Сохранение данных об открытых диалогах */
    public function setOpenedDialogsData(array $openedDialogsData): void
    {
        CookieHelper::batchSetCookie(['opened_dialogs' => json_encode($openedDialogsData)]);
    }

    /** Вывод данных внутри диалогов */
    public function loadConversation(
        ?int $objId,
        int $objLimit,
        bool $dynamicLoad,
        string $searchString,
        int $showLimit,
    ): array {
        $userService = $this->getUserService();

        $LOCALE = $this->getLOCALE();

        $returnArr = [];

        // поисковый запрос
        $searchString = (REQUEST_TYPE->isApiRequest() ? trim($searchString) : '');

        if ($searchString !== '' && mb_strlen($searchString) >= 3) {
            // заглушка количества сообщений на странице
            $cCount = 10000000;

            $result = DB->query(
                'SELECT cm.*, min(cms2.message_read) as cms_read FROM conversation c LEFT JOIN relation r ON r.obj_id_to=c.id LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id LEFT JOIN conversation_message_status cms2 ON cms2.message_id=cm.id AND cms2.user_id != :user_id AND cms2.message_read = "1" WHERE (r.obj_id_from=:obj_id_from AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_type_to="{conversation}") AND c.obj_id IS NULL AND (cms.message_deleted!="1" OR cms.message_deleted IS NULL) AND cms.user_id=:user_id AND c.id=:id AND (cm.content LIKE :search_string OR cm.attachments LIKE :search_string) GROUP BY cm.id ORDER BY cm.updated_at DESC',
                [
                    ['user_id', CURRENT_USER->id()],
                    ['obj_id_from', CURRENT_USER->id()],
                    ['id', $objId],
                    ['search_string', '%' . $searchString . '%'],
                ],
            );
        } else {
            // количество сообщений на странице
            $cCount = ($showLimit > 0 ? $showLimit : 10);

            // если стоит параметр $dynamicLoad, это означает, что запрос, на самом деле, не знает количества непрочтенных сообщений, так что выдаем их все
            if ($dynamicLoad) {
                $result = DB->query(
                    'SELECT cm.*, min(cms2.message_read) as cms_read, c.use_names_type, c.id as c_id FROM conversation c LEFT JOIN relation r ON r.obj_id_to=c.id LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id LEFT JOIN conversation_message_status cms2 ON cms2.message_id=cm.id AND cms2.user_id != :user_id1 AND cms2.message_read = "1" WHERE (r.obj_id_from=:obj_id_from AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_type_to="{conversation}") AND c.obj_id IS NULL AND (cms.message_deleted!="1" OR cms.message_deleted IS NULL) AND (cms.message_read IS NULL OR cms.message_read!="1") AND cms.user_id=:user_id2 AND c.id=:id GROUP BY cm.id ORDER BY cm.updated_at DESC',
                    [
                        ['user_id1', CURRENT_USER->id()],
                        ['user_id2', CURRENT_USER->id()],
                        ['obj_id_from', CURRENT_USER->id()],
                        ['id', $objId],
                    ],
                );
            } else {
                $result = DB->query(
                    'SELECT cm.*, min(cms2.message_read) as cms_read, c.use_names_type, c.id as c_id FROM conversation c LEFT JOIN relation r ON r.obj_id_to=c.id LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id LEFT JOIN conversation_message_status cms2 ON cms2.message_id=cm.id AND cms2.user_id != :user_id1 AND cms2.message_read = "1" WHERE (r.obj_id_from=:obj_id_from AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_type_to="{conversation}") AND c.obj_id IS NULL AND (cms.message_deleted!="1" OR cms.message_deleted IS NULL) AND cms.user_id=:user_id2 AND c.id=:id GROUP BY cm.id ORDER BY cm.updated_at DESC LIMIT :limit OFFSET :offset',
                    [
                        ['user_id1', CURRENT_USER->id()],
                        ['user_id2', CURRENT_USER->id()],
                        ['obj_id_from', CURRENT_USER->id()],
                        ['id', $objId],
                        ['limit', $cCount],
                        ['offset', $objLimit],
                    ],
                );
            }
        }
        $cTotal = count($result);
        $this->hasMessages = count($result) > 0;

        $text = '';
        $messagesRead = [];
        $responseData = [];

        foreach ($result as $messageData) {
            $messagesRead[] = $messageData['id'];

            if (REQUEST_TYPE->isApiRequest()) {
                $userData = $userService->get($messageData['creator_id']);
                $responseData[$messageData['id']] = [
                    'message_id' => $messageData['id'],
                    'author_id' => $userData->id->getAsInt(),
                    'author_avatar' => $userService->photoUrl($userData),
                    'author_name' => $userService->showName($userData),
                    'time' => DateHelper::showDateTime($messageData['updated_at']),
                    'timestamp' => (string) $messageData['updated_at'],
                    'read' => ($messageData['cms_read'] === '1' ? 'read' : 'unread'),
                ];
                $responseData[$messageData['id']] = array_merge(
                    $responseData[$messageData['id']],
                    MessageHelper::viewActions($messageData['id']),
                );
            } else {
                $text = MessageHelper::conversationConversationComment($messageData) . $text;
            }
        }

        if (!$dynamicLoad && $cTotal > $objLimit + $cCount) {
            $text = '<a class="load_conversation" obj_limit="' . ($objLimit + $cCount) . '" obj_id="' . $objId . '">' . $LOCALE['previous'] . ' ' . $cCount . '</a>' . $text;
            $responseData['obj_limit'] = (string) ($objLimit + $cCount);
        }

        if (count($messagesRead) > 0) {
            $this->setRead($messagesRead);
        }

        if (REQUEST_TYPE->isApiRequest()) {
            if ((isset($responseData['obj_limit']) && count($responseData) > 1) || (!isset($responseData['obj_limit']) && count($responseData) > 0)) {
                $conversationParentInfo = DB->select(
                    'relation',
                    [
                        'obj_type_from' => '{conversation}',
                        'obj_id_from' => $objId,
                        'type' => '{child}',
                    ],
                    true,
                );
                $result = DB->select(
                    'relation',
                    [
                        'obj_type_to' => '{conversation}',
                        'obj_id_to' => $objId,
                        'type' => '{member}',
                        'obj_type_from' => '{user}',
                    ],
                );
                $memberCount = count($result);

                if ($memberCount > 2 || $conversationParentInfo['obj_id_to'] > 0) {
                    $responseData['rights'] = [
                        'add_user_to_dialog' => 'true',
                        'leave_dialog' => 'true',
                        'conversation_rename' => 'true',
                    ];

                    if ($conversationParentInfo['obj_id_to'] > 0) {
                        $responseData['rights']['obj_type'] = $conversationParentInfo['obj_type_to'];
                        $responseData['rights']['obj_id'] = $conversationParentInfo['obj_id_to'];
                    }
                } else {
                    $responseData['rights'] = [
                        'add_user_to_dialog' => 'true',
                        'leave_dialog' => 'false',
                        'conversation_rename' => 'false',
                    ];
                }

                $returnArr = ['response' => 'success', 'response_data' => $responseData];
            }
        } else {
            $returnArr = ['response' => 'success', 'response_text' => $text];
        }

        return $returnArr;
    }

    /** Добавление сообщения **/
    public function dialogNewMessage(int|string|null $objId, int|string|null $userId, string $content): ?array
    {
        $userIdArray = explode(',', (string) $userId);
        $usersList = [];

        foreach ($userIdArray as $value) {
            $usersList[$value] = 'on';
        }

        $newMessage = $this->messageService->newMessage(
            $objId !== 'new' ? $objId : null,
            $content,
            '',
            $usersList,
        );

        if ($objId === 'new' && $newMessage > 0) {
            $rData = DB->findObjectById($newMessage, 'conversation_message');

            if ($rData['conversation_id'] > 0) {
                $objId = $rData['conversation_id'];
            }
        }

        return ['response' => 'success', 'response_data' => $objId];
    }

    /** Выставление отметки о прочтенности сообщения */
    public function setRead(array $messages): true
    {
        if (is_null(CURRENT_USER->getAdminData())) {
            DB->update(
                'conversation_message_status',
                [
                    'message_read' => 1,
                    'updated_at' => DateHelper::getNow(),
                ],
                [
                    'message_id' => $messages,
                    'user_id' => CURRENT_USER->id(),
                ],
            );
        }

        return true;
    }

    /** Сохранение изменений в сообщении */
    public function messageSave(?int $objId, string $text): ?array
    {
        if ($objId > 0) {
            $LOCALE = $this->getLOCALE();

            $returnArr = [];

            $objData = DB->findObjectById($objId, 'conversation_message');

            if ($objData['creator_id'] === CURRENT_USER->id() && DateHelper::getNow() <= $objData['created_at'] + 3600) {
                $text = str_replace(['<b>', '</b>'], '**', $text);
                $text = str_replace(['<i>', '</i>'], '__', $text);
                DB->update('conversation_message', ['content' => TextHelper::makeATsInactive($text)], ['id' => $objId]);
                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['messages']['wall_message_save_success'],
                ];
            }

            return $returnArr;
        }

        return null;
    }

    /** Удаление сообщения */
    public function messageDelete(?int $objId): ?array
    {
        if ($objId > 0) {
            $LOCALE = $this->getLOCALE();
            $userService = $this->getUserService();

            $returnArr = [];

            $objData = DB->findObjectById($objId, 'conversation_message');
            $conversationData = DB->findObjectById($objData['conversation_id'], 'conversation');

            if ($conversationData) {
                // выясняем, к какому из объектов относится диалог
                $allowDeleteObjs = [
                    '{project_wall}',
                    '{project_conversation}',
                    '{community_wall}',
                    '{community_conversation}',
                    '{project_application_conversation}',
                    '{publication_wall}',
                    '{ruling_item_wall}',
                ];

                if (in_array($conversationData['obj_type'], $allowDeleteObjs)) {
                    $parentObjType = '';

                    if (in_array($conversationData['obj_type'], ['{project_wall}', '{project_conversation}'])) {
                        $parentObjType = 'project';
                    } elseif (in_array($conversationData['obj_type'], ['{community_wall}', '{community_conversation}'])) {
                        $parentObjType = 'community';
                    } elseif ($conversationData['obj_type'] === '{publication_wall}') {
                        $parentObjType = 'publication';
                    } elseif ($conversationData['obj_type'] === '{ruling_item_wall}') {
                        $parentObjType = 'ruling_item';
                    } elseif ($conversationData['obj_type'] === '{project_application_conversation}') {
                        $parentObjType = 'project_application';
                    }

                    if ($parentObjType !== '') {
                        $parentObjId = $conversationData['obj_id'];

                        if (
                            $objData['creator_id'] === CURRENT_USER->id()
                            || RightsHelper::checkRights(['{admin}', '{moderator}'], DataHelper::addBraces($parentObjType), $parentObjId)
                            || CURRENT_USER->isAdmin() || $userService->isModerator()
                        ) {
                            // тип удаления по умолчанию: означает, что само сообщение нужно оставить, просто поменять в нем текст на "Сообщение удалено"
                            $deleteType = 'leave message';

                            if ($objData['parent'] === 0) {
                                // это основное сообщение ветки, его удаляем, если только оно единственное вообще в диалоге
                                $result = DB->select('conversation_message', ['conversation_id' => $objData['conversation_id']]);
                                $messagesCount = count($result);

                                if ($messagesCount === 1) {
                                    $deleteType = 'delete all';
                                }
                            } else {
                                // это ответ, так что можем удалить целиком, если на него нет ответов
                                $result = DB->select('conversation_message', ['parent' => $objId]);
                                $messagesCount = count($result);

                                if ($messagesCount === 0) {
                                    $deleteType = 'delete message';
                                }
                            }

                            if ($deleteType === 'leave message') {
                                DB->update(
                                    'conversation_message',
                                    ['content' => '__' . $LOCALE['messages']['wall_message_delete_success_extended'] . '__'],
                                    ['id' => $objId],
                                );
                            } elseif ($deleteType === 'delete message') {
                                DB->delete('conversation_message_status', ['message_id' => $objId]);
                                DB->delete('conversation_message', ['id' => $objId]);

                                /* удаляем неверифицированные транзакции, привязанные к сообщению */
                                DB->delete('project_transaction', ['conversation_message_id' => $objId, 'verified' => 0]);
                            } elseif ($deleteType === 'delete all') {
                                DB->query(
                                    'DELETE conversation_message_status FROM conversation_message_status LEFT JOIN conversation_message AS cm ON cm.id=conversation_message_status.message_id WHERE cm.conversation_id=:conversation_id AND cm.id IS NOT NULL',
                                    [
                                        ['conversation_id', $conversationData['id']],
                                    ],
                                );

                                /* удаляем неверифицированные транзакции, привязанные к сообщению */
                                DB->query(
                                    'DELETE FROM project_transaction WHERE conversation_message_id IN (SELECT id FROM conversation_message WHERE conversation_id=:conversation_id) AND verified="0"',
                                    [
                                        ['conversation_id', $conversationData['id']],
                                    ],
                                );

                                DB->delete('conversation_message', ['conversation_id' => $conversationData['id']]);
                                DB->delete('conversation', ['id' => $conversationData['id']]);
                                RightsHelper::deleteRights(
                                    null,
                                    '{conversation}',
                                    $conversationData['id'],
                                    '{user}',
                                    0,
                                );
                            }

                            /* если это сообщение про попытку оплаты в заявке, то проверяем, не надо ли убрать галочку: оплата требует подтверждения - с заявки */
                            if ($conversationData['obj_type'] === '{project_application_conversation}' && $objData['message_action'] === '{fee_payment}' && $conversationData['obj_id'] > 0) {
                                /* если к транзакции была привязана заявка, проверяем есть ли еще транзакции с ней. Если нет, то ставим признак, что их нет, в заявке */
                                $result = DB->select('project_transaction', ['project_application_id' => $conversationData['obj_id'], 'verified' => 0]);
                                $moreTransactionsToApprove = count($result);

                                if ($moreTransactionsToApprove === 0) {
                                    DB->update(
                                        tableName: 'project_application',
                                        data: [
                                            'money_need_approve' => 0,
                                        ],
                                        criteria: [
                                            'id' => $conversationData['obj_id'],
                                        ],
                                    );
                                }
                            }

                            $returnArr = [
                                'response' => 'success',
                                'response_text' => $LOCALE['messages']['wall_message_delete_success'],
                                'delete_type' => $deleteType,
                            ];
                        }
                    }
                }
            }

            return $returnArr;
        }

        return null;
    }

    /** Проверка на наличие диалога с соответствующим пользователем и перевод в него при наличии, а также добавление его в друзья */
    public function contact(int $userId): null
    {
        $checkFriends = RightsHelper::checkRights('{friend}', '{user}', CURRENT_USER->id(), '{user}', $userId);

        if (!$checkFriends) {
            $result = $this->getUserService()->becomeFriends($userId);

            if ($result['response'] === 'success') {
                ResponseHelper::success($result['response_text']);
            } elseif ($result['response_text'] ?? false) {
                ResponseHelper::error($result['response_text']);
            }
        }

        $rData = DB->query(
            'SELECT r.obj_id_to FROM relation r LEFT JOIN conversation c ON c.id=r.obj_id_to WHERE r.obj_type_to="{conversation}" AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_id_from IN (:obj_id_from) AND (c.name IS NULL OR c.name="") AND EXISTS (SELECT 1 FROM relation r2 WHERE r.obj_id_to=r2.obj_id_to AND r2.obj_type_to="{conversation}" AND r2.type="{member}" AND r2.obj_type_from="{user}" GROUP BY r2.obj_id_to HAVING COUNT(r2.obj_id_from)=2) GROUP BY r.obj_id_to HAVING COUNT(r.obj_id_from)=2 ORDER BY r.obj_id_to LIMIT 1',
            [
                ['obj_id_from', [$userId, CURRENT_USER->id()]],
            ],
            true,
        );

        if ($rData) {
            CookieHelper::batchSetCookie(['full_conversation_kind' => 'true']); // необходимо для того, чтобы диалоги открылись цельно при этой форме перехода

            ResponseHelper::redirect(ABSOLUTE_PATH . '/' . KIND . '/' . $rData['obj_id_to'] . '/');
        }

        return null;
    }

    /** Крайне упрощенное удаление сообщения (исключительно для диалогов) */
    public function conversationMessageDelete(?int $objId): ?array
    {
        if ($objId > 0) {
            $LOCALE = $this->getLOCALE();

            $conversationMessage = DB->findObjectById($objId, 'conversation_message');

            if ($conversationMessage['creator_id'] === CURRENT_USER->id()) {
                DB->delete('conversation_message', ['id' => $objId]);
                DB->delete('conversation_message_status', ['message_id' => $objId]);
            } else {
                DB->update('conversation_message_status', ['message_deleted' => 1], ['message_id' => $objId, 'user_id' => CURRENT_USER->id()]);
            }

            return [
                'response' => 'success',
                'response_text' => $LOCALE['messages']['delete_message_success'],
            ];
        }

        return null;
    }

    /** Обработка различных действий подтверждения-отказа внутри диалогов */
    public function resolveAction(string $action, ?int $objId): ?array
    {
        if ($action !== '' && $objId > 0) {
            $userService = $this->getUserService();

            $LOCALE = $this->getLOCALE();

            $returnArr = [];

            $message = DB->findObjectById($objId, 'conversation_message');
            $user = $userService->get($message['creator_id']);

            if ($message['message_action'] !== '' && $message['id'] !== '' && $user->id->getAsInt()) {
                $obj = [];

                preg_match('#{([^:]+):([^,]+)}#', $message['message_action_data'], $match);

                if ($match[1] === 'project_id' && $match[2] > 0) {
                    $obj['word'] = $LOCALE['obj_types']['project3'];
                    $obj['kind'] = '{project}';
                } elseif ($match[1] === 'community_id' && $match[2] > 0) {
                    $obj['word'] = $LOCALE['obj_types']['community3'];
                    $obj['kind'] = '{community}';
                } elseif ($match[1] === 'task_id' && $match[2] > 0) {
                    $obj['word'] = $LOCALE['obj_types']['task3'];
                    $obj['kind'] = '{task}';
                } elseif ($match[1] === 'event_id' && $match[2] > 0) {
                    $obj['word'] = $LOCALE['obj_types']['event3'];
                    $obj['kind'] = '{event}';
                } elseif ($match[1] === 'user_id' && $match[2] > 0) {
                    $obj['kind'] = '{user}';
                } elseif ($match[1] === 'project_room_id' && $match[2] > 0) {
                    $obj['kind'] = '{project_room}';
                }

                $resolvedId = 0;

                if ($message['message_action'] === '{get_access}' && $match[2] > 0) {
                    if (RightsHelper::checkRights('{admin}', $obj['kind'], $match[2]) || CURRENT_USER->isAdmin()) {
                        if ($action === 'grant_access') {
                            $me = $userService->get(CURRENT_USER->id());
                            RightsHelper::addRights('{member}', $obj['kind'], $match[2], '{user}', $user->id->getAsInt());
                            $conversationId = RightsHelper::findOneByRights(
                                '{child}',
                                $obj['kind'],
                                $match[2],
                                '{conversation}',
                            );

                            if ($conversationId > 0) {
                                RightsHelper::addRights(
                                    '{member}',
                                    '{conversation}',
                                    $conversationId,
                                    '{user}',
                                    $user->id->getAsInt(),
                                );
                            }
                            $resolvedId = $this->messageService->newMessage(
                                $message['conversation_id'],
                                $LOCALE['messages']['access_granted'] . LocaleHelper::declineVerb($me) . '.',
                            );
                            $returnArr = [
                                'response' => 'success',
                                'response_text' => sprintf($LOCALE['messages']['access_granted_extended'], $obj['word']),
                            ];
                        } elseif ($action === 'deny_access') {
                            $resolvedId = $this->messageService->newMessage($message['conversation_id'], $LOCALE['messages']['access_denied']);
                            $returnArr = [
                                'response' => 'success',
                                'response_text' => sprintf($LOCALE['messages']['access_denied_extended'], $obj['word']),
                            ];
                        }
                        DB->update(
                            'conversation_message',
                            [
                                'message_action_data' => mb_substr($message['message_action_data'], 0, mb_strlen($message['message_action_data']) - 1) .
                                    ',resolved:' . $resolvedId . '}',
                            ],
                            ['id' => $message['id']],
                        );
                    }
                } elseif ($message['message_action'] === '{send_invitation}' && $match[2] > 0) {
                    $me = $userService->get(CURRENT_USER->id());

                    if ($action === 'accept_invitation') {
                        if (DataHelper::clearBraces($obj['kind']) === 'project_room') { // принятие приглашений к совместному проживанию
                            $roomId = $match[2];

                            // проверяем: есть ли свободные места в указанной комнате?
                            $roomData = DB->select('project_room', ['id' => $roomId], true);
                            $membersCount = RightsHelper::findByRights(
                                '{member}',
                                '{room}',
                                $roomId,
                                '{application}',
                                false,
                            );
                            $LOCALE_MYAPPLICATION = LocaleHelper::getLocale(['myapplication', 'global']);

                            if ($roomData['places_count'] <= count($membersCount)) {
                                $returnArr = [
                                    'response' => 'error',
                                    'response_text' => $LOCALE_MYAPPLICATION['messages']['add_neighboor_request']['not_enough_places_on_accept'],
                                ];
                            } else {
                                // проверяем: может ли данный игрок выбрать эту комнату в рамках дополнительной настройки взносов, если они есть?
                                /** @var ApplicationService */
                                $applicationService = CMSVCHelper::getService('application');

                                $checkApplicationExistsInvited = $applicationService->get(
                                    id: null,
                                    criteria: [
                                        'project_id' => $roomData['project_id'],
                                        'creator_id' => CURRENT_USER->id(),
                                        'deleted_by_player' => 0,
                                        'deleted_by_gamemaster' => 0,
                                    ],
                                    order: [
                                        'updated_at DESC',
                                    ],
                                );

                                if ($checkApplicationExistsInvited) {
                                    $hasLimitations = false;
                                    $allowRoom = false;
                                    $playerFees = $checkApplicationExistsInvited->project_fee_ids->get();

                                    foreach ($playerFees as $playerFeeId) {
                                        $feeDateData = DB->select('project_fee', ['id' => $playerFeeId], true);
                                        $feeDataParent = DB->select('project_fee', ['id' => $feeDateData['parent']], true);
                                        $playerRoomsTypes = DataHelper::multiselectToArray($feeDataParent['project_room_ids']);

                                        if (count($playerRoomsTypes) > 0) {
                                            $hasLimitations = true;

                                            if (in_array($roomId, $playerRoomsTypes)) {
                                                $allowRoom = true;
                                            }
                                        }
                                    }

                                    if (!$hasLimitations || $allowRoom) {
                                        RightsHelper::deleteRights(
                                            '{member}',
                                            '{room}',
                                            null,
                                            '{application}',
                                            $checkApplicationExistsInvited->id->getAsInt(),
                                        );
                                        RightsHelper::addRights(
                                            '{member}',
                                            '{room}',
                                            $roomId,
                                            '{application}',
                                            $checkApplicationExistsInvited->id->getAsInt(),
                                        );

                                        $resolvedId = $this->messageService->newMessage(
                                            $message['conversation_id'],
                                            $LOCALE['messages']['invitation_accepted'] . LocaleHelper::declineVerb(
                                                $me,
                                            ) . '.',
                                        );
                                        $returnArr = [
                                            'response' => 'success',
                                            'response_text' => $LOCALE['messages']['invitation_accepted_extended'],
                                        ];
                                    } else {
                                        $returnArr = [
                                            'response' => 'error',
                                            'response_text' => $LOCALE_MYAPPLICATION['messages']['add_neighboor_request']['cannot_be_invited_to_room_type_on_accept'],
                                        ];
                                    }
                                }
                            }
                        } else {
                            RightsHelper::addRights('{member}', $obj['kind'], $match[2]);
                            $conversationId = RightsHelper::findOneByRights(
                                '{child}',
                                $obj['kind'],
                                $match[2],
                                '{conversation}',
                            );

                            if ($conversationId > 0) {
                                RightsHelper::addRights('{member}', '{conversation}', $conversationId);
                            }
                            $resolvedId = $this->messageService->newMessage(
                                $message['conversation_id'],
                                $LOCALE['messages']['invitation_accepted'] . LocaleHelper::declineVerb(
                                    $me,
                                ) . '.',
                            );

                            if (in_array(DataHelper::clearBraces($obj['kind']), ['task', 'event'])) {
                                $parentProjects = RightsHelper::findByRights(
                                    '{child}',
                                    '{project}',
                                    null,
                                    $obj['kind'],
                                    $match[2],
                                );
                                $parentCommunities = RightsHelper::findByRights(
                                    '{child}',
                                    '{community}',
                                    null,
                                    $obj['kind'],
                                    $match[2],
                                );

                                if ($parentProjects) {
                                    foreach ($parentProjects as $value) {
                                        $checkAccessToParent = RightsHelper::checkAnyRights('{project}', $value);

                                        if (!$checkAccessToParent) {
                                            RightsHelper::addRights('{member}', '{project}', $value);
                                            $conversationId = RightsHelper::findOneByRights(
                                                '{child}',
                                                '{project}',
                                                $value,
                                                '{conversation}',
                                            );

                                            if ($conversationId > 0) {
                                                RightsHelper::addRights('{member}', '{conversation}', $conversationId);
                                            }
                                        }
                                    }
                                }

                                if ($parentCommunities) {
                                    foreach ($parentCommunities as $value) {
                                        $checkAccessToParent = RightsHelper::checkAnyRights('{community}', $value);

                                        if (!$checkAccessToParent) {
                                            RightsHelper::addRights('{member}', '{community}', $value);
                                            $conversationId = RightsHelper::findOneByRights(
                                                '{child}',
                                                '{community}',
                                                $value,
                                                '{conversation}',
                                            );

                                            if ($conversationId > 0) {
                                                RightsHelper::addRights('{member}', '{conversation}', $conversationId);
                                            }
                                        }
                                    }
                                }
                            }

                            /* проверяем наличие прав в формате user_invited и принимаем их */
                            $toConfirmRelations = DB->select(
                                'relation',
                                [
                                    'obj_type_to' => $obj['kind'],
                                    'obj_id_to' => $match[2],
                                    'obj_type_from' => '{user_invited}',
                                    'obj_id_from' => CURRENT_USER->id(),
                                ],
                            );

                            foreach ($toConfirmRelations as $toConfirmRelation) {
                                DB->update('relation', ['obj_type_from' => '{user}'], ['id' => $toConfirmRelation['id']]);
                            }

                            $returnArr = [
                                'response' => 'success',
                                'response_text' => $LOCALE['messages']['invitation_accepted_extended'],
                            ];
                        }
                    } elseif ($action === 'decline_invitation') {
                        $resolvedId = $this->messageService->newMessage(
                            $message['conversation_id'],
                            $LOCALE['messages']['invitation_declined'] . LocaleHelper::declineVerb($me) . '.',
                        );

                        /* проверяем наличие прав в формате user_invited и удаляем их */
                        $toConfirmRelations = DB->select(
                            'relation',
                            [
                                'obj_type_to' => $obj['kind'],
                                'obj_id_to' => $match[2],
                                'obj_type_from' => '{user_invited}',
                                'obj_id_from' => CURRENT_USER->id(),
                            ],
                        );

                        foreach ($toConfirmRelations as $toConfirmRelation) {
                            DB->delete('relation', ['id' => $toConfirmRelation['id']]);
                        }

                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $LOCALE['messages']['invitation_declined_extended'],
                        ];
                    }

                    if ($resolvedId > 0) {
                        DB->update(
                            'conversation_message',
                            [
                                'message_action_data' => mb_substr($message['message_action_data'], 0, mb_strlen($message['message_action_data']) - 1) .
                                    ',resolved:' . $resolvedId . '}',
                            ],
                            ['id' => $message['id']],
                        );
                    }
                } elseif ($message['message_action'] === '{become_friends}' && $match[2] > 0) {
                    $me = $userService->get(CURRENT_USER->id());

                    if ($action === 'accept_friend') {
                        RightsHelper::addRights('{friend}', '{user}', $message['creator_id'], '{user}', $me->id->getAsInt());
                        RightsHelper::addRights('{friend}', '{user}', $me->id->getAsInt(), '{user}', $message['creator_id']);
                        $resolvedId = $this->messageService->newMessage(
                            $message['conversation_id'],
                            $LOCALE['messages']['friend_accepted'] . LocaleHelper::declineVerb($me) . '.',
                        );
                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $LOCALE['messages']['friend_accepted_extended'],
                        ];
                    } elseif ($action === 'decline_friend') {
                        $resolvedId = $this->messageService->newMessage(
                            $message['conversation_id'],
                            $LOCALE['messages']['friend_declined'] . LocaleHelper::declineVerb($me) . '.',
                        );
                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $LOCALE['messages']['friend_declined_extended'],
                        ];
                    }
                    DB->update(
                        'conversation_message',
                        [
                            'message_action_data' => mb_substr($message['message_action_data'], 0, mb_strlen($message['message_action_data']) - 1) .
                                ',resolved:' . $resolvedId . '}',
                        ],
                        ['id' => $message['id']],
                    );
                }

                // отмечаем resolved_id в $message['conversation_id'], как прочитанный, для всех, кроме автора запроса
                $conversationUsers = RightsHelper::findByRights(
                    '{member}',
                    '{conversation}',
                    $message['conversation_id'],
                    '{user}',
                    false,
                );

                if ($conversationUsers) {
                    foreach ($conversationUsers as $conversationUser) {
                        if ($conversationUser !== CURRENT_USER->id() && $conversationUser !== $message['creator_id']) {
                            if ($_ENV['DATABASE_TYPE'] === 'pgsql') {
                                $checkRecord = DB->select(
                                    'conversation_message_status',
                                    ['message_id' => $resolvedId, 'user_id' => $conversationUser],
                                    true,
                                );

                                if ($checkRecord['id']) {
                                    DB->update(
                                        'conversation_message_status',
                                        [
                                            'message_read' => 1,
                                            'updated_at' => DateHelper::getNow(),
                                        ],
                                        [
                                            'message_id' => $resolvedId,
                                            'user_id' => $conversationUser,
                                        ],
                                    );
                                } else {
                                    DB->insert(
                                        'conversation_message_status',
                                        [
                                            'message_id' => $resolvedId,
                                            'user_id' => $conversationUser,
                                            'message_read' => 1,
                                            'created_at' => DateHelper::getNow(),
                                            'updated_at' => DateHelper::getNow(),
                                        ],
                                    );
                                }
                                $checkRecord = DB->select(
                                    'conversation_message_status',
                                    [
                                        'message_id' => $message['id'],
                                        'user_id' => $conversationUser,
                                    ],
                                    true,
                                );

                                if ($checkRecord['id']) {
                                    DB->update(
                                        'conversation_message_status',
                                        ['message_read' => 1, 'updated_at' => DateHelper::getNow()],
                                        ['message_id' => $message['id'], 'user_id' => $conversationUser],
                                    );
                                } else {
                                    DB->insert(
                                        'conversation_message_status',
                                        [
                                            'message_id' => $message['id'],
                                            'user_id' => $conversationUser,
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
                                        ['message_id', $resolvedId],
                                        ['user_id', $conversationUser],
                                        ['time_1', DateHelper::getNow()],
                                        ['time_2', DateHelper::getNow()],
                                    ],
                                );
                                DB->query(
                                    "INSERT INTO conversation_message_status (message_id, user_id, message_read, updated_at, created_at) VALUES (:message_id, :user_id, '1', :time_1, :time_2) ON DUPLICATE KEY UPDATE message_read=VALUES(message_read)",
                                    [
                                        ['message_id', $message['id']],
                                        ['user_id', $conversationUser],
                                        ['time_1', DateHelper::getNow()],
                                        ['time_2', DateHelper::getNow()],
                                    ],
                                );
                            }
                        }
                    }
                }
            }

            return $returnArr;
        }

        return null;
    }

    /** Выход из диалога */
    public function leaveDialog(?int $objId): array
    {
        $LOCALE = $this->getLOCALE();

        $returnArr = [];

        if (RightsHelper::checkRights('{member}', '{conversation}', $objId)) {
            $userService = $this->getUserService();

            $groupConversations = $userService->getGroupConversations();

            if ($groupConversations[$objId] ?? false) {
                $user = $userService->get(CURRENT_USER->id());
                $text =
                    sprintf(
                        $LOCALE['messages']['user_left_the_dialog'],
                        $userService->showNameExtended(
                            $user,
                            true,
                            false,
                            '',
                            false,
                            false,
                            true,
                        ),
                        LocaleHelper::declineVerb($user),
                    );
                $this->messageService->newMessage($objId, $text);

                if (RightsHelper::deleteRights('{member}', '{conversation}', $objId)) {
                    $returnArr = [
                        'response' => 'success',
                        'response_text' => $LOCALE['messages']['dialog_leave_success'],
                    ];
                }
            }
        }

        return $returnArr;
    }

    /**  Приглашение в объект */
    public function sendInvitation(?string $objType, ?int $objId, int|string|null $userId): ?array
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

        if (!RightsHelper::checkRights('{member}', DataHelper::addBraces($objType), $objId, '{user}', $userId)) {
            $cId = null;
            $result = DB->query(
                "SELECT c.id, cm.updated_at FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN relation r ON r.obj_id_to=c.id WHERE cm.message_action_data=:message_action_data AND cm.message_action='{send_invitation}' AND (r.obj_id_from=:obj_id_from AND r.type='{member}' AND r.obj_type_to='{conversation}' AND r.obj_type_from='{user}') AND cm.creator_id=:creator_id ORDER BY updated_at DESC",
                [
                    ['message_action_data', '{' . DataHelper::clearBraces($objType) . '_id:' . $objId . '}'],
                    ['obj_id_from', $userId],
                    ['creator_id', CURRENT_USER->id()],
                ],
                true,
            );

            if ($result) {
                if (DateHelper::getNow() - $result['updated_at'] > 600) {
                    $cId = $result['id'];
                } else {
                    return [
                        'response' => 'error',
                        'response_code' => 're_invitation_blocked',
                        'response_text' => $LOCALE['messages']['re_invitation_blocked'],
                    ];
                }
            }

            $users = [];

            if (is_null($cId)) {
                if ($userService->get($userId)) {
                    $users[$userId] = 'on';
                }
            }

            if (!is_null($cId) || count($users) > 0) {
                $result = $this->messageService->newMessage(
                    $cId,
                    '{action}',
                    '',
                    $users,
                    [],
                    [],
                    '{send_invitation}',
                    '{' . DataHelper::clearBraces($objType) . '_id:' . $objId . '}',
                );

                if ($result && is_null($cId)) {
                    return [
                        'response' => 'success',
                        'response_text' => $LOCALE['messages']['invitation_success'],
                    ];
                } elseif ($result) {
                    return [
                        'response' => 'success',
                        'response_text' => $LOCALE['messages']['re_invitation_success'],
                    ];
                }
            } else {
                return [
                    'response' => 'error',
                    'response_code' => 'invitation_recepient_not_set',
                    'response_text' => $LOCALE['messages']['invitation_recepient_not_set'],
                ];
            }
        }

        return null;
    }

    /** Добавление пользователя в диалог */
    public function addUserToDialog(?int $objId, ?int $userId): ?array
    {
        if ($objId > 0 && $userId > 0) {
            $LOCALE = $this->getLOCALE();

            $returnArr = [];

            if (RightsHelper::checkRights('{member}', '{conversation}', $objId)) {
                /** @var UserService $userService */
                $userService = CMSVCHelper::getService('user');

                $groupConversations = $userService->getGroupConversations();
                $addedUserName = $userService->get($userId);

                if (($groupConversations[$objId] ?? false) && $addedUserName) {
                    // это групповой диалог. Значит, просто добавляем к нему пользователя и пишем об этом в чатик.
                    $conversationData = DB->findObjectById($objId, 'conversation');

                    if ($conversationData['id'] ?? false) {
                        RightsHelper::addRights('{member}', '{conversation}', $objId, '{user}', $userId);
                        $user = $userService->get(CURRENT_USER->id());
                        $text =
                            sprintf(
                                $LOCALE['messages']['user_added_user_to_dialog'],
                                $userService->showNameExtended(
                                    $user,
                                    true,
                                    false,
                                    '',
                                    false,
                                    false,
                                    true,
                                ),
                                LocaleHelper::declineVerb($user),
                                $userService->showName($addedUserName),
                            );
                        $this->messageService->newMessage($objId, $text);
                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $LOCALE['messages']['add_user_success'],
                            'conversation_id' => $objId,
                        ];
                    } else {
                        $returnArr = [
                            'response' => 'error',
                            'response_code' => 'add_user_fail',
                            'response_text' => $LOCALE['messages']['add_user_fail'],
                        ];
                    }
                } elseif ($addedUserName->id->getAsInt() !== '') {
                    // это персональный диалог. Создаем новый групповой диалог с данным пользователем.
                    $user = $userService->get(CURRENT_USER->id());
                    $text =
                        sprintf(
                            $LOCALE['messages']['user_added_user_to_dialog'],
                            $userService->showNameExtended(
                                $user,
                                true,
                                false,
                                '',
                                false,
                                false,
                                true,
                            ),
                            LocaleHelper::declineVerb($user),
                            $userService->showName($addedUserName),
                        );
                    $users = [];
                    $userData = RightsHelper::findByRights('{member}', '{conversation}', $objId, '{user}', false);
                    $userIds = [];

                    foreach ($userData as $value) {
                        if ($value !== CURRENT_USER->id()) {
                            $users[$value] = 'on';
                            $userIds[] = $value;
                        }
                    }
                    $users[$userId] = 'on';
                    $userIds[] = $userId;
                    $messageId = $this->messageService->newMessage(null, $text, '', $users);

                    if ($messageId > 0) {
                        $conversationData = DB->findObjectById($messageId, 'conversation_message');
                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $LOCALE['messages']['add_user_success'],
                            'conversation_id' => $conversationData['conversation_id'] ?? false,
                            'user_id' => implode(',', $userIds),
                        ];
                    } else {
                        $returnArr = [
                            'response' => 'error',
                            'response_code' => 'add_user_fail',
                            'response_text' => $LOCALE['messages']['add_user_fail'],
                        ];
                    }
                }
            }

            return $returnArr;
        }

        return null;
    }

    /** Переименование группового диалога */
    public function conversationRename(?int $objId, string $value): ?array
    {
        if ($objId > 0 && $value !== '') {
            $LOCALE = $this->getLOCALE();

            $returnArr = [];

            if (RightsHelper::checkRights('{member}', '{conversation}', $objId)) {
                $userService = $this->getUserService();

                $conversationParentInfo = DB->select(
                    'relation',
                    [
                        'obj_type_from' => '{conversation}',
                        'type' => '{child}',
                        'obj_id_from' => $objId,
                    ],
                    true,
                );
                $groupConversations = $userService->getGroupConversations();

                if (($groupConversations[$objId] ?? false) || ($conversationParentInfo['obj_id_to'] ?? false)) {
                    $conversationData = DB->findObjectById($objId, 'conversation');

                    if ($conversationData['id'] ?? false) {
                        DB->update('conversation', ['name' => $value], ['id' => $objId]);
                        $user = $userService->get(CURRENT_USER->id());
                        $text =
                            sprintf(
                                $LOCALE['messages']['user_changed_dialog_name'],
                                $userService->showNameExtended(
                                    $user,
                                    true,
                                    false,
                                    '',
                                    false,
                                    false,
                                    true,
                                ),
                                LocaleHelper::declineVerb($user),
                                $value,
                            );
                        $this->messageService->newMessage($objId, $text);
                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $LOCALE['messages']['change_name_success'],
                        ];
                    } else {
                        $returnArr = [
                            'response' => 'error',
                            'response_code' => 'change_name_fail',
                            'response_text' => $LOCALE['messages']['change_name_fail'],
                        ];
                    }
                } else {
                    $returnArr = [
                        'response' => 'error',
                        'response_code' => 'change_name_fail',
                        'response_text' => $LOCALE['messages']['change_name_fail'],
                    ];
                }
            }

            return $returnArr;
        }

        return null;
    }

    /** Переключение вида вывода имен в диалогах: пожизневые или игровые */
    public function switchUseNamesType(?int $objId): ?array
    {
        if ($objId > 0) {
            $returnArr = [];

            $conversationData = DB->findObjectById($objId, 'conversation');

            if (($conversationData['id'] ?? false) && $conversationData['creator_id'] === CURRENT_USER->id()) {
                DB->update('conversation', ['use_names_type' => ($conversationData['use_names_type'] === 1 ? 0 : 1)], ['id' => $objId]);

                $returnArr = [
                    'response' => 'success',
                ];
            }

            return $returnArr;
        }

        return null;
    }

    private function getConnectedObjectUsers(): array
    {
        $connectedObjectUsers = $this->connectedObjectUsers;

        if (is_null($connectedObjectUsers)) {
            $userIds = [];
            $images = [];
            $userIdsSort = [];
            $userImagesSort = [];

            $objType = OBJ_TYPE[0] ?? OBJ_TYPE;

            if (OBJ_ID > 0 && $objType && RightsHelper::checkRights(['{admin}', '{responsible}', '{moderator}'], $objType, OBJ_ID)) {
                $result = RightsHelper::findByRights(null, $objType, OBJ_ID, '{user}', false);
            } else {
                $result = RightsHelper::findByRights('{friend}', '{user}');
            }

            if ($result && count($result) > 0) {
                foreach ($result as $value) {
                    if ($value !== CURRENT_USER->id()) {
                        $userData = $this->getUserService()->get($value);

                        if (!is_null($userData)) {
                            $showName = $this->getUserService()->showName($userData);
                            $userIds[] = [$userData->id->getAsInt(), $showName];
                            $userIdsSort[] = $showName;

                            $image = $this->getUserService()->photoLink($userData, 30);
                            $images[] = [$userData->id->getAsInt(), $image];
                        }
                    }
                }
                $userImagesSort = $userIdsSort;
                array_multisort($userIdsSort, SORT_ASC, $userIds);
                array_multisort($userImagesSort, SORT_ASC, $images);
            }

            $this->connectedObjectUsers = $connectedObjectUsers = [
                'ids' => $userIds,
                'images' => $images,
            ];
        }

        return $connectedObjectUsers;
    }
}
