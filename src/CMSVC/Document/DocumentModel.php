<?php

declare(strict_types=1);

namespace App\CMSVC\Document;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute as Attribute, Item as Item};

#[Controller(DocumentController::class)]
class DocumentModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Wysiwyg(
        defaultValue: 'getPossibleFieldsDefault',
        context: [
            'document:view',
        ],
    )]
    public Item\Wysiwyg $possible_fields;

    #[Attribute\Wysiwyg(
        defaultValue: 'getContentDefault',
    )]
    public Item\Wysiwyg $content;

    #[Attribute\Text]
    public Item\Text $outer_css;
}
