<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgCode;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait};
use Fraym\Element\{Attribute as Attribute, Item as Item};

#[Controller(QrpgCodeController::class)]
class QrpgCodeModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $sid;

    #[Attribute\Number(
        useInFilters: true,
    )]
    public Item\Number $copies;

    #[Attribute\Text(
        useInFilters: true,
    )]
    public Item\Text $category;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $location;

    #[Attribute\Multiselect(
        useInFilters: true,
    )]
    public Item\Multiselect $settings;

    #[Attribute\Text(
        defaultValue: 'getGenerateDefault',
        context: [
            ':view',
        ],
        saveHtml: true,
    )]
    public Item\Text $generate;

    #[Attribute\H1]
    public Item\H1 $h1_1;

    #[Attribute\Textarea(
        group: 1,
        noData: true,
    )]
    public Item\Textarea $conditions;

    #[Attribute\Multiselect(
        values: 'getQrpgKeysValues',
        images: 'getQrpgKeysImages',
        search: true,
        obligatory: true,
        group: 1,
        useInFilters: true,
    )]
    public Item\Multiselect $qrpg_keys;

    #[Attribute\Multiselect(
        values: 'getQrpgKeysValues',
        images: 'getQrpgKeysImages',
        search: true,
        group: 1,
        useInFilters: true,
    )]
    public Item\Multiselect $not_qrpg_keys;

    #[Attribute\Select(
        group: 1,
        useInFilters: true,
    )]
    public Item\Select $hacking_settings;

    #[Attribute\Text(
        group: 1,
        useInFilters: true,
    )]
    public Item\Text $text_to_access;

    #[Attribute\Textarea(
        group: 1,
        noData: true,
    )]
    public Item\Textarea $on_success;

    #[Attribute\Textarea(
        rows: 5,
        obligatory: true,
        group: 1,
        useInFilters: true,
    )]
    public Item\Textarea $description;

    #[Attribute\Multiselect(
        values: 'getQrpgKeysValues',
        images: 'getQrpgKeysImages',
        search: true,
        group: 1,
        useInFilters: true,
    )]
    public Item\Multiselect $removes_qrpg_keys_user;

    #[Attribute\Multiselect(
        values: 'getQrpgKeysValues',
        images: 'getQrpgKeysImages',
        search: true,
        group: 1,
        useInFilters: true,
    )]
    public Item\Multiselect $removes_qrpg_keys;

    #[Attribute\Multiselect(
        values: 'getQrpgKeysValues',
        images: 'getQrpgKeysImages',
        search: true,
        group: 1,
        useInFilters: true,
    )]
    public Item\Multiselect $removes_copies_of_qrpg_codes;

    #[Attribute\Multiselect(
        values: 'getQrpgKeysValues',
        images: 'getQrpgKeysImages',
        search: true,
        group: 1,
        useInFilters: true,
    )]
    public Item\Multiselect $gives_qrpg_keys;

    #[Attribute\Number(
        group: 1,
    )]
    public Item\Number $gives_qrpg_keys_for_minutes;

    #[Attribute\Textarea(
        group: 1,
        noData: true,
    )]
    public Item\Textarea $give_currency;

    #[Attribute\Number(
        group: 1,
    )]
    public Item\Number $gives_bank_currency_amount;

    #[Attribute\Select(
        defaultValue: 'getGivesBankCurrencyDefault',
        values: 'getGivesBankCurrencyValues',
        group: 1,
        useInFilters: true,
        context: 'getGivesBankCurrencyContext',
    )]
    public Item\Select $gives_bank_currency;

    #[Attribute\Number(
        group: 1,
    )]
    public Item\Number $gives_bank_currency_total_times;

    #[Attribute\Number(
        group: 1,
    )]
    public Item\Number $gives_bank_currency_once_in_minutes;

    #[Attribute\Number(
        group: 1,
    )]
    public Item\Number $gives_bank_currency_total_times_user;

    #[Attribute\Number(
        defaultValue: 1,
        group: 1,
    )]
    public Item\Number $gives_bank_currency_once_in_minutes_user;

    #[Attribute\Textarea(
        group: 1,
        noData: true,
    )]
    public Item\Textarea $on_fail;

    #[Attribute\Multiselect(
        values: 'getQrpgKeysValues',
        images: 'getQrpgKeysImages',
        search: true,
        group: 1,
    )]
    public Item\Multiselect $gives_bad_qrpg_keys;

    #[Attribute\Number(
        group: 1,
    )]
    public Item\Number $gives_bad_qrpg_keys_for_minutes;

    #[Attribute\Textarea(
        rows: 5,
        group: 1,
    )]
    public Item\Textarea $description_bad;
}
