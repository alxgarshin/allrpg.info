<?php

declare(strict_types=1);

namespace App\CMSVC\Fee;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<FeeService> */
#[CMSVC(
    model: FeeModel::class,
    service: FeeService::class,
    view: FeeView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectActionAccessFee',
)]
class FeeController extends BaseController
{
}
