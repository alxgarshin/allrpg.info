<?php

declare(strict_types=1);

namespace App\CMSVC\Go;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\{DataHelper, ResponseHelper};
use Fraym\Interface\Response;

#[CMSVC(
    controller: GoController::class,
)]
class GoController extends BaseController
{
    public function Response(): ?Response
    {
        $forProjectId = DataHelper::getId();

        $characterId = false;

        foreach ($_REQUEST as $key => $value) {
            if (is_numeric($key) && $value === '') {
                $characterId = $key;
                break;
            }
        }

        ResponseHelper::redirect(ABSOLUTE_PATH . '/myapplication/for_project_id=' . $forProjectId . ($characterId ? '&character_id=' . $characterId : ''));

        return null;
    }
}
