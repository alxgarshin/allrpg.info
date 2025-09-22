<?php

declare(strict_types=1);

namespace App\CMSVC\ArticlesEdit;

use Fraym\BaseObject\{BaseController, CMSVC, IsAdmin};

/** @extends BaseController<ArticlesEditService> */
#[CMSVC(
    model: ArticlesEditModel::class,
    service: ArticlesEditService::class,
    view: ArticlesEditView::class,
)]
#[IsAdmin('/start/')]
class ArticlesEditController extends BaseController
{
}
