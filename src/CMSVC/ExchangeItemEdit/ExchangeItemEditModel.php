<?php

declare(strict_types=1);

namespace App\CMSVC\ExchangeItemEdit;

use App\CMSVC\HelperGeographyCity\HelperGeographyCityController;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(ExchangeItemEditController::class)]
class ExchangeItemEditModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Select(
        defaultValue: 'getRegionDefault',
        helper: new HelperGeographyCityController(),
        obligatory: true,
    )]
    public Item\Select $region;

    #[Attribute\Multiselect(
        values: 'getExchange_category_idsValues',
        search: true,
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Multiselect $exchange_category_ids;

    #[Attribute\Textarea(
        rows: 5,
        obligatory: true,
    )]
    public Item\Textarea $description;

    #[Attribute\Multiselect]
    public Item\Multiselect $additional;

    #[Attribute\Number]
    public Item\Number $price_lease;

    #[Attribute\Number]
    public Item\Number $price_buy;

    #[Attribute\Select(
        defaultValue: 'RUR',
        obligatory: true,
    )]
    public Item\Select $currency;

    #[Attribute\Checkbox(
        defaultValue: true,
    )]
    public Item\Checkbox $active;

    #[Attribute\H1]
    public Item\H1 $h1_1;

    #[Attribute\Text(
        obligatory: true,
        group: 1,
    )]
    public Item\Text $images;
}
