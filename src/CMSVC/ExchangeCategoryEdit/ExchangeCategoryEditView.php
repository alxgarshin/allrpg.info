<?php

declare(strict_types=1);

namespace App\CMSVC\ExchangeCategoryEdit;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{CatalogEntity, CatalogItemEntity, EntitySortingItem, Rights};
use Fraym\Helper\LocaleHelper;
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

#[CatalogEntity(
    'exchangeCategoryEdit',
    'exchange_category',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
    ],
)]
#[CatalogItemEntity(
    'exchangeCategoryEditChild',
    'exchange_category',
    ExchangeCategoryEditModel::class,
    'parent',
    'content',
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
)]
#[Controller(ExchangeCategoryEditController::class)]
class ExchangeCategoryEditView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $RESPONSE_DATA = $response->getHtml();
        $RESPONSE_DATA = preg_replace('#<a [^>]+><span class="sbi sbi-plus"><\/span>' . $LOCALE_FRAYM['dynamiccreate']['add'] . ' <\/a>#', '', $RESPONSE_DATA);

        return $response->setHtml($RESPONSE_DATA);
    }
}
