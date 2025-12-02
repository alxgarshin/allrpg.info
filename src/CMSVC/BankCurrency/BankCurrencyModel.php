<?php

declare(strict_types=1);

namespace App\CMSVC\BankCurrency;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(BankCurrencyController::class)]
class BankCurrencyModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        maxChar: 255,
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Checkbox]
    public Item\Checkbox $default_one;
}
