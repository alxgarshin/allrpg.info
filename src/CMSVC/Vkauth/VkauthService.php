<?php

declare(strict_types=1);

namespace App\CMSVC\Vkauth;

use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\ResponseHelper;
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

#[Controller(VkauthController::class)]
class VkauthService extends BaseService
{
    public function outputRedirect(): ?Response
    {
        $redirectPath = ResponseHelper::createRedirect();

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<script>
	window.location="' . ($redirectPath ?? ABSOLUTE_PATH . '/start/') . '";
</script>
</div>';

        return new HtmlResponse($RESPONSE_DATA);
    }
}
