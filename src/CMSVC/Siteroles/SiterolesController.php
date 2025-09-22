<?php

declare(strict_types=1);

namespace App\CMSVC\Siteroles;

use Fraym\BaseObject\BaseController;
use Fraym\Helper\{DataHelper, ResponseHelper};
use Fraym\Interface\Response;

class SiterolesController extends BaseController
{
    public function Response(): ?Response
    {
        ResponseHelper::redirect(ABSOLUTE_PATH . '/roles/' . DataHelper::getId() . '/' . (($_REQUEST['orders'] ?? false) === '1' ? 'orders=1' : ''));

        return null;
    }
}
