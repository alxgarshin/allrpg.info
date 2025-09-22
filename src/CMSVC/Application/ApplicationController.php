<?php

declare(strict_types=1);

namespace App\CMSVC\Application;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Interface\Response;

/** @extends BaseController<ApplicationService> */
#[CMSVC(
    model: ApplicationModel::class,
    service: ApplicationService::class,
    view: ApplicationView::class,
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
class ApplicationController extends BaseController
{
    public function getApplicationsTable(): ?Response
    {
        return $this->asArray(
            $this->getService()->getApplicationsTable(
                $_REQUEST['obj_name'] ?? '',
            ),
        );
    }

    public function getApplicationsCommentsTable(): ?Response
    {
        return $this->asArray(
            $this->getService()->getApplicationsCommentsTable(
                $_REQUEST['obj_name'] ?? '',
            ),
        );
    }

    public function setSpecialGroup(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->getService()->setSpecialGroup(
                    OBJ_ID,
                    $_REQUEST['filter'] ?? null,
                ),
            );
        }

        return null;
    }

    public function fixCharacterNameBySorter(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->getService()->fixCharacterNameBySorter(
                    OBJ_ID,
                    $_REQUEST['name'] ?? null,
                ),
            );
        }

        return null;
    }

    public function transferApplication(): ?Response
    {
        if (OBJ_ID > 0 && (int) ($_REQUEST['user_id'] ?? null) > 0) {
            return $this->asArray(
                $this->getService()->transferApplication(
                    OBJ_ID,
                    (int) ($_REQUEST['user_id'] ?? null),
                ),
            );
        }

        return null;
    }

    public function transferApplicationCancel(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->getService()->transferApplicationCancel(
                    OBJ_ID,
                ),
            );
        }

        return null;
    }

    public function getListOfRoomNeighboors(): ?Response
    {
        return $this->asArray(
            $this->getService()->getListOfRoomNeighboors(
                OBJ_ID,
            ),
        );
    }
}
