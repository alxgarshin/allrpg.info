<?php

declare(strict_types=1);

namespace App\CMSVC\User;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Conversation\ConversationService;
use App\CMSVC\Message\MessageService;
use App\CMSVC\Task\TaskService;
use App\Helper\{DesignHelper, FileHelper, MessageHelper, RightsHelper, UniversalHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{AuthHelper, CMSVCHelper, CookieHelper, DataHelper, EmailHelper, LocaleHelper};
use Identicon\Identicon;

/** @extends BaseService<UserModel> */
#[Controller(UserController::class)]
class UserService extends BaseService
{
    private ?array $currentUserContactsIds = null;
    private array $usersOnlineStatuses = [];
    private array $groupConversations = [];
    private bool $initializedGroupConversation = false;
    private ?array $allConversationsIds = null;
    private bool $initializedAllConversationsIds = false;
    private array $contactConversations = [];
    private bool $initializedContactConversations = false;

    /** Проверка, является ли пользователь модератором */
    public function isModerator(): bool
    {
        return CURRENT_USER->isAdmin();
    }

    /** Вычистка url'ов социальных сетей */
    public function social(?string $soc = ''): string
    {
        return str_replace([
            'http://',
            'https://',
            'www.',
            '/posts',
            'vkontakte.ru/',
            'm.vk.com/',
            'vk.com/',
            't.me/@',
            't.me/',
            '.livejournal.com',
            'twitter.com/#!/',
            'facebook.com',
            'profile.php?id=',
            'plus.google.com',
            'fotki.yandex.ru/users/',
            '/',
        ], '', (string) $soc);
    }

    /** Выведение url'ов социальных сетей с иконками */
    public function social2(string $path, string $type = '', bool $pic = false): string
    {
        if ($type === '') {
            if (preg_match('#vkontakte.ru#', $path)) {
                $type = 'vkontakte';
            } elseif (preg_match('#vk.com#', $path)) {
                $type = 'vkontakte';
            } elseif (preg_match('#fotki.yandex.ru#', $path)) {
                $type = 'yandex';
            } elseif (preg_match('#twitter.com#', $path)) {
                $type = 'twitter';
            } elseif (preg_match('#livejournal.com#', $path)) {
                $type = 'livejournal';
            } elseif (preg_match('#facebook.com#', $path)) {
                $type = 'facebook';
            } elseif (preg_match('#plus.google.com#', $path)) {
                $type = 'googleplus';
            } elseif (preg_match('#t.me#', $path)) {
                $type = 'telegram';
            }
        }

        $path = str_replace([
            'http://',
            'https://',
            'www.',
            '/posts',
            'vkontakte.ru/',
            'm.vk.com/',
            'vk.com/',
            'Мk.com/',
            't.me/@',
            't.me/',
            '.livejournal.com',
            'twitter.com/#!/',
            'facebook.com',
            'profile.php?id=',
            'plus.google.com',
            'fotki.yandex.ru/users/',
            '/',
        ], '', $path);

        $rpath = '';

        if ($path !== '') {
            if ($type === 'telegram') {
                $path = preg_replace('#^@#', '', $path);
                $rpath .= '<a href="https://t.me/' . $path . '" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'networks/tg_icon.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } elseif ($type === 'vkontakte') {
                $path = preg_replace('#^m\.#', '', $path);
                $rpath .= '<a href="https://vk.com/' . $path . '" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'networks/vk_icon.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } elseif ($type === 'twitter') {
                $rpath .= '<a href="http://www.twitter.com/#!/' . $path . '" target="_blank">';
                $rpath .= $path;
                $rpath .= '</a>';
            } elseif ($type === 'livejournal') {
                $rpath .= '<a href="http://' . $path . '.livejournal.com" target="_blank">';
                $rpath .= $path;
                $rpath .= '</a>';
            } elseif ($type === 'facebook') {
                $rpath .= '<a href="http://www.facebook.com/' . $path . '" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'networks/fb_icon.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } elseif ($type === 'googleplus') {
                $rpath .= '<a href="https://plus.google.com/' . $path . '/posts" target="_blank">';
                $rpath .= $path;
                $rpath .= '</a>';
            } elseif ($type === 'yandex') {
                $rpath .= '<a href="http://fotki.yandex.ru/users/' . $path . '/" target="_blank">';
                $rpath .= $path;
                $rpath .= '</a>';
            } else {
                $rpath .= $path;
            }
        }

        return $rpath;
    }

    /** Часто используемый вариант вывода имени пользователя */
    public function showName(?UserModel $userModel, bool $link = false): string
    {
        return $this->showNameExtended($userModel, true, $link, '', false, false, true);
    }

    /** Часто используемый вариант вывода имени пользователя вместе с его id */
    public function showNameWithId(?UserModel $userModel, bool $link = false): string
    {
        return $this->showNameExtended($userModel, false, $link, '', false, true, true);
    }

    /** Трансформация Ф.И.О. пользователя в ссылку и удобно читаемый вид */
    public function showNameExtended(
        ?UserModel $userModel,
        bool $showId = false,
        bool $link = false,
        string $class = '',
        bool $shortName = false,
        bool $addIdInAnyCase = false,
        bool $addNickname = false,
    ): string {
        $LOCALE = LocaleHelper::getLocale(['global']);

        if (is_null($userModel)) {
            return $LOCALE['deleted_user'];
        }

        $kindsWithFullData = [
            'transaction',
            'application',
            'org',
            'character',
            'roles_gamemaster',
        ];

        $result = '';

        if ($link) {
            if (CURRENT_USER->isLogged()) {
                $result .= '<a href="' . ABSOLUTE_PATH . '/people/' .
                    (!is_null($userModel->sid->get()) ? $userModel->sid->get() . '/' : '') . '"' .
                    ($class !== '' ? ' class="' . $class . '"' : '') . '>';
            }
        }

        if (!is_null($userModel->id->getAsInt())) {
            $gotSomeName = false;

            if (!CURRENT_USER->blockedProfileEdit()) {
                $hidesome = $userModel->hidesome->get();

                if (
                    (!in_array(10, $hidesome) || in_array(KIND, $kindsWithFullData)) && $userModel->fio->get() !== null
                    && !($shortName && $addNickname && (!in_array(0, $hidesome)
                        || in_array(KIND, $kindsWithFullData)) && $userModel->nick->get() !== null)
                ) {
                    $fio = trim($userModel->fio->get());

                    if ($shortName) {
                        $fio = preg_replace('#\s+#', ' ', $fio);
                        $name = explode(' ', $fio);

                        if (!empty($name[1])) {
                            if (!preg_match('#вич|вна#', $name[1]) && ($name[2] ?? false)) {
                                $result .= $name[1];
                            } else {
                                $result .= $name[0];
                            }
                        } else {
                            $result .= $fio;
                        }
                    } else {
                        $result .= $fio;
                    }
                    $gotSomeName = true;
                }

                if (
                    $addNickname && (!in_array(0, $userModel->hidesome->get()) || in_array(KIND, $kindsWithFullData))
                    && $userModel->nick->get() !== null
                ) {
                    if ($gotSomeName) {
                        $result .= ' (';
                    }
                    $result .= $userModel->nick->get();

                    if ($gotSomeName) {
                        $result .= ')';
                    }
                    $gotSomeName = true;
                }
            } else {
                $showId = true;
            }

            $LOCALE_USER = LocaleHelper::getLocale(['user', 'global']);

            if ($showId && !$gotSomeName) {
                $result .= $LOCALE['user_id'] . ' ' . $userModel->sid->get();
            } elseif ($addIdInAnyCase) {
                if (!$gotSomeName) {
                    $result .= $LOCALE_USER['name_hidden'];
                }
                $result .= ' (' . $LOCALE['user_id'] . ' ' . $userModel->sid->get() . ')';
            } elseif (!$gotSomeName) {
                $result .= $LOCALE_USER['name_hidden'];
            }
        } else {
            $result .= $LOCALE['deleted_user'];
        }

        if ($link) {
            if (CURRENT_USER->isLogged()) {
                $result .= '</a>';
            }
        }

        return $result;
    }

    /** Получение полного списка всех возможных объектов подписки через тире */
    public function getSubsObjectsList(): array
    {
        $LOCALE = LocaleHelper::getLocale(['user', 'fraym_model']);

        $subsObjectsArray = [];
        $subsObjectsList = $LOCALE['elements']['subs_objects']['values'];

        foreach ($subsObjectsList as $array) {
            $subsObjectsArray[] = $array[0];
        }

        return $subsObjectsArray;
    }

    /** Превращение имени пользователя в читаемый формат */
    public function getUserName(UserModel $userModel): string
    {
        $name = explode(' ', $userModel->fio->get());

        if ($name[1] !== '') {
            if (!preg_match('#вич|вна#', $name[1]) && $name[2] !== '') {
                $resultName = $name[1] . ' ' . $name[0];
            } elseif ($name[2] !== '') {
                $resultName = $name[0] . ' ' . $name[2];
            } else {
                $resultName = $userModel->fio->get();
            }
        } else {
            $resultName = $userModel->fio->get();
        }

        return $resultName;
    }

    /** Проверка, может ли пользователь присылать и получать картинки в диалогах */
    public function canUseImagesInDialogs(?int $userId = null): bool
    {
        if (is_null($userId)) {
            $userId = CURRENT_USER->id();
        }
        $userData = $this->get($userId);

        return in_array('send_images', $userData->rights->get());
    }

    /** Проверка, может ли пользователь управлять "Рулёжкой" */
    public function isRulingAdmin(): bool
    {
        return CURRENT_USER->isAdmin() || CURRENT_USER->checkAllrights('ruling');
    }

    /** Проверка на наличие ачивок */
    public function checkForAchievements(int $userId, array $achievementIds = []): array
    {
        $achievementsFound = [];

        if (in_array(1, $achievementIds) || count($achievementIds) === 0) {
            // Разработчик allrpg.info
            RightsHelper::deleteRights('{has}', '{achievement}', 1, '{user}', $userId);
            $usersThatHaveIt = [15];

            if (in_array($userId, $usersThatHaveIt)) {
                $achievementsFound[] = 1;
            }
        }

        if (in_array(2, $achievementIds) || count($achievementIds) === 0) {
            // Помощник allrpg.info
            RightsHelper::deleteRights('{has}', '{achievement}', 2, '{user}', $userId);
            $usersThatHaveIt = [
                91,
                141,
                1609,
                485,
                195,
                404,
                4813,
                6727,
                4930,
                2668,
                1208,
                966,
                129,
                6013,
                9388,
            ];

            if (in_array($userId, $usersThatHaveIt)) {
                $achievementsFound[] = 2;
            }
        }

        if (in_array(3, $achievementIds) || count($achievementIds) === 0) {
            // Бета-тестер allrpg.info
            RightsHelper::deleteRights('{has}', '{achievement}', 3, '{user}', $userId);
            $usersThatHaveIt = [129, 2417, 141, 6013, 479];

            if (in_array($userId, $usersThatHaveIt)) {
                $achievementsFound[] = 3;
            }
        }

        if (in_array(4, $achievementIds) || count($achievementIds) === 0) {
            // Тестировал allrpg.info и выжил
            RightsHelper::deleteRights('{has}', '{achievement}', 4, '{user}', $userId);
            $usersThatHaveIt = [9455, 5, 873];

            if (in_array($userId, $usersThatHaveIt)) {
                $achievementsFound[] = 4;
            }
        }

        if (DataHelper::inArrayAny([5, 6, 7], $achievementIds) || count($achievementIds) === 0) {
            // Посещено N игр
            RightsHelper::deleteRights('{has}', '{achievement}', 5, '{user}', $userId);
            RightsHelper::deleteRights('{has}', '{achievement}', 6, '{user}', $userId);
            RightsHelper::deleteRights('{has}', '{achievement}', 7, '{user}', $userId);

            $result = DB->query(
                "SELECT p.id FROM played p WHERE p.creator_id=:creator_id AND (p.specializ != '-' OR (p.specializ2 = '-' AND p.specializ3 = '-')) AND p.active='1'",
                [
                    ['creator_id', $userId],
                ],
            );
            $playedCount = count($result);

            if ($playedCount >= 50) {
                $achievementsFound[] = 5;
            } elseif ($playedCount >= 25) {
                $achievementsFound[] = 6;
            } elseif ($playedCount >= 10) {
                $achievementsFound[] = 7;
            }
        }

        if (DataHelper::inArrayAny([8, 9, 10], $achievementIds) || count($achievementIds) === 0) {
            // Мастер N проектов
            RightsHelper::deleteRights('{has}', '{achievement}', 8, '{user}', $userId);
            RightsHelper::deleteRights('{has}', '{achievement}', 9, '{user}', $userId);
            RightsHelper::deleteRights('{has}', '{achievement}', 10, '{user}', $userId);

            $result = DB->select(
                tableName: 'played',
                criteria: [
                    'creator_id' => $userId,
                    ['specializ2', '-', [OperandEnum::NOT_EQUAL]],
                    'active' => '1',
                ],
                fieldsSet: [
                    'id',
                ],
            );
            $playedCount = count($result);

            if ($playedCount >= 25) {
                $achievementsFound[] = 8;
            } elseif ($playedCount >= 15) {
                $achievementsFound[] = 9;
            } elseif ($playedCount >= 5) {
                $achievementsFound[] = 10;
            }
        }

        if (DataHelper::inArrayAny([11, 12, 13], $achievementIds) || count($achievementIds) === 0) {
            // Написано N отчетов
            RightsHelper::deleteRights('{has}', '{achievement}', 11, '{user}', $userId);
            RightsHelper::deleteRights('{has}', '{achievement}', 12, '{user}', $userId);
            RightsHelper::deleteRights('{has}', '{achievement}', 13, '{user}', $userId);

            $result = DB->select(
                tableName: 'report',
                criteria: [
                    'creator_id' => $userId,
                ],
            );
            $reportCount = count($result);

            if ($reportCount >= 25) {
                $achievementsFound[] = 11;
            } elseif ($reportCount >= 15) {
                $achievementsFound[] = 12;
            } elseif ($reportCount >= 5) {
                $achievementsFound[] = 13;
            }
        }

        if (in_array(14, $achievementIds) || count($achievementIds) === 0) {
            // Полностью заполнил профиль
            RightsHelper::deleteRights('{has}', '{achievement}', 14, '{user}', $userId);

            if ($this->calculateProfileCompletion($userId) === 100) {
                $achievementsFound[] = 14;
            }
        }

        foreach ($achievementsFound as $achievementId) {
            RightsHelper::addRights('{has}', '{achievement}', $achievementId, '{user}', $userId, 'done');
        }

        return $achievementsFound;
    }

    /** Отметка пользователя как спам */
    public function markUserSpam(int $objId): array
    {
        $LOCALE = LocaleHelper::getLocale(['user_report', 'global', 'messages']);

        if (CURRENT_USER->isAdmin()) {
            $userData = $this->get($objId);

            if ($userData->id->getAsInt() > 0) {
                $rights = $userData->rights->get();
                $rights[] = 'banned';
                $rights = array_unique($rights);

                DB->update(
                    tableName: 'user',
                    data: [
                        'rights' => DataHelper::arrayToMultiselect($rights),
                    ],
                    criteria: [
                        'id' => $userData->id->getAsInt(),
                    ],
                );

                return [
                    'response' => 'success',
                    'response_text' => $LOCALE['user_marked_as_spam'],
                ];
            }
        }

        return [];
    }

    /** Получение списка пользователей объекта */
    public function loadUsersList(int|string|null $objId, string $objType, int $limit, int $shownLimit, string $subObjType): array
    {
        $LOCALE = LocaleHelper::getLocale([DataHelper::clearBraces($objType), 'global']);
        $LOCALE_PEOPLE = LocaleHelper::getLocale(['people', 'global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym', 'functions']);
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $responseText = '';

        if (DataHelper::addBraces($objType) === '{user}') {
            $result = RightsHelper::findByRights('{friend}', '{user}', null, '{user}', $objId);
        } else {
            $result = RightsHelper::findByRights(
                $subObjType !== '' ? DataHelper::addBraces($subObjType) : null,
                DataHelper::addBraces($objType),
                $objId,
                '{user}',
                false,
            );
        }

        $userDatas = [];
        $userDatasForPictures = [];
        $userDatasSort = [];

        if ($result) {
            foreach ($result as $key => $value) {
                if ($value === '') {
                    unset($result[$key]);
                }
            }

            $userRawDatas = $this->getAll([
                ['id', $result],
            ]);

            foreach ($userRawDatas as $userRawData) {
                $userDatas[] = [
                    $userRawData->id->getAsInt(),
                    $this->showName($userRawData),
                ];
                $userDatasForPictures[$userRawData->id->getAsInt()] = $userRawData;
            }

            foreach ($userDatas as $key => $row) {
                $userDatasSort[$key] = $row[1];
            }
            array_multisort($userDatasSort, SORT_ASC, $userDatas);
        }

        $totalObjects = count($userDatas);
        $admin = false;

        if (DataHelper::addBraces($objType) === '{user}' && $objId === CURRENT_USER->id()) {
            $admin = true;
        } elseif (RightsHelper::checkRights('{admin}', DataHelper::addBraces($objType), $objId)) {
            $admin = true;
        }

        foreach ($userDatas as $userKey => $userDataFull) {
            if ($userKey >= $limit && $userKey < $limit + $shownLimit) {
                $userData = $userDataFull[0];
                $userDataForPicture = $userDatasForPictures[$userDataFull[0]];
                $responseText .= $this->photoNameLink($userDataForPicture, '', true, '', '', true);

                if ($admin) {
                    $rightsPlate = '<div class="user_rights_bar" obj_type="' .
                        DataHelper::clearBraces($objType) . '" obj_id="' . $objId . '" user_id="' . $userData . '">';

                    // нельзя снять с себя права админа
                    if (DataHelper::addBraces($objType) !== '{user}') {
                        if ($userData !== CURRENT_USER->id()) {
                            $rightsPlate .= '<a id="admin" class="user_rights_bar' . (RightsHelper::checkRights(
                                '{admin}',
                                DataHelper::addBraces($objType),
                                $objId,
                                '{user}',
                                $userData,
                            ) ? ' selected' : '') . '">' . $LOCALE['modal_group_admin_first_letter'] . '</a>';
                        } else {
                            $rightsPlate .= '<span class="selected">' . $LOCALE['modal_group_admin_first_letter'] . '</span>';
                        }
                    } else {
                        $rightsPlate .= '<a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $userData . '" class="user_rights_bar">' . $LOCALE_PEOPLE['contact_user_only'] . '</a>';
                    }

                    if (DataHelper::addBraces($objType) === '{project}') {
                        $rightsPlate .= '<a id="gamemaster" class="user_rights_bar' . (RightsHelper::checkRights(
                            '{gamemaster}',
                            DataHelper::addBraces($objType),
                            $objId,
                            '{user}',
                            $userData,
                        ) ? ' selected' : '') . '">' . $LOCALE['modal_group_management_first_letter'] . '</a>';
                        $rightsPlate .= '<a id="newsmaker" class="user_rights_bar' . (RightsHelper::checkRights(
                            '{newsmaker}',
                            DataHelper::addBraces($objType),
                            $objId,
                            '{user}',
                            $userData,
                        ) ? ' selected' : '') . '">' . $LOCALE['modal_group_technology_first_letter'] . '</a>';
                        $rightsPlate .= '<a id="budget" class="user_rights_bar' . (RightsHelper::checkRights(
                            '{budget}',
                            DataHelper::addBraces($objType),
                            $objId,
                            '{user}',
                            $userData,
                        ) ? ' selected' : '') . '">' . $LOCALE['modal_group_business_first_letter'] . '</a>';
                        $rightsPlate .= '<a id="fee" class="user_rights_bar' . (RightsHelper::checkRights(
                            '{fee}',
                            DataHelper::addBraces($objType),
                            $objId,
                            '{user}',
                            $userData,
                        ) ? ' selected' : '') . '">' . $LOCALE['modal_group_fee_first_letter'] . '</a>';
                    } elseif (DataHelper::addBraces($objType) === '{community}') {
                        $rightsPlate .= '<a id="moderator" class="user_rights_bar' . (RightsHelper::checkRights(
                            '{moderator}',
                            DataHelper::addBraces($objType),
                            $objId,
                            '{user}',
                            $userData,
                        ) ? ' selected' : '') . '">' . $LOCALE['modal_group_moderator_first_letter'] . '</a>';
                    } elseif (DataHelper::addBraces($objType) === '{task}') {
                        $rightsPlate .= '<a id="responsible" class="user_rights_bar' . (RightsHelper::checkRights(
                            '{responsible}',
                            DataHelper::addBraces($objType),
                            $objId,
                            '{user}',
                            $userData,
                        ) ? ' selected' : '') . '">' . $LOCALE['modal_group_responsible_first_letter'] . '</a>';
                    }

                    // нельзя удалить самого себя из списка
                    if ($userData !== CURRENT_USER->id()) {
                        $rightsPlate .= '<a id="delete_all" title="' . $LOCALE_FRAYM['delete'] . '" class="user_rights_bar"></a>';
                    }

                    $rightsPlate .= '</div></div>';
                    $responseText = mb_substr(
                        $responseText,
                        0,
                        mb_strlen($responseText) - 12,
                    ) . $rightsPlate . '</div></div>';
                }
            }

            if ($userKey >= $limit + $shownLimit) {
                break;
            }
        }

        if ($totalObjects > $limit + $shownLimit) {
            $responseText .= '<a class="load_users_list" obj_type="' . $objType . '" obj_id="' . $objId . '" limit="' . ($limit + $shownLimit) . '" shown_limit="' . $shownLimit . '" sub_obj_type="' . $subObjType . '">' . $LOCALE_GLOBAL['show_next'] . '</a>';
        }

        return ['response' => 'success', 'response_text' => $responseText];
    }

    /** Приглашение в контакты / друзья / коллеги */
    public function becomeFriends(int|string $objId): ?array
    {
        /** @var MessageService $messageService */
        $messageService = CMSVCHelper::getService('message');

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global', 'messages']);

        /* нельзя больше 20 запросов на сутки и чаще, чем раз в 10 секунд. */
        $checkTooOftenRequests = DB->query(
            'SELECT * FROM conversation_message WHERE creator_id=:creator_id AND message_action="{become_friends}" AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY ORDER BY created_at DESC',
            [
                ['creator_id', CURRENT_USER->id()],
            ],
        );
        $requestsCount = $checkTooOftenRequests ? count($checkTooOftenRequests) : 0;

        if ($requestsCount > 20 || ($checkTooOftenRequests[0]['created_at'] ?? 0) > time() - 10) {
            return [
                'response' => 'error',
                'response_text' => $LOCALE['too_often_friends_request'],
            ];
        }

        $result = DB->query(
            'SELECT c.id, cm.updated_at FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN relation r ON r.obj_id_to=c.id WHERE cm.message_action_data=:user_id AND cm.message_action="{become_friends}" AND (r.obj_id_from=:obj_id_from AND r.type="{member}" AND r.obj_type_to="{conversation}" AND r.obj_type_from="{user}") AND cm.creator_id=:creator_id ORDER BY updated_at DESC',
            [
                ['user_id', '{user_id:' . $objId . '}'],
                ['obj_id_from', $objId],
                ['creator_id', CURRENT_USER->id()],
            ],
            true,
        );

        if ($result) {
            return ['response' => 'error'];
        }

        $users = [];
        $users[$objId] = 'on';

        $result = $messageService->newMessage(
            null,
            '',
            '',
            $users,
            [],
            [],
            '{become_friends}',
            '{user_id:' . $objId . '}',
        );

        if ($result) {
            return [
                'response' => 'success',
                'response_text' => $LOCALE['friends_success'],
            ];
        }

        return null;
    }

    /** Удаление из друзей / коллег */
    public function removeFriend(int|string $objId): ?array
    {
        if (RightsHelper::checkRights('{friend}', '{user}', $objId)) {
            if (RightsHelper::deleteRights('{friend}', '{user}', $objId)) {
                $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

                return [
                    'response' => 'success',
                    'response_text' => $LOCALE['messages']['remove_friend'],
                ];
            }
        }

        return null;
    }

    /** Установка статуса */
    public function changeStatus(?string $status = null): ?array
    {
        DB->update(
            'user',
            [
                'status' => $status,
            ],
            [
                'id' => CURRENT_USER->id(),
            ],
        );

        return [
            'response' => 'success',
            'response_text' => DataHelper::escapeOutput($status),
        ];
    }

    /** Регистрация токена для браузерных уведомлений */
    public function webpushSubscribe(
        ?string $deviceId,
        ?string $endpoint,
        ?string $p256dh,
        ?string $auth,
        ?string $contentEncoding = 'aesgcm',
    ): array {
        if ($deviceId && $endpoint && $p256dh && $auth && $contentEncoding) {
            $checkExistSubscription = DB->select(
                tableName: 'user__push_subscriptions',
                criteria: [
                    'user_id' => CURRENT_USER->id(),
                    'device_id' => $deviceId,
                ],
                oneResult: true,
            );

            if ($checkExistSubscription) {
                DB->update(
                    tableName: 'user__push_subscriptions',
                    data: [
                        'endpoint' => $endpoint,
                        'p256dh' => $p256dh,
                        'auth' => $auth,
                        'content_encoding' => $contentEncoding,
                    ],
                    criteria: [
                        'id' => $checkExistSubscription['id'],
                    ],
                );
            } else {
                DB->insert(
                    tableName: 'user__push_subscriptions',
                    data: [
                        'user_id' => CURRENT_USER->id(),
                        'device_id' => $deviceId,
                        'endpoint' => $endpoint,
                        'p256dh' => $p256dh,
                        'auth' => $auth,
                        'content_encoding' => $contentEncoding,
                    ],
                );
            }

            return [
                'response' => 'success',
            ];
        } else {
            return [
                'response' => 'error',
            ];
        }
    }

    /** Удаление токена для браузерных уведомлений */
    public function webpushUnsubscribe(
        ?string $deviceId,
    ): array {
        DB->delete(
            tableName: 'user__push_subscriptions',
            criteria: [
                'user_id' => CURRENT_USER->id(),
                'device_id' => $deviceId,
            ],
        );

        return [
            'response' => 'success',
        ];
    }

    /** Проверка возможности регистрироваться */
    public function registrationIsOpen(): bool
    {
        return true;
    }

    /** Расчет и выдача процента заполнения профиля */
    public function calculateProfileCompletion(int|string $userId): int
    {
        $profileCompletion = 0;

        if (CURRENT_USER->isLogged()) {
            $userData = $this->get($userId, null, null, true);

            if (
                (
                    ($userData->em->get() !== null && $userData->em_verified->get() === '1')
                    || $userData->facebook_visible->get() !== null
                    || $userData->vkontakte_visible->get() !== null
                    || $userData->telegram->get() !== null
                )
                && $userData->fio->get() !== null
                && $userData->phone->get() !== null
                && $userData->birth->get() !== null
                && $userData->city->get() !== null
                && (
                    $userData->photo->get() !== null && !str_contains($userData->photo->get(), 'identicon')
                )
            ) {
                $profileCompletion = 100;
            } elseif (
                ($userData->em->get() !== null && $userData->em_verified->get() === '1')
                || $userData->facebook_visible->get() !== null
                || $userData->vkontakte_visible->get() !== null
                || $userData->telegram->get() !== null
            ) {
                $profileCompletion = 50;
            }
        }

        return $profileCompletion;
    }

    /** Сортировка аватарок пользователей */
    public function sortPhotoNameLinks(array $result): array
    {
        $allusersData = [];
        $allusersDataReturn = [];

        foreach ($result as $userData) {
            $userData = $userData instanceof UserModel ? $userData : $this->arrayToModel($userData);
            $allusersData[] = [
                $this->showNameExtended(
                    $userData,
                    true,
                    false,
                    '',
                    false,
                    false,
                    true,
                ),
                $userData,
            ];
        }
        $allusersDataSort = [];

        foreach ($allusersData as $key => $row) {
            $allusersDataSort[$key] = mb_strtolower($row[0]);
        }
        array_multisort($allusersDataSort, SORT_ASC, $allusersData);

        foreach ($allusersData as $data) {
            $allusersDataReturn[] = $data[1];
        }

        return $allusersDataReturn;
    }

    /** Отсылка новому пользователю уведомления о необходимости подтвердить email и расчета заполненности профиля */
    public function postRegister(int $id): void
    {
        $LOCALE = LocaleHelper::getLocale(['global']);
        $LOCALE_PROFILE = LocaleHelper::getLocale(['profile', 'global']);

        $userData = $this->get($id);

        if ($userData->id->getAsInt()) {
            if ($userData->em->get() !== null) {
                $idToReverify = md5($userData->id->getAsInt() . $userData->created_at->getAsTimeStamp() . $userData->em->get() . $_ENV['PROJECT_HASH_WORD']);
                $text = sprintf(
                    $LOCALE_PROFILE['verify_em']['base_text'],
                    $LOCALE['sitename'],
                    ABSOLUTE_PATH . '/profile/action=verify_em&verify_id=' . $idToReverify,
                    ABSOLUTE_PATH . '/profile/action=verify_em&verify_id=' . $idToReverify,
                );
                EmailHelper::sendMail(
                    $LOCALE['sitename'],
                    $LOCALE['admin_mail'],
                    $userData->em->get(),
                    sprintf($LOCALE_PROFILE['verify_em']['name'], $LOCALE['sitename']),
                    $text,
                    true,
                );
            }

            $topSid = DB->select(
                tableName: 'user',
                criteria: [
                    ['sid', '', [OperandEnum::NOT_NULL]],
                ],
                oneResult: true,
                order: [
                    'sid DESC',
                ],
                limit: 1,
            );

            DB->update(
                tableName: 'user',
                data: [
                    'sid' => ((int) $topSid['sid'] + 1),
                ],
                criteria: [
                    'id' => $id,
                ],
            );

            CURRENT_USER->setSid((int) $topSid['sid'] + 1);

            $this->calculateProfileCompletion($userData->id->getAsInt());
        }
    }

    /** Создание и сохранения identicon'а для пользователя */
    public function createIdenticon(UserModel $userModel): string
    {
        $uploads = $_ENV['UPLOADS'];

        $name = 'none_';

        if ($userModel->gender->get() === 2) {
            $name .= 'female';
        } else {
            $name .= 'male';
        }

        $email = $userModel->em->get();
        $login = $userModel->login->get();
        $photo = $userModel->photo->get();

        if ($email !== null || $login !== null) {
            $tryName = md5(md5($email !== null ? $email : $login) . $_ENV['PROJECT_HASH_WORD']);

            if (file_exists(INNER_PATH . 'public' . $_ENV['UPLOADS_PATH'] . $uploads[2]['path'] . $tryName . '.png')) {
                $name = $tryName;
            } else {
                $identicon = new Identicon();

                $identicon->identicon('', [
                    'size' => 35,
                    'backr' => [255, 255],
                    'backg' => [255, 255],
                    'backb' => [255, 255],
                    'forer' => [1, 255],
                    'foreg' => [1, 255],
                    'foreb' => [1, 255],
                    'squares' => 4,
                    'autoadd' => 1,
                    'gravatar' => 0,
                    'grey' => 0,
                ]);

                $image = $identicon->identicon_build($tryName, 300);

                ob_start();
                imagepng($image);
                $imageData = ob_get_clean();
                imagedestroy($image);

                if ($imageData && file_put_contents(INNER_PATH . 'public' . $_ENV['UPLOADS_PATH'] . $uploads[2]['path'] . $tryName . '.png', $imageData)) {
                    $name = $tryName;

                    if (!$photo || str_contains($photo, 'identicon')) {
                        DB->update(
                            tableName: 'user',
                            data: [
                                'photo' => '{identicon.png:' . $tryName . '.png}',
                            ],
                            criteria: [
                                'id' => $userModel->id->getAsInt(),
                            ],
                        );
                    }
                }
            }
        }

        return ABSOLUTE_PATH . $_ENV['UPLOADS_PATH'] . $uploads[2]['path'] . $name . '.png';
    }

    /** Проверка наличия пользователя(-ей) на сайте */
    public function checkUserOnline(int|string|array|null $userId): int|bool
    {
        if (is_null($userId)) {
            return false;
        }

        $contactUsers = $this->getCurrentUserContactsIds();
        $usersOnlineStatuses = $this->getUsersOnlineStatuses();

        if (is_array($userId)) {
            // если это массив, то нам нужно просто количество людей онлайн
            $usersOnlineCount = 0;

            if (count($userId) > 0) {
                foreach ($userId as $uId) {
                    $usersOnlineStatuses[$uId] = false;
                }
                $result = $this->getAll([
                    ['id', $userId],
                    ['updated_at', time() - 180, [OperandEnum::MORE_OR_EQUAL]],
                ]);

                foreach ($result as $userData) {
                    if (in_array($userData->id->getAsInt(), $contactUsers)) {
                        $usersOnlineStatuses[$userData->id->getAsInt()] = true;
                        ++$usersOnlineCount;
                    }
                }
            }
            $this->usersOnlineStatuses = $usersOnlineStatuses;

            return $usersOnlineCount;
        } elseif ((int) $userId > 0) {
            // если единичная запись, то нам нужен статус конкретного человека
            if ($userId === CURRENT_USER->id()) {
                return true;
            } elseif ($usersOnlineStatuses[$userId] ?? false) {
                $this->usersOnlineStatuses = $usersOnlineStatuses;

                return $usersOnlineStatuses[$userId];
            } else {
                if (is_array($contactUsers) && in_array($userId, $contactUsers)) {
                    $updatedData = DB->select(
                        'user',
                        ['id' => $userId],
                        true,
                        null,
                        null,
                        null,
                        false,
                        [
                            'updated_at',
                        ],
                    );

                    if (time() - $updatedData['updated_at'] <= 180) {
                        $usersOnlineStatuses[$userId] = true;

                        return true;
                    }
                }
                $usersOnlineStatuses[$userId] = false;
            }
        }

        $this->usersOnlineStatuses = $usersOnlineStatuses;

        return false;
    }

    /** Создание фото из профиля пользователя (если нет, то identicon) со ссылкой */
    public function photoLink(UserModel $userData, int $size = 50, bool $small = false): string
    {
        return '<img src="' . $this->photoUrl($userData) . '" width="' . $size . '" title="' .
            $this->showNameExtended($userData, true) . '"' . ($small ? ' class="small"' : '') . '>';
    }

    /** Расширенная функция создания фото из профиля пользователя (если нет, то identicon) со ссылкой + имя пользователя */
    public function photoNameLink(
        ?UserModel $userModel,
        string $size = '',
        bool $showName = true,
        string $class = '',
        string|bool $fixedtitle = '',
        bool $addIdInAnyCase = false,
        bool $noDynamicContent = false,
        bool $link = true,
    ): string {
        if (is_null($userModel)) {
            return '<div class="photoName"><a><div class="photoName_photo_wrapper"><div class="photoName_photo" style="background-image: url(\'' . ABSOLUTE_PATH . '/uploads/users/none_male.png\')"></div></div></a></div>';
        }

        $result = '<div user_id="' . $userModel->id->getAsInt() . '" ' .
            ($size !== '' ? 'style="width: ' . $size . '" ' : '') .
            ' class="photoName' .
            ($class !== '' ? ' ' . $class : '') .
            ($this->checkUserOnline($userModel->id->getAsInt()) ? ' online_marker' : '') .
            '">' .
            (
                $link ?
                '<a href="' . ABSOLUTE_PATH . '/people/' . (!is_null($userModel->sid->get()) ? $userModel->sid->get() . '/' : '') . '" ' . ($noDynamicContent ? 'class="no_dynamic_content"' : '') . '>' :
                ''
            ) . '<div class="photoName_photo_wrapper"><div class="photoName_photo' . ($class !== '' ? ' ' . $class : '') . '" style="' . DesignHelper::getCssBackgroundImage($this->photoUrl($userModel)) . '" ' .
            (
                $fixedtitle === false ? '' : ($fixedtitle !== '' ?
                    'title="' . $fixedtitle . '"' :
                    'title="' . $this->showNameExtended(
                        $userModel,
                        true,
                        false,
                        '',
                        false,
                        $addIdInAnyCase,
                        true,
                    )) . '"'
            ) . '></div></div>' . ($link ? '</a>' : '');

        if ($showName) {
            $result .= '<div class="photoNameNameWrapper"><div class="photoName_name">' . $this->showNameExtended(
                $userModel,
                true,
                true,
                '',
                false,
                $addIdInAnyCase,
                true,
            ) . '</div></div>';
        }

        $result .= '</div>';

        return $result;
    }

    /** Расширенная функция создания фото из профиля пользователя (если нет, то identicon) со ссылкой + имя пользователя.
     *
     * @param UserModel[] $userData
     */
    public function photoNameLinkMulti(array $userData, string|bool $fixedtitle = '', bool $link = true): string
    {
        $i = 0;
        $userCount = count($userData);
        $result = '';

        foreach ($userData as $contactData) {
            if ($i < 4) {
                $result .= $this->photoNameLink(
                    $contactData,
                    $userCount === 1 ? '' : '50%',
                    false,
                    '',
                    $fixedtitle,
                    false,
                    false,
                    $link,
                );
            }
            ++$i;
        }

        return $result;
    }

    /** Получение ссылки на фото пользователя */
    public function photoUrl(UserModel $userModel, bool $thumbnail = false): string
    {
        return FileHelper::getImagePath($userModel->photo->get(), FileHelper::getUploadNumByType('user'), $thumbnail) ??
            $this->createIdenticon($userModel);
    }

    /** Вычистка url'ов социальных сетей */
    public function socialEncode(string $path): string
    {
        return str_replace([
            'http://',
            'https://',
            'www.',
            '/posts',
            'vkontakte.ru/',
            'vk.com/',
            '.livejournal.com',
            'twitter.com/#!/',
            'facebook.com',
            'profile.php?id=',
            'plus.google.com',
            'fotki.yandex.ru/users/',
            'linkedin.com/in/',
            't.me',
            '/',
        ], '', $path);
    }

    /** Выдача url'ов социальных сетей с иконками */
    public function socialShow(string $path, string $type = '', bool $pic = false): string
    {
        if ($type === '') {
            if (preg_match('#vkontakte.ru#', $path)) {
                $type = 'vkontakte';
            } elseif (preg_match('#vk.com#', $path)) {
                $type = 'vkontakte';
            } elseif (preg_match('#fotki.yandex.ru#', $path)) {
                $type = 'yandex';
            } elseif (preg_match('#twitter.com#', $path)) {
                $type = 'twitter';
            } elseif (preg_match('#livejournal.com#', $path)) {
                $type = 'livejournal';
            } elseif (preg_match('#facebook.com#', $path)) {
                $type = 'facebook';
            } elseif (preg_match('#plus.google.com#', $path)) {
                $type = 'googleplus';
            } elseif (preg_match('#linkedin.com#', $path)) {
                $type = 'linkedin';
            } elseif (preg_match('#t.me#', $path)) {
                $type = 'telegram';
            }
        }

        $path = $this->socialEncode($path);

        $rpath = '';

        if ($path !== '') {
            if ($type === 'vkontakte') {
                $path = preg_replace('#^m\.#', '', $path);
                $rpath .= '<a href="https://vk.com/' . $path . '" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . '/files/networks/vk_icon.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } elseif ($type === 'twitter') {
                $rpath .= '<a href="http://www.twitter.com/#!/' . $path . '" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . '/files/networks/twitter.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } elseif ($type === 'livejournal') {
                $rpath .= '<a href="http://' . $path . '.livejournal.com" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . '/files/networks/livejournal.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } elseif ($type === 'facebook') {
                $rpath .= '<a href="http://www.facebook.com/' . $path . '" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . '/files/networks/fb_icon.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } elseif ($type === 'googleplus') {
                $rpath .= '<a href="https://plus.google.com/' . $path . '/posts" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . '/files/networks/google.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } elseif ($type === 'yandex') {
                $rpath .= '<a href="http://fotki.yandex.ru/users/' . $path . '/" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . '/files/networks/yandex.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } elseif ($type === 'telegram') {
                $path = preg_replace('#^@#', '', $path);
                $rpath .= '<a href="http://t.me/' . $path . '" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . '/files/networks/telegram.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } elseif ($type === 'linkedin') {
                $rpath .= '<a href="https://www.linkedin.com/in/' . $path . '/" target="_blank">';

                if ($pic) {
                    $rpath .= '<img src="' . ABSOLUTE_PATH . '/files/networks/linkedin.svg"> ' . $path;
                } else {
                    $rpath .= $path;
                }
                $rpath .= '</a>';
            } else {
                $rpath .= $path;
            }
        }

        return $rpath;
    }

    /** Формирование ссылки на подписку */
    /*public function showSubscribe(string $objTypeTo, int $objIdTo): string
    {
        $LOCALE = Locale::getLocale(['global', 'subscription']);

        if ($_ENV['SUBSCRIBE_UNSUBSCRIBE']) {
            return '<a obj_type="'.$objTypeTo.'" obj_id="'.$objIdTo.'" '.(RightsHelper::checkRights(
                    '{subscribe}',
                    $objTypeTo,
                    $objIdTo
                ) ? 'class="unsubscribe">'.$LOCALE['unsubscribe'] : 'class="subscribe">'.$LOCALE['subscribe']).'</a>';
        } else {
            return '';
        }
    }*/

    /** Удаление подписки */
    public function deleteSubscribe(string $objTypeTo, int $objIdTo): bool
    {
        return RightsHelper::deleteRights('{subscribe}', $objTypeTo, $objIdTo);
    }

    /** Добавление подписки */
    public function addSubscribe(string $objTypeTo, int $objIdTo): bool
    {
        return RightsHelper::addRights('{subscribe}', $objTypeTo, $objIdTo);
    }

    /** Получение id диалогов (conversation) у данного пользователя с группой пользователей */
    public function getGroupConversations(): array
    {
        if ($this->initializedGroupConversation) {
            return $this->groupConversations;
        }

        $groupConversations = [];
        $allConversationsIds = $this->getAllConversationsIds();

        if ($allConversationsIds) {
            $result = DB->query(
                'SELECT min(r.obj_id_to) as r_obj_id_to, c.name FROM relation r LEFT JOIN conversation c ON c.id=r.obj_id_to WHERE r.obj_type_to="{conversation}" AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_id_to IN (:obj_id_to) GROUP BY r.obj_id_to, c.name HAVING COUNT(r.obj_id_from)>2',
                [
                    ['obj_id_to', $allConversationsIds],
                ],
            );

            foreach ($result as $conversationData) {
                $conversationUsers = RightsHelper::findByRights(
                    '{member}',
                    '{conversation}',
                    $conversationData['r_obj_id_to'],
                    '{user}',
                    false,
                );
                $conversationUsers = array_diff($conversationUsers, [CURRENT_USER->id()]);
                $lastMessage = DB->select(
                    tableName: 'conversation_message',
                    criteria: [
                        'conversation_id' => $conversationData['r_obj_id_to'],
                    ],
                    oneResult: true,
                    order: [
                        'id DESC',
                    ],
                    limit: 1,
                );

                if ($lastMessage && is_int($lastMessage['created_at']) && $lastMessage['created_at'] > time() - 24 * 3600 * 7) {
                    $groupConversations[$conversationData['r_obj_id_to']] = [
                        'name' => DataHelper::escapeOutput(
                            $conversationData['name'],
                        ),
                        'user_id' => implode(',', $conversationUsers),
                        'users' => $conversationUsers,
                        'last_message_date' => $lastMessage['created_at'],
                    ];
                }
            }

            /* выбираем отдельно все разговоры, которые являются наследующими какими-то объектам, даже если в них всего один-два пользователя */
            $result = DB->query(
                'SELECT r.obj_id_to, c2.name FROM relation r LEFT JOIN relation r2 ON r2.obj_id_from=r.obj_id_to LEFT JOIN conversation c2 ON c2.id=r.obj_id_to WHERE r.obj_type_to="{conversation}" AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_id_from=:obj_id_from AND r2.obj_type_from="{conversation}" AND r2.type="{child}"',
                [
                    ['obj_id_from', CURRENT_USER->id()],
                ],
            );

            foreach ($result as $conversationData) {
                if (!isset($groupConversations[$conversationData['obj_id_to']])) {
                    $conversationUsers = RightsHelper::findByRights(
                        '{member}',
                        '{conversation}',
                        $conversationData['obj_id_to'],
                        '{user}',
                        false,
                    );
                    $conversationUsers = array_diff($conversationUsers, [CURRENT_USER->id()]);
                    $lastMessage = DB->select(
                        tableName: 'conversation_message',
                        criteria: [
                            'conversation_id' => $conversationData['obj_id_to'],
                        ],
                        oneResult: true,
                        order: [
                            'id DESC',
                        ],
                        limit: 1,
                        fieldsSet: [
                            'created_at',
                        ],
                    );

                    if (is_int($lastMessage['created_at']) && $lastMessage['created_at'] > time() - 24 * 3600 * 7) {
                        $groupConversations[$conversationData['obj_id_to']] = [
                            'name' => DataHelper::escapeOutput($conversationData['name']),
                            'user_id' => implode(',', $conversationUsers),
                            'users' => $conversationUsers,
                            'last_message_date' => $lastMessage['created_at'],
                        ];
                    }
                }
            }
        }

        $this->groupConversations = $groupConversations;
        $this->initializedGroupConversation = true;

        return $groupConversations;
    }

    /** Получение id диалогов (conversation) у данного пользователя с конкретным пользователем */
    public function getContactsConversations(): array
    {
        $contactUsers = $this->getCurrentUserContactsIds();

        if ($this->initializedContactConversations) {
            return $this->contactConversations;
        }
        $contactConversations = [];
        $allConversationsIds = $this->getAllConversationsIds();

        if ($allConversationsIds) {
            $result = DB->query(
                'SELECT SQL_NO_CACHE min(r.obj_id_to) as obj_id_to, min(r.obj_id_from) as obj_id_from FROM relation r LEFT JOIN conversation c ON c.id=r.obj_id_to WHERE r.obj_type_to="{conversation}" AND r.obj_id_to IN (:obj_id_to) AND r.type="{member}" AND r.obj_type_from="{user}" AND r.obj_id_from!=:obj_id_from AND c.obj_id IS NULL AND c.name IS NULL GROUP BY r.obj_id_to HAVING COUNT(r.obj_id_from)=1 ORDER BY r.obj_id_to',
                [
                    ['obj_id_to', $allConversationsIds],
                    ['obj_id_from', CURRENT_USER->id()],
                ],
            );

            foreach ($result as $conversationData) {
                $contactConversations[$conversationData['obj_id_from']] = $conversationData['obj_id_to'];
            }
        }

        if ($contactUsers) {
            foreach ($contactUsers as $contactUser) {
                if (!($contactConversations[$contactUser] ?? false)) {
                    $contactConversations[$contactUser] = 'new';
                }
            }
        }

        $this->initializedContactConversations = true;
        $this->contactConversations = $contactConversations;

        return $contactConversations;
    }

    /** Проверка наличия контактов на сайте */
    public function getContactsOnline(): int
    {
        $contactUsers = $this->getCurrentUserContactsIds();
        $contactsOnline = 0;

        if ($contactUsers) {
            $contactsOnline = $this->checkUserOnline($contactUsers);
        }

        return $contactsOnline;
    }

    /** Расширенная проверка наличия контактов на сайте */
    public function getContactsOnlineExtended(bool $showList, bool $getOpenedDialogs): array
    {
        $contactsOnline = $this->getContactsOnline();
        $usersOnlineStatuses = $this->getUsersOnlineStatuses();

        $onlineUsersArray = [
            CURRENT_USER->id() => true,
        ];

        if (count($usersOnlineStatuses) > 0) {
            foreach ($usersOnlineStatuses as $key => $value) {
                if ($value) {
                    $onlineUsersArray[$key] = true;
                }
            }
        }

        if ($showList) {
            $contactsOffline = $this->getContactsOffline();
            $contactConversations = $this->getContactsConversations();
            $groupConversations = $this->getGroupConversations();

            $contactsList['online']['count'] = [
                'count' => $contactsOnline,
                'ending' => LocaleHelper::declineMale($contactsOnline),
            ];
            $userDatas = [];
            $userDatasSort = [];

            foreach ($usersOnlineStatuses as $key => $value) {
                if ($value) {
                    $userInfo = $this->get($key);
                    $conversationDataName = false;

                    if (($contactConversations[$key] ?? '') !== 'new') {
                        $conversationData = DB->findObjectById($contactConversations[$key], 'conversation');
                        $conversationDataName = DataHelper::escapeOutput($conversationData['name']);
                    }

                    $userDatas[] = [
                        'dialog_name' => (
                            $conversationDataName ?: $this->showNameExtended(
                                $userInfo,
                                true,
                                false,
                                '',
                                false,
                                false,
                                true,
                            )
                        ),
                        'dialog_avatar' => FileHelper::getImagePath($userInfo->photo->get(), FileHelper::getUploadNumByType('user')) ??
                            $this->createIdenticon($userInfo),
                        'obj_id' => $contactConversations[$key],
                        'user_id' => $userInfo->id->getAsInt(),
                        'photoNameLink' => $this->photoNameLink($userInfo, '', false),
                    ];
                }
            }

            foreach ($userDatas as $key => $row) {
                $userDatasSort[$key] = $row['dialog_name'];
            }
            array_multisort($userDatasSort, SORT_ASC, $userDatas);

            foreach ($userDatas as $userDataFull) {
                if (REQUEST_TYPE->isApiRequest()) {
                    unset($userDataFull['photoNameLink']);
                }
                $contactsList['online'][] = $userDataFull;
            }

            $contactsList['group']['count'] = [
                'count' => count($groupConversations),
                'ending' => LocaleHelper::declineMaleAdjective(count($groupConversations)),
                'ending2' => LocaleHelper::declineMale(count($groupConversations)),
            ];

            if (count($groupConversations) > 0) {
                $userDatas = [];
                $userDatasSort = [];

                foreach ($groupConversations as $key => $array) {
                    $conversationName = $array['name'];

                    $userModels = [];

                    foreach ($array['users'] as $user) {
                        $userModels[] = $this->get($user);
                    }

                    if ($conversationName === '') {
                        foreach ($userModels as $userModel) {
                            $conversationName .= $this->showNameExtended($userModel, true) . ', ';
                        }
                        $conversationName = mb_substr(
                            $conversationName,
                            0,
                            mb_strlen($conversationName) - 2,
                            'utf8',
                        );
                    }

                    $userDatas[] = [
                        'dialog_name' => $conversationName,
                        'dialog_avatar' => ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'group.png',
                        'obj_id' => (string) $key,
                        'user_id' => $array['user_id'],
                        'photoNameLink' => $this->photoNameLinkMulti($userModels),
                    ];
                }

                foreach ($userDatas as $key => $row) {
                    $userDatasSort[$key] = $row['dialog_name'];
                }
                array_multisort($userDatasSort, SORT_ASC, $userDatas);

                foreach ($userDatas as $userDataFull) {
                    if (REQUEST_TYPE->isApiRequest()) {
                        unset($userDataFull['photoNameLink']);
                    }
                    $contactsList['group'][] = $userDataFull;
                }
            }

            $contactsList['offline']['count'] = [
                'count' => $contactsOffline,
                'ending' => LocaleHelper::declineMale($contactsOffline),
            ];
            $userDatas = [];
            $userDatasSort = [];

            foreach ($usersOnlineStatuses as $key => $value) {
                if (!$value) {
                    $userInfo = $this->get($key);
                    $conversationDataName = false;

                    if (!in_array($contactConversations[$key] ?? '', ['new', ''])) {
                        $conversationData = DB->findObjectById($contactConversations[$key], 'conversation');
                        $conversationDataName = DataHelper::escapeOutput($conversationData['name']);
                    }

                    $userDatas[] = [
                        'dialog_name' => (
                            $conversationDataName ?: $this->showNameExtended(
                                $userInfo,
                                true,
                                false,
                                '',
                                false,
                                false,
                                true,
                            )
                        ),
                        'dialog_avatar' => FileHelper::getImagePath($userInfo->photo->get(), FileHelper::getUploadNumByType('user')) ??
                            $this->createIdenticon($userInfo),
                        'obj_id' => $contactConversations[$key],
                        'user_id' => $userInfo->id->getAsInt(),
                        'photoNameLink' => $this->photoNameLink($userInfo, '', false),
                    ];
                }
            }

            foreach ($userDatas as $key => $row) {
                $userDatasSort[$key] = $row['dialog_name'];
            }
            array_multisort($userDatasSort, SORT_ASC, $userDatas);

            foreach ($userDatas as $userDataFull) {
                if (REQUEST_TYPE->isApiRequest()) {
                    unset($userDataFull['photoNameLink']);
                }
                $contactsList['offline'][] = $userDataFull;
            }

            $returnArr = [
                'response' => 'success',
                'response_text' => $contactsOnline,
                'response_data' => $contactsList,
                'online_array' => $onlineUsersArray,
            ];
        } elseif ($getOpenedDialogs) {
            /** @var ConversationService */
            $conversationService = CMSVCHelper::getService('conversation');

            $returnArr = [
                'response' => 'success',
                'response_text' => $contactsOnline,
                'response_data' => $conversationService->getOpenedDialogsData(),
                'online_array' => $onlineUsersArray,
            ];
        } else {
            $returnArr = [
                'response' => 'success',
                'response_text' => $contactsOnline,
                'online_array' => $onlineUsersArray,
            ];
        }

        return $returnArr;
    }

    /** Проверка отсутствия контактов на сайте */
    public function getContactsOffline(): int
    {
        $contactUsers = $this->getCurrentUserContactsIds();
        $usersOnlineStatuses = $this->getUsersOnlineStatuses();
        $contactsOffline = 0;

        if ($contactUsers) {
            foreach ($contactUsers as $contact) {
                if (false === ($usersOnlineStatuses[$contact] ?? null) || !$this->checkUserOnline($contact)) {
                    ++$contactsOffline;
                }
            }
        }

        return $contactsOffline;
    }

    /** Проверка наличия непрочтенных сообщений */
    public function getNewEvents(int|string|null $objId, ?string $objType, bool $getOpenedDialogs, bool $showList): array
    {
        /** @var ApplicationService */
        $applicationService = CMSVCHelper::getService('application');

        $LOCALE = LocaleHelper::getLocale(['conversation', 'global']);

        /** Проверяем, когда была последняя проверка: если менее 30 секунд назад, то блокируем выдачу звукового оповещения, т.к. это, скорее всего, второе+
         * окошко нас опрашивает */
        $blockSound = false;

        if (!$getOpenedDialogs && !$showList) { // не делаем эту проверку, если идет запрос на проверку открытых диалогов (стартовый запрос на странице) или на открытие списка диалогов
            $meData = $this->get(CURRENT_USER->id(), null, null, true);
            $lastGetNewEvents = (int) $meData->last_get_new_events->get();

            if (time() - $lastGetNewEvents < 15 && $lastGetNewEvents > 0) {
                $blockSound = true;
            } else {
                DB->update('user', ['last_get_new_events' => time()], ['id' => CURRENT_USER->id()]);
            }
        }

        $newEvents = [
            'conversation' => [],
            'project_conversation' => [],
            'community_conversation' => [],
            'project_wall' => [],
            'community_wall' => [],
            'event_comment' => [],
            'task_comment' => [],
            'application_comments' => [],
            'ingame_application_comments' => [],
        ];
        $newEventsCounters = [
            'conversation' => 0,
            'project_conversation' => 0,
            'community_conversation' => 0,
            'project_wall' => 0,
            'community_wall' => 0,
            'event_comment' => 0,
            'task_comment' => 0,
            'task_comment_by_types' => [
                'mine' => 0,
                'membered' => 0,
                'delayed' => 0,
                'closed' => 0,
            ],
        ];

        /** Новое в сообщениях */
        $result = DB->query(
            'SELECT
			c.id as obj_id,
			COUNT(DISTINCT cms.id) as new_messages_count,
			MAX(cms.message_id) as last_message_id
		FROM
			relation r LEFT JOIN
			conversation c ON
				c.id=r.obj_id_to AND
				c.obj_id IS NULL LEFT JOIN
			conversation_message cm ON
				cm.conversation_id=c.id LEFT JOIN
			conversation_message_status cms ON
				cms.message_id=cm.id AND
				(
					cms.message_id!=0 AND
					cms.message_id IS NOT NULL
				) AND
				cms.user_id=:user_id AND
				(
					cms.message_deleted!="1" OR
					cms.message_deleted IS NULL
				) AND (
					cms.message_read="0" OR
					cms.message_read IS NULL
				)
			WHERE
				c.updated_at > :updated_at AND
				r.obj_id_from=:obj_id_from AND
				r.type="{member}" AND
				r.obj_type_from="{user}" AND
				r.obj_type_to="{conversation}"
			GROUP BY
				c.id',
            [
                ['user_id', CURRENT_USER->id()],
                ['updated_at', time() - 24 * 3600 * 90],
                ['obj_id_from', CURRENT_USER->id()],
            ],
        );

        foreach ($result as $data) {
            if ($data['new_messages_count'] > 0 && $data['obj_id'] > 0) {
                $users = RightsHelper::findByRights('{member}', '{conversation}', $data['obj_id'], '{user}', false);
                $users = array_diff($users, [CURRENT_USER->id()]);

                $lastMessageData = DB->select('conversation_message', ['id' => $data['last_message_id']], true);

                $newEvents['conversation'][$data['obj_id']] = [
                    'count' => $data['new_messages_count'],
                    'user_id' => implode(',', $users),
                    'content_preview' => (!REQUEST_TYPE->isApiRequest() ? ($lastMessageData['creator_id'] === CURRENT_USER->id() ? '<span class="gray">' . $LOCALE['you'] . ':</span> ' : (
                        count($users) > 1 ? '<span class="gray">' . $this->showNameExtended(
                            $this->get($lastMessageData['creator_id']),
                            true,
                            false,
                            '',
                            true,
                        ) . ':</span> ' : ''
                    )) .
                        mb_substr(
                            strip_tags(str_replace(['<br>', '<br />', '<br/>'], ' ', MessageHelper::viewActions($lastMessageData['id']))),
                            0,
                            40,
                        ) : ''),
                ];

                $newEventsCounters['conversation'] += $data['new_messages_count'];
            }
        }

        /** Проверяем, не было ли за последние 30 секунд прочтения последнего сообщения кем-то */
        $otherUsersReadQuery = DB->query(
            'SELECT
			c.id as obj_id
		FROM
			relation r LEFT JOIN conversation c ON
				c.id=r.obj_id_to
			LEFT JOIN conversation_message cm ON
				cm.conversation_id = c.id
			LEFT JOIN conversation_message_status cms ON
				cms.message_id=cm.id AND (
					cms.message_id!=0 AND
					cms.message_id IS NOT NULL
				) AND
				cms.user_id != :user_id AND
				(
					cms.message_deleted!="1" OR
					cms.message_deleted IS NULL
				) AND
				cms.message_read="1"
			WHERE
				r.obj_id_from=:obj_id_from AND
				r.type="{member}" AND
				r.obj_type_from="{user}" AND
				r.obj_type_to="{conversation}" AND
				c.obj_type IS NULL AND
				cms.created_at > :created_at AND
				cms.created_at IS NOT NULL
			GROUP BY
				c.id,
				cm.id
			ORDER BY
				cm.id DESC',
            [
                ['user_id', CURRENT_USER->id()],
                ['obj_id_from', CURRENT_USER->id()],
                ['created_at', time() - 60],
            ],
        );

        foreach ($otherUsersReadQuery as $otherUsersReadData) {
            if (!isset($newEvents['conversation'][$otherUsersReadData['obj_id']])) {
                $newEvents['conversation'][$otherUsersReadData['obj_id']] = [
                    'count' => '-1',
                ];
            }
        }

        /** Новое в разных объектах */
        $result = DB->query(
            "SELECT
				COUNT(cm.id) as new_messages_count,
				c.obj_type AS obj_type,
				c.obj_id AS obj_id
			FROM
				relation r LEFT JOIN
				conversation c ON c.obj_id=r.obj_id_to LEFT JOIN
				conversation_message cm ON cm.conversation_id=c.id LEFT JOIN
				conversation_message_status cms ON cms.message_id=cm.id AND
					cms.user_id=:user_id
			WHERE
				c.updated_at > :updated_at AND
				r.obj_type_from='{user}' AND
				r.type='{member}' AND
				r.obj_id_from=:obj_id_from AND
				r.type NOT IN (:types) AND
				(
					(
						r.obj_type_to='{project}' AND
						c.obj_type='{project_conversation}' AND
						cm.use_group_name='1'
					)
					OR
					(
						r.obj_type_to='{community}' AND
						c.obj_type='{community_conversation}' AND
						cm.use_group_name='1'
					)
					OR
					(
						r.obj_type_to='{project}' AND
						c.obj_type='{project_wall}' AND
						cm.use_group_name='1'
					)
					OR
					(
						r.obj_type_to='{community}' AND
						c.obj_type='{community_wall}' AND
						cm.use_group_name='1'
					)
					OR
					(
						r.obj_type_to='{event}' AND
						c.obj_type='{event_comment}'
					)
					OR
					(
						r.obj_type_to='{task}' AND
						c.obj_type='{task_comment}'
					)
				) AND
				c.id IS NOT NULL AND
				cm.id IS NOT NULL AND
				(cms.message_deleted='0' OR cms.message_deleted IS NULL) AND
				(cms.message_read!='1' OR cms.message_read IS NULL)
			GROUP BY
				c.obj_type,
				c.obj_id",
            [
                ['user_id', CURRENT_USER->id()],
                ['updated_at', time() - 24 * 3600 * 90],
                ['obj_id_from', CURRENT_USER->id()],
                ['types', RightsHelper::getBannedTypes()],
            ],
        );

        foreach ($result as $data) {
            $newEventsCounters[DataHelper::clearBraces($data['obj_type'])] += $data['new_messages_count'];
        }

        if (!is_null($objType) && $objType !== '') {
            $taskService = new TaskService();

            if ($objId > 0 && $objId !== 'all') {
                if (!RightsHelper::checkAnyRights(DataHelper::addBraces($objType), $objId)) {
                    $objId = 'all';
                }
            } else {
                $objId = 'all';
            }

            $result = DB->query(
                'SELECT DISTINCT te.* FROM task_and_event te' . ($objId !== 'all' ? " LEFT JOIN relation r ON r.obj_id_from=te.id AND r.obj_type_to=:obj_type_to AND r.obj_type_from='{task}' AND r.type='{child}'" : '') . " LEFT JOIN relation r2 ON r2.obj_id_to=te.id AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from WHERE r2.type='{responsible}'" . ($objId !== 'all' ? ' AND r.obj_id_to=:obj_id_to' : '') . " AND te.status NOT IN ('{closed}', '{rejected}', '{delayed}')",
                [
                    ['obj_type_to', DataHelper::addBraces($objType)],
                    ['obj_id_from', CURRENT_USER->id()],
                    ['obj_id_to', $objId],
                ],
            );
            $newEventsCounters['task_comment_by_types']['mine'] = $taskService->conversationTaskUnreadCount($result);

            $result = DB->query(
                'SELECT te.* FROM task_and_event te' . ($objId !== 'all' ? " LEFT JOIN relation r ON r.obj_id_from=te.id AND r.obj_type_to=:obj_type_to AND r.obj_type_from='{task}' AND r.type='{child}'" : '') . " LEFT JOIN relation r2 ON r2.obj_id_to=te.id AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.type='{member}' LEFT JOIN relation r3 ON r3.obj_id_to=te.id AND r3.obj_type_from='{user}' AND r3.obj_type_to='{task}' AND r3.obj_id_from=:obj_id_from_1 AND r3.type='{responsible}' WHERE r2.obj_id_from=:obj_id_from_2" . ($objId !== 'all' ? ' AND r.obj_id_to=:obj_id_to' : '') . " AND r3.obj_id_to IS NULL AND te.status NOT IN ('{closed}', '{rejected}', '{delayed}') GROUP BY te.id",
                [
                    ['obj_type_to', DataHelper::addBraces($objType)],
                    ['obj_id_from_1', CURRENT_USER->id()],
                    ['obj_id_from_2', CURRENT_USER->id()],
                    ['obj_id_to', $objId],
                ],
            );
            $newEventsCounters['task_comment_by_types']['membered'] = $taskService->conversationTaskUnreadCount($result);

            $result = DB->query(
                'SELECT DISTINCT te.* FROM task_and_event te' . ($objId !== 'all' ? " LEFT JOIN relation r ON r.obj_id_from=te.id AND r.obj_type_to=:obj_type_to AND r.obj_type_from='{task}' AND r.type='{child}'" : '') . " LEFT JOIN relation r2 ON r2.obj_id_to=te.id AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from AND r2.type NOT IN (:types) WHERE r2.obj_id_to IS NOT NULL" . ($objId !== 'all' ? ' AND r.obj_id_to=:obj_id_to' : '') . " AND te.status='{delayed}'",
                [
                    ['obj_type_to', DataHelper::addBraces($objType)],
                    ['obj_id_from', CURRENT_USER->id()],
                    ['types', RightsHelper::getBannedTypes()],
                    ['obj_id_to', $objId],
                ],
            );
            $newEventsCounters['task_comment_by_types']['delayed'] = $taskService->conversationTaskUnreadCount($result);

            $result = DB->query(
                'SELECT DISTINCT te.* FROM task_and_event te' . ($objId !== 'all' ? " LEFT JOIN relation r ON r.obj_id_from=te.id AND r.obj_type_to=:obj_type_to AND r.obj_type_from='{task}' AND r.type='{child}'" : '') . " LEFT JOIN relation r2 ON r2.obj_id_to=te.id AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from AND r2.type NOT IN (:types) WHERE r2.obj_id_to IS NOT NULL" . ($objId !== 'all' ? ' AND r.obj_id_to=:obj_id_to' : '') . " AND (te.status='{closed}' OR te.status='{rejected}')",
                [
                    ['obj_type_to', DataHelper::addBraces($objType)],
                    ['obj_id_from', CURRENT_USER->id()],
                    ['types', RightsHelper::getBannedTypes()],
                    ['obj_id_to', $objId],
                ],
            );
            $newEventsCounters['task_comment_by_types']['closed'] = $taskService->conversationTaskUnreadCount($result);
        }

        /** Оповещения о новых комментариях в заявках за последние 10 минут */
        $cookieProjectId = (int) CookieHelper::getCookie('project_id');

        if ($cookieProjectId > 0) {
            $noreplyApplicationsData = DB->query(
                "SELECT pa.id as application_id, pa.sorter, cm.id AS comment_id, cm.parent, cm.conversation_id, cm.content, u.* FROM project_application AS pa LEFT JOIN user AS u ON u.id=pa.creator_id LEFT JOIN conversation_message AS cm ON cm.id = (SELECT cm2.id FROM conversation AS c LEFT JOIN conversation_message AS cm2 ON cm2.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm2.id AND cms.user_id=:user_id WHERE c.obj_id=pa.id AND c.obj_type='{project_application_conversation}' AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{to_player}') AND cm2.message_action!='{fee_payment}' AND (cms.message_deleted='0' OR cms.message_deleted IS NULL) AND (cms.message_read!='1' OR cms.message_read IS NULL) ORDER BY cm2.id DESC LIMIT 1) WHERE pa.project_id=:project_id AND cm.creator_id=pa.creator_id AND pa.deleted_by_gamemaster='0' AND pa.deleted_by_player='0' AND pa.status!=4 AND pa.responsible_gamemaster_id=:responsible_gamemaster_id AND cm.created_at > :created_at",
                [
                    ['user_id', CURRENT_USER->id()],
                    ['project_id', $cookieProjectId],
                    ['responsible_gamemaster_id', CURRENT_USER->id()],
                    ['created_at', time() - 600],
                ],
            );

            foreach ($noreplyApplicationsData as $noreplyApplicationData) {
                $parentObjectType = 'project_application';
                $parentObjData = $applicationService->get($noreplyApplicationData['application_id']);
                $commentHtml = '';

                if ($parentObjData) {
                    $parentValue = $noreplyApplicationData['parent'];

                    if ($parentValue > 0) {
                        $commentData = DB->query(
                            'SELECT cm.*, cms.message_read as cms_read FROM conversation_message cm LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND (cms.user_id=:user_id OR cms.user_id IS NULL) WHERE cm.id=:id',
                            [
                                ['user_id', CURRENT_USER->id()],
                                ['id', $noreplyApplicationData['comment_id']],
                            ],
                            true,
                        );
                        $uploadNum = FileHelper::getUploadNumByType($parentObjectType);
                        $groupName = $parentObjData->sorter->get();
                        $commentHtml .= MessageHelper::conversationTreeComment(
                            $commentData,
                            1,
                            $groupName,
                            $uploadNum,
                            $parentObjectType,
                            $parentObjData,
                            'application',
                            null,
                        );
                    } else {
                        $conversationData = DB->query(
                            'SELECT c.id AS c_id, c.name AS c_name, c.sub_obj_type as c_sub_obj_type FROM conversation c WHERE c.id=:id',
                            [
                                ['id', $noreplyApplicationData['conversation_id']],
                            ],
                            true,
                        );
                        $LOCALE_APPLICATION = LocaleHelper::getLocale(['application', 'global']);
                        $commentHtml .= MessageHelper::conversationTree(
                            $conversationData['c_id'],
                            0,
                            1,
                            $parentObjectType,
                            $parentObjData,
                            DataHelper::clearBraces($conversationData['c_sub_obj_type']),
                            $LOCALE_APPLICATION['titles_conversation_sub_obj_types'][DataHelper::clearBraces(
                                $conversationData['c_sub_obj_type'],
                            )],
                        );
                    }
                }

                $userModel = $this->arrayToModel($noreplyApplicationData);
                $newEvents['application_comments'][] = [
                    'comment_id' => $noreplyApplicationData['comment_id'],
                    'application_id' => $noreplyApplicationData['application_id'],
                    'application_sorter' => $this->showNameExtended(
                        $userModel,
                        true,
                        false,
                        '',
                        false,
                        true,
                        true,
                    ) . ' (' . DataHelper::escapeOutput($noreplyApplicationData['sorter']) . ')',
                    'comment_text' => DataHelper::escapeOutput($noreplyApplicationData['content']),
                    'html' => $commentHtml,
                    'parent' => $noreplyApplicationData['parent'],
                ];
            }
        }

        /* оповещения о новых комментариях в моей заявке, запущенной в модуле игрока за последние 10 минут */
        if (CookieHelper::getCookie('ingame_application_id')) {
            $noreplyApplicationsData = DB->query(
                "SELECT pa.id as application_id, pa.sorter, cm.id AS comment_id, cm.parent, cm.conversation_id, cm.content, u.* FROM project_application AS pa LEFT JOIN conversation_message AS cm ON cm.id = (SELECT cm2.id FROM conversation AS c LEFT JOIN conversation_message AS cm2 ON cm2.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm2.id AND cms.user_id=:user_id WHERE c.obj_id=pa.id AND c.obj_type='{project_application_conversation}' AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{to_player}') AND cm2.message_action!='{fee_payment}' AND (cms.message_deleted='0' OR cms.message_deleted IS NULL) AND (cms.message_read!='1' OR cms.message_read IS NULL) ORDER BY cm2.id DESC LIMIT 1) LEFT JOIN user AS u ON u.id=cm.creator_id WHERE pa.id=:id AND cm.creator_id != pa.creator_id AND cm.created_at > :created_at",
                [
                    ['user_id', CURRENT_USER->id()],
                    ['id', CookieHelper::getCookie('ingame_application_id')],
                    ['created_at', time() - 600],
                ],
            );

            foreach ($noreplyApplicationsData as $noreplyApplicationData) {
                $parentObjectType = 'project_application';
                $parentObjData = $applicationService->get(CookieHelper::getCookie('ingame_application_id'));
                $commentHtml = '';

                if ($parentObjData) {
                    $parentValue = $noreplyApplicationData['parent'];

                    if ($parentValue > 0) {
                        $commentData = DB->query(
                            'SELECT cm.*, cms.message_read as cms_read FROM conversation_message cm LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND (cms.user_id=:user_id OR cms.user_id IS NULL) WHERE cm.id=:id',
                            [
                                ['user_id', CURRENT_USER->id()],
                                ['id', $noreplyApplicationData['comment_id']],
                            ],
                            true,
                        );
                        $uploadNum = FileHelper::getUploadNumByType($parentObjectType);
                        $groupName = $parentObjData->sorter->get();
                        $commentHtml .= MessageHelper::conversationTreeComment(
                            $commentData,
                            1,
                            $groupName,
                            $uploadNum,
                            $parentObjectType,
                            $parentObjData,
                            'ingame',
                            (int) CookieHelper::getCookie('ingame_application_id'),
                        );
                    } else {
                        $conversationData = DB->query(
                            'SELECT c.id AS c_id, c.name AS c_name, c.sub_obj_type as c_sub_obj_type FROM conversation c WHERE c.id=:id',
                            [
                                ['id', $noreplyApplicationData['conversation_id']],
                            ],
                            true,
                        );
                        $LOCALE_MYAPPLICATION = LocaleHelper::getLocale(['myapplication', 'global']);
                        $commentHtml .= MessageHelper::conversationTree(
                            $conversationData['c_id'],
                            0,
                            1,
                            $parentObjectType,
                            $parentObjData,
                            DataHelper::clearBraces($conversationData['c_sub_obj_type']),
                            $LOCALE_MYAPPLICATION['titles_conversation_sub_obj_types'][DataHelper::clearBraces(
                                $conversationData['c_sub_obj_type'],
                            )],
                        );
                    }
                }

                $userModel = $this->arrayToModel($noreplyApplicationData);
                $newEvents['ingame_application_comments'][] = [
                    'comment_id' => $noreplyApplicationData['comment_id'],
                    'application_id' => $noreplyApplicationData['application_id'],
                    'application_sorter' => $this->showNameExtended(
                        $userModel,
                        false,
                        false,
                        '',
                        false,
                        false,
                        true,
                    ),
                    'comment_text' => DataHelper::escapeOutput($noreplyApplicationData['content']),
                    'html' => $commentHtml,
                    'parent' => $noreplyApplicationData['parent'],
                ];
            }
        }

        /*$newEventsCounters = array(
            'conversation' => 10,
            'project_conversation' => 1,
            'community_conversation' => 5,
            'project_wall' => 2,
            'community_wall' => 22,
            'event_comment' => 11,
            'task_comment' => 1,
            'task_comment_by_types' => array(
                'mine' => 2,
                'membered' => 3,
                'delayed' => 4,
                'closed' => 5,
            ),
        );*/

        return [
            'response' => 'success',
            'new_events' => $newEvents,
            'new_events_counters' => $newEventsCounters,
            'block_sound' => $blockSound,
        ];
    }

    /** Переотсылка проверочного email'а подтверждения email'а */
    public function reverifyEm(): array
    {
        $LOCALE = LocaleHelper::getLocale(['global']);
        $LOCALE_PROFILE = LocaleHelper::getLocale(['profile', 'global']);

        $returnArr = [];

        $userData = $this->get(CURRENT_USER->id(), null, null, true);

        if ($userData->id->getAsInt()) {
            $idToReverify = md5($userData->id->getAsInt() . $userData->created_at->getAsTimeStamp() . $userData->em->get() . $_ENV['PROJECT_HASH_WORD']);
            $text = sprintf(
                $LOCALE_PROFILE['verify_em']['base_text'],
                $LOCALE['sitename'],
                ABSOLUTE_PATH . '/profile/action=verify_em&verify_id=' . $idToReverify,
                ABSOLUTE_PATH . '/profile/action=verify_em&verify_id=' . $idToReverify,
            );

            if (
                EmailHelper::sendMail(
                    $LOCALE['sitename'],
                    $LOCALE['admin_mail'],
                    $userData->em->get(),
                    sprintf($LOCALE_PROFILE['verify_em']['name'], $LOCALE['sitename']),
                    $text,
                    true,
                )
            ) {
                $returnArr = [
                    'response' => 'success',
                    'response_text' => sprintf(
                        $LOCALE_PROFILE['messages']['verification_link_sent'],
                        $userData->em->get(),
                    ),
                ];
            } else {
                $returnArr = [
                    'response' => 'error',
                    'response_text' => sprintf(
                        $LOCALE_PROFILE['messages']['verification_link_not_sent'],
                        $userData->em->get(),
                    ),
                ];
            }
        }

        return $returnArr;
    }

    /** Список контактов пользователя */
    public function getCurrentUserContactsIds(): ?array
    {
        if (CURRENT_USER->isLogged()) {
            if (is_null($this->currentUserContactsIds)) {
                $this->currentUserContactsIds = RightsHelper::findByRights('{friend}', '{user}');
            }

            return $this->currentUserContactsIds;
        }

        return null;
    }

    /** Список пользователей в онлайне */
    public function getUsersOnlineStatuses(): array
    {
        return $this->usersOnlineStatuses;
    }

    /** Список всех диалогов */
    public function getAllConversationsIds(): array
    {
        if (!$this->initializedAllConversationsIds) {
            $this->initializedAllConversationsIds = true;

            if (CURRENT_USER->isLogged()) {
                $this->allConversationsIds = RightsHelper::findByRights('{member}', '{conversation}');
            }
        }

        return $this->allConversationsIds;
    }

    /** Вспомнить пароль */
    public function remindPassword(string $userEmail): array
    {
        $LOCALE = LocaleHelper::getLocale(['login', 'global', 'messages']);

        $userData = DB->select('user', ['em' => $userEmail], true, ['id']);

        if ($userEmail !== '' && $userData['id'] !== '') {
            $pass = '';
            $salt = 'abcdefghijklmnopqrstuvwxyz123456789';
            srand((int) ((float) microtime() * 1000000));
            $i = 0;

            while ($i <= 7) {
                $num = rand() % 35;
                $tmp = mb_substr($salt, $num, 1);
                $pass .= $tmp;
                ++$i;
            }

            DB->update(
                tableName: 'user',
                data: [
                    'password_hashed' => AuthHelper::hashPassword($pass),
                ],
                criteria: [
                    'id' => $userData['id'],
                ],
            );

            $myname = str_replace(['http://', '/'], '', ABSOLUTE_PATH);
            $contactemail = $userEmail;

            $message = $userData['fio'] . sprintf(
                $LOCALE['remind_message'],
                $myname,
                $pass,
            );
            $subject = $LOCALE['remind_subject'] . ' ' . $myname;

            if (EmailHelper::sendMail($myname, '', $contactemail, $subject, $message)) {
                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['new_pass_sent'],
                ];
            } else {
                $returnArr = [
                    'response' => 'error',
                    'response_error_code' => 'error_while_sending',
                    'response_text' => $LOCALE['error_while_sending'],
                ];
            }
        } else {
            $returnArr = [
                'response' => 'error',
                'response_error_code' => 'no_email_found_in_db',
                'response_text' => $LOCALE['no_email_found_in_db'],
            ];
        }

        return $returnArr;
    }

    /** Обновить капчу */
    public function getCaptcha(): array
    {
        return UniversalHelper::getCaptcha();
    }

    public function getRightsContext(): array
    {
        return CURRENT_USER->isAdmin() ? UserModel::CONTEXT : [];
    }

    public function getSidDefault(): ?int
    {
        return CURRENT_USER->sid();
    }

    public function dynamicAddRights(
        string $objType,
        string|int $objId,
        string|int $userId,
        string $rightsType,
    ): array {
        return RightsHelper::dynamicAddRights(
            $objType,
            $objId,
            $userId,
            $rightsType,
        );
    }

    public function dynamicRemoveRights(
        string $objType,
        string|int $objId,
        string|int $userId,
        string $rightsType,
    ): array {
        return RightsHelper::dynamicRemoveRights(
            $objType,
            $objId,
            $userId,
            $rightsType,
        );
    }

    public function checkRights(): string
    {
        return 'id=' . CURRENT_USER->id();
    }
}
