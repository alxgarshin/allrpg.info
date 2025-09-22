<?php

declare(strict_types=1);

namespace App\CMSVC\Photo;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Enum\{ActEnum, ActionEnum};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseController<PhotoService> */
#[CMSVC(
    model: PhotoModel::class,
    service: PhotoService::class,
    view: PhotoView::class,
)]
class PhotoController extends BaseController
{
    protected function Default(): ?Response
    {
        if (ACTION === ActionEnum::create) {
            return $this->getEntity()->fraymAction();
        }

        $responseData = $this->getEntity()->view(ActEnum::add);

        if ($responseData instanceof HtmlResponse) {
            $this->getEntity()->getView()->postViewHandler($responseData);
        }

        return $responseData;
    }
}
