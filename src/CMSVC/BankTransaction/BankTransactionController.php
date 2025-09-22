<?php

declare(strict_types=1);

namespace App\CMSVC\BankTransaction;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<BankTransactionService> */
#[CMSVC(
    model: BankTransactionModel::class,
    service: BankTransactionService::class,
    view: BankTransactionView::class,
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
class BankTransactionController extends BaseController
{
}
