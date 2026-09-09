<?php

declare(strict_types=1);

namespace App\CMSVC\Rooms;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
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
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('application_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function addNeighboor(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->addNeighboor(
                    (int) OBJ_ID,
                    $this->param('application_id'),
                ),
            );
        }

        return null;
    }
}
