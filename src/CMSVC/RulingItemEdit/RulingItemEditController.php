<?php

declare(strict_types=1);

namespace App\CMSVC\RulingItemEdit;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\{CMSVCHelper, ResponseHelper};
use Fraym\Interface\Response;

/** @extends BaseController<RulingItemEditService> */
#[CMSVC(
    model: RulingItemEditModel::class,
    service: RulingItemEditService::class,
    view: RulingItemEditView::class,
)]
class RulingItemEditController extends BaseController
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
