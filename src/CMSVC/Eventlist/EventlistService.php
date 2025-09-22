<?php

declare(strict_types=1);

namespace App\CMSVC\Eventlist;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseService, Controller};

#[Controller(EventlistController::class)]
class EventlistService extends BaseService
{
    private ?array $myCommunities = null;
    private ?array $myProjects = null;

    public function getDate(): int
    {
        $date = date('Y-m');

        if ($_REQUEST['date'] !== '') {
            $date = $_REQUEST['date'];
        }

        return strtotime($date);
    }

    public function getDates(): array
    {
        $date = $this->getDate();

        return [
            $date,
            date('Y-m', strtotime('+1 month', $date)),
            date('Y-m', strtotime('-1 month', $date)),
        ];
    }

    public function getMyCommunities(): array
    {
        if ($this->myCommunities === null) {
            $this->myCommunities = RightsHelper::findByRights(null, '{community}');

            if (!$this->myCommunities) {
                $this->myCommunities = [];
            }
        }

        return $this->myCommunities;
    }

    public function getMyProjects(): array
    {
        if ($this->myProjects === null) {
            $this->myProjects = RightsHelper::findByRights(null, '{project}');

            if (!$this->myProjects) {
                $this->myProjects = [];
            }
        }

        return $this->myProjects;
    }

    public function getEventsData(string $curDate): array
    {
        $query = "SELECT DISTINCT te.*, r2.obj_type_to as type, r.obj_type_to as parent_type, r.obj_id_to as parent_id FROM task_and_event te LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND (r2.obj_type_to='{task}' OR r2.obj_type_to='{event}') AND r2.obj_type_from='{user}' AND r2.obj_id_from=" . CURRENT_USER->id() . " LEFT JOIN relation r ON r.obj_id_from=r2.obj_id_to AND r.obj_type_from=r2.obj_type_to AND ((r.obj_type_to='{project}' AND r.obj_id_to" . ($this->getMyProjects() !== [] ? ' IN (' . implode(',', $this->getMyProjects()) . ')' : '=0') . ") OR (r.obj_type_to='{community}' AND r.obj_id_to" . ($this->getMyCommunities() !== [] ? ' IN (' . implode(',', $this->getMyCommunities()) . ')' : '=0') . ")) WHERE r2.type IN ('{admin}','{responsible}','{member}') AND (r.type='{child}' OR r.type IS NULL) AND ((r2.obj_type_to='{task}' AND te.status NOT IN ('{closed}','{rejected}','{delayed}')) OR r2.obj_type_to='{event}') AND (te.date_from<DATE_ADD(:curDate_1, INTERVAL 1 DAY) OR te.date_from IS NULL) AND (te.date_to>=:curDate_2 OR te.date_to IS NULL) ORDER BY IF((DATE(te.date_from)=DATE(:curDate_3) OR DATE(te.date_to)=DATE(:curDate_4)),IF(DATE(te.date_from)=DATE(:curDate_5),te.date_from,te.date_to),'3030-03-03') ASC, te.name ASC";

        return DB->query($query, [
            ['curDate_1', $curDate],
            ['curDate_2', $curDate],
            ['curDate_3', $curDate],
            ['curDate_4', $curDate],
            ['curDate_5', $curDate],
        ]);
    }

    public function getNewsData(string $curDate): array
    {
        $query = "SELECT * FROM news WHERE active='1' AND type='1' AND ((show_date>=:curDate_1 AND show_date<DATE_ADD(:curDate_2, INTERVAL 1 DAY) AND to_date IS NULL AND from_date IS NULL) OR (from_date<=:curDate_3 AND (to_date>=:curDate_4 OR to_date IS NULL)) OR (to_date>=:curDate_5 AND (from_date<=:curDate_6 OR from_date IS NULL))) ORDER BY show_date DESC, updated_at DESC";

        return DB->query($query, [
            ['curDate_1', $curDate],
            ['curDate_2', $curDate],
            ['curDate_3', $curDate],
            ['curDate_4', $curDate],
            ['curDate_5', $curDate],
            ['curDate_6', $curDate],
        ]);
    }

    public function generateIcalHash(): string
    {
        return md5(CURRENT_USER->id() . '.ical.' . ABSOLUTE_PATH) . CURRENT_USER->id();
    }
}
