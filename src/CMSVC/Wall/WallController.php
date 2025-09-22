<?php

declare(strict_types=1);

namespace App\CMSVC\Wall;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<WallService> */
#[IsAccessible(
    '/login/',
    [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
    ],
)]
#[CMSVC(
    service: WallService::class,
    view: WallView::class,
)]
class WallController extends BaseController
{
}
