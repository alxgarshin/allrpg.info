<?php

declare(strict_types=1);

namespace App\CMSVC\Photo;

use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(PhotoController::class)]
class PhotoModel extends BaseModel
{
    #[Attribute\Text(
        defaultValue: 'getNameDefault',
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Email(
        defaultValue: 'getEmailDefault',
        obligatory: true,
    )]
    public Item\Email $em;

    #[Attribute\Text(
        obligatory: true,
    )]
    public Item\Text $link;

    #[Attribute\Checkbox(
        obligatory: true,
    )]
    public Item\Checkbox $agreed;

    #[Attribute\Textarea(
        obligatory: true,
    )]
    public Item\Textarea $details;

    #[Attribute\Hidden(
        noData: true,
    )]
    public Item\Hidden $approvement;
}
