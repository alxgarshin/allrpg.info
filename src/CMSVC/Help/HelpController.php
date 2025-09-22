<?php

declare(strict_types=1);

namespace App\CMSVC\Help;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Enum\{ActEnum, ActionEnum};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseController<HelpService> */
#[CMSVC(
    model: HelpModel::class,
    service: HelpService::class,
    view: HelpView::class,
)]
class HelpController extends BaseController
{
    public function Response(): ?Response
    {
        if (ACTION === ActionEnum::create) {
            return $this->getEntity()->fraymAction();
        }

        $responseData = $this->getEntity()->view(ActEnum::add);

        if ($responseData instanceof HtmlResponse) {
            $this->getEntity()->getView()->postViewHandler($responseData);
        }

        return $responseData;
    }
}
