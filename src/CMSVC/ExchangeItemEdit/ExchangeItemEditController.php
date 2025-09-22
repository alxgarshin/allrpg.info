<?php

declare(strict_types=1);

namespace App\CMSVC\ExchangeItemEdit;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<ExchangeItemEditService> */
#[IsAccessible(
    '/login/',
    [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
    ],
)]
#[CMSVC(
    model: ExchangeItemEditModel::class,
    service: ExchangeItemEditService::class,
    view: ExchangeItemEditView::class,
)]
class ExchangeItemEditController extends BaseController
{
}
