<?php

declare(strict_types=1);

namespace App\CMSVC\Org;

use App\CMSVC\Trait\ProjectSectionsPostViewHandlerTrait;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights};
use Fraym\Enum\SubstituteDataTypeEnum;
use Fraym\Interface\Response;

#[MultiObjectsEntity(
    'org',
    'relation',
    [
        new EntitySortingItem(
            tableFieldName: 'obj_id_to',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortObjIdTo',
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
    viewRestrict: 'checkRightsViewChangeRestrict',
    changeRestrict: 'checkRightsViewChangeRestrict',
    deleteRestrict: 'checkRightsDeleteRestrict',
)]
#[Controller(OrgController::class)]
class OrgView extends BaseView
{
    use ProjectSectionsPostViewHandlerTrait;

    public function Response(): ?Response
    {
        return null;
    }
}
