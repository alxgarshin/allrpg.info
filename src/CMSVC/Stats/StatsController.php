<?php

declare(strict_types=1);

namespace App\CMSVC\Stats;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible, IsAdmin};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;

/** @extends BaseController<StatsService> */
#[CMSVC(
    service: StatsService::class,
    view: StatsView::class,
)]
#[IsAdmin('/start/')]
class StatsController extends BaseController
{
    #[ApiAction(params: [
        new ApiParam('additional_ids', ApiParamTypeEnum::string, default: ''),
    ])]
    public function exportToExcel(): void
    {
        if ($this->service->getStatsModel()) {
            $additionalIds = DataHelper::multiselectToArray($this->param('additional_ids'));

            /** @var StatsView */
            $statsView = $this->CMSVC->view;

            $statsView->exportToExcel($additionalIds);
        }
    }

    #[IsAccessible(
        additionalCheckAccessHelper: StatsService::class,
        additionalCheckAccessMethod: 'checkCanChangeProfiles',
    )]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function setStatus(): ?Response
    {
        return $this->asArray(
            $this->service->setStatus(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible(
        additionalCheckAccessHelper: StatsService::class,
        additionalCheckAccessMethod: 'checkCanChangeProfiles',
    )]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function setAdditionalGroups(): ?Response
    {
        return $this->asArray(
            $this->service->setAdditionalGroups(
                OBJ_ID,
            ),
        );
    }
}
