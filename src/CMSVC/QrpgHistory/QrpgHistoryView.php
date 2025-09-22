<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgHistory;

use App\CMSVC\Trait\ProjectSectionsPostViewHandlerTrait;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights};
use Fraym\Enum\TableFieldOrderEnum;
use Fraym\Interface\Response;

#[MultiObjectsEntity(
    name: 'qrpgHistory',
    table: 'qrpg_history',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'created_at',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
        ),
    ],
    elementsPerPage: 500,
)]
#[Rights(
    viewRight: true,
    addRight: false,
    changeRight: false,
    deleteRight: false,
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(QrpgHistoryController::class)]
class QrpgHistoryView extends BaseView
{
    use ProjectSectionsPostViewHandlerTrait;

    public function Response(): ?Response
    {
        return null;
    }

    public function additionalPostViewHandler(string $RESPONSE_DATA): string
    {
        $RESPONSE_DATA = preg_replace('#<table#', '<form><table', $RESPONSE_DATA);
        $RESPONSE_DATA = preg_replace('#</table>#', '</table></form>', $RESPONSE_DATA);

        return $RESPONSE_DATA;
    }
}
