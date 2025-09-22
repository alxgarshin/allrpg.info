<?php

declare(strict_types=1);

namespace App\CMSVC\User;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Interface\Response;

#[TableEntity(
    'profile',
    'user',
    [
        new EntitySortingItem(
            tableFieldName: 'id',
        ),
    ],
)]
#[Rights(
    viewRight: true,
    addRight: false,
    changeRight: false,
    deleteRight: false,
)]
#[Controller(UserController::class)]
class UserView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }
}
