<?php

declare(strict_types=1);

namespace App\CMSVC\BankTransaction;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(BankTransactionController::class)]
class BankTransactionModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        maxChar: 255,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Multiselect(
        values: 'getProjectApplications',
        one: true,
        search: true,
        useInFilters: true,
    )]
    public Item\Multiselect $from_project_application_id;

    #[Attribute\Select(
        values: 'getBankCurrencyIdValues',
        useInFilters: true,
    )]
    public Item\Select $from_bank_currency_id;

    #[Attribute\Number(
        round: true,
        useInFilters: true,
    )]
    public Item\Number $amount_from;

    #[Attribute\Multiselect(
        values: 'getProjectApplications',
        one: true,
        search: true,
        useInFilters: true,
    )]
    public Item\Multiselect $to_project_application_id;

    #[Attribute\Select(
        values: 'getBankCurrencyIdValues',
        useInFilters: true,
    )]
    public Item\Select $bank_currency_id;

    #[Attribute\Number(
        round: true,
        useInFilters: true,
    )]
    public Item\Number $amount;

    #[Attribute\Timestamp(
        showInObjects: true,
        useInFilters: true,
        context: [
            ':list',
            ':view',
            ':create',
        ],
    )]
    #[Attribute\OnCreate(callback: 'getTime')]
    public Item\Timestamp $created_at;
}
