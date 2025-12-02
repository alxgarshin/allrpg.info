<?php

declare(strict_types=1);

namespace App\CMSVC\BannersEdit;

use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(BannersEditController::class)]
class BannersEditModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;

    #[Attribute\Text(
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Textarea(
        saveHtml: true,
    )]
    public Item\Textarea $description;

    #[Attribute\Text]
    public Item\Text $link;

    #[Attribute\File(
        uploadNum: 6,
        obligatory: true,
    )]
    public Item\File $img;

    #[Attribute\Select(
        defaultValue: 1,
        obligatory: true,
    )]
    public Item\Select $type;

    #[Attribute\Checkbox(
        defaultValue: true,
    )]
    public Item\Checkbox $active;
}
