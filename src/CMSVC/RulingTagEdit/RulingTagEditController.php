<?php

declare(strict_types=1);

namespace App\CMSVC\RulingTagEdit;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\{CMSVCHelper, ResponseHelper};
use Fraym\Interface\Response;

/** @extends BaseController<RulingTagEditService> */
#[CMSVC(
    model: RulingTagEditModel::class,
    service: RulingTagEditService::class,
    view: RulingTagEditView::class,
)]
class RulingTagEditController extends BaseController
{
    public function Response(): ?Response
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        if ($userService->isRulingAdmin()) {
            return parent::Response();
        }
        ResponseHelper::redirect('/start/');

        return null;
    }
}
