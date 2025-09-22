<?php

declare(strict_types=1);

namespace App\CMSVC\Setup;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Enum\ActEnum;
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
        $this->getService()->setApplicationType();

        $LOCALE = $this->getLOCALE();

        if (DataHelper::getActDefault($this->getEntity()) === ActEnum::add && ACTION === null) {
            ResponseHelper::info($LOCALE['messages']['do_not_add_player_fields']);
        }

        return parent::Response();
    }

    public function changeProjectFieldCode(): ?Response
    {
        $setupService = $this->getService();

        return $this->asArray(
            $setupService->changeProjectFieldCode(
                OBJ_ID,
                (int) $_REQUEST['code'],
            ),
        );
    }
}
