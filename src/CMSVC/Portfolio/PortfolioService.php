<?php

declare(strict_types=1);

namespace App\CMSVC\Portfolio;

use App\CMSVC\User\UserService;
use App\Helper\DateHelper;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\{PostChange, PostCreate, PostDelete};
use Fraym\Enum\ActEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Generator;

/** @extends BaseService<PortfolioModel> */
#[Controller(PortfolioController::class)]
#[PostCreate]
#[PostChange]
#[PostDelete]
class PortfolioService extends BaseService
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

    public function getSortId(): array
    {
        $LOCALE = $this->getLOCALE();

        $playedDates = [];
        $myPlayed = DB->query(
            'SELECT p.id, ce.date_from, ce.date_to FROM played p LEFT JOIN calendar_event ce ON ce.id=p.calendar_event_id WHERE p.creator_id=:creator_id ORDER BY ce.date_from DESC',
            [
                ['creator_id', CURRENT_USER->id()],
            ],
        );

        foreach ($myPlayed as $myPlayedData) {
            if (DateHelper::dateFromToEvent($myPlayedData['date_from'], $myPlayedData['date_to']) !== '') {
                $playedDates[] = [
                    $myPlayedData['id'],
                    DateHelper::dateFromToEvent($myPlayedData['date_from'], $myPlayedData['date_to']),
                ];
            } else {
                $playedDates[] = [$myPlayedData['id'], $LOCALE['no_data']];
            }
        }

        return $playedDates;
    }

    public function checkRights(): bool
    {
        return CURRENT_USER->isLogged();
    }

    public function checkRightsRestrict(): string
    {
        return 'creator_id=' . CURRENT_USER->id();
    }

    public function getToGameDefault(): string
    {
        if ($this->getAct() === ActEnum::edit && DataHelper::getId() > 0) {
            $LOCALE = LocaleHelper::getLocale(['fraym']);
            $portfolioItem = DB->findObjectById(DataHelper::getId(), 'played');

            return '<a href="' . ABSOLUTE_PATH . '/calendar_event/' . $portfolioItem['calendar_event_id'] . '/" target="_blank">' . $LOCALE['functions']['open_in_a_new_window'] . '</a>';
        }

        return '';
    }

    public function getCalendarEventIdDefault(): ?int
    {
        if ($_REQUEST['calendar_event_id'] ?? false) {
            return (int) $_REQUEST['calendar_event_id'];
        }

        return null;
    }

    public function getSpecializValues(): Generator
    {
        return DB->getArrayOfItems('speciality WHERE gr=1 ORDER BY name', 'id', 'name');
    }

    public function getSpecializ2Values(): Generator
    {
        return DB->getArrayOfItems('speciality WHERE gr=2 ORDER BY name', 'id', 'name');
    }

    public function getSpecializ3Values(): Generator
    {
        return DB->getArrayOfItems('speciality WHERE gr=3 ORDER BY name', 'id', 'name');
    }

    private function checkAchievements(): void
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $userService->checkForAchievements(CURRENT_USER->id(), [5, 6, 7]);
        $userService->checkForAchievements(CURRENT_USER->id(), [8, 9, 10]);
    }
}
