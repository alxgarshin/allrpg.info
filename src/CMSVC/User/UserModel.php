<?php

declare(strict_types=1);

namespace App\CMSVC\User;

use App\CMSVC\HelperGeographyCity\HelperGeographyCityController;
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\BaseObject\Trait\CreatedUpdatedAtTrait;
use Fraym\Element\{Attribute, Item};

#[Controller(UserController::class)]
class UserModel extends BaseModel
{
    use CreatedUpdatedAtTrait;

    public const CONTEXT = [
        'user:list',
        'user:view',
        'user:create',
        'user:update',
        'user:embedded',
        'profile:view',
        'profile:create',
        'profile:update',
        'profile:embedded',
    ];

    public const REGISTER_CONTEXT = [
        'register:view',
        'register:create',
    ];

    #[Attribute\H1(
        context: self::REGISTER_CONTEXT,
    )]
    public Item\H1 $through_social_network;

    #[Attribute\Text(
        context: ['register:view'],
        saveHtml: true,
    )]
    public Item\Text $list_of_social_networks;

    #[Attribute\H1(
        context: self::REGISTER_CONTEXT,
    )]
    public Item\H1 $or_by_inputting_data;

    #[Attribute\Hidden(
        obligatory: true,
        noData: true,
        context: self::CONTEXT,
    )]
    public Item\Hidden $id;

    #[Attribute\Tab(
        context: self::CONTEXT,
    )]
    public Item\Tab $baseinfo;

    #[Attribute\H1(
        context: self::CONTEXT,
    )]
    public Item\H1 $h1_1;

    #[Attribute\Number(
        defaultValue: 'getSidDefault',
        context: ['user:list', 'user:view', 'user:embedded', 'profile:list', 'profile:view', 'profile:embedded'],
    )]
    public Item\Number $sid;

    #[Attribute\File(
        uploadNum: 2,
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\File $photo;

    #[Attribute\Text(
        obligatory: true,
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Text $fio;

    #[Attribute\Text(
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Text $nick;

    #[Attribute\Calendar(
        obligatory: true,
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Calendar $birth;

    #[Attribute\Select(
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Select $gender;

    #[Attribute\Select(
        helper: new HelperGeographyCityController(),
        obligatory: true,
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Select $city;

    #[Attribute\H1(
        context: self::CONTEXT,
    )]
    public Item\H1 $h1_2;

    #[Attribute\Email(
        obligatory: true,
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Email $em;

    #[Attribute\Select(
        context: ['user:list', 'user:view', 'user:embedded', 'profile:list', 'profile:view', 'profile:embedded'],
    )]
    public Item\Select $em_verified;

    #[Attribute\Hidden(
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Hidden $login;

    #[Attribute\Password(
        repeatPasswordFieldName: 'pass2',
        minChar: 3,
        maxChar: 20,
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Password $password_hashed;

    #[Attribute\Password(
        minChar: 3,
        maxChar: 20,
        noData: true,
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Password $pass2;

    #[Attribute\Checkbox(
        defaultValue: true,
        obligatory: true,
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Checkbox $agreement;

    #[Attribute\Tab(
        context: self::CONTEXT,
    )]
    public Item\Tab $contacts;

    #[Attribute\H1(
        context: self::CONTEXT,
    )]
    public Item\H1 $h1_3;

    #[Attribute\Text(
        context: [self::CONTEXT, self::REGISTER_CONTEXT],
    )]
    public Item\Text $phone;

    #[Attribute\Text(
        context: self::CONTEXT,
    )]
    public Item\Text $telegram;

    #[Attribute\Text(
        context: self::CONTEXT,
    )]
    public Item\Text $vkontakte_visible;

    #[Attribute\Hidden(
        context: self::CONTEXT,
    )]
    public Item\Hidden $vkontakte;

    #[Attribute\Text(
        context: self::CONTEXT,
    )]
    public Item\Text $facebook_visible;

    #[Attribute\Hidden(
        context: self::CONTEXT,
    )]
    public Item\Hidden $facebook;

    #[Attribute\Text(
        context: self::CONTEXT,
    )]
    public Item\Text $livejournal;

    #[Attribute\Text(
        context: self::CONTEXT,
    )]
    public Item\Text $skype;

    #[Attribute\Text(
        context: self::CONTEXT,
    )]
    public Item\Text $icq;

    #[Attribute\Text(
        context: self::CONTEXT,
    )]
    public Item\Text $jabber;

    #[Attribute\Text(
        context: self::CONTEXT,
    )]
    public Item\Text $twitter;

    #[Attribute\Text(
        context: self::CONTEXT,
    )]
    public Item\Text $linkedin;

    #[Attribute\Tab(
        context: self::CONTEXT,
    )]
    public Item\Tab $additional_information;

    #[Attribute\H1(
        context: self::CONTEXT,
    )]
    public Item\H1 $h1_4;

    #[Attribute\Multiselect(
        context: self::CONTEXT,
    )]
    public Item\Multiselect $prefer;

    #[Attribute\Multiselect(
        context: self::CONTEXT,
    )]
    public Item\Multiselect $prefer2;

    #[Attribute\Multiselect(
        context: self::CONTEXT,
    )]
    public Item\Multiselect $prefer3;

    #[Attribute\Multiselect(
        context: self::CONTEXT,
    )]
    public Item\Multiselect $prefer4;

    #[Attribute\Multiselect(
        context: self::CONTEXT,
    )]
    public Item\Multiselect $speciality;

    #[Attribute\H1(
        context: self::CONTEXT,
    )]
    public Item\H1 $h1_5;

    #[Attribute\Text(
        context: self::CONTEXT,
    )]
    public Item\Text $ingroup;

    #[Attribute\Textarea(
        context: self::CONTEXT,
    )]
    public Item\Textarea $additional;

    #[Attribute\Textarea(
        context: self::CONTEXT,
    )]
    public Item\Textarea $sickness;

    #[Attribute\Tab(
        context: self::CONTEXT,
    )]
    public Item\Tab $settings;

    #[Attribute\H1(
        context: self::CONTEXT,
    )]
    public Item\H1 $h1_6;

    #[Attribute\Multiselect(
        defaultValue: [2],
        context: self::CONTEXT,
    )]
    public Item\Multiselect $hidesome;

    #[Attribute\Number(
        defaultValue: 50,
        context: self::CONTEXT,
    )]
    public Item\Number $bazecount;

    #[Attribute\Select(
        defaultValue: 1,
        obligatory: true,
        context: self::CONTEXT,
    )]
    public Item\Select $subs_type;

    #[Attribute\Multiselect(
        defaultValue: 'getSubsObjectsList',
        context: self::CONTEXT,
    )]
    public Item\Multiselect $subs_objects;

    #[Attribute\Multiselect(
        context: 'getRightsContext',
    )]
    public Item\Multiselect $rights;

    #[Attribute\Checkbox(
        noData: true,
        context: self::CONTEXT,
    )]
    public Item\Checkbox $messaging_active;

    #[Attribute\Checkbox(
        context: self::CONTEXT,
    )]
    public Item\Checkbox $block_save_referer;

    #[Attribute\Checkbox(
        context: self::CONTEXT,
    )]
    public Item\Checkbox $block_auto_redirect;

    #[Attribute\Hidden(
        context: self::CONTEXT,
    )]
    public Item\Hidden $last_get_new_events;

    #[Attribute\Checkbox(
        defaultValue: false,
        noData: true,
        context: ['user:embedded'],
    )]
    public Item\Checkbox $calendarstyle;

    #[Attribute\Text(
        context: ['people:view'],
    )]
    public Item\Text $status;

    #[Attribute\Hidden(
        noData: true,
        context: self::REGISTER_CONTEXT,
    )]
    public Item\Hidden $approvement;
}
