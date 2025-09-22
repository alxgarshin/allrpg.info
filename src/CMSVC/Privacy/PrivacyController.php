<?php

declare(strict_types=1);

namespace App\CMSVC\Privacy;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Interface\Response;

#[CMSVC(
    view: PrivacyView::class,
)]
class PrivacyController extends BaseController
{
    public function Response(): ?Response
    {
        return $this->getCMSVC()->getView()->Response();
    }
}
