<?php

declare(strict_types=1);

namespace App\CMSVC\Area;

use Fraym\BaseObject\{BaseController, CMSVC};

/** @extends BaseController<AreaService> */
#[CMSVC(
    model: AreaModel::class,
    service: AreaService::class,
    view: AreaView::class,
)]
class AreaController extends BaseController
{
}
