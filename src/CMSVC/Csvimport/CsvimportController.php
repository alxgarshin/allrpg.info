<?php

declare(strict_types=1);

namespace App\CMSVC\Csvimport;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\ApiParamTypeEnum;
use Fraym\Helper\ResponseHelper;
use Fraym\Interface\Response;

/** @extends BaseController<CsvimportService> */
#[CMSVC(
    service: CsvimportService::class,
    view: CsvimportView::class,
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
class CsvimportController extends BaseController
{
    #[ApiAction(mutating: true, params: [
        new ApiParam('attachments', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function importCharacters(): ?Response
    {
        if (PRE_REQUEST_CHECK) {
            if ($this->param('attachments') !== '') {
                return ResponseHelper::response([], 'submit');
            } else {
                $LOCALE = $this->LOCALE;
                ResponseHelper::responseOneBlock('error', $LOCALE['messages']['no_file_selected'], ['attachments']);
            }
        }

        $this->service->importCharacters();

        return $this->CMSVC->view->Response();
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('attachments', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function importApplications(): ?Response
    {
        if (PRE_REQUEST_CHECK) {
            if ($this->param('attachments') !== '') {
                return ResponseHelper::response([], 'submit');
            } else {
                $LOCALE = $this->LOCALE;
                ResponseHelper::responseOneBlock('error', $LOCALE['messages']['no_file_selected'], ['attachments']);
            }
        }

        $this->service->importApplications();

        return $this->CMSVC->view->Response();
    }
}
