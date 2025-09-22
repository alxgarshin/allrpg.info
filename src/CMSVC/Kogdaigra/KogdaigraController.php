<?php

declare(strict_types=1);

namespace App\CMSVC\Kogdaigra;

use App\CMSVC\User\UserService;
use App\Helper\DateHelper;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper};
use Fraym\Interface\Response;

/** @extends BaseController<KogdaigraService> */
#[CMSVC(
    service: KogdaigraService::class,
)]
class KogdaigraController extends BaseController
{
    public function Response(): ?Response
    {
        $kogdaIgraService = $this->getService();

        $from = (int) ($_REQUEST['from'] ?? 1500);
        $to = (int) ($_REQUEST['to'] ?? $from + 100);

        $types = [
            1 => 15,
            2 => 2,
            3 => 14,
            4 => 14,
            5 => 4,
            6 => 33,
            7 => 34,
            8 => 2,
            9 => 2,
            10 => 34,
            12 => 3,
            13 => 37,
        ];

        $deletes = 0;
        $contentRows = [];

        for ($id = $from; $id <= $to; ++$id) {
            $resultRow = [];

            $info = $kogdaIgraService->loadDataFromKogdaigra($id);

            $name = $info['name'];
            $region = $info['sub_region_name'];
            // $gametype = $info['game_type_name'];
            $polygon = $info['polygon_name'];
            $mg = $info['mg'];
            $allrpgId = (int) ($info['allrpg_info_id'] ?? 0);

            if (($name !== '' || $allrpgId > 0) && $info['deleted_flag'] !== '1') {
                $dateArrival = '';

                $allrpgData = $kogdaIgraService->findGameInAllrpg($id, $allrpgId, $name, $info['begin']);

                $resultRow['kogda_igra_id'] = $id;
                $resultRow['kogda_igra_name'] = $name;
                $resultRow['allrpg_id'] = $allrpgData['id'] ?? null;
                $resultRow['allrpg_name'] = $allrpgData['name'] ?? null;
                $resultRow['kogdaigra_subregion'] = $region;

                $idInAllrpg = $allrpgData['id'] ?? null;

                if ($idInAllrpg) {
                    $dateArrival = $allrpgData['date_arrival'];
                }

                $sqlValues = [
                    'kogdaigra_id' => $id,
                    'name' => $name,
                ];

                $regionId = $kogdaIgraService->getRegionByName($region);
                $sqlValues['region'] = $regionId;
                $resultRow['region_id'] = $regionId;

                $areaId = $kogdaIgraService->getAreaForSync($polygon, (int) $info['polygon_name']);
                $sqlValues['area'] = $areaId ?: 110;
                $resultRow['area_id'] = $areaId;
                $resultRow['kogda_igra_polygon'] = $info['polygon_name'];

                $allrpgGametypes = (int) $types[$info['type']];
                $sqlValues['gametype2'] = '-' . $allrpgGametypes . '-';
                $resultRow['allrpg_gametypes'] = $allrpgGametypes;
                $resultRow['kogda_igra_gametype'] = $info['type'];

                $sqlValues['mg'] = str_replace(['«', '»'], '', $mg);

                if ($info['uri'] !== '') {
                    if (preg_match('#allrpg\.info#', $info['uri']) || preg_match('#joinrpg\.ru#', $info['uri'])) {
                        $sqlValues['orderpage'] = $info['uri'];
                    } else {
                        $sqlValues['site'] = $info['uri'];
                    }
                } elseif ($info['vk_club'] !== '') {
                    $sqlValues['site'] = 'https://www.vk.com/' . $info['vk_club'];
                } elseif ($info['lj_comm'] !== '') {
                    $sqlValues['site'] = 'http://' . $info['lj_comm'] . '.livejournal.com/profile';
                } elseif ($info['fb_comm'] !== '') {
                    $sqlValues['site'] = 'https://www.facebook.com/groups/' . $info['fb_comm'] . '/';
                }

                $sqlValues['date_from'] = $info['begin'];

                $dateTo = date('Y-m-d', strtotime($info['begin']) + 3600 * 24 * ($info['time'] - 1));
                $sqlValues['date_to'] = $dateTo;

                if (!$idInAllrpg || strtotime($dateArrival) > strtotime($info['begin']) || strtotime($dateArrival) < strtotime($info['begin']) - (3600 * 24 * 7)) {
                    $sqlValues['date_arrival'] = $info['begin'];
                }

                if ($info['players_count'] !== '') {
                    $sqlValues['playernum'] = $info['players_count'];
                }

                $sqlValues['updated_at'] = DateHelper::getNow();

                $sqlValues['moved'] = $info['status'] === 3 ? 1 : 0;
                $sqlValues['wascancelled'] = $info['status'] === 5 ? 1 : 0;

                $resultRow['status_text'] = $sqlValues['moved'] ? 'отложена' : ($sqlValues['wascancelled'] ? 'отменена' : '');

                if ($idInAllrpg) {
                    DB->update(
                        tableName: 'calendar_event',
                        data: $sqlValues,
                        criteria: [
                            'id' => $idInAllrpg,
                        ],
                    );
                } else {
                    $sqlValues['creator_id'] = 15;
                    $sqlValues['created_at'] = DateHelper::getNow();
                    $sqlValues['gametype3'] = 67;
                    $sqlValues['gametype'] = '-38-'; // gametype = 38: не определен

                    DB->insert(
                        tableName: 'calendar_event',
                        data: $sqlValues,
                    );

                    $resultRow['allrpg_id'] = DB->lastInsertId();
                    $resultRow['allrpg_name'] = $resultRow['kogda_igra_name'];
                }

                $resultRow['sql_result'] = DB->rowCount();
            } elseif ($info['deleted_flag'] === '1') {
                if ($allrpgId > 0) {
                    DB->delete(
                        tableName: 'calendar_event',
                        criteria: [
                            'id' => $allrpgId,
                        ],
                    );
                } elseif ($id > 0) {
                    DB->delete(
                        tableName: 'calendar_event',
                        criteria: [
                            'kogdaigra_id' => $id,
                        ],
                    );
                }

                if (DB->rowCount() > 0) {
                    ++$deletes;
                }
            }

            $contentRows[] = $resultRow;
        }

        header('Access-Control-Allow-Origin: *');
        header('Content-Type: text/html;charset=UTF-8');

        echo DataHelper::jsonFixedEncode($contentRows);
        exit;
    }

    public function externalData(): void
    {
        $datestart = $_REQUEST['datestart'] ?? null;
        $datefinish = $_REQUEST['datefinish'] ?? null;
        $gameId = $_REQUEST['game_id'] ?? null;
        $openList = $_REQUEST['open_list'] ?? null;
        $kogdaigraId = $_REQUEST['kogdaigra_id'] ?? null;

        $games = [];

        if ($kogdaigraId || ($datestart && $datefinish)) {
            if ($kogdaigraId) {
                $kogdaigraId = (int) $kogdaigraId;
                $result = DB->select(
                    tableName: 'calendar_event',
                    criteria: [
                        'kogdaigra_id' => $kogdaigraId,
                        'parent' => 0,
                    ],
                    order: [
                        'name',
                    ],
                );
            } else {
                $result = DB->query(
                    query: 'SELECT id, name, kogdaigra_id FROM calendar_event WHERE ((datestart BETWEEN :datestart_1 AND :datefinish_1) OR (datefinish BETWEEN :datestart_2 AND :datefinish_2)) AND parent=0 ORDER BY name',
                    data: [
                        ['datestart_1', $datestart],
                        ['datefinish_1', $datefinish],
                        ['datestart_2', $datestart],
                        ['datefinish_2', $datefinish],
                    ],
                );
            }

            foreach ($result as $item) {
                $games[] = [
                    'allrpg_info_id' => $item['id'],
                    'allrpg_info_name' => DataHelper::escapeOutput($item['name']),
                    'kogdaigra_id' => $item['kogdaigra_id'],
                ];
            }
        } elseif ($gameId) {
            /** @var UserService */
            $userService = CMSVCHelper::getService('user');

            $calendarEventData = DB->select(
                tableName: 'calendar_event',
                criteria: [
                    'id' => $gameId,
                ],
                oneResult: true,
            );
            $creatorData = DB->select(
                tableName: 'user',
                criteria: [
                    'sid' => $calendarEventData['creator_id'],
                ],
                oneResult: true,
            );

            $games = [
                'info' => [
                    'name' => DataHelper::escapeOutput($calendarEventData['name']),
                    'site' => DataHelper::escapeOutput($calendarEventData['site']),
                    'mg' => DataHelper::escapeOutput($calendarEventData['mg']),
                    'playernum' => DataHelper::escapeOutput($calendarEventData['playernum']),
                    'datestart' => $calendarEventData['datestart'],
                    'datefinish' => $calendarEventData['datefinish'],
                    'datearrival' => $calendarEventData['datearrival'],
                    'author_mail' => $creatorData['em'],
                    'author_name' => $userService->showNameWithId($userService->arrayToModel($creatorData)),
                ],
                'masters' => [],
            ];

            $mastersInfo = [];

            $masters = DB->select(
                tableName: 'played',
                criteria: [
                    'calendar_event_id' => $calendarEventData['id'],
                ],
            );

            $specialities = DB->query(
                query: 'SELECT * FROM speciality WHERE gr=2 OR gr=3',
                data: [],
            );

            foreach ($masters as $master) {
                $duties = [];

                foreach ($specialities as $speciality) {
                    if (
                        preg_match('#-' . $speciality['id'] . '-#', $calendarEventData['specializ2'])
                        || preg_match('#-' . $speciality['id'] . '-#', $calendarEventData['specializ3'])
                    ) {
                        $duties[] = DataHelper::escapeOutput($speciality['name']);
                    }
                }

                $mastersInfo[] = [
                    'email' => DataHelper::escapeOutput($master['em']),
                    'name' => $userService->showNameWithId($userService->arrayToModel($master)),
                    'duty' => $duties,
                ];
            }

            $games['masters'] = $mastersInfo;
        } elseif ($openList) {
            $calendarEvents = DB->select(
                tableName: 'project',
                criteria: [
                    'status' => 0,
                    ['date_to', date('Y-m-d'), OperandEnum::MORE_OR_EQUAL],
                ],
                order: [
                    'title',
                ],
            );

            foreach ($calendarEvents as $calendarEvent) {
                $games[] = [
                    'allrpg_id' => $calendarEvent['id'],
                    'name' => DataHelper::escapeOutput($calendarEvent['name']),
                ];
            }
        }

        $content = DataHelper::jsonFixedEncode($games);

        header('Access-Control-Allow-Origin: *');
        header('Content-Type: text/html;charset=UTF-8');

        echo $content;
        exit;
    }
}
