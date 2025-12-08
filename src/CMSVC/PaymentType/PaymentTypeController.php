<?php

declare(strict_types=1);

namespace App\CMSVC\PaymentType;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
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
    public function pmAdd(): ?Response
    {
        return $this->service->paymentTypeAdd('paymaster');
    }

    public function pkAdd(): ?Response
    {
        return $this->service->paymentTypeAdd('paykeeper');
    }

    public function ykAdd(): ?Response
    {
        return $this->service->paymentTypeAdd('yandex');
    }

    public function pawAdd(): ?Response
    {
        return $this->service->paymentTypeAdd('payanyway');
    }
}
