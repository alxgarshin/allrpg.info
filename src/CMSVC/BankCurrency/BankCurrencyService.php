<?php

declare(strict_types=1);

namespace App\CMSVC\BankCurrency;

use App\CMSVC\Trait\{ProjectDataTrait};
use Fraym\BaseObject\{BaseService, Controller};

/** @extends BaseService<BankCurrencyModel> */
#[Controller(BankCurrencyController::class)]
class BankCurrencyService extends BaseService
{
    use ProjectDataTrait;
}
