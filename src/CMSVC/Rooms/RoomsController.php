<?php

declare(strict_types=1);

namespace App\CMSVC\Rooms;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Interface\Response;

/** @extends BaseController<RoomsService> */
#[CMSVC(
    model: RoomsModel::class,
    service: RoomsService::class,
    view: RoomsView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectKindAccessAndRedirect',
)]
class RoomsController extends BaseController
{
    public function addNeighboor(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->getService()->addNeighboor(
                    (int) OBJ_ID,
                    (int) ($_REQUEST['application_id'] ?? false),
                ),
            );
        }

        return null;
    }
}
