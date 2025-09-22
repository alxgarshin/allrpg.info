<?php

declare(strict_types=1);

namespace App\CMSVC\Transaction;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait, LastUserUpdateIdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(TransactionController::class)]
class TransactionModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use LastUserUpdateIdTrait;
    use ProjectIdTrait;

    #[Attribute\Timestamp(
        showInObjects: true,
        customAsHTMLRenderer: 'getUpdatedAtCustomAsHTMLRenderer',
    )]
    #[Attribute\OnCreate(callback: 'getTime')]
    #[Attribute\OnChange(callback: 'getTime')]
    public Item\Timestamp $updated_at;

    #[Attribute\Hidden(
        defaultValue: 'getProjectApplicationIdDefault',
        context: [
            'myapplication:view',
            'myapplication:create',
        ],
    )]
    public Item\Hidden $project_application_id_hidden;

    #[Attribute\Multiselect(
        values: 'getProjectApplicationIdValues',
        one: true,
    )]
    public Item\Multiselect $project_application_id;

    #[Attribute\Text(
        defaultValue: 'getHelperBeforeTransactionAdd',
        context: [
            'myapplication:view',
        ],
    )]
    public Item\Text $helper_before_transaction_add;

    #[Attribute\Number(
        defaultValue: 0,
        obligatory: true,
        useInFilters: true,
        context: [
            'transaction:list',
            'transaction:view',
            'transaction:create',
            'transaction:update',
            'transaction:embedded',
            'myapplication:view',
            'myapplication:create',
        ],
    )]
    public Item\Number $amount;

    #[Attribute\Checkbox(
        noData: true,
        context: 'getPayByCardContext',
    )]
    public Item\Checkbox $pay_by_card;

    #[Attribute\Checkbox(
        noData: true,
        context: 'getTestPaymentContext',
    )]
    public Item\Checkbox $test_payment;

    #[Attribute\Select(
        values: [
            ['0', '<a id="verify_transaction"><span class="sbi sbi-times"></span></a>'],
            ['1', '<span class="sbi sbi-check"></span>'],
        ],
        context: [
            'transaction:list',
            'transaction:view',
        ],
        useInFilters: true,
    )]
    public Item\Select $verified;

    #[Attribute\Select(
        values: 'getHasCheckValues',
        context: [
            'transaction:list',
            'transaction:view',
        ],
        noData: true,
        alternativeDataColumnName: 'id',
    )]
    public Item\Select $hasCheck;

    #[Attribute\Select(
        defaultValue: 'getProjectPaymentTypeIdDefault',
        values: 'getProjectPaymentTypeIdValues',
        obligatory: true,
        useInFilters: true,
        context: [
            'transaction:list',
            'transaction:view',
            'transaction:create',
            'transaction:update',
            'transaction:embedded',
            'myapplication:view',
            'myapplication:create',
        ],
    )]
    public Item\Select $project_payment_type_id;

    #[Attribute\Text(
        maxChar: 500,
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Calendar(
        showDatetime: true,
        obligatory: true,
        context: 'getPaymentDatetimeContext',
    )]
    public Item\Calendar $payment_datetime;

    #[Attribute\Textarea(
        rows: 1,
        context: [
            'transaction:list',
            'transaction:view',
            'transaction:create',
            'transaction:update',
            'transaction:embedded',
            'myapplication:view',
            'myapplication:create',
        ],
    )]
    public Item\Textarea $content;

    #[Attribute\Text]
    public Item\Text $comission_percent;

    #[Attribute\Text]
    public Item\Text $comission_value;
}
