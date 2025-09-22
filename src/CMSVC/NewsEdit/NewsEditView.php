<?php

declare(strict_types=1);

namespace App\CMSVC\NewsEdit;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\TableFieldOrderEnum;
use Fraym\Interface\Response;

#[TableEntity(
    'newsEdit',
    'news',
    [
        new EntitySortingItem(
            tableFieldName: 'updated_at',
            tableFieldOrder: TableFieldOrderEnum::DESC,
        ),
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
)]
#[Controller(NewsEditController::class)]
class NewsEditView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
