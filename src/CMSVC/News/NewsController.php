<?php

declare(strict_types=1);

namespace App\CMSVC\News;

use Fraym\BaseObject\{BaseController, CMSVC};

/** @extends BaseController<NewsService> */
#[CMSVC(
    service: NewsService::class,
    view: NewsView::class,
)]
class NewsController extends BaseController
{
}
