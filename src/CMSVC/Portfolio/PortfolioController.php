<?php

declare(strict_types=1);

namespace App\CMSVC\Portfolio;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<PortfolioService> */
#[IsAccessible(
    '/login/',
    [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
    ],
)]
#[CMSVC(
    model: PortfolioModel::class,
    service: PortfolioService::class,
    view: PortfolioView::class,
)]
class PortfolioController extends BaseController
{
}
