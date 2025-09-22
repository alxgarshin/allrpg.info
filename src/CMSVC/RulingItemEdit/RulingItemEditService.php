<?php

declare(strict_types=1);

namespace App\CMSVC\RulingItemEdit;

use App\CMSVC\RulingQuestionEdit\RulingQuestionEditService;
use App\CMSVC\User\UserService;
use App\Helper\{DateHelper, RightsHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\PostCreate;
use Fraym\Enum\ActEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};

/** @extends BaseService<RulingItemEditModel> */
#[PostCreate]
#[Controller(RulingItemEditController::class)]
class RulingItemEditService extends BaseService
{
    public ?array $rulingTagIdsValues = null;

    public function PostCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $id) {
            if ($id > 0 && CURRENT_USER->id() === 15) {
                DB->update('ruling_item', ['creator_id' => 1], ['id' => $id]);
            }
        }
    }

    public function getObjHelper2Default(): string
    {
        if ($this->getAct() === ActEnum::edit && DataHelper::getId() > 0) {
            $LOCALE = LocaleHelper::getLocale(['fraym']);

            return '<a href="' . ABSOLUTE_PATH . '/ruling/' . DataHelper::getId() . '/" target="_blank">' . $LOCALE['functions']['open_in_a_new_window'] . '</a>';
        }

        return '';
    }

    public function getObjHelper3Default(): string
    {
        if ($this->getAct() === ActEnum::edit && DataHelper::getId() > 0) {
            $LOCALE = LocaleHelper::getLocale(['fraym']);

            return '<a href="' . ABSOLUTE_PATH . '/ruling_edit/ruling_item_id=' . DataHelper::getId() . '" target="_blank">' . $LOCALE['functions']['open_in_a_new_window'] . '</a>';
        }

        return '';
    }

    public function getRulingTagIdsValues(): array
    {
        if (is_null($this->rulingTagIdsValues)) {
            $this->rulingTagIdsValues = DB->getTreeOfItems(
                false,
                'ruling_tag',
                'parent',
                null,
                '',
                'name',
                0,
                'id',
                'name',
                1000000,
            );
        }

        return $this->rulingTagIdsValues;
    }

    public function getRulingTagIdsMultiselectCreatorCreatorId(): ?int
    {
        return CURRENT_USER->id();
    }

    public function getRulingTagIdsMultiselectCreatorGetNow(): int
    {
        return DateHelper::getNow();
    }

    public function getAuthorValues(): array
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $authorsIds = [];

        $alreadyFoundAuthorsIds = [];

        $authorsIds[] = [
            CURRENT_USER->id(),
            $userService->showName($userService->get(CURRENT_USER->id())),
        ];
        $alreadyFoundAuthorsIds[] = CURRENT_USER->id();

        if (DataHelper::getId() > 0) {
            $rulingItem = DB->findObjectById(DataHelper::getId(), 'ruling_item');

            if ($rulingItem['author'] !== '') {
                $authorsArray = DataHelper::multiselectToArray($rulingItem['author']);

                if ($authorsArray[0] ?? false) {
                    foreach ($authorsArray as $authorId) {
                        if (!in_array($authorId, $alreadyFoundAuthorsIds)) {
                            $authorsIds[] = [
                                $authorId,
                                $userService->showName($userService->get($authorId)),
                            ];
                            $alreadyFoundAuthorsIds[] = $authorId;
                        }
                    }
                }
            }
        }

        $colleagues = RightsHelper::findByRights('{friend}', '{user}');

        if ($colleagues) {
            foreach ($colleagues as $colleagueId) {
                if (!in_array($colleagueId, $alreadyFoundAuthorsIds)) {
                    $authorsIds[] = [
                        $colleagueId,
                        $userService->showName($userService->get($colleagueId)),
                    ];
                    $alreadyFoundAuthorsIds[] = $colleagueId;
                }
            }
        }
        $authorsIdsSort = [];

        foreach ($authorsIds as $key => $row) {
            $authorsIdsSort[$key] = mb_strtolower($row[1]);
        }
        array_multisort($authorsIdsSort, SORT_ASC, $authorsIds);

        return $authorsIds;
    }

    public function getShowIfValues(): array
    {
        /** @var RulingQuestionEditService $rulingQuestionEditService */
        $rulingQuestionEditService = CMSVCHelper::getService('rulingQuestionEdit');

        return $rulingQuestionEditService->getShowIfValues();
    }

    public function checkRights(): bool
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        return $userService->isRulingAdmin();
    }

    public function checkRightsDelete(): bool
    {
        return CURRENT_USER->isAdmin();
    }
}
