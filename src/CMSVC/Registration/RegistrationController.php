<?php

declare(strict_types=1);

namespace App\CMSVC\Registration;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
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
    #[ApiAction(params: [
        new ApiParam('obj_name', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function getRegistrationPlayer(): ?Response
    {
        return $this->asArray(
            $this->service->getRegistrationPlayer(
                $this->param('obj_name'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
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

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
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

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
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

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('value', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function setRegistrationComments(): ?Response
    {
        if (OBJ_ID > 0) {
            return $this->asArray(
                $this->service->setRegistrationComments(
                    OBJ_ID,
                    $this->param('value'),
                ),
            );
        }

        return null;
    }
}
