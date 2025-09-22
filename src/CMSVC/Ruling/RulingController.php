<?php

declare(strict_types=1);

namespace App\CMSVC\Ruling;

use App\CMSVC\Trait\RequestCheckSearchTrait;
use Fraym\BaseObject\{BaseController, CMSVC};
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
        $view = $this->getCMSVC()->getView();

        return $view->Response(
            ($_REQUEST['view_all'] ?? false) === '1' || ($_REQUEST['ruling_tag'] ?? 0) > 0,
            (int) ($_REQUEST['ruling_tag'] ?? 0),
            $_REQUEST['search'] ?? '',
        );
    }

    public function Fillform(): ?Response
    {
        /** @var RulingView $view */
        $view = $this->getCMSVC()->getView();

        return $view->Response(
            false,
            0,
            '',
            true,
        );
    }

    public function Generate(): ?Response
    {
        /** @var RulingView $view */
        $view = $this->getCMSVC()->getView();

        return $view->Generate(($_REQUEST['print_mode'] ?? false) === '1');
    }
}
