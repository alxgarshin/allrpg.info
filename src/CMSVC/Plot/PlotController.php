<?php

declare(strict_types=1);

namespace App\CMSVC\Plot;

use App\CMSVC\Group\GroupService;
use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
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
    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getListOfPlotSides(): ?Response
    {
        return $this->asArray(
            [
                'response' => 'success',
                'response_data' => $this->service->getApplicationsListInPlot(OBJ_ID),
            ],
        );
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
    ])]
    public function getListOfGroupsByCharacterOrApplication(): ?Response
    {
        /** @var GroupService */
        $groupService = CMSVCHelper::getService('group');

        return $this->asArray(
            $groupService->getListOfGroupsByCharacterOrApplication(OBJ_ID, OBJ_TYPE),
        );
    }
}
