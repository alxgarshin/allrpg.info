<?php

declare(strict_types=1);

namespace App\CMSVC\Transaction;

use App\CMSVC\Trait\ProjectSectionsPostViewHandlerTrait;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights};
use Fraym\Enum\TableFieldOrderEnum;
use Fraym\Interface\Response;

/** @extends BaseView<TransactionService> */
#[MultiObjectsEntity(
    'transaction',
    'project_transaction',
    [
        new EntitySortingItem(
            tableFieldName: 'updated_at',
            tableFieldOrder: TableFieldOrderEnum::DESC,
        ),
    ],
    null,
    5000,
)]
#[Rights(
    viewRight: true,
    addRight: 'checkRights',
    changeRight: false,
    deleteRight: 'checkRights',
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(TransactionController::class)]
class TransactionView extends BaseView
{
    use ProjectSectionsPostViewHandlerTrait;

    public function Response(): ?Response
    {
        return null;
    }

    public function preViewHandler(): void
    {
        $transactionService = $this->service;
        $projectData = $transactionService->getProjectData();

        if ($projectData->show_datetime_in_transaction->get()) {
            $entitySortingItem = new EntitySortingItem(
                tableFieldName: 'payment_datetime',
                tableFieldOrder: TableFieldOrderEnum::DESC,
                showFieldDataInEntityTable: false,
                showFieldShownNameInCatalogItemString: false,
            );
            $this->entity->insertEntitySortingData($entitySortingItem, 1);
        }
    }

    public function additionalPostViewHandler(string $RESPONSE_DATA): string
    {
        /** @var TransactionModel */
        $model = $this->model;

        if (count($model->getElement('payment_datetime')->getAttribute()->context) === 0) {
            $RESPONSE_DATA = preg_replace('#multi_objects_table excel#', 'multi_objects_table excel without_payment_datetime', $RESPONSE_DATA);
        } else {
            $RESPONSE_DATA = preg_replace('#multi_objects_table excel#', 'multi_objects_table excel with_payment_datetime', $RESPONSE_DATA);
        }

        return $RESPONSE_DATA;
    }
}
