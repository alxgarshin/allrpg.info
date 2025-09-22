<?php

declare(strict_types=1);

namespace App\CMSVC\NewsEdit;

use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(NewsEditController::class)]
class NewsEditModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Select(
        obligatory: true,
    )]
    public Item\Select $type;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Hidden(
        defaultValue: '15',
    )]
    public Item\Hidden $creator_id;

    #[Attribute\Checkbox(
        defaultValue: true,
    )]
    public Item\Checkbox $active;

    #[Attribute\Calendar(
        defaultValue: 'getShowDateDefault',
        showDatetime: true,
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Calendar $show_date;

    #[Attribute\Calendar]
    public Item\Calendar $from_date;

    #[Attribute\Calendar]
    public Item\Calendar $to_date;

    #[Attribute\Text]
    public Item\Text $data_came_from;

    #[Attribute\File(
        uploadNum: 8,
    )]
    public Item\File $attachments;

    #[Attribute\Text]
    public Item\Text $quote;

    #[Attribute\Wysiwyg(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Wysiwyg $annotation;

    #[Attribute\File(
        uploadNum: 11,
    )]
    public Item\File $attachments2;

    #[Attribute\Wysiwyg(
        useInFilters: true,
    )]
    public Item\Wysiwyg $content;

    #[Attribute\Multiselect(
        values: 'getTagsValues',
        locked: 'getTagsLocked',
        search: true,
        creator: new Item\MultiselectCreator(
            table: 'tag',
            name: 'name',
            additional: [
                'creator_id' => 'getTagsMultiselectCreatorCreatorId',
                'updated_at' => 'getTagsMultiselectCreatorCreatedUpdatedAt',
                'created_at' => 'getTagsMultiselectCreatorCreatedUpdatedAt',
                'parent' => 0,
            ],
        ),
    )]
    public Item\Multiselect $tags;
}
