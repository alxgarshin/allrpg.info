<?php

declare(strict_types=1);

namespace App\CMSVC\Logout;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\ResponseHelper;
use Fraym\Interface\Response;

#[CMSVC(
    controller: LogoutController::class,
)]
class LogoutController extends BaseController
{
    public function Response(): ?Response
    {
        if (CURRENT_USER->isLogged()) {
            ResponseHelper::redirect('/action=logout');
        } else {
            ResponseHelper::redirect('/');
        }

        return null;
    }
}
