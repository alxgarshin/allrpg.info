<?php

declare(strict_types=1);

namespace App\CMSVC\Help;

use Fraym\BaseObject\Trait\IdTrait;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(HelpController::class)]
class HelpModel extends BaseModel
{
    use IdTrait;

    #[Attribute\Wysiwyg(
        context: ['help:view'],
    )]
    public Item\Wysiwyg $beforeask;

    #[Attribute\Text(
        defaultValue: 'getNameDefault',
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Email(
        defaultValue: 'getEmailDefault',
    )]
    public Item\Email $em;

    #[Attribute\Text(
        obligatory: true,
    )]
    public Item\Text $maintext;

    #[Attribute\Text]
    public Item\Text $link;

    #[Attribute\Textarea(
        rows: 5,
        obligatory: true,
    )]
    public Item\Textarea $details;

    #[Attribute\Textarea(
        rows: 1,
    )]
    public Item\Textarea $technical;

    #[Attribute\Hidden(
        noData: true,
    )]
    public Item\Hidden $approvement;
}
