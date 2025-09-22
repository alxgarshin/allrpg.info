<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgKey;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<QrpgKeyService> */
#[CMSVC(
    model: QrpgKeyModel::class,
    service: QrpgKeyService::class,
    view: QrpgKeyView::class,
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
class QrpgKeyController extends BaseController
{
}
