<?php

declare(strict_types=1);

namespace App\CMSVC\BankRule;

use App\CMSVC\Trait\ProjectSectionsPostViewHandlerTrait;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights};
use Fraym\Enum\TableFieldOrderEnum;
use Fraym\Interface\Response;

#[MultiObjectsEntity(
    name: 'bankRule',
    table: 'bank_rule',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'created_at',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
        ),
    ],
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: true,
    deleteRight: true,
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(BankRuleController::class)]
class BankRuleView extends BaseView
{
    use ProjectSectionsPostViewHandlerTrait;

    public function Response(): ?Response
    {
        return null;
    }
}
