<?php

declare(strict_types=1);

namespace App\CMSVC\Exchange;

use App\CMSVC\Trait\RequestCheckSearchTrait;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Interface\Response;

/** @extends BaseController<ExchangeService> */
#[CMSVC(
    service: ExchangeService::class,
    view: ExchangeView::class,
)]
class ExchangeController extends BaseController
{
    use RequestCheckSearchTrait;

    public function Response(): ?Response
    {
        $this->requestCheckSearch();

        return $this->CMSVC->view->Response();
    }
}
