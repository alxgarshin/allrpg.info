<?php

declare(strict_types=1);

namespace App\CMSVC\Setup;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ActEnum, ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Helper\{DataHelper, ResponseHelper};
use Fraym\Interface\Response;

/** @extends BaseController<SetupService> */
#[CMSVC(
    model: SetupModel::class,
    service: SetupService::class,
    view: SetupView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectKindAccessAndRedirect',
)]
class SetupController extends BaseController
{
    public function Response(): ?Response
    {
        $this->service->setApplicationType();

        $LOCALE = $this->LOCALE;

        if (DataHelper::getActDefault($this->entity) === ActEnum::add && ACTION === null) {
            ResponseHelper::info($LOCALE['messages']['do_not_add_player_fields']);
        }

        return parent::Response();
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('code', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function changeProjectFieldCode(): ?Response
    {
        $setupService = $this->service;

        return $this->asArray(
            $setupService->changeProjectFieldCode(
                OBJ_ID,
                $this->param('code'),
            ),
        );
    }
}
