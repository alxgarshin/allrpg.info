<?php

declare(strict_types=1);

namespace App\CMSVC\Community;

use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(CommunityController::class)]
class CommunityModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Select(
        defaultValue: '{request}',
        obligatory: true,
    )]
    public Item\Select $type;

    #[Attribute\Text(
        maxChar: 255,
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Multiselect(
        defaultValue: 'getCommunitiesDefault',
        values: 'getCommunitiesValues',
        search: true,
        noData: true,
    )]
    public Item\Multiselect $communities;

    #[Attribute\Wysiwyg(
        obligatory: true,
    )]
    public Item\Wysiwyg $description;

    #[Attribute\File(
        uploadNum: 9,
    )]
    public Item\File $attachments;

    #[Attribute\Select(
        defaultValue: 1,
    )]
    public Item\Select $access_to_childs;
}
