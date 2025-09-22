<?php

declare(strict_types=1);

namespace App\CMSVC\Calendar;

use App\CMSVC\CalendarEvent\{CalendarEventModel, CalendarEventService};
use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\{CMSVCHelper, DataHelper};
use Generator;

#[Controller(CalendarController::class)]
class CalendarService extends BaseService
{
    public function changeCalendarstyle(): ?array
    {
        if (CURRENT_USER->isLogged()) {
            /** @var UserService $userService */
            $userService = CMSVCHelper::getService('user');

            $userData = $userService->get(CURRENT_USER->id());
            DB->update('user', ['calendarstyle' => !$userData->calendarstyle->get() ? 1 : 0], ['id' => CURRENT_USER->id()]);
        }

        return ['response' => 'success'];
    }

    public function getMonths(): array
    {
        $months = [
            1 => [1, 31],
            2 => [2, 28],
            3 => [3, 31],
            4 => [4, 30],
            5 => [5, 31],
            6 => [6, 30],
            7 => [7, 31],
            8 => [8, 31],
            9 => [9, 30],
            10 => [10, 31],
            11 => [11, 30],
            12 => [12, 31],
        ];

        if ($this->getYear() % 4 === 0) {
            $months[2][1] = 29;
        }

        return $months;
    }

    public function getYear(): int
    {
        $year = (int) date('Y');

        if ((int) DataHelper::getId() > 1900 && (int) DataHelper::getId() < 2200) {
            $year = (int) DataHelper::getId();
        } elseif (date('m') >= 10) {
            ++$year;
        }

        return $year;
    }

    public function getRegionsLists(): array
    {
        $regionsList = DB->getArrayOfItemsAsArray(
            'geography WHERE id IN (SELECT DISTINCT region FROM calendar_event) ORDER BY name ASC',
            'id',
            'name',
        );
        $regionsListRehashed = [];

        foreach ($regionsList as $key => $regionData) {
            $regionsList[$key] = [$regionData[0], DataHelper::escapeOutput($regionData[1])];
            $regionsListRehashed[$regionData[0]] = DataHelper::escapeOutput($regionData[1]);
        }

        return [$regionsList, $regionsListRehashed];
    }

    public function getSettingsList(): Generator
    {
        return DB->getArrayOfItems('gameworld ORDER BY name', 'id', 'name');
    }

    public function getTypesLists(): array
    {
        $typesList = DB->getArrayOfItemsAsArray('gametype WHERE gametype=2 ORDER BY name', 'id', 'name');
        $typesListRehashed = [];

        foreach ($typesList as $key => $typeList) {
            $typesList[$key] = [$typeList[0], DataHelper::escapeOutput($typeList[1])];
            $typesListRehashed[$typeList[0]] = DataHelper::escapeOutput($typeList[1]);
        }

        return [$typesList, $typesListRehashed];
    }

    public function getMinDate(): string
    {
        return $this->getYear() . '-01-01';
    }

    public function getMaxDate(): string
    {
        return $this->getYear() . '-12-31';
    }

    public function getRegionsIds(array $calendarEventsData): array
    {
        $regionsIds = [];

        foreach ($calendarEventsData as $calendarEventItem) {
            $regionsIds[] = $calendarEventItem['region'];
        }

        return array_unique($regionsIds);
    }

    public function getMinYear(): int
    {
        $minYear = DB->query("SELECT MIN(date_from) AS date FROM calendar_event WHERE date_from != '0000-00-00' AND date_from IS NOT NULL", [], true);

        return (int) date('Y', (($minYear['date'] ?? false) ? strtotime($minYear['date']) : strtotime('now')));
    }

    public function getMaxYear(): int
    {
        $maxYear = DB->query('SELECT MAX(date_to) AS date FROM calendar_event', [], true);

        return (int) date('Y', (($maxYear['date'] ?? false) ? strtotime($maxYear['date']) : strtotime('now')));
    }

    public function get(int|string|null $id = null, ?array $criteria = null, ?array $order = null, bool $refresh = false, bool $strict = false): ?CalendarEventModel
    {
        /** @var CalendarEventService $calendarEventService */
        $calendarEventService = CMSVCHelper::getService('calendarEvent');

        return $calendarEventService->get($id, $criteria, $order, $refresh, $strict);
    }

    public function getAllAsArray(): array
    {
        $logged = CURRENT_USER->isLogged();
        $region = (int) ($_REQUEST['region'] ?? false) > 0 ? (int) $_REQUEST['region'] : false;
        $minDate = date('Y-m-d', strtotime($this->getMinDate()));
        $maxDate = date('Y-m-d', strtotime($this->getMaxDate()));

        return DB->query(
            "SELECT ce.*, SUM(IF(n.rating = '1', 1, IF(n.rating IS NULL, 0, -1))) AS notion_rating, COUNT(n.id) AS notion_count, COUNT(r.id) AS report_count, COUNT(cag.id) AS gallery_count" . ($logged ? ', p.id as played_id, p.specializ2, p.specializ3' : '') . " FROM calendar_event ce LEFT JOIN notion n ON n.calendar_event_id=ce.id AND n.active='1' LEFT JOIN report r ON r.calendar_event_id=ce.id LEFT JOIN calendar_event_gallery cag ON cag.calendar_event_id=ce.id" . ($logged ? ' LEFT JOIN played p ON p.calendar_event_id=ce.id AND p.creator_id=:user_id' : '') . ' WHERE ((ce.date_from <= :min_date1 AND ce.date_to >= :min_date2) OR (ce.date_from <= :max_date1 AND ce.date_to >= :max_date2) OR (ce.date_from >= :min_date3 AND ce.date_to <= :max_date3))' . ($region ? ' AND ce.region IN (:region)' : '') . ' GROUP BY ce.id' . ($logged ? ', p.id' : '') . ' ORDER BY ce.date_from ASC',
            [
                ['user_id', CURRENT_USER->id()],
                ['min_date1', $minDate],
                ['min_date2', $minDate],
                ['min_date3', $minDate],
                ['max_date1', $maxDate],
                ['max_date2', $maxDate],
                ['max_date3', $maxDate],
                ['region', $region],
            ],
        );
    }
}
