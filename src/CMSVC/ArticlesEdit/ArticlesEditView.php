<?php

declare(strict_types=1);

namespace App\CMSVC\ArticlesEdit;

use App\CMSVC\ArticlesEdit\ArticlesEditPage\ArticlesEditPageModel;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{CatalogEntity, CatalogItemEntity, EntitySortingItem, Rights};
use Fraym\Interface\Response;

#[CatalogEntity(
    name: 'articlesEdit',
    table: 'article',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'code',
            showFieldDataInEntityTable: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'code',
            doNotUseIfNotSortedByThisField: true,
        ),
    ],
)]
#[CatalogItemEntity(
    name: 'articlesEditPage',
    table: 'article',
    catalogItemModelClass: ArticlesEditPageModel::class,
    tableFieldWithParentId: 'parent',
    tableFieldToDetectType: 'content',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'code',
            showFieldDataInEntityTable: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'updated_at',
        ),
    ],
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: true,
    deleteRight: true,
)]
#[Controller(ArticlesEditController::class)]
class ArticlesEditView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
