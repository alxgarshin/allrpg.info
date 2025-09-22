<?php

declare(strict_types=1);

namespace App\CMSVC\Conversation;

use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(ConversationController::class)]
class ConversationModel extends BaseModel
{
    #[Attribute\Select(
        values: 'getGroupValues',
        locked: ['locked1', 'locked2'],
        context: ['conversation:view', 'conversation:create', 'conversation:embedded'],
    )]
    public Item\Select $group;

    #[Attribute\Multiselect(
        defaultValue: 'getUserIdDefault',
        values: 'getUserIdValues',
        images: 'getUserIdImages',
        search: true,
        obligatory: true,
    )]
    public Item\Multiselect $user_id;

    #[Attribute\Text(
        defaultValue: 'getNameDefault',
    )]
    public Item\Text $name;

    #[Attribute\Textarea(
        obligatory: true,
    )]
    public Item\Textarea $content;

    /*#[Attribute\File(
        uploadNum: 1
    )]
    public Item\File $attachments;*/

    #[Attribute\File(
        uploadNum: 15,
    )]
    public Item\File $avatar;
}
