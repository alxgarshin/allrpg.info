<?php

declare(strict_types=1);

namespace App\CMSVC\PublicationsEdit;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<PublicationsEditService> */
#[CMSVC(
    model: PublicationsEditModel::class,
    service: PublicationsEditService::class,
    view: PublicationsEditView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
    ],
)]
class PublicationsEditController extends BaseController
{
}
