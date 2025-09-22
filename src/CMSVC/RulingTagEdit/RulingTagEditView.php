<?php

declare(strict_types=1);

namespace App\CMSVC\RulingTagEdit;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{CatalogEntity, CatalogItemEntity, EntitySortingItem, Rights};
use Fraym\Helper\LocaleHelper;
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

#[CatalogEntity(
    'rulingTagEdit',
    'ruling_tag',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'show_in_cloud',
            removeDotAfterText: true,
        ),
    ],
    null,
    5000,
)]
#[CatalogItemEntity(
    'rulingTagEditChild',
    'ruling_tag',
    RulingTagEditModel::class,
    'parent',
    'content',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'show_in_cloud',
            removeDotAfterText: true,
        ),
    ],
)]
#[Rights(
    viewRight: 'checkRights',
    addRight: 'checkRights',
    changeRight: 'checkRights',
    deleteRight: 'checkRightsDelete',
)]
#[Controller(RulingTagEditController::class)]
class RulingTagEditView extends BaseView
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
