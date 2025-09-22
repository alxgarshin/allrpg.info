<?php

declare(strict_types=1);

namespace App\CMSVC\ArticlesEdit;

use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(ArticlesEditController::class)]
class ArticlesEditModel extends BaseModel
{
    use IdTrait;
    use CreatorIdTrait;
    use CreatedUpdatedAtTrait;

    #[Attribute\Select(
        values: 'getParentValues',
        obligatory: true,
    )]
    public Item\Select $parent;

    #[Attribute\Number(
        defaultValue: 1,
        round: true,
        obligatory: true,
    )]
    public Item\Number $code;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Text]
    public Item\Text $attachments;

    #[Attribute\Checkbox(
        defaultValue: true,
    )]
    public Item\Checkbox $active;

    #[Attribute\Hidden(
        defaultValue: '{menu}',
        obligatory: true,
    )]
    public Item\Hidden $content;
}
