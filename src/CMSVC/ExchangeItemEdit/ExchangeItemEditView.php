<?php

declare(strict_types=1);

namespace App\CMSVC\ExchangeItemEdit;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{SubstituteDataTypeEnum, TableFieldOrderEnum};
use Fraym\Interface\Response;

#[TableEntity(
    'exchangeItemEdit',
    'exchange_item',
    [
        new EntitySortingItem(
            tableFieldName: 'updated_at',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            showFieldDataInEntityTable: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'exchange_category_ids',
            substituteDataType: SubstituteDataTypeEnum::TABLE,
            substituteDataTableName: 'exchange_category',
            substituteDataTableId: 'id',
            substituteDataTableField: 'name',
        ),
    ],
)]
#[Rights(
    viewRight: 'checkRights',
    addRight: 'checkRights',
    changeRight: 'checkRights',
    deleteRight: 'checkRights',
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(ExchangeItemEditController::class)]
class ExchangeItemEditView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
