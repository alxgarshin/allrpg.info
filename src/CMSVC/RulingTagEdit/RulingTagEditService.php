<?php

declare(strict_types=1);

namespace App\CMSVC\RulingTagEdit;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\{CMSVCHelper, DataHelper};

/** @extends BaseService<RulingTagEditModel> */
#[Controller(RulingTagEditController::class)]
class RulingTagEditService extends BaseService
{
    private ?array $parentValues = null;

    public function checkRights(): bool
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        return $userService->isRulingAdmin();
    }

    public function checkRightsDelete(): bool
    {
        return CURRENT_USER->isAdmin();
    }

    public function getParentValues(): array
    {
        if (is_null($this->parentValues)) {
            $this->parentValues = DB->getTreeOfItems(
                true,
                'ruling_tag',
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

        return $this->parentValues;
    }
}
