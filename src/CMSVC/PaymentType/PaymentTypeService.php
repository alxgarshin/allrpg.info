<?php

declare(strict_types=1);

namespace App\CMSVC\PaymentType;

use App\CMSVC\Trait\{GamemastersListTrait, ProjectDataTrait};
use App\Helper\DateHelper;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\ResponseHelper;
use Fraym\Interface\Response;

/** @extends BaseService<PaymentTypeModel> */
#[Controller(PaymentTypeController::class)]
class PaymentTypeService extends BaseService
{
    use GamemastersListTrait;
    use ProjectDataTrait;

    public function getPaymentTypeFieldName(string $paymentTypeName): string
    {
        return match ($paymentTypeName) {
            'yandex' => 'yk_type',
            'paykeeper' => 'pk_type',
            'payanyway' => 'paw_type',
            'paymaster' => 'pm_type',
            default => 'pm_type',
        };
    }

    public function checkPaymentTypeId(string $paymentTypeName): ?int
    {
        $paymentTypeFieldName = $this->getPaymentTypeFieldName($paymentTypeName);

        $data = DB->select(
            tableName: 'project_payment_type',
            criteria: [
                $paymentTypeFieldName => '1',
                'project_id' => $this->getActivatedProjectId(),
            ],
            oneResult: true,
            fieldsSet: [
                'id',
            ],
        );

        return $data['id'] ?? null;
    }

    public function checkPkProjectFieldsFilled(): bool
    {
        $projectData = $this->getProjectData();

        if (!$projectData) {
            return false;
        }

        return
            $projectData->paykeeper_login->get()
            && $projectData->paykeeper_pass->get()
            && $projectData->paykeeper_server->get()
            && $projectData->paykeeper_secret->get();
    }

    public function checkPmProjectFieldsFilled(): bool
    {
        $projectData = $this->getProjectData();

        if (!$projectData) {
            return false;
        }

        return
            $projectData->paymaster_merchant_id->get()
            && $projectData->paymaster_code->get();
    }

    public function checkYkProjectFieldsFilled(): bool
    {
        $projectData = $this->getProjectData();

        if (!$projectData) {
            return false;
        }

        return
            $projectData->yk_acc_id->get()
            && $projectData->yk_code->get();
    }

    public function checkPawProjectFieldsFilled(): bool
    {
        $projectData = $this->getProjectData();

        if (!$projectData) {
            return false;
        }

        return
            $projectData->paw_mnt_id->get()
            && $projectData->paw_code->get();
    }

    public function checkProjectFieldsFilled(string $paymentTypeName): bool
    {
        return match ($paymentTypeName) {
            'yandex' => $this->checkYkProjectFieldsFilled(),
            'paykeeper' => $this->checkPkProjectFieldsFilled(),
            'payanyway' => $this->checkPawProjectFieldsFilled(),
            'paymaster' => $this->checkPmProjectFieldsFilled(),
            default => false,
        };
    }

    /** Если нет типа платежа, создаем */
    public function paymentTypeAdd(string $paymentTypeName): ?Response
    {
        $LOCALE = $this->getLOCALE();

        $checkPaymentTypeId = $this->checkPaymentTypeId($paymentTypeName);

        if (is_null($checkPaymentTypeId)) {
            $paymentTypeFieldName = $this->getPaymentTypeFieldName($paymentTypeName);

            DB->insert(
                tableName: 'project_payment_type',
                data: [
                    'project_id' => $this->getActivatedProjectId(),
                    'name' => $LOCALE[$paymentTypeFieldName],
                    $paymentTypeFieldName => '1',
                    'creator_id' => CURRENT_USER->id(),
                    'created_at' => DateHelper::getNow(),
                    'updated_at' => DateHelper::getNow(),
                ],
            );
            $checkPaymentTypeId = DB->lastInsertId();
        }

        if ($checkPaymentTypeId) {
            if ($paymentTypeName === 'payanyway') {
                ResponseHelper::response(
                    [],
                    $_ENV['PAY_ANY_WAY']['partner_register_link'],
                );
            } else {
                ResponseHelper::response(
                    [
                        ['success', $this->getEntity()->getObjectMessages($this->getEntity())[0]],
                    ],
                    'stayhere',
                );
            }
        }

        return $this->getCMSVC()->getController()->Response();
    }

    public function checkRights(): bool
    {
        return in_array('{admin}', PROJECT_RIGHTS);
    }

    public function getUserIdValues(): array
    {
        return $this->getGamemastersList();
    }
}
