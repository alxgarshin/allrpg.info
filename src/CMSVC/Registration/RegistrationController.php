<?php

declare(strict_types=1);

namespace App\CMSVC\Registration;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Interface\Response;

/** @extends BaseController<RegistrationService> */
#[CMSVC(
    service: RegistrationService::class,
    view: RegistrationView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectKindAccessAndRedirect',
)]
class RegistrationController extends BaseController
{
    public function getRegistrationPlayer(): ?Response
    {
        return $this->asArray(
            $this->service->getRegistrationPlayer(
                $_REQUEST['obj_name'] ?? '',
            ),
        );
    }

    public function setRegistrationPlayer(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->setRegistrationPlayer(
                    OBJ_ID,
                ),
            );
        }

        return null;
    }

    public function setRegistrationPlayerMoney(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->setRegistrationPlayerMoney(
                    OBJ_ID,
                ),
            );
        }

        return null;
    }

    public function setRegistrationEcoMoney(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->setRegistrationEcoMoney(
                    OBJ_ID,
                ),
            );
        }

        return null;
    }

    public function setRegistrationComments(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->setRegistrationComments(
                    OBJ_ID,
                    $_REQUEST['value'] ?? '',
                ),
            );
        }

        return null;
    }
}
