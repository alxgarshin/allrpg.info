<?php

declare(strict_types=1);

namespace App\CMSVC\Geoposition;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Interface\Response;

/** @extends BaseController<GeopositionService> */
#[CMSVC(
    service: GeopositionService::class,
    view: GeopositionView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectKindAccessAndRedirect',
)]
class GeopositionController extends BaseController
{
    public function getPlayersGeoposition(): ?Response
    {
        return $this->asArray(
            $this->service->getPlayersGeoposition(),
        );
    }
}
