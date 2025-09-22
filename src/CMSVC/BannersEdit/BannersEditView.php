<?php

declare(strict_types=1);

namespace App\CMSVC\BannersEdit;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights};
use Fraym\Enum\TableFieldOrderEnum;
use Fraym\Interface\Response;

#[MultiObjectsEntity(
    'bannersEdit',
    'banner',
    [
        new EntitySortingItem(
            tableFieldName: 'active',
            tableFieldOrder: TableFieldOrderEnum::DESC,
        ),
        new EntitySortingItem(
            tableFieldName: 'id',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            showFieldDataInEntityTable: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
    ],
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: true,
    deleteRight: true,
)]
#[Controller(BannersEditController::class)]
class BannersEditView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
