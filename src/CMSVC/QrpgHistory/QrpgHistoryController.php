<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgHistory;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<QrpgHistoryService> */
#[CMSVC(
    model: QrpgHistoryModel::class,
    service: QrpgHistoryService::class,
    view: QrpgHistoryView::class,
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
class QrpgHistoryController extends BaseController
{
}
