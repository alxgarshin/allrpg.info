<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgCode;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<QrpgCodeService> */
#[CMSVC(
    model: QrpgCodeModel::class,
    service: QrpgCodeService::class,
    view: QrpgCodeView::class,
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
class QrpgCodeController extends BaseController
{
}
