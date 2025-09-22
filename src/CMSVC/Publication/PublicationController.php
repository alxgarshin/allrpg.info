<?php

declare(strict_types=1);

namespace App\CMSVC\Publication;

use App\CMSVC\Trait\RequestCheckSearchTrait;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Interface\Response;

/** @extends BaseController<PublicationService> */
#[CMSVC(
    service: PublicationService::class,
    view: PublicationView::class,
)]
class PublicationController extends BaseController
{
    use RequestCheckSearchTrait;

    public function Response(): ?Response
    {
        $this->requestCheckSearch();

        return $this->getCMSVC()->getView()->Response();
    }
}
