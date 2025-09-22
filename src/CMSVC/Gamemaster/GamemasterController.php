<?php

declare(strict_types=1);

namespace App\CMSVC\Gamemaster;

use Fraym\BaseObject\{BaseController, CMSVC};

/** @extends BaseController<GamemasterService> */
#[CMSVC(
    service: GamemasterService::class,
    view: GamemasterView::class,
)]
class GamemasterController extends BaseController
{
}
