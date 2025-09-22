<?php

declare(strict_types=1);

namespace App\CMSVC\ExchangeCategoryEdit;

use Fraym\BaseObject\{BaseController, CMSVC, IsAdmin};

/** @extends BaseController<ExchangeCategoryEditService> */
#[CMSVC(
    model: ExchangeCategoryEditModel::class,
    service: ExchangeCategoryEditService::class,
    view: ExchangeCategoryEditView::class,
)]
#[IsAdmin('/start/')]
class ExchangeCategoryEditController extends BaseController
{
}
