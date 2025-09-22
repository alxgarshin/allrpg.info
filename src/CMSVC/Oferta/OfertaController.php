<?php

declare(strict_types=1);

namespace App\CMSVC\Oferta;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Interface\Response;

#[CMSVC(
    view: OfertaView::class,
)]
class OfertaController extends BaseController
{
    public function Response(): ?Response
    {
        return $this->getCMSVC()->getView()->Response();
    }
}
