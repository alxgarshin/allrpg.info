<?php

declare(strict_types=1);

namespace App\CMSVC\Search;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\ResponseHelper;
use Fraym\Interface\Response;

/** @extends BaseController<SearchService> */
#[CMSVC(
    service: SearchService::class,
    view: SearchView::class,
)]
class SearchController extends BaseController
{
    public function Response(): ?Response
    {
        /** @var SearchService $searchService */
        $searchService = $this->service;

        if (PRE_REQUEST_CHECK) {
            if (!$searchService->checkIfSearch()) {
                $LOCALE = $this->LOCALE;
                ResponseHelper::responseOneBlock('error', $LOCALE['messages']['need_more_symbols']);
            } else {
                return ResponseHelper::response([], 'submit');
            }
        }

        return $this->CMSVC->view->Response();
    }
}
