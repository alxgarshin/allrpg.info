<?php

declare(strict_types=1);

namespace App\CMSVC\Report;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\{PostChange, PostCreate, PostDelete};
use Fraym\Helper\CMSVCHelper;

/** @extends BaseService<ReportModel> */
#[Controller(ReportController::class)]
#[PostCreate]
#[PostChange]
#[PostDelete]
class ReportService extends BaseService
{
    public function postCreate(array $successfulResultsIds): void
    {
        $this->checkAchievements();
    }

    public function postChange(array $successfulResultsIds): void
    {
        $this->checkAchievements();
    }

    public function postDelete(array $successfulResultsIds): void
    {
        $this->checkAchievements();
    }

    public function getSortCreatorId(): array
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $allusers = [];
        $allusersSort = [];

        $usersData = $userService->arraysToModels(DB->query('SELECT * FROM user WHERE id IN (SELECT DISTINCT creator_id FROM report)', []));

        foreach ($usersData as $userData) {
            $allusers[] = [
                $userData->id->getAsInt(),
                $userService->showName($userData),
            ];
        }

        foreach ($allusers as $key => $row) {
            $allusersSort[$key] = mb_strtolower($row[1]);
        }
        array_multisort($allusersSort, SORT_ASC, $allusers);

        return $allusers;
    }

    public function getDefaultCalendarEventId(): ?int
    {
        return ($_REQUEST['calendar_event_id'] ?? false) ? (int) $_REQUEST['calendar_event_id'] : null;
    }

    public function checkRights(): bool
    {
        return CURRENT_USER->isLogged();
    }

    public function checkRightsViewRestrict(): ?string
    {
        return ($_REQUEST['mine'] ?? false) === '1' ? 'creator_id=' . CURRENT_USER->id() : null;
    }

    public function checkRightsRestrict(): ?string
    {
        return CURRENT_USER->isAdmin() || CURRENT_USER->checkAllRights('info') ? null : 'creator_id=' . CURRENT_USER->id();
    }

    private function checkAchievements(): void
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $userService->checkForAchievements(CURRENT_USER->id(), [11, 12, 13]);
    }
}
