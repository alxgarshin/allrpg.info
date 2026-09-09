<?php

declare(strict_types=1);

namespace App\CMSVC\Roles;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
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
    #[ApiAction(mutating: true)]
    public function switchShowRoleslist(): ?Response
    {
        if (RightsHelper::checkAllowProjectActions(PROJECT_RIGHTS, null)) {
            return $this->asArray(
                $this->service->switchShowRoleslist(),
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
            $this->service->switchViewRoleslistMode(),
        );
    }

    #[ApiAction(params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('command', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('project_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('excel', ApiParamTypeEnum::bool, default: false),
    ])]
    public function getRolesList(): ?Response
    {
        if ($this->param('command') !== '' && $this->param('project_id') > 0 && OBJ_TYPE && OBJ_ID) {
            return $this->asArray(
                $this->service->getRolesList(
                    OBJ_TYPE,
                    OBJ_ID,
                    $this->param('command'),
                    $this->param('project_id'),
                    $this->param('excel'),
                ),
            );
        }

        return null;
    }
}
