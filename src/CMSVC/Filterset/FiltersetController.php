<?php

declare(strict_types=1);

namespace App\CMSVC\Filterset;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<FiltersetService> */
#[CMSVC(
    model: FiltersetModel::class,
    service: FiltersetService::class,
    view: FiltersetView::class,
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
class FiltersetController extends BaseController
{
}
