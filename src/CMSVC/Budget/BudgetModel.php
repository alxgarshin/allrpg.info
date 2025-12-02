<?php

declare(strict_types=1);

namespace App\CMSVC\Budget;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(BudgetController::class)]
class BudgetModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Number(
        defaultValue: 0,
    )]
    public Item\Number $price;

    #[Attribute\Number(
        defaultValue: 0,
    )]
    public Item\Number $quantity_needed;

    #[Attribute\Number(
        defaultValue: 0,
    )]
    public Item\Number $quantity;

    #[Attribute\Textarea(
        rows: 1,
    )]
    public Item\Textarea $description;

    #[Attribute\Select(
        values: 'getResponsibleIdValues',
    )]
    public Item\Select $responsible_id;

    #[Attribute\Select(
        values: 'getBoughtByValues',
    )]
    public Item\Select $bought_by;

    #[Attribute\Checkbox]
    public Item\Checkbox $distributed_item;

    #[Attribute\Checkbox]
    public Item\Checkbox $is_category;
}
