<?php

declare(strict_types=1);

namespace App\CMSVC\Stats;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible, IsAdmin};
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
    public function exportToExcel(): void
    {
        if ($this->getService()->getStatsModel()) {
            $additionalIds = DataHelper::multiselectToArray($_REQUEST['additional_ids'] ?? '');

            /** @var StatsView */
            $statsView = $this->getCMSVC()->getView();

            $statsView->exportToExcel($additionalIds);
        }
    }

    #[IsAccessible(
        additionalCheckAccessHelper: StatsService::class,
        additionalCheckAccessMethod: 'checkCanChangeProfiles',
    )]
    public function setStatus(): ?Response
    {
        return $this->asArray(
            $this->getService()->setStatus(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible(
        additionalCheckAccessHelper: StatsService::class,
        additionalCheckAccessMethod: 'checkCanChangeProfiles',
    )]
    public function setAdditionalGroups(): ?Response
    {
        return $this->asArray(
            $this->getService()->setAdditionalGroups(
                OBJ_ID,
            ),
        );
    }
}
