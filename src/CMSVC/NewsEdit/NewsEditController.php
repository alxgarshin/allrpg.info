<?php

declare(strict_types=1);

namespace App\CMSVC\NewsEdit;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\ResponseHelper;
use Fraym\Interface\Response;

/** @extends BaseController<NewsEditService> */
#[CMSVC(
    model: NewsEditModel::class,
    service: NewsEditService::class,
    view: NewsEditView::class,
)]
class NewsEditController extends BaseController
{
    public function Response(): ?Response
    {
        if ($this->service->checkRights()) {
            return parent::Response();
        }

        ResponseHelper::redirect('/start/');

        return null;
    }
}
