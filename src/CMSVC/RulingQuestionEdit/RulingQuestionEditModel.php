<?php

declare(strict_types=1);

namespace App\CMSVC\RulingQuestionEdit;

use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(RulingQuestionEditController::class)]
class RulingQuestionEditModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Text(
        defaultValue: 'getObjHelper1Default',
        context: ['rulingQuestionEdit:view'],
        saveHtml: true,
    )]
    public Item\Text $obj_helper_1;

    #[Attribute\Text(
        obligatory: true,
    )]
    public Item\Text $field_name;

    #[Attribute\Select(
        defaultValue: 1,
        obligatory: true,
    )]
    public Item\Select $field_type;

    #[Attribute\Text]
    public Item\Text $code;

    #[Attribute\Textarea]
    public Item\Textarea $field_values;

    #[Attribute\H1]
    public Item\H1 $h1_group;

    #[Attribute\Multiselect(
        values: 'getShowIfValues',
        search: true,
        group: 1,
    )]
    public Item\Multiselect $show_if;
}
