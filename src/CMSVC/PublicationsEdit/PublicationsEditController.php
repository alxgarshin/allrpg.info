<?php

declare(strict_types=1);

namespace App\CMSVC\PublicationsEdit;

use Fraym\BaseObject\{BaseController, CMSVC};

/** @extends BaseController<PublicationsEditService> */
#[CMSVC(
    model: PublicationsEditModel::class,
    service: PublicationsEditService::class,
    view: PublicationsEditView::class,
)]
class PublicationsEditController extends BaseController
{
}
