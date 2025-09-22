<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgCode;

use App\CMSVC\BankCurrency\BankCurrencyService;
use App\CMSVC\Trait\{ProjectDataTrait};
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Enum\LocalableFieldsEnum;
use Fraym\Helper\{DataHelper, LocaleHelper};

/** @extends BaseService<QrpgCodeModel> */
#[Controller(QrpgCodeController::class)]
class QrpgCodeService extends BaseService
{
    use ProjectDataTrait;

    #[DependencyInjection]
    public BankCurrencyService $bankCurrencyService;

    private ?array $qrpgKeysValues = null;
    private ?array $qrpgKeysImages = null;
    private ?array $bankCurrencies = null;
    private ?int $bankCurrenciesDefault = null;

    public function getSortSettings(): array
    {
        return LocaleHelper::getElementText($this->getEntity(), $this->getModel()->getElement('settings'), LocalableFieldsEnum::values);
    }

    public function getGenerateDefault(): string
    {
        $LOCALE = $this->getLOCALE();

        return '<a href="/qrpg_generator/project_id=' . $this->getActivatedProjectId() . '&qrpg_code_id=' . DataHelper::getId() . '" target="_blank" class="qrpg_code_generate_link">' . $LOCALE['generate_qrpg_code'] . '</a><a href="/qrpg_generator/project_id=' . $this->getActivatedProjectId() . '&qrpg_code_id=' . DataHelper::getId() . '&color=1" target="_blank" class="qrpg_code_generate_link">' . $LOCALE['generate_qrpg_code_color'] . '</a>';
    }

    public function getQrpgKeysValues(): array
    {
        $this->prepareQrpgKeysAndImagesValues();

        return $this->qrpgKeysValues;
    }

    public function getQrpgKeysImages(): array
    {
        $this->prepareQrpgKeysAndImagesValues();

        return $this->qrpgKeysImages;
    }

    public function getGivesBankCurrencyDefault(): ?int
    {
        $this->prepareBankCurrenciesValues();

        return $this->bankCurrenciesDefault;
    }

    public function getGivesBankCurrencyContext(): array
    {
        $this->prepareBankCurrenciesValues();

        if (count($this->bankCurrencies) > 0) {
            return [
                ':list',
                ':view',
                ':create',
                ':update',
            ];
        }

        return [];
    }

    public function getGivesBankCurrencyValues(): array
    {
        $this->prepareBankCurrenciesValues();

        return $this->bankCurrencies;
    }

    private function prepareQrpgKeysAndImagesValues(): void
    {
        if ($this->qrpgKeysValues === null || $this->qrpgKeysImages === null) {
            $qrpgKeysValues = [];
            $qrpgKeysValuesSort = [];
            $qrpgKeysImagesSort = [];
            $qrpgKeysImages = [];
            $qrpgKeys = DB->query("SELECT qk.*, qk2.property_name AS double_name FROM qrpg_key AS qk LEFT JOIN qrpg_key AS qk2 ON qk2.property_name=qk.property_name AND qk2.id!=qk.id WHERE qk.project_id=:project_id", [
                ['project_id',  $this->getActivatedProjectId()],
            ]);

            foreach ($qrpgKeys as $qrpgKey) {
                $qrpg_key_name = DataHelper::escapeOutput($qrpgKey['property_name'] !== '' ? $qrpgKey['property_name'] . ($qrpgKey['double_name'] !== '' ? ' (' . $qrpgKey['name'] . ')' : '') : $qrpgKey['name']);
                $qrpgKeysValues[] = [$qrpgKey['id'], $qrpg_key_name];
                $qrpgKeysValuesSort[] = $qrpg_key_name;
                $qrpgKeysImagesSort[] = $qrpg_key_name;

                if ($qrpgKey['img'] > 0) {
                    $qrpgKeysImages[] = [$qrpgKey['id'], ABSOLUTE_PATH . '/design/qrpg/' . $qrpgKey['img'] . '.svg'];
                } else {
                    $qrpgKeysImages[] = '';
                }
            }
            array_multisort($qrpgKeysValuesSort, SORT_ASC, $qrpgKeysValues);
            array_multisort($qrpgKeysImagesSort, SORT_ASC, $qrpgKeysImages);

            $this->qrpgKeysValues = $qrpgKeysValues;
            $this->qrpgKeysImages = $qrpgKeysImages;
        }
    }

    private function prepareBankCurrenciesValues(): void
    {
        if ($this->bankCurrencies === null) {
            $this->bankCurrencies = [];

            $bankCurrencies = $this->bankCurrencyService->getAll(
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                ],
                order: [
                    'name',
                ],
            );

            foreach ($bankCurrencies as $bankCurrency) {
                $this->bankCurrencies[] = [$bankCurrency->id->get(), $bankCurrency->name->get()];

                if ($this->bankCurrenciesDefault === null && $bankCurrency->default_one->get()) {
                    $this->bankCurrenciesDefault = (int) $bankCurrency->id->get();
                }
            }
        }
    }
}
