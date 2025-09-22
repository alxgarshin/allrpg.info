<?php

declare(strict_types=1);

namespace App\CMSVC\PopupHelper;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Trait\UserServiceTrait;
use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\{CMSVCHelper, DataHelper};

#[Controller(PopupHelperController::class)]
class PopupHelperService extends BaseService
{
    use UserServiceTrait;

    /** Проверка и вывод, кем прочитано сообщение*/
    public function getUnreadPeople(?int $objId): ?array
    {
        if ($objId > 0 && CURRENT_USER->isLogged()) {
            $userService = $this->getUserService();

            $check = DB->select(
                'conversation_message_status',
                [
                    'message_id' => $objId,
                    'user_id' => CURRENT_USER->id(),
                ],
                true,
            );

            if ($check['user_id'] === CURRENT_USER->id()) {
                $text = '';
                $result = DB->query(
                    "SELECT u.* FROM user u INNER JOIN conversation_message_status cms ON u.id=cms.user_id AND cms.message_id=:message_id AND cms.message_read='0'",
                    [
                        ['message_id', $objId],
                    ],
                );

                if ($result) {
                    foreach ($result as $userData) {
                        $text .= $userService->photoNameLink($this->getUserService()->arrayToModel($userData), '', false);
                    }
                }

                return ['response' => 'success', 'response_text' => $text];
            }
        }

        return null;
    }

    /** Проверка и вывод, кем прочитано сообщение в задаче */
    public function getTaskUnreadPeople(?int $objId): ?array
    {
        if ($objId > 0 && CURRENT_USER->isLogged()) {
            $check = DB->select(
                'conversation_message_status',
                [
                    ['message_id', $objId],
                    ['user_id', CURRENT_USER->id()],
                ],
                true,
            );

            if ($check['user_id'] === CURRENT_USER->id()) {
                $text = '';
                $check = DB->query(
                    'SELECT c.obj_id FROM conversation c INNER JOIN conversation_message cm ON cm.conversation_id=c.id WHERE cm.id=:id',
                    [
                        ['id', $objId],
                    ],
                    true,
                );
                $users = RightsHelper::findByRights(null, '{task}', $check['obj_id'], '{user}', false);

                if ($users) {
                    $userService = $this->getUserService();

                    foreach ($users as $value) {
                        if ($value !== CURRENT_USER->id()) {
                            $check = DB->select(
                                'conversation_message_status',
                                [
                                    ['message_id', $objId],
                                    ['user_id', $value],
                                ],
                                true,
                            );

                            if (!$check) {
                                $text .= $userService->photoNameLink($userService->get($value), '4em', false);
                            }
                        }
                    }
                }

                return ['response' => 'success', 'response_text' => $text];
            }
        }

        return null;
    }

    /** Проверка и вывод, кем прочитано сообщение в заявке */
    public function getApplicationUnreadPeople(?int $objId): ?array
    {
        $text = '';

        if ($objId > 0 && CURRENT_USER->isLogged()) {
            $check = DB->query(
                'SELECT c.obj_id FROM conversation c INNER JOIN conversation_message cm ON cm.conversation_id=c.id WHERE cm.id=:id',
                [
                    ['id', $objId],
                ],
                true,
            );

            if ($check['obj_id'] ?? false) {
                /** @var ApplicationService */
                $applicationService = CMSVCHelper::getService('application');

                $applicationData = $applicationService->get($check['obj_id']);

                if (
                    $applicationData->creator_id->getAsInt() === CURRENT_USER->id()
                    || RightsHelper::checkRights(['{admin}', '{gamemaster}'], '{project}', $applicationData->project_id->get())
                ) {
                    $users = RightsHelper::findByRights(null, '{subscribe}', $check['obj_id'], '{user}', false);

                    if ($applicationData->creator_id->getAsInt()) {
                        $users[] = $applicationData->creator_id->getAsInt();
                    }

                    if ($applicationData->responsible_gamemaster_id->get()) {
                        $users[] = $applicationData->responsible_gamemaster_id->get();
                    }
                    $users = array_unique($users);

                    if (count($users) > 0) {
                        $userService = $this->getUserService();

                        foreach ($users as $value) {
                            if ($value !== CURRENT_USER->id()) {
                                $check = DB->select(
                                    'conversation_message_status',
                                    [
                                        'message_id' => $objId,
                                        'user_id' => $value,
                                    ],
                                    true,
                                );

                                if (!$check) {
                                    $text .= $userService->photoNameLink($userService->get($value), '4em', false);
                                }
                            }
                        }
                    }
                }
            }
        }

        return ['response' => 'success', 'response_text' => $text];
    }

    /** Получение результатов голосования */
    public function getVote(?int $objId, ?string $value): ?array
    {
        $userService = $this->getUserService();

        if ($objId > 0 && !is_null($value)) {
            $people = RightsHelper::findByRights('{voted}', '{message}', $objId, '{user}', false, 0, $value);

            if ($people) {
                $text = '';

                if (CURRENT_USER->isLogged()) {
                    $contactUsers = RightsHelper::findByRights('{friend}', '{user}');
                    $result = $userService->getAll(
                        ['id' => $people],
                        false,
                        ['(id=' . CURRENT_USER->id() . ') DESC', count($contactUsers) > 0 ? 'id IN (' . implode(',', $contactUsers) . ') DESC' : null],
                        4,
                    );
                } else {
                    $result = $userService->getAll(['id' => $people], false, ['id DESC'], 4);
                }

                $foundItem = false;

                foreach ($result as $userData) {
                    $text .= $userService->photoNameLink($userData, '', false);
                    $foundItem = true;
                }

                if ($foundItem) {
                    return ['response' => 'success', 'response_text' => $text];
                } else {
                    return ['response' => 'success', 'response_text' => ''];
                }
            } else {
                return ['response' => 'success', 'response_text' => ''];
            }
        }

        return null;
    }

    /** Проверка отмеченности важным */
    public function getImportant(string $objType, ?int $objId): ?array
    {
        $userService = $this->getUserService();

        if ($objType !== '' && !is_null($objId)) {
            $responseData = [];
            $people = RightsHelper::findByRights('{important}', DataHelper::addBraces($objType), $objId, '{user}', false);

            if ($people) {
                $text = '';

                if (CURRENT_USER->isLogged()) {
                    $contactUsers = RightsHelper::findByRights('{friend}', '{user}');

                    $result = $userService->getAll(
                        ['id' => $people],
                        false,
                        ['(id=' . CURRENT_USER->id() . ') DESC', $contactUsers ? 'id IN (' . implode(',', $contactUsers) . ') DESC' : null],
                        4,
                    );
                } else {
                    $result = $userService->getAll(['id' => $people], false, ['id DESC'], 4);
                }

                $foundItem = false;

                foreach ($result as $userData) {
                    $text .= $userService->photoNameLink($userData, '', false);

                    $responseData[$userData->id->getAsInt()] = [
                        'user_id' => $userData->id->getAsInt(),
                        'fio' => DataHelper::escapeOutput($userData->fio->get()),
                        'photo' => $userService->photoUrl($userData),
                    ];

                    $foundItem = true;
                }

                if ($foundItem) {
                    if (REQUEST_TYPE->isApiRequest()) {
                        return ['response' => 'success', 'response_data' => $responseData];
                    } else {
                        return ['response' => 'success', 'response_text' => $text];
                    }
                } elseif (REQUEST_TYPE->isApiRequest()) {
                    return ['response' => 'success', 'response_data' => $responseData];
                } else {
                    return ['response' => 'success', 'response_text' => ''];
                }
            } elseif (REQUEST_TYPE->isApiRequest()) {
                return ['response' => 'success', 'response_data' => $responseData];
            } else {
                return ['response' => 'success', 'response_text' => ''];
            }
        }

        return null;
    }

    /** Получение списка авторов объекта */
    public function getAuthors(string $objType, ?int $objId): ?array
    {
        $userService = $this->getUserService();

        if ($objType !== '' && !is_null($objId)) {
            $people = RightsHelper::findByRights('{admin}', DataHelper::addBraces($objType), $objId, '{user}', false);

            if ($people) {
                $text = '';
                $result = $userService->getAll(['id' => $people], false, ['id DESC'], 4);

                $foundItem = false;

                foreach ($result as $userData) {
                    $text .= $userService->photoNameLink($userData, '', false);
                    $foundItem = true;
                }

                if ($foundItem) {
                    return ['response' => 'success', 'response_text' => $text];
                } else {
                    return ['response' => 'success', 'response_text' => ''];
                }
            } else {
                return ['response' => 'success', 'response_text' => ''];
            }
        }

        return null;
    }

    /** Вывод информации о пользователе в сетке ролей */
    public function showUserInfo(string $objType, ?int $objId, int $projectId): array
    {
        $returnArr = ['response' => 'success', 'response_text' => ''];

        if (CURRENT_USER->isLogged()) {
            if ($objType === 'roleslist') {
                if ($projectId > 0) {
                    if ($objId > 0) {
                        $projectAdmin = RightsHelper::findByRights(
                            ['{admin}', '{gamemaster}'],
                            '{project}',
                            $projectId,
                        );

                        if ($projectAdmin) {
                            $canSee = true;
                        } else {
                            $checkApplications = DB->query(
                                "SELECT pa2.id FROM project_application pa2 LEFT JOIN project_application pa ON pa.project_id=pa2.project_id AND pa.creator_id=:creator_id AND pa.deleted_by_player='0' AND pa.deleted_by_gamemaster='0' WHERE pa2.creator_id=:creator_id AND pa2.deleted_by_player='0' AND pa2.deleted_by_gamemaster='0' AND pa.id IS NOT NULL AND pa.project_id=:project_id",
                                [
                                    ['creator_id', $objId],
                                    ['project_id', $projectId],
                                ],
                                true,
                            );
                            $canSee = (int) $checkApplications > 0;
                        }

                        if ($canSee) {
                            $userData = $this->getUserService()->get($objId);

                            if ($userData) {
                                $text = '<div class="social">' .
                                    ($userData->vkontakte_visible->get() !== null ?
                                        '<span class="c1">' . $this->getUserService()->social2(DataHelper::escapeOutput($userData->vkontakte_visible->get()), 'vkontakte') . '</span>' : '') .
                                    ($userData->telegram->get() !== null ?
                                        '<span class="c2">' . $this->getUserService()->social2(DataHelper::escapeOutput($userData->telegram->get()), 'telegram') . '</span>' : '') .
                                    '</div>' .
                                    $this->getUserService()->photoNameLink($userData, '', false);
                                $returnArr = ['response' => 'success', 'response_text' => $text];
                            }
                        }
                    }
                }
            }
        }

        return $returnArr;
    }
}
