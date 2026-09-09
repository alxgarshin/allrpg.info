<?php

declare(strict_types=1);

namespace App\CMSVC\Budget;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Interface\Response;

/** @extends BaseController<BudgetService> */
#[CMSVC(
    model: BudgetModel::class,
    service: BudgetService::class,
    view: BudgetView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectActionAccessBudget',
)]
class BudgetController extends BaseController
{
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('after_obj_id', ApiParamTypeEnum::int, default: 0),
    ])]
    public function changeBudgetCode(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->changeBudgetCode(
                    OBJ_ID,
                    $this->param('after_obj_id'),
                ),
            );
        }

        return null;
    }
}
