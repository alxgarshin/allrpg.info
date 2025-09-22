<?php

declare(strict_types=1);

namespace App\CMSVC\Eventlist;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<EventlistService> */
#[CMSVC(
    service: EventlistService::class,
    view: EventlistView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
    ],
)]
class EventlistController extends BaseController
{
}
