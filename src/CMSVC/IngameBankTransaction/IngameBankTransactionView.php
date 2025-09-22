<?php

declare(strict_types=1);

namespace App\CMSVC\IngameBankTransaction;

use Fraym\BaseObject\BaseView;
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
    deleteRight: false,
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]

class IngameBankTransactionView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
