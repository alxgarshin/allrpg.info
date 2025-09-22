<?php

declare(strict_types=1);

namespace App\CMSVC\PublicationsEdit;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Interface\Response;

#[TableEntity(
    'publicationsEdit',
    'publication',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
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
#[Controller(PublicationsEditController::class)]
class PublicationsEditView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
