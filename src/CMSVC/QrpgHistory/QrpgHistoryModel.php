<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgHistory;

use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{IdTrait};
use Fraym\Element\{Attribute as Attribute, Item as Item};

#[Controller(QrpgHistoryController::class)]
class QrpgHistoryModel extends BaseModel
{
    use IdTrait;

    #[Attribute\Select(
        values: 'getCreatorIdValues',
        useInFilters: true,
    )]
    public Item\Select $creator_id;

    #[Attribute\Select(
        values: 'getQrpgCodeIdValues',
        useInFilters: true,
    )]
    public Item\Select $qrpg_code_id;

    #[Attribute\Select(
        values: 'getCodesSuccessesValues',
        noData: true,
        alternativeDataColumnName: 'id',
    )]
    public Item\Select $codes_successes;

    #[Attribute\Select(
        values: 'getRemoveCopiesSuccessesValues',
        noData: true,
        alternativeDataColumnName: 'id',
    )]
    public Item\Select $remove_copies_successes;

    #[Attribute\Select(
        values: 'getCurrenciesSuccessesValues',
        noData: true,
        alternativeDataColumnName: 'id',
    )]
    public Item\Select $currencies_successes;

    #[Attribute\Timestamp(
        showInObjects: true,
        useInFilters: true,
    )]
    public Item\Timestamp $created_at;
}
