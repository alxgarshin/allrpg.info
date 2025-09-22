<?php

declare(strict_types=1);

namespace App\CMSVC\Faq;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\ResponseHelper;

#[CMSVC(
    controller: self::class,
)]
class FaqController extends BaseController
{
    public function Default(): null
    {
        ResponseHelper::redirect(ABSOLUTE_PATH . '/publication/155/');
        exit;
    }
}
