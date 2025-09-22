<?php

declare(strict_types=1);

namespace App\CMSVC\Project;

use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(ProjectController::class)]
class ProjectModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;
    use IdTrait;
    use CreatedUpdatedAtTrait;

    public const array EDIT_VIEW_CONTEXT = ['project:list', 'project:update'];

    #[Attribute\H1(
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\H1 $h1_2;

    #[Attribute\Select(
        defaultValue: '{open}',
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
        context: 'getCommunitiesContext',
    )]
    public Item\Multiselect $communities;

    #[Attribute\Multiselect(
        defaultValue: 'getUserIdDefault',
        values: 'getUserIdValues',
        locked: 'getUserIdLocked',
        search: true,
        noData: true,
        context: 'getUserIdContext',
    )]
    public Item\Multiselect $user_id;

    #[Attribute\Wysiwyg]
    public Item\Wysiwyg $annotation;

    #[Attribute\Wysiwyg]
    public Item\Wysiwyg $description;

    #[Attribute\Text]
    public Item\Text $external_link;

    #[Attribute\File(
        uploadNum: 9,
    )]
    public Item\File $attachments;

    #[Attribute\Calendar(
        defaultValue: 'getDateFromDefault',
        obligatory: true,
    )]
    public Item\Calendar $date_from;

    #[Attribute\Calendar(
        defaultValue: 'getDateToDefault',
        obligatory: true,
    )]
    public Item\Calendar $date_to;

    #[Attribute\H1(
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\H1 $h1_1;

    #[Attribute\Select(
        obligatory: true,
        values: 'getSorterValues',
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Select $sorter;

    #[Attribute\Select(
        values: 'getSorter2Values',
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Select $sorter2;

    #[Attribute\Select(
        defaultValue: 0,
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Select $show_roleslist;

    #[Attribute\Select(
        defaultValue: 0,
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Select $status;

    #[Attribute\Text(
        defaultValue: 'getGotoLinkDefault',
        noData: true,
        context: 'getViewOnNotAddContext',
        saveHtml: true,
    )]
    public Item\Text $goto_link;

    #[Attribute\Checkbox(
        defaultValue: true,
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Checkbox $oneorderfromplayer;

    #[Attribute\Checkbox(
        defaultValue: false,
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Checkbox $showonlyacceptedroles;

    #[Attribute\H1(
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\H1 $h1_3;

    #[Attribute\Text(
        noData: true,
        context: 'getViewOnNotAddContext',
        saveHtml: true,
    )]
    public Item\Text $money_link;

    #[Attribute\Select(
        defaultValue: 'RUR',
        obligatory: true,
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Select $currency;

    #[Attribute\Number(
        defaultValue: 100,
        obligatory: true,
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Number $player_count;

    #[Attribute\Text(
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Text $helper_before_transaction_add;

    #[Attribute\Checkbox(
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Checkbox $show_datetime_in_transaction;

    #[Attribute\Checkbox(
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Checkbox $show_budget_info;

    #[Attribute\H1(
        context: 'getEditViewPaymentSystemsContext',
    )]
    public Item\H1 $h1_5;

    #[Attribute\Text(
        noData: true,
        context: 'getEditViewPaymentSystemsPaykeeperContext',
        saveHtml: true,
    )]
    public Item\Text $helper_1_pk;

    #[Attribute\Text(
        context: 'getEditViewPaymentSystemsPaykeeperContext',
    )]
    public Item\Text $paykeeper_login;

    #[Attribute\Text(
        context: 'getEditViewPaymentSystemsPaykeeperContext',
    )]
    public Item\Text $paykeeper_pass;

    #[Attribute\Text(
        context: 'getEditViewPaymentSystemsPaykeeperContext',
    )]
    public Item\Text $paykeeper_server;

    #[Attribute\Text(
        context: 'getEditViewPaymentSystemsPaykeeperContext',
    )]
    public Item\Text $paykeeper_secret;

    #[Attribute\Text(
        noData: true,
        context: 'getEditViewPaymentSystemsPaymasterContext',
        saveHtml: true,
    )]
    public Item\Text $helper_1_pm;

    #[Attribute\Text(
        context: 'getEditViewPaymentSystemsPaymasterContext',
    )]
    public Item\Text $paymaster_merchant_id;

    #[Attribute\Text(
        context: 'getEditViewPaymentSystemsPaymasterContext',
    )]
    public Item\Text $paymaster_code;

    #[Attribute\Text(
        noData: true,
        context: 'getEditViewPaymentSystemsYandexContext',
        saveHtml: true,
    )]
    public Item\Text $helper_1_yk;

    #[Attribute\Text(
        context: 'getEditViewPaymentSystemsYandexContext',
    )]
    public Item\Text $yk_acc_id;

    #[Attribute\Text(
        context: 'getEditViewPaymentSystemsYandexContext',
    )]
    public Item\Text $yk_code;

    #[Attribute\Text(
        noData: true,
        context: 'getEditViewPaymentSystemsPayAnyWayContext',
        saveHtml: true,
    )]
    public Item\Text $helper_1_paw;

    #[Attribute\Text(
        context: 'getEditViewPaymentSystemsPayAnyWayContext',
    )]
    public Item\Text $paw_mnt_id;

    #[Attribute\Text(
        context: 'getEditViewPaymentSystemsPayAnyWayContext',
    )]
    public Item\Text $paw_code;

    #[Attribute\H1(
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\H1 $h1_4;

    #[Attribute\Select(
        defaultValue: 1,
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Select $access_to_childs;

    #[Attribute\Text(
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Text $ingame_css;

    #[Attribute\Checkbox(
        context: self::EDIT_VIEW_CONTEXT,
    )]
    public Item\Checkbox $disable_taken_field;
}
