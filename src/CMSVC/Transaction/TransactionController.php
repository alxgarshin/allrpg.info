<?php

declare(strict_types=1);

namespace App\CMSVC\Transaction;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
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
    public function changeComission(): ?Response
    {
        $transactionService = $this->getService();

        return $this->asArray(
            $transactionService->changeComission(
                OBJ_ID,
                OBJ_TYPE,
                (int) ($_REQUEST['value'] ?? 0),
            ),
        );
    }

    #[IsAccessible(
        additionalCheckAccessHelper: RightsHelper::class,
        additionalCheckAccessMethod: 'checkProjectActionAccessBudget',
    )]
    public function nullifyFees(): ?Response
    {
        $transactionService = $this->getService();

        return $this->asArray(
            $transactionService->nullifyFees(),
        );
    }

    #[IsAccessible(
        additionalCheckAccessHelper: RightsHelper::class,
        additionalCheckAccessMethod: 'checkProjectActionAccessFee',
    )]
    public function confirmPayment(): ?Response
    {
        $transactionService = $this->getService();

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
    public function declinePayment(): ?Response
    {
        $transactionService = $this->getService();

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
    public function verifyTransaction(): ?Response
    {
        $transactionService = $this->getService();

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
    public function unVerifyTransaction(): ?Response
    {
        $transactionService = $this->getService();

        return $this->asArray(
            $transactionService->unVerifyTransaction(
                OBJ_ID,
            ),
        );
    }
}
