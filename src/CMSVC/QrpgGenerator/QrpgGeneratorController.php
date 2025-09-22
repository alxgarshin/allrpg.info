<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgGenerator;

use Fraym\BaseObject\{BaseController, CMSVC};

/** @extends BaseController<QrpgGeneratorService> */
#[CMSVC(
    service: QrpgGeneratorService::class,
    view: QrpgGeneratorView::class,
)]
class QrpgGeneratorController extends BaseController
{
}
