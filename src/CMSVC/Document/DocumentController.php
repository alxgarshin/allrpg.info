<?php

declare(strict_types=1);

namespace App\CMSVC\Document;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
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
    public function generateDocuments(): ?Response
    {
        if (PRE_REQUEST_CHECK) {
            if (($this->getService()->getApplicationsByFilter() || count($this->getService()->getAapplicationsByIds()) > 0)  && (int) ($_REQUEST['template_id'] ?? false) > 0) {
                return ResponseHelper::response([], 'submit');
            } else {
                $LOCALE = $this->getLOCALE();
                ResponseHelper::responseOneBlock('error', $LOCALE['messages']['no_application_selected'], ['application_id[0]']);
            }
        }

        if ((!$this->getService()->getApplicationsByFilter() && count($this->getService()->getAapplicationsByIds()) === 0)) {
            ResponseHelper::redirect(ABSOLUTE_PATH . '/' . KIND . '/');
        }

        /** @var DocumentView */
        $documentView = $this->getCMSVC()->getView();

        $documentView->generateDocuments();

        return null;
    }
}
