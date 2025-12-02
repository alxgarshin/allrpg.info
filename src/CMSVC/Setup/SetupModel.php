<?php

declare(strict_types=1);

namespace App\CMSVC\Setup;

use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait};
use Fraym\Element\{Attribute, Item};

#[Controller(SetupController::class)]
class SetupModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;

    #[Attribute\Text(
        obligatory: true,
    )]
    public Item\Text $field_name;

    #[Attribute\Select(
        obligatory: true,
    )]
    public Item\Select $field_type;

    #[Attribute\Select(
        defaultValue: 3,
        obligatory: true,
    )]
    public Item\Select $field_rights;

    #[Attribute\Checkbox]
    public Item\Checkbox $field_mustbe;

    #[Attribute\Textarea]
    public Item\Textarea $field_values;

    #[Attribute\Textarea]
    public Item\Textarea $field_default;

    #[Attribute\Multiselect(
        values: 'getShowIfValues',
        search: true,
    )]
    public Item\Multiselect $show_if;

    #[Attribute\Textarea]
    public Item\Textarea $field_help;

    #[Attribute\Number(
        defaultValue: 'getCodeDefault',
        obligatory: true,
    )]
    public Item\Number $field_code;

    #[Attribute\Number]
    public Item\Number $field_height;

    #[Attribute\Multiselect]
    public Item\Multiselect $ingame_settings;

    #[Attribute\Checkbox(
        defaultValue: true,
    )]
    public Item\Checkbox $show_in_filters;

    #[Attribute\Checkbox]
    public Item\Checkbox $show_in_table;

    #[Attribute\Checkbox]
    public Item\Checkbox $hide_field_on_application_create;
}
