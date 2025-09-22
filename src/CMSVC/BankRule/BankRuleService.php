<?php

declare(strict_types=1);

namespace App\CMSVC\BankRule;

use App\CMSVC\Trait\{ProjectDataTrait};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\LocaleHelper;

/** @extends BaseService<BankRuleModel> */
#[Controller(BankRuleController::class)]
class BankRuleService extends BaseService
{
    use ProjectDataTrait;

    private ?array $qrpgKeysIds = null;
    private ?array $currenciesIds = null;

    public function getQrpgKeysIds(): ?array
    {
        if ($this->qrpgKeysIds === null) {
            $LOCALE = LocaleHelper::getLocale(['bank_rule', 'fraym_model']);

            $this->qrpgKeysIds = array_merge(
                $LOCALE['elements']['qrpg_keys_from_ids']['default_values'],
                DB->getArrayOfItemsAsArray('qrpg_key WHERE project_id=' . $this->getActivatedProjectId() . ' ORDER BY name', 'id', 'name'),
            );
        }

        return $this->qrpgKeysIds;
    }

    public function getQrpgKeysFromIdsValues(): array
    {
        return $this->getQrpgKeysIds();
    }

    public function getCurrenciesIds(): array
    {
        if ($this->currenciesIds === null) {
            $this->currenciesIds = DB->getArrayOfItemsAsArray('bank_currency WHERE project_id=' . $this->getActivatedProjectId() . ' ORDER BY name', 'id', 'name');
        }

        return $this->currenciesIds;
    }

    public function getCurrencyFromIdValues(): array
    {
        return $this->getCurrenciesIds();
    }

    public function getQrpgKeysToIdsValues(): array
    {
        return $this->getQrpgKeysIds();
    }

    public function getCurrencyToIdValues(): array
    {
        return $this->getCurrenciesIds();
    }
}
