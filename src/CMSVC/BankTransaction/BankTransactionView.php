<?php

declare(strict_types=1);

namespace App\CMSVC\BankTransaction;

use App\CMSVC\Trait\ProjectSectionsPostViewHandlerTrait;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights};
use Fraym\Enum\TableFieldOrderEnum;
use Fraym\Interface\Response;

#[MultiObjectsEntity(
    name: 'bankTransaction',
    table: 'bank_transaction',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'created_at',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
        ),
    ],
    elementsPerPage: 1000,
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: false,
    deleteRight: true,
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(BankTransactionController::class)]
class BankTransactionView extends BaseView
{
    use ProjectSectionsPostViewHandlerTrait;

    public function Response(): ?Response
    {
        return null;
    }
}
