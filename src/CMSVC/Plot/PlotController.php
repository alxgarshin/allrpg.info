<?php

declare(strict_types=1);

namespace App\CMSVC\Plot;

use App\CMSVC\Group\GroupService;
use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Helper\CMSVCHelper;
use Fraym\Interface\Response;

/** @extends BaseController<PlotService> */
#[CMSVC(
    model: PlotModel::class,
    service: PlotService::class,
    view: PlotView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectToObject' => CMSVC,
        'redirectToId' => ID,
        'redirectParams' => 'act=edit',
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectKindAccessAndRedirect',
)]
class PlotController extends BaseController
{
    public function getListOfPlotSides(): ?Response
    {
        return $this->asArray(
            [
                'response' => 'success',
                'response_data' => $this->service->getApplicationsListInPlot(OBJ_ID),
            ],
        );
    }

    public function getListOfGroupsByCharacterOrApplication(): ?Response
    {
        /** @var GroupService */
        $groupService = CMSVCHelper::getService('group');

        return $this->asArray(
            $groupService->getListOfGroupsByCharacterOrApplication(OBJ_ID, OBJ_TYPE),
        );
    }
}
