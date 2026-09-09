<?php

declare(strict_types=1);

namespace App\CMSVC\Transaction;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Interface\Response;

/** @extends BaseController<TransactionService> */
#[CMSVC(
    model: TransactionModel::class,
    service: TransactionService::class,
    view: TransactionView::class,
    context: [
        'LIST' => [
            ':list',
            'transaction:list',
        ],
        'VIEW' => [
            ':view',
            'transaction:view',
            'myapplication:view',
        ],
        'VIEWIFNOTNULL' => [
            ':viewIfNotNull',
            'transaction:viewIfNotNull',
        ],
        'CREATE' => [
            ':create',
            'transaction:create',
            'myapplication:create',
        ],
        'UPDATE' => [
            ':update',
            'transaction:update',
        ],
        'EMBEDDED' => [
            ':embedded',
            'transaction:embedded',
        ],
    ],
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectActionAccessBudgetFee',
)]
class TransactionController extends BaseController
{
    #[IsAccessible(
        additionalCheckAccessHelper: RightsHelper::class,
        additionalCheckAccessMethod: 'checkProjectActionAccessBudget',
    )]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('value', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function changeComission(): ?Response
    {
        $transactionService = $this->service;

        return $this->asArray(
            $transactionService->changeComission(
                OBJ_ID,
                OBJ_TYPE,
                $this->param('value'),
            ),
        );
    }

    #[IsAccessible(
        additionalCheckAccessHelper: RightsHelper::class,
        additionalCheckAccessMethod: 'checkProjectActionAccessBudget',
    )]
    #[ApiAction(mutating: true)]
    public function nullifyFees(): ?Response
    {
        $transactionService = $this->service;

        return $this->asArray(
            $transactionService->nullifyFees(),
        );
    }

    #[IsAccessible(
        additionalCheckAccessHelper: RightsHelper::class,
        additionalCheckAccessMethod: 'checkProjectActionAccessFee',
    )]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function confirmPayment(): ?Response
    {
        $transactionService = $this->service;

        return $this->asArray(
            $transactionService->confirmPayment(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible(
        additionalCheckAccessHelper: RightsHelper::class,
        additionalCheckAccessMethod: 'checkProjectActionAccessFee',
    )]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function declinePayment(): ?Response
    {
        $transactionService = $this->service;

        return $this->asArray(
            $transactionService->declinePayment(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible(
        additionalCheckAccessHelper: RightsHelper::class,
        additionalCheckAccessMethod: 'checkProjectActionAccessBudget',
    )]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function verifyTransaction(): ?Response
    {
        $transactionService = $this->service;

        return $this->asArray(
            $transactionService->verifyTransaction(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible(
        additionalCheckAccessHelper: RightsHelper::class,
        additionalCheckAccessMethod: 'checkProjectActionAccessBudget',
    )]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function unVerifyTransaction(): ?Response
    {
        $transactionService = $this->service;

        return $this->asArray(
            $transactionService->unVerifyTransaction(
                OBJ_ID,
            ),
        );
    }
}
