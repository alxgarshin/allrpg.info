<?php

declare(strict_types=1);

namespace App\CMSVC\Geoposition;

use App\CMSVC\Trait\{ProjectDataTrait, UserServiceTrait};
use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\DataHelper;

#[Controller(GeopositionController::class)]
class GeopositionService extends BaseService
{
    use ProjectDataTrait;
    use UserServiceTrait;

    /** Получение данных по геопозиции пользователей */
    public function getPlayersGeoposition(): array
    {
        $userService = $this->getUserService();

        $result = [];

        $projectRights = RightsHelper::checkProjectRights();

        if (DataHelper::inArrayAny(['{admin}', '{gamemaster}'], $projectRights)) {
            $projectApplicationGeopositionData = DB->query(
                'SELECT
                    pag.longitude,
                    pag.latitude,
                    pag.created_at AS pag_created,
                    pa.sorter,
                    pa.id AS application_id,
                    u.*
                FROM
                    project_application_geoposition AS pag INNER JOIN
                        (
                            SELECT
                                project_application_id,
                                MAX(id) AS id
                            FROM
                                project_application_geoposition
                            WHERE
                                project_id=:project_id
                            GROUP BY
                                project_application_id
                        ) AS pag2 ON
                            pag2.id=pag.id
                            LEFT JOIN
                    project_application AS pa ON pa.id=pag.project_application_id LEFT JOIN
                    user AS u ON u.id=pa.creator_id',
                [
                    ['project_id', $this->getActivatedProjectId()],
                ],
            );

            foreach ($projectApplicationGeopositionData as $pag) {
                $result[] = [
                    'name' => DataHelper::escapeOutput($pag['sorter']),
                    'coords_longitude' => $pag['longitude'],
                    'coords_latitude' => $pag['latitude'],
                    'photo' => $userService->photoUrl($userService->arrayToModel($pag)),
                    'player' => $userService->showNameWithId($userService->arrayToModel($pag), true),
                    'active' => (time() - $pag['pag_created'] <= 60),
                    'last_active' => date('d.m.Y H:i:s', $pag['pag_created']),
                    'application_id' => $pag['application_id'],
                ];
            }
        }

        return [
            'response' => 'success',
            'response_data' => $result,
        ];
    }
}
