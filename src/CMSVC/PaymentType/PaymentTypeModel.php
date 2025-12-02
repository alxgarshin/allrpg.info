<?php

declare(strict_types=1);

namespace App\CMSVC\PaymentType;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(PaymentTypeController::class)]
class PaymentTypeModel extends BaseModel
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

    #[Attribute\Select(
        values: 'getUserIdValues',
        obligatory: true,
    )]
    public Item\Select $user_id;

    #[Attribute\Number(
        context: [
            'paymentType:list',
            'paymentType:view',
        ],
    )]
    public Item\Number $amount;
}
