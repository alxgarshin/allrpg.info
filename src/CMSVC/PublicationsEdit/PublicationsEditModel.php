<?php

declare(strict_types=1);

namespace App\CMSVC\PublicationsEdit;

use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(PublicationsEditController::class)]
class PublicationsEditModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Multiselect(
        values: 'getAuthorValues',
        search: true,
    )]
    public Item\Multiselect $author;

    #[Attribute\Textarea(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Textarea $annotation;

    #[Attribute\Wysiwyg(
        useInFilters: true,
    )]
    public Item\Wysiwyg $content;

    #[Attribute\Checkbox(
        defaultValue: true,
    )]
    public Item\Checkbox $active;

    #[Attribute\Checkbox]
    public Item\Checkbox $nocomments;

    #[Attribute\Multiselect(
        values: 'getTagsValues',
        search: true,
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Multiselect $tags;
}
