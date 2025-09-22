<?php

declare(strict_types=1);

namespace App\CMSVC\Wall2;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<Wall2Service> */
#[IsAccessible(
    '/login/',
    [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
    ],
)]
#[CMSVC(
    service: Wall2Service::class,
    view: Wall2View::class,
)]
class Wall2Controller extends BaseController
{
}
