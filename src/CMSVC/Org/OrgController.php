<?php

declare(strict_types=1);

namespace App\CMSVC\Org;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<OrgService> */
#[CMSVC(
    model: OrgModel::class,
    service: OrgService::class,
    view: OrgView::class,
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
class OrgController extends BaseController
{
}
