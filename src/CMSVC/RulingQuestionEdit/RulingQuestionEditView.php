<?php

declare(strict_types=1);

namespace App\CMSVC\RulingQuestionEdit;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\SubstituteDataTypeEnum;
use Fraym\Interface\Response;

#[TableEntity(
    'rulingQuestionEdit',
    'ruling_question',
    [
        new EntitySortingItem(
            tableFieldName: 'id',
            showFieldDataInEntityTable: false,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortId',
        ),
        new EntitySortingItem(
            tableFieldName: 'code',
        ),
        new EntitySortingItem(
            tableFieldName: 'field_name',
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
#[Controller(RulingQuestionEditController::class)]
class RulingQuestionEditView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
