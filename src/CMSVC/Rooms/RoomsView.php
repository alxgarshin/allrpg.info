<?php

declare(strict_types=1);

namespace App\CMSVC\Rooms;

use App\CMSVC\Trait\ProjectSectionsPostViewHandlerTrait;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{ActEnum, SubstituteDataTypeEnum};
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;

/** @extends BaseView<RoomsService> */
#[TableEntity(
    name: 'rooms',
    table: 'project_room',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'one_place_price',
        ),
        new EntitySortingItem(
            tableFieldName: 'id',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getRoomsData',
        ),
    ],
    elementsPerPage: 500,
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: true,
    deleteRight: true,
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(RoomsController::class)]
class RoomsView extends BaseView
{
    use ProjectSectionsPostViewHandlerTrait;

    public function Response(): ?Response
    {
        return null;
    }

    public function additionalPostViewHandler(string $RESPONSE_DATA): string
    {
        $LOCALE = $this->getLOCALE();

        $roomsService = $this->getService();
        $userService = $roomsService->getUserService();

        if (DataHelper::getActDefault($this->getEntity()) === ActEnum::list) {
            $RESPONSE_DATA = preg_replace('#<div class="indexer_toggle(.*?)<\/div>#', '<div class="indexer_toggle$1</div><div class="filter">' . (!$roomsService->getListView() ? '<a href="/' . KIND . '/list=1" class="fixed_select">' . $LOCALE['switch_to_list'] . '</a>' : '<a href="/' . KIND . '/" class="fixed_select">' . $LOCALE['switch_to_all'] . '</a>') . '</div>', $RESPONSE_DATA);
        }

        if (DataHelper::getId() > 0) {
            $RESPONSE_DATA = str_replace('<div class="field wysiwyg" id="field_room_neighboors[0]">', '<div class="field wysiwyg" id="field_room_neighboors[0]"><a class="add_something_svg" id="add_neighboor" obj_id="' . DataHelper::getId() . '" project_id="' . $roomsService->getActivatedProjectId() . '"></a>', $RESPONSE_DATA);
        }

        if ($roomsService->getListView()) {
            $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . ' autocreated">
    <h1 class="page_header">' . $LOCALE['title'] . '</h1>
	<div class="page_blocks margin_top">
	    <div class="page_block">';

            $RESPONSE_DATA .= '<div class="filter"><a href="/' . KIND . '/" class="fixed_select">' . $LOCALE['switch_to_all'] . '</a></div><div class="publication_content">';

            /** Формируем полный список комнат и игроков в них */
            $applicationsData = [];
            $applicationsQuery = DB->query('SELECT pa.sorter, pa.id AS project_application_id, u.* FROM project_application AS pa LEFT JOIN user AS u ON u.id=pa.creator_id WHERE pa.project_id=' . $roomsService->getActivatedProjectId(), []);

            foreach ($applicationsQuery as $applicationData) {
                $applicationsData[$applicationData['project_application_id']] = '<a href="/application/' . $applicationData['project_application_id'] . '/">' . $applicationData['sorter'] . '</a> - <span class="small">' . $userService->showNameExtended($userService->arrayToModel($applicationData), false, true, '', false, true, true) . '</span>';
            }

            /** Разбиваем все имена и пытаемся разложить на вложенный массив комнат и домиков */
            $listOfApplications = $roomsService->getListOfApplications();
            $listOfRoomNames = [];

            foreach ($roomsService->getFullRoomsData() as $roomId => $roomData) {
                unset($decodedNames);
                $decodedNames = explode(':', DataHelper::escapeOutput($roomData['name']));

                foreach ($decodedNames as $key => $decodedName) {
                    $decodedNames[$key] = trim($decodedName);
                }
                $curListLvl = &$listOfRoomNames;

                foreach ($decodedNames as $key => $decodedName) {
                    if (!isset($curListLvl[$decodedName])) {
                        $curListLvl[$decodedName] = [];
                    }
                    $curListLvl = &$curListLvl[$decodedName];

                    if ($key === count($decodedNames) - 1) {
                        $curListLvl['data'] = [
                            'taken' => (is_array($listOfApplications[$roomId]) ? count($listOfApplications[$roomId]) : 0),
                            'total' => $roomData['places_count'],
                            'id' => $roomId,
                        ];

                        foreach ($listOfApplications[$roomId] as $applicationId) {
                            $curListLvl['applications'][] = $applicationsData[$applicationId];
                        }
                    }
                }
            }

            $RESPONSE_DATA .= $roomsService->array2ul($listOfRoomNames);

            $RESPONSE_DATA .= '</div>
        </div>
    </div>
</div>';
        }

        return $RESPONSE_DATA;
    }
}
