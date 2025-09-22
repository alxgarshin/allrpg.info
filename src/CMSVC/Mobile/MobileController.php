<?php

declare(strict_types=1);

namespace App\CMSVC\Mobile;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Interface\Response;

#[CMSVC(
    view: MobileView::class,
)]
class MobileController extends BaseController
{
    public function Response(): ?Response
    {
        return $this->getCMSVC()->getView()->Response();
    }
}
