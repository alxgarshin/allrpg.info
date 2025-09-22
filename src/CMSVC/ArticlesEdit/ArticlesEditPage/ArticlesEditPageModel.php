<?php

declare(strict_types=1);

namespace App\CMSVC\ArticlesEdit\ArticlesEditPage;

use Fraym\BaseObject\BaseModel;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

class ArticlesEditPageModel extends BaseModel
{
    use IdTrait;
    use CreatorIdTrait;
    use CreatedUpdatedAtTrait;

    #[Attribute\Text(
        defaultValue: 'getLinDefault',
        context: ['articlesEditPage:view', 'articlesEditPage:embedded'],
        saveHtml: true,
    )]
    public Item\Text $lin;

    #[Attribute\Select(
        values: 'getParentValuesForPage',
        obligatory: true,
    )]
    public Item\Select $parent;

    #[Attribute\Number(
        defaultValue: 0,
    )]
    public Item\Number $code;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Text]
    public Item\Text $author;

    #[Attribute\File(
        uploadNum: 7,
    )]
    public Item\File $attachments;

    #[Attribute\Wysiwyg(
        useInFilters: true,
    )]
    public Item\Wysiwyg $content;

    #[Attribute\Checkbox(
        defaultValue: true,
    )]
    public Item\Checkbox $active;

    #[Attribute\Checkbox(
        defaultValue: true,
    )]
    public Item\Checkbox $nocomments;

    #[Attribute\Multiselect(
        values: 'getTagsValues',
        search: true,
        creator: new Item\MultiselectCreator(
            table: 'tag',
            name: 'name',
            additional: [
                'creator_id' => 'getTagsMultiselectCreatorCreatorId',
                'updated_at' => 'getTagsMultiselectCreatorUpdatedAt',
                'created_at' => 'getTagsMultiselectCreatorCreatedAt',
                'parent' => 0,
            ],
        ),
        useInFilters: true,
    )]
    public Item\Multiselect $tags;
}
