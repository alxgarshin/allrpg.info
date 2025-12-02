<?php

declare(strict_types=1);

namespace App\CMSVC\Roles;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;

/** @extends BaseController<RolesService> */
#[CMSVC(
    service: RolesService::class,
    view: RolesView::class,
)]
class RolesController extends BaseController
{
    public function Response(): ?Response
    {
        $_ENV['CANONICAL_URL'] = ABSOLUTE_PATH . '/' . KIND . '/' . (DataHelper::getId() ? DataHelper::getId() . '/' . (OBJ_TYPE ? OBJ_TYPE . '/' . (OBJ_ID ? OBJ_ID . '/' : '') : '') : '');

        return parent::Response();
    }

    #[IsAccessible(
        additionalCheckAccessHelper: RightsHelper::class,
        additionalCheckAccessMethod: 'checkProjectKindAccessAndRedirect',
    )]
    public function switchShowRoleslist(): ?Response
    {
        if (RightsHelper::checkAllowProjectActions(PROJECT_RIGHTS, null)) {
            return $this->asArray(
                $this->getService()->switchShowRoleslist(),
            );
        }

        return null;
    }

    #[IsAccessible(
        additionalCheckAccessHelper: RightsHelper::class,
        additionalCheckAccessMethod: 'checkProjectKindAccessAndRedirect',
    )]
    public function switchViewRoleslistMode(): ?Response
    {
        return $this->asArray(
            $this->getService()->switchViewRoleslistMode(),
        );
    }

    public function getRolesList(): ?Response
    {
        if (($_REQUEST['command'] ?? '') !== '' && (int) ($_REQUEST['project_id'] ?? false) > 0 && OBJ_TYPE && OBJ_ID) {
            return $this->asArray(
                $this->getService()->getRolesList(
                    OBJ_TYPE,
                    OBJ_ID,
                    $_REQUEST['command'] ?? '',
                    (int) ($_REQUEST['project_id'] ?? false),
                    ($_REQUEST['excel'] ?? '') === '1',
                ),
            );
        }

        return null;
    }
}
