<?php

declare(strict_types=1);

namespace App\CMSVC\BankTransaction;

use App\CMSVC\BankCurrency\BankCurrencyService;
use App\CMSVC\Trait\{ProjectDataTrait, UserServiceTrait};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\PreCreate;
use Fraym\Helper\{CMSVCHelper, DataHelper, ResponseHelper};

/** @extends BaseService<BankTransactionModel> */
#[Controller(BankTransactionController::class)]
#[PreCreate]
class BankTransactionService extends BaseService
{
    use ProjectDataTrait;
    use UserServiceTrait;

    private ?array $projectApplications = null;
    private ?array $bankCurrencies = null;

    /** Получение баланса заявки по всем ресурсам */
    public static function getApplicationBalances(int $objId): array
    {
        $income = [];
        $expenses = [];
        $bankBalance = [];

        $incomeData = DB->query(
            'SELECT SUM(amount) AS summ, bank_currency_id FROM bank_transaction WHERE to_project_application_id=:to_project_application_id GROUP BY bank_currency_id',
            [
                ['to_project_application_id', $objId],
            ],
        );

        foreach ($incomeData as $incomeDataElem) {
            $income[$incomeDataElem['bank_currency_id']] = (int) $incomeDataElem['summ'];
        }

        $expensesData = DB->query(
            'SELECT SUM(amount_from) AS summ, from_bank_currency_id FROM bank_transaction WHERE from_project_application_id=:from_project_application_id GROUP BY from_bank_currency_id',
            [
                ['from_project_application_id', $objId],
            ],
        );

        foreach ($expensesData as $expensesDataElem) {
            $expenses[$expensesDataElem['from_bank_currency_id']] = (int) $expensesDataElem['summ'];
        }

        foreach ($income as $currencyId => $summ) {
            $bankBalance[$currencyId] = $summ - ($expenses[$currencyId] ?? 0);
        }

        return $bankBalance;
    }

    public function preCreate(): void
    {
        $LOCALE = $this->LOCALE;

        if (round((int) $_REQUEST['amount'][0]) <= 0) {
            ResponseHelper::responseOneBlock('error', $LOCALE['messages']['too_small_amount'], [0]);
        }
    }

    public function getProjectApplications(): array
    {
        if ($this->projectApplications === null) {
            $userService = $this->getUserService();

            $applications = [];
            $applicationsData = DB->query(
                "SELECT pa.sorter, pa.id AS application_id, u.* FROM project_application AS pa LEFT JOIN user AS u ON u.id=pa.creator_id WHERE pa.project_id=:project_id AND pa.status!=4 AND pa.deleted_by_gamemaster!='1'",
                [
                    ['project_id', $this->getActivatedProjectId()],
                ],
            );

            foreach ($applicationsData as $applicationData) {
                $applications[] = [
                    $applicationData['application_id'],
                    '<a href="' . ABSOLUTE_PATH . '/application/' . $applicationData['application_id'] . '/" target="_blank">' . DataHelper::escapeOutput($applicationData['sorter']) . '</a> (' . $userService->showNameExtended($userService->arrayToModel($applicationData), shortName: true, addNickname: true) . ')',
                ];
            }

            $this->projectApplications = $applications;
        }

        return $this->projectApplications;
    }

    public function getBankCurrencyIdValues(): array
    {
        if ($this->bankCurrencies === null) {
            /** @var BankCurrencyService */
            $bankCurrencyService = CMSVCHelper::getService('bankCurrency');

            $currencies = [];

            foreach (
                $bankCurrencyService->getAll(
                    criteria: [
                        'project_id' => $this->getActivatedProjectId(),
                    ],
                    order: [
                        'name',
                    ],
                ) as $currencyData
            ) {
                $currencies[] = [$currencyData->id->getAsInt(), mb_strtolower(DataHelper::escapeOutput($currencyData->name->get()))];
            }

            $this->bankCurrencies = $currencies;
        }

        return $this->bankCurrencies;
    }
}
