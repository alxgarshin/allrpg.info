<?php

declare(strict_types=1);

namespace App\CMSVC\Trait;

use Fraym\Response\HtmlResponse;

/** Стандартная постобработка разделов управления проектом */
trait ProjectSectionsPostViewHandlerTrait
{
    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $RESPONSE_DATA = $response->getHtml();

        $RESPONSE_DATA = $this->additionalPostViewHandler($RESPONSE_DATA);

        return $response->setHtml($RESPONSE_DATA);
    }

    public function additionalPostViewHandler(string $RESPONSE_DATA): string
    {
        return $RESPONSE_DATA;
    }
}
