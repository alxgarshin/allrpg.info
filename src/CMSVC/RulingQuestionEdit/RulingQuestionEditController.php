<?php

declare(strict_types=1);

namespace App\CMSVC\RulingQuestionEdit;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\{CMSVCHelper, ResponseHelper};
use Fraym\Interface\Response;

/** @extends BaseController<RulingQuestionEditService> */
#[CMSVC(
    model: RulingQuestionEditModel::class,
    service: RulingQuestionEditService::class,
    view: RulingQuestionEditView::class,
)]
class RulingQuestionEditController extends BaseController
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
