<?php

declare(strict_types=1);

namespace App\CMSVC\BankRule;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(BankRuleController::class)]
class BankRuleModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use ProjectIdTrait;

    #[Attribute\Multiselect(
        values: 'getQrpgKeysFromIdsValues',
        search: true,
        useInFilters: true,
    )]
    public Item\Multiselect $qrpg_keys_from_ids;

    #[Attribute\Select(
        values: 'getCurrencyFromIdValues',
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Select $currency_from_id;

    #[Attribute\Number(
        round: true,
        obligatory: true,
    )]
    public Item\Number $amount_from;

    #[Attribute\Multiselect(
        values: 'getQrpgKeysToIdsValues',
        search: true,
        useInFilters: true,
    )]
    public Item\Multiselect $qrpg_keys_to_ids;

    #[Attribute\Select(
        values: 'getCurrencyToIdValues',
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Select $currency_to_id;

    #[Attribute\Number(
        round: true,
        obligatory: true,
    )]
    public Item\Number $amount_to;
}
