<?php

declare(strict_types=1);

namespace App\CMSVC\PaymentType;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, BaseController, CMSVC, IsAccessible};
use Fraym\Interface\Response;

/** @extends BaseController<PaymentTypeService> */
#[CMSVC(
    model: PaymentTypeModel::class,
    service: PaymentTypeService::class,
    view: PaymentTypeView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectActionAccessFee',
)]
class PaymentTypeController extends BaseController
{
    #[ApiAction(mutating: true)]
    public function pmAdd(): ?Response
    {
        return $this->service->paymentTypeAdd('paymaster');
    }

    #[ApiAction(mutating: true)]
    public function pkAdd(): ?Response
    {
        return $this->service->paymentTypeAdd('paykeeper');
    }

    #[ApiAction(mutating: true)]
    public function ykAdd(): ?Response
    {
        return $this->service->paymentTypeAdd('yandex');
    }

    #[ApiAction(mutating: true)]
    public function pawAdd(): ?Response
    {
        return $this->service->paymentTypeAdd('payanyway');
    }
}
