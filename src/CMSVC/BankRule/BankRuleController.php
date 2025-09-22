<?php

declare(strict_types=1);

namespace App\CMSVC\BankRule;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<BankRuleService> */
#[CMSVC(
    model: BankRuleModel::class,
    service: BankRuleService::class,
    view: BankRuleView::class,
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
class BankRuleController extends BaseController
{
}
