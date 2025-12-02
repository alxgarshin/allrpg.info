<?php

declare(strict_types=1);

namespace App\CMSVC\Rooms;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(RoomsController::class)]
class RoomsModel extends BaseModel
{
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use IdTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Number(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Number $one_place_price;

    #[Attribute\Number(
        defaultValue: 1,
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Number $places_count;

    #[Attribute\Checkbox]
    public Item\Checkbox $allow_player_select;

    #[Attribute\Textarea(
        useInFilters: true,
    )]
    public Item\Textarea $description;

    #[Attribute\Wysiwyg(
        defaultValue: 'getRoomNeighboorsDefault',
        context: [
            'rooms:view',
        ],
    )]
    public Item\Wysiwyg $room_neighboors;
}
