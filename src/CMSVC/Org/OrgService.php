<?php

declare(strict_types=1);

namespace App\CMSVC\Org;

use App\CMSVC\Conversation\ConversationService;
use App\CMSVC\Trait\{ProjectDataTrait, UserServiceTrait};
use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Entity\{PostChange, PostCreate, PreChange, PreCreate};
use Fraym\Helper\{LocaleHelper, ResponseHelper};

/** @extends BaseService<OrgModel> */
#[Controller(OrgController::class)]
#[PreCreate]
#[PreChange]
#[PostCreate]
#[PostChange]
class OrgService extends BaseService
{
    use ProjectDataTrait;
    use UserServiceTrait;

    #[DependencyInjection]
    public ?ConversationService $conversationService = null;

    private array $usersDataTableView = [];

    public function init(): static
    {
        $userService = $this->getUserService();

        $usersDataTableView = [];
        $colleagues = RightsHelper::findByRights('{friend}', '{user}');
        $colleagues[] = CURRENT_USER->id();
        $admins = RightsHelper::findByRights(
            ['{admin}', '{gamemaster}', '{newsmaker}', '{fee}', '{budget}'],
            '{project}',
            $this->getActivatedProjectId(),
            '{user}',
            false,
        );

        if ($admins) {
            $colleagues = array_merge($colleagues, $admins);
        }
        $adminsInvited = RightsHelper::findByRights(
            ['{admin}', '{gamemaster}', '{newsmaker}', '{fee}', '{budget}'],
            '{project}',
            $this->getActivatedProjectId(),
            '{user_invited}',
            false,
        );

        if ($adminsInvited) {
            $colleagues = array_merge($colleagues, $adminsInvited);
        }
        $colleagues = array_unique($colleagues);
        $usersResult = DB->query(
            'SELECT DISTINCT u.* FROM user AS u WHERE u.id IN (:colleagues) ORDER BY u.fio',
            [
                ['colleagues', $colleagues],
            ],
        );

        foreach ($usersResult as $userData) {
            $usersDataTableView[] = [
                $userData['id'],
                $userService->showNameWithId($userService->arrayToModel($userData)),
            ];
        }

        $this->usersDataTableView = $usersDataTableView;

        return $this;
    }

    public function preCreate(): void
    {
        $this->clearLockedRadioButtons();
    }

    public function preChange(): void
    {
        $this->clearLockedRadioButtons();

        $LOCALE = $this->LOCALE;

        foreach ($_REQUEST['obj_id_from'] as $key => $objIdFrom) {
            if ($objIdFrom === CURRENT_USER->id()) {
                $relationData = DB->findObjectById($_REQUEST['id'][$key], 'relation');

                if ($_REQUEST['type'][$key] !== '{admin}' && $relationData['type'] === '{admin}') {
                    ResponseHelper::responseOneBlock('error', $LOCALE['cannot_remove_admin'], [$key]);
                }
            }
        }
    }

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            DB->update(
                'relation',
                [
                    'obj_type_from' => '{user}',
                    'obj_type_to' => '{project}',
                    'obj_id_to' => $this->getActivatedProjectId(),
                ],
                [
                    'id' => $successfulResultsId,
                ],
            );

            $this->confirmationCheck((int) $successfulResultsId);
        }
    }

    public function postChange(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            $this->confirmationCheck((int) $successfulResultsId);
        }
    }

    public function getSortObjIdTo(): array
    {
        return $this->usersDataTableView;
    }

    public function checkRightsViewChangeRestrict(): string
    {
        return 'obj_id_to=' . $this->getActivatedProjectId() . ' AND obj_type_to="{project}" AND (obj_type_from="{user}" OR obj_type_from="{user_invited}") AND type IN ("{admin}", "{gamemaster}", "{newsmaker}", "{fee}", "{budget}")';
    }

    public function checkRightsDeleteRestrict(): string
    {
        return 'obj_id_to=' . $this->getActivatedProjectId() . ' AND obj_type_to="{project}" AND (obj_type_from="{user}" OR obj_type_from="{user_invited}") AND type IN ("{admin}", "{gamemaster}", "{newsmaker}", "{fee}", "{budget}") AND NOT (obj_id_from=' . CURRENT_USER->id() . ' AND type="{admin}")';
    }

    public function getObjIdFromValues(): array
    {
        return $this->usersDataTableView;
    }

    public function getCommentValues(): array
    {
        $LOCALE = LocaleHelper::getLocale(['org', 'fraym_model', 'elements', 'comment']);
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $commentValues = [];
        $commentValues[] = ['help_1', $LOCALE['help_1']];
        $commentValues = array_merge(
            $commentValues,
            DB->getArrayOfItemsAsArray('project_filterset WHERE project_id=' . $this->getActivatedProjectId(), 'id', 'name'),
        );
        $commentValues[] = ['help_2', $LOCALE['help_2']];

        /** Разделы, которые всегда доступны "мастерам", поэтому их выбрать тут нельзя */
        $defaultSections = [
            'roles/{id}/',
            'budget',
            'fee',
        ];

        foreach ($LOCALE_GLOBAL['project_control_items'] as $key => $item) {
            if (preg_match('#{' . $key . '}#', $item[1]) && !preg_match('#tab#', $key) && !in_array($key, $defaultSections)) {
                $commentValues[] = [$key, $item[0]];
            }
        }

        return $commentValues;
    }

    public function getObjIdToDefault(): int
    {
        return $this->getActivatedProjectId();
    }

    private function clearLockedRadioButtons(): void
    {
        /** Вычищаем изменение заблокированных radio-кнопок */
        foreach ($_REQUEST['type'] as $key => $type) {
            if (is_array($type)) {
                if (count($type) === 1) {
                    $correctKey = array_keys($type);
                    $_REQUEST['type'][$key] = $correctKey[0];
                } else {
                    unset($_REQUEST['type'][$key]);
                }
            }
        }
    }

    private function confirmationCheck(int|string $id): void
    {
        $LOCALE = $this->LOCALE;

        /** Если пользователь не состоит в проекте, отправляем ему приглашение, а право переводим в режим ожидания подтверждения */
        $relationData = DB->findObjectById($id, 'relation');

        if ($relationData['id'] > 0) {
            if (!RightsHelper::checkRights(
                '{member}',
                '{project}',
                $this->getActivatedProjectId(),
                '{user}',
                $relationData['obj_id_from'],
            )) {
                DB->update(
                    'relation',
                    [
                        'obj_type_from' => '{user_invited}',
                    ],
                    [
                        'id' => $id,
                    ],
                );

                $invitationResults = $this->conversationService->sendInvitation(
                    '{project}',
                    $this->getActivatedProjectId(),
                    $relationData['obj_id_from'],
                );

                if ($invitationResults['response'] === 'success') {
                    ResponseHelper::success($LOCALE['messages']['invitation_sent']);
                } elseif ($invitationResults['response_text'] !== '') {
                    ResponseHelper::error($invitationResults['response_text']);
                }
            }
        }
    }
}
