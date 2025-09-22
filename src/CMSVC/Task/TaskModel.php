<?php

declare(strict_types=1);

namespace App\CMSVC\Task;

use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(TaskController::class)]
class TaskModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Select(
        defaultValue: 'getObjIdDefault',
        values: 'getObjIdValues',
        noData: true,
    )]
    public Item\Select $obj_id;

    #[Attribute\Text(
        maxChar: 255,
        obligatory: true,
    )]
    public Item\Text $name;

    #[Attribute\Hidden(
        defaultValue: 'getObjTypeDefault',
        noData: true,
    )]
    public Item\Hidden $obj_type;

    #[Attribute\Hidden(
        defaultValue: 'getMessageIdDefault',
        noData: true,
        context: ['task:add'],
    )]
    public Item\Hidden $message_id;

    #[Attribute\Multiselect(
        defaultValue: 'getUserIdDefault',
        values: 'getUserIdValues',
        locked: 'getUserIdLocked',
        search: true,
        obligatory: true,
        noData: true,
    )]
    public Item\Multiselect $user_id;

    #[Attribute\Select(
        defaultValue: 'getResponsibleIdDefault',
        values: 'getResponsibleValues',
        obligatory: true,
        noData: true,
    )]
    public Item\Select $responsible;

    #[Attribute\Text(
        minChar: 3,
        maxChar: 1000,
    )]
    public Item\Text $place;

    #[Attribute\Textarea]
    public Item\Textarea $result;

    #[Attribute\Textarea]
    public Item\Textarea $description;

    #[Attribute\Calendar(
        defaultValue: 'getDateFromDefault',
        showDatetime: true,
    )]
    public Item\Calendar $date_from;

    #[Attribute\Calendar(
        defaultValue: 'getDateToDefault',
        showDatetime: true,
    )]
    public Item\Calendar $date_to;

    #[Attribute\Checkbox]
    public Item\Checkbox $do_not_count_as_busy;

    #[Attribute\Select(
        defaultValue: 'single',
        context: ['task:create'],
    )]
    public Item\Select $repeat_mode;

    #[Attribute\Calendar(
        defaultValue: 'getRepeatUntilDefault',
        showDatetime: true,
        context: ['task:create'],
    )]
    public Item\Calendar $repeat_until;

    #[Attribute\Select(
        defaultValue: 'all',
        noData: true,
        context: 'getRepeatedTasksChangeContext',
    )]
    public Item\Select $repeated_tasks_change;

    #[Attribute\Select(
        defaultValue: '{new}',
        obligatory: true,
    )]
    public Item\Select $status;

    #[Attribute\Select(
        defaultValue: 4,
        obligatory: true,
    )]
    public Item\Select $priority;

    #[Attribute\Number]
    public Item\Number $percentage;

    #[Attribute\Select(
        defaultValue: 'getParentTaskDefault',
        values: 'getParentTaskValues',
        noData: true,
    )]
    public Item\Select $parent_task;

    #[Attribute\Select(
        defaultValue: 'getFollowingTaskDefault',
        values: 'getFollowingTaskValues',
        noData: true,
    )]
    public Item\Select $following_task;
}
