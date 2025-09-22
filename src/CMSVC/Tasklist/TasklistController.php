<?php

declare(strict_types=1);

namespace App\CMSVC\Tasklist;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Interface\Response;

#[CMSVC(
    view: TasklistView::class,
)]
#[IsAccessible]
class TasklistController extends BaseController
{
    public function Response(): ?Response
    {
        return $this->getCMSVC()->getView()->Response();
    }
}
