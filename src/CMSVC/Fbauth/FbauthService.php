<?php

declare(strict_types=1);

namespace App\CMSVC\Fbauth;

use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\ResponseHelper;

#[Controller(FbauthController::class)]
class FbauthService extends BaseService
{
    public function outputRedirect(): void
    {
        $redirectPath = ResponseHelper::createRedirect();

        if ($redirectPath !== '') {
            ResponseHelper::redirect($redirectPath);
        } else {
            ResponseHelper::redirect(ABSOLUTE_PATH . '/start/');
        }
    }
}
