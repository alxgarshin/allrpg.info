<?php

declare(strict_types=1);

namespace App\CMSVC\BannersEdit;

use Fraym\BaseObject\{BaseController, CMSVC, IsAdmin};

/** @extends BaseController<BannersEditService> */
#[CMSVC(
    model: BannersEditModel::class,
    service: BannersEditService::class,
    view: BannersEditView::class,
)]
#[IsAdmin('/start/')]
class BannersEditController extends BaseController
{
}
