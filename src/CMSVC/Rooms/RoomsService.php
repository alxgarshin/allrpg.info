<?php

declare(strict_types=1);

namespace App\CMSVC\Rooms;

use App\CMSVC\Trait\{ProjectDataTrait, UserServiceTrait};
use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\DataHelper;

/** @extends BaseService<RoomsModel> */
#[Controller(RoomsController::class)]
class RoomsService extends BaseService
{
    use ProjectDataTrait;
    use UserServiceTrait;

    private ?array $roomsData = null;
    private ?array $fullRoomsData = null;
    private ?array $listOfApplications = null;

    /** Добавление человека из заявки в комнату */
    public function addNeighboor(int $objId, int $applicationId): array
    {
        if ($objId > 0 && $applicationId > 0) {
            RightsHelper::deleteRights('{member}', '{room}', null, '{application}', $applicationId);
            RightsHelper::addRights('{member}', '{room}', $objId, '{application}', $applicationId);
        }

        return [
            'response' => 'success',
        ];
    }

    public function getRoomsData(): array
    {
        if (is_null($this->roomsData)) {
            $this->prepareData();
        }

        return $this->roomsData;
    }

    public function getFullRoomsData(): array
    {
        if (is_null($this->fullRoomsData)) {
            $this->prepareData();
        }

        return $this->fullRoomsData;
    }

    public function getListOfApplications(): array
    {
        if (is_null($this->listOfApplications)) {
            $this->prepareData();
        }

        return $this->listOfApplications;
    }

    public function getRoomNeighboorsDefault(): string
    {
        $roomsPeopleList = ' ';
        $usersInRoom = DB->query(
            "SELECT u.*, pa.id AS application_id, pa.sorter FROM relation AS r LEFT JOIN project_application AS pa ON pa.id=r.obj_id_from LEFT JOIN user AS u ON u.id=pa.creator_id WHERE r.obj_id_to=:obj_id_to AND r.type='{member}' AND r.obj_type_to='{room}' AND r.obj_type_from='{application}' ORDER BY u.id",
            [
                ['obj_id_to', DataHelper::getId()],
            ],
        );
        $i = 0;

        foreach ($usersInRoom as $userInRoom) {
            $roomsPeopleList .= '<a href="/application/' . $userInRoom['application_id'] . '/" class="' . ($i % 2 === 0 ? 'string1' : 'string2') . '" target="_blank">' . DataHelper::escapeOutput($userInRoom['sorter']) . '</a>' .
                preg_replace(
                    '#<a([^>]+)>#',
                    '<a$1 target="_blank">',
                    $this->getUserService()->showNameExtended(
                        $this->getUserService()->arrayToModel($userInRoom),
                        true,
                        true,
                        $i % 2 === 0 ? 'string1' : 'string2',
                        false,
                        true,
                        true,
                    ),
                );
            ++$i;
        }

        return $roomsPeopleList;
    }

    public function getListView(): bool
    {
        return ($_REQUEST['list'] ?? false) === '1';
    }

    public function array2ul($array): string
    {
        $LOCALE = $this->LOCALE;

        $out = '<ul>';

        foreach ($array as $key => $elem) {
            if (!is_array($elem)) {
                $out .= '<li>' . $elem . '</li>';
            } elseif ($key === 'data') {
                // игнорим
            } elseif ($key === 'applications') {
                $out .= $this->array2ul($elem);
            } else {
                $out .= '<li><b>';

                if (isset($elem['data']['id'])) {
                    $out .= '<a href="/' . KIND . '/' . $elem['data']['id'] . '/">' . $key . '</a></b> (' . sprintf($LOCALE['taken_of'], $elem['data']['taken'], $elem['data']['total']) . ')';
                } else {
                    $out .= $key . '</b>';
                }
                $out .= $this->array2ul($elem) . '</li>';
            }
        }
        $out .= '</ul>';

        return $out;
    }

    private function prepareData(): void
    {
        $LOCALE = $this->LOCALE;

        $roomsData = [];
        $fullRoomsData = [];
        $listOfApplications = [];
        $allRoomsData = DB->select(
            tableName: 'project_room',
            criteria: [
                'project_id' => $this->getActivatedProjectId(),
            ],
            order: [
                'name',
            ],
        );

        foreach ($allRoomsData as $roomData) {
            $roomsTaken = RightsHelper::findByRights('{member}', '{room}', $roomData['id'], '{application}', false);
            $fullRoomsData[$roomData['id']] = $roomData;
            $listOfApplications[$roomData['id']] = $roomsTaken;
            $roomsTaken = (is_array($roomsTaken) ? count($roomsTaken) : 0);

            $roomsData[] = [
                $roomData['id'],
                ($roomsTaken > $roomData['places_count'] ? '<span class="red">' : '') . sprintf(
                    $LOCALE['taken_of'],
                    $roomsTaken,
                    $roomData['places_count'],
                ) . ($roomsTaken > $roomData['places_count'] ? '</span>' : ''),
            ];
        }

        $this->roomsData = $roomsData;
        $this->fullRoomsData = $fullRoomsData;
        $this->listOfApplications = $listOfApplications;
    }
}
