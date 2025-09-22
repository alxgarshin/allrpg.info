<?php

declare(strict_types=1);

namespace App\CMSVC\Portfolio;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\SubstituteDataTypeEnum;
use Fraym\Interface\Response;

#[TableEntity(
    'portfolio',
    'played',
    [
        new EntitySortingItem(
            tableFieldName: 'id',
            showFieldDataInEntityTable: false,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortId',
        ),
        new EntitySortingItem(
            tableFieldName: 'calendar_event_id',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::TABLE,
            substituteDataTableName: 'calendar_event',
            substituteDataTableId: 'id',
            substituteDataTableField: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'id',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortId',
        ),
    ],
    null,
    5000,
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
#[Controller(PortfolioController::class)]
class PortfolioView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
