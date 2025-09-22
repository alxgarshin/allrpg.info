<?php

declare(strict_types=1);

namespace App\CMSVC\BannersEdit;

use Fraym\BaseObject\{BaseService, Controller};

/** @extends BaseService<BannersEditModel> */
#[Controller(BannersEditController::class)]
class BannersEditService extends BaseService
{
}
