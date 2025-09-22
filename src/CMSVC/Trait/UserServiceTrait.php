<?php

declare(strict_types=1);

namespace App\CMSVC\Trait;

use App\CMSVC\User\UserService;
use Fraym\Helper\CMSVCHelper;

/** Ленивая подгрузка UserService*/
trait UserServiceTrait
{
    private ?UserService $userService = null;

    public function getUserService(): ?UserService
    {
        if (is_null($this->userService)) {
            /** @var UserService */
            $userService = CMSVCHelper::getService('user');

            $this->userService = $userService;
        }

        return $this->userService;
    }
}
