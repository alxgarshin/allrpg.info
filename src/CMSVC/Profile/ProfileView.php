<?php

declare(strict_types=1);

namespace App\CMSVC\Profile;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

#[TableEntity(
    name: 'profile',
    table: 'user',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'fio',
        ),
    ],
)]
#[Rights(
    viewRight: 'checkRights',
    addRight: false,
    changeRight: 'checkRights',
    deleteRight: 'checkRights',
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(ProfileController::class)]
class ProfileView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        return $response->setHtml(str_replace('<button class="careful', '<button class="careful no_dynamic_content', $response->getHtml()));
    }
}
