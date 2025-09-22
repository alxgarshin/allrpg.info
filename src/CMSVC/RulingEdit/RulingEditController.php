<?php

declare(strict_types=1);

namespace App\CMSVC\RulingEdit;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\{CMSVCHelper, ResponseHelper};
use Fraym\Interface\Response;

/** @extends BaseController<RulingEditService> */
#[CMSVC(
    service: RulingEditService::class,
    view: RulingEditView::class,
)]
class RulingEditController extends BaseController
{
    public function Response(): ?Response
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        if ($userService->isRulingAdmin()) {
            return $this->getCMSVC()->getView()->Response();
        }
        ResponseHelper::redirect('/start/');

        return null;
    }
}
