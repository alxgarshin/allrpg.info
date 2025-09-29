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
            header('Location: ' . $redirectPath);
            exit;
        } else {
            header('Location: ' . ABSOLUTE_PATH . '/start/');
            exit;
        }
    }
}
