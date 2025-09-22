<?php

declare(strict_types=1);

namespace App\CMSVC\Filterset;

use App\CMSVC\Trait\ProjectSectionsPostViewHandlerTrait;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights};
use Fraym\Interface\Response;

#[MultiObjectsEntity(
    'filterset',
    'project_filterset',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
        ),
    ],
    null,
    5000,
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
#[Controller(FiltersetController::class)]
class FiltersetView extends BaseView
{
    use ProjectSectionsPostViewHandlerTrait;

    public function Response(): ?Response
    {
        return null;
    }
}
