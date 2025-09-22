<?php

declare(strict_types=1);

namespace App\CMSVC\Start;

use Fraym\BaseObject\{BaseController, CMSVC};

#[CMSVC(
    view: StartView::class,
)]
class StartController extends BaseController
{
}
