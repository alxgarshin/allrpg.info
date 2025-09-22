<?php

declare(strict_types=1);

namespace App\CMSVC\ExchangeCategoryEdit;

use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\DataHelper;

/** @extends BaseService<ExchangeCategoryEditModel> */
#[Controller(ExchangeCategoryEditController::class)]
class ExchangeCategoryEditService extends BaseService
{
    public function checkRights(): bool
    {
        return CURRENT_USER->isAdmin();
    }

    public function getParentValues(): array
    {
        return DB->getTreeOfItems(
            true,
            'exchange_category',
            'parent',
            null,
            " AND content='{menu}'" . (DataHelper::getId() > 0 ? ' AND id!=' . DataHelper::getId() : ''),
            'name',
            1,
            'id',
            'name',
            1000000,
        );
    }
}
