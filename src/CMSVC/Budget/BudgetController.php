<?php

declare(strict_types=1);

namespace App\CMSVC\Budget;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
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
    public function changeBudgetCode(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->changeBudgetCode(
                    OBJ_ID,
                    (int) ($_REQUEST['after_obj_id'] ?? false),
                ),
            );
        }

        return null;
    }
}
