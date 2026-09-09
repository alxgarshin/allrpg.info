<?php

declare(strict_types=1);

namespace App\CMSVC\Ruling;

use App\CMSVC\Trait\RequestCheckSearchTrait;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC};
use Fraym\Enum\ApiParamTypeEnum;
use Fraym\Interface\Response;

/** @extends BaseController<RulingService> */
#[CMSVC(
    service: RulingService::class,
    view: RulingView::class,
)]
class RulingController extends BaseController
{
    use RequestCheckSearchTrait;

    public function Response(): ?Response
    {
        $this->requestCheckSearch();

        /** @var RulingView $view */
        $view = $this->CMSVC->view;

        return $view->Response(
            ($_REQUEST['view_all'] ?? false) === '1' || ($_REQUEST['ruling_tag'] ?? 0) > 0,
            (int) ($_REQUEST['ruling_tag'] ?? 0),
            $_REQUEST['search'] ?? '',
        );
    }

    #[ApiAction]
    public function Fillform(): ?Response
    {
        /** @var RulingView $view */
        $view = $this->CMSVC->view;

        return $view->Response(
            false,
            0,
            '',
            true,
        );
    }

    #[ApiAction(params: [
        new ApiParam('print_mode', ApiParamTypeEnum::bool, default: false),
    ])]
    public function Generate(): ?Response
    {
        /** @var RulingView $view */
        $view = $this->CMSVC->view;

        return $view->Generate($this->param('print_mode'));
    }
}
