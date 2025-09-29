<?php

declare(strict_types=1);

namespace App\CMSVC\Vkauth;

use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\ResponseHelper;

#[Controller(VkauthController::class)]
class VkauthService extends BaseService
{
    public function outputRedirect(): void
    {
        $redirectPath = ResponseHelper::createRedirect();

        if ($redirectPath) {
            ResponseHelper::redirect($redirectPath);
        } else {
            ResponseHelper::redirect(ABSOLUTE_PATH . '/start/');
        }
    }
}
