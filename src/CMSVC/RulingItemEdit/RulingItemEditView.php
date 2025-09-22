<?php

declare(strict_types=1);

namespace App\CMSVC\RulingItemEdit;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\SubstituteDataTypeEnum;
use Fraym\Interface\Response;

#[TableEntity(
    'rulingItemEdit',
    'ruling_item',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'ruling_tag_ids',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::TABLE,
            substituteDataTableName: 'ruling_tag',
            substituteDataTableId: 'id',
            substituteDataTableField: 'name',
        ),
    ],
    null,
    5000,
)]
#[Rights(
    viewRight: 'checkRights',
    addRight: 'checkRights',
    changeRight: 'checkRights',
    deleteRight: 'checkRightsDelete',
)]
#[Controller(RulingItemEditController::class)]
class RulingItemEditView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
