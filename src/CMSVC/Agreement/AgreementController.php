<?php

declare(strict_types=1);

namespace App\CMSVC\Agreement;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Interface\Response;

#[CMSVC(
    view: AgreementView::class,
)]
class AgreementController extends BaseController
{
    public function Response(): ?Response
    {
        return $this->CMSVC->view->Response();
    }
}
