<?php

declare(strict_types=1);

namespace App\CMSVC\Document;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\ApiParamTypeEnum;
use Fraym\Helper\ResponseHelper;
use Fraym\Interface\Response;

/** @extends BaseController<DocumentService> */
#[CMSVC(
    model: DocumentModel::class,
    service: DocumentService::class,
    view: DocumentView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectKindAccessAndRedirect',
)]
class DocumentController extends BaseController
{
    #[ApiAction(params: [
        new ApiParam('template_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('application_id[0]', ApiParamTypeEnum::array, obligatory: true),
    ])]
    public function generateDocuments(): ?Response
    {
        if (PRE_REQUEST_CHECK) {
            if (($this->service->getApplicationsByFilter() || count($this->service->getAapplicationsByIds()) > 0)  && $this->param('template_id') > 0) {
                return ResponseHelper::response([], 'submit');
            } else {
                $LOCALE = $this->LOCALE;
                ResponseHelper::responseOneBlock('error', $LOCALE['messages']['no_application_selected'], ['application_id[0]']);
            }
        }

        if ((!$this->service->getApplicationsByFilter() && count($this->service->getAapplicationsByIds()) === 0)) {
            ResponseHelper::redirect(ABSOLUTE_PATH . '/' . KIND . '/');
        }

        /** @var DocumentView */
        $documentView = $this->CMSVC->view;

        $documentView->generateDocuments();

        return null;
    }
}
