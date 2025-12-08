<?php

declare(strict_types=1);

namespace App\CMSVC\IngameBankTransaction;

use App\CMSVC\BankCurrency\BankCurrencyService;
use App\CMSVC\BankRule\BankRuleService;
use App\CMSVC\Ingame\IngameService;
use Fraym\BaseObject\{BaseService, CMSVC};
use Fraym\Entity\PreCreate;
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, ResponseHelper};

/** @extends BaseService<IngameBankTransactionModel> */
#[CMSVC(
    model: IngameBankTransactionModel::class,
    view: IngameBankTransactionView::class,
)]
#[PreCreate]
class IngameBankTransactionService extends BaseService
{
    private ?int $activatedProjectId = null;
    private ?array $bankCurrencies = null;

    public function getActivatedProjectId(): ?int
    {
        if (is_null($this->activatedProjectId) && CookieHelper::getCookie('ingame_application_id')) {
            $applicationData = DB->findObjectById(CookieHelper::getCookie('ingame_application_id'), 'project_application');
            $this->activatedProjectId = $applicationData['project_id'];
        }

        return $this->activatedProjectId;
    }

    public function preCreate(): void
    {
        $LOCALE = $this->LOCALE;

        if (round((int) $_REQUEST['amount'][0]) <= 0) {
            ResponseHelper::responseOneBlock('error', $LOCALE['messages']['too_small_amount'], [0]);
        }
    }

    public function checkRightsRestrict(): string
    {
        return 'from_project_application_id=' . CookieHelper::getCookie('ingame_application_id') . ' OR to_project_application_id=' . CookieHelper::getCookie('ingame_application_id');
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

    public function getFromBankCurrencyIdLocked(): array
    {
        /** Блокируем в выборке те варианты, которые недоступны игроку: блокировать нужно, чтобы в списке транзакций при этом всё было видно успешно */
        $currenciesLocked = [];

        /** @var IngameService */
        $ingameService = CMSVCHelper::getService('ingame');

        /** @var BankRuleService */
        $bankRuleService = CMSVCHelper::getService('bankRule');

        $qrpgKeys = $ingameService->qrpgGetKeysAndProperties();
        $qrpgKeys = $qrpgKeys['response_data']['my_qrpg_keys'];

        foreach ($this->getBankCurrencyIdValues() as $currency) {
            $blockCurrency = false;

            $bankRulesData = $bankRuleService->getAll(
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                    'currency_from_id' => $currency[0],
                ],
            );

            foreach ($bankRulesData as $bankRuleData) {
                $qrpgKeysIds = $bankRuleData->qrpg_keys_from_ids->get();

                /** Если хотя бы в одном правиле ресурса есть разрешение на переводы всем, то точно разрешаем */
                if (count($qrpgKeysIds) === 0) {
                    $blockCurrency = false;
                    break;
                } elseif (in_array('none', $qrpgKeysIds)) {
                    $blockCurrency = true;
                } else {
                    /** Проверяем, есть ли у игрока хотя бы один из перечисленных qrpg-ключей. Если нет ни одного блокируем. */
                    $blockCurrency = true;

                    foreach ($qrpgKeysIds as $qrpgKeysId) {
                        if (in_array($qrpgKeysId, $qrpgKeys)) {
                            $blockCurrency = false;
                            break;
                        }
                    }

                    if (!$blockCurrency) {
                        break;
                    }
                }
            }

            if ($blockCurrency) {
                $currenciesLocked[] = $currency[0];
            }
        }

        return $currenciesLocked;
    }
}
