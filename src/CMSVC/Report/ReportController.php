<?php

declare(strict_types=1);

namespace App\CMSVC\Report;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Enum\ActEnum;
use Fraym\Helper\{DataHelper, ResponseHelper};
use Fraym\Interface\Response;

/** @extends BaseController<ReportService> */
#[CMSVC(
    model: ReportModel::class,
    service: ReportService::class,
    view: ReportView::class,
)]
class ReportController extends BaseController
{
    public function Response(): ?Response
    {
        if (!CURRENT_USER->isLogged() && DataHelper::getActDefault($this->getEntity()) === ActEnum::add) {
            ResponseHelper::redirect(
                '/login/',
                [
                    'redirectobj' => KIND,
                    'redirectparams' => 'act=' . ActEnum::add->value . '&calendar_event_id=' . (int) ($_REQUEST['calendar_event_id'] ?? 0),
                ],
            );
        }

        return parent::Response();
    }
}
