<?php

declare(strict_types=1);

namespace App\CMSVC\Fee;

use App\CMSVC\Fee\FeeOption\FeeOptionModel;
use App\CMSVC\Trait\ProjectSectionsPostViewHandlerTrait;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{CatalogEntity, CatalogItemEntity, EntitySortingItem, Rights};
use Fraym\Interface\Response;

#[CatalogEntity(
    name: 'fee',
    table: 'project_fee',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'name',
            showFieldShownNameInCatalogItemString: false,
        ),
    ],
    elementsPerPage: 5000,
)]
#[CatalogItemEntity(
    name: 'feeOption',
    table: 'project_fee',
    catalogItemModelClass: FeeOptionModel::class,
    tableFieldWithParentId: 'parent',
    tableFieldToDetectType: 'content',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'date_from',
            showFieldDataInEntityTable: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'cost',
        ),
        new EntitySortingItem(
            tableFieldName: 'date_from',
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
#[Controller(FeeController::class)]
class FeeView extends BaseView
{
    use ProjectSectionsPostViewHandlerTrait;

    public function Response(): ?Response
    {
        return null;
    }
}
