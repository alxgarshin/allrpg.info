<?php

declare(strict_types=1);

namespace App\CMSVC\BankCurrency;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<BankCurrencyService> */
#[CMSVC(
    model: BankCurrencyModel::class,
    service: BankCurrencyService::class,
    view: BankCurrencyView::class,
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
class BankCurrencyController extends BaseController
{
}
