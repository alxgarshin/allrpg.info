<?php

declare(strict_types=1);

namespace App\CMSVC\Application;

use App\Helper\RightsHelper;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait, LastUserUpdateIdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(ApplicationController::class)]
class ApplicationModel extends BaseModel
{
    use CreatedUpdatedAtTrait;
    use IdTrait;
    use LastUserUpdateIdTrait;

    public const APPLICATION_VIEW_CONTEXT = [
        'application:list',
        'application:view',
        'application:embedded',
    ];

    public const APPLICATION_WRITE_CONTEXT = [
        'application:create',
        'application:update',
    ];

    public const MYAPPLICATION_VIEW_CONTEXT = [
        'myapplication:list',
        'myapplication:view',
        'myapplication:embedded',
    ];

    public const MYAPPLICATION_IF_NOTNULL_VIEW_CONTEXT = [
        'myapplication:viewIfNotNull',
    ];

    public const MYAPPLICATION_WRITE_CONTEXT = [
        'myapplication:create',
        'myapplication:update',
    ];

    #[Attribute\H1(
        context: [self::APPLICATION_VIEW_CONTEXT],
    )]
    public Item\H1 $h1_1;

    #[Attribute\Select(
        values: 'getProjectIdValues',
        context: [self::MYAPPLICATION_VIEW_CONTEXT],
        alternativeDataColumnName: 'project_id',
        linkAtBegin: '<a href="/project/{value}/">',
        linkAtEnd: '</a>',
    )]
    public Item\Select $project_id_myapplication;

    #[Attribute\Hidden(
        defaultValue: 'getProjectId',
        context: [
            ':list',
            ':view',
            ':create',
        ],
    )]
    #[Attribute\OnCreate(callback: 'getProjectId')]
    public Item\Hidden $project_id;

    #[Attribute\Select(
        values: 'getCreatorIdValues',
        useInFilters: true,
        context: [self::APPLICATION_VIEW_CONTEXT],
    )]
    public Item\Select $creator_id;

    #[Attribute\H1(
        context: [
            self::APPLICATION_VIEW_CONTEXT,
            ['myapplication:update'],
        ],
    )]
    public Item\H1 $h1_2;

    #[Attribute\Select(
        obligatory: true,
        useInFilters: true,
        context: [
            self::APPLICATION_VIEW_CONTEXT,
            self::APPLICATION_WRITE_CONTEXT,
            self::MYAPPLICATION_VIEW_CONTEXT,
        ],
    )]
    public Item\Select $status;

    #[Attribute\Select(
        defaultValue: 'getResponsibleGamemasterIdDefault',
        values: 'getResponsibleGamemasterIdValues',
        obligatory: true,
        useInFilters: true,
        context: [
            self::APPLICATION_VIEW_CONTEXT,
            self::APPLICATION_WRITE_CONTEXT,
            self::MYAPPLICATION_VIEW_CONTEXT,
        ],
    )]
    public Item\Select $responsible_gamemaster_id;

    #[Attribute\Checkbox(
        useInFilters: true,
        context: [self::APPLICATION_VIEW_CONTEXT, self::APPLICATION_WRITE_CONTEXT],
    )]
    public Item\Checkbox $player_registered;

    #[Attribute\Select(
        context: 'getTeamApplicationContext',
    )]
    public Item\Select $team_application;

    #[Attribute\Select(
        context: [self::MYAPPLICATION_VIEW_CONTEXT],
        noData: true,
        alternativeDataColumnName: 'team_application',
    )]
    public Item\Select $team_application_myapplication;

    #[Attribute\Hidden(
        defaultValue: 'getTeamApplicationMyapplicationDefault',
        context: ['myapplication:viewOnActAdd', 'myapplication:create'],
        alternativeDataColumnName: 'team_application',
    )]
    public Item\Hidden $team_application_myapplication_add;

    #[Attribute\H1(
        context: 'getH13Context',
    )]
    public Item\H1 $h1_3;

    #[Attribute\Multiselect(
        values: 'getProjectFeeIdsValues',
        obligatory: true,
        context: 'getProjectFeeIdsContext',
    )]
    public Item\Multiselect $project_fee_ids;

    #[Attribute\Number(
        useInFilters: true,
        context: 'getMoneyContext',
    )]
    public Item\Number $money;

    #[Attribute\Number(
        useInFilters: true,
        context: 'getMoneyProvidedContext',
        defaultValue: 0,
    )]
    public Item\Number $money_provided;

    #[Attribute\Checkbox(
        useInFilters: true,
        context: 'getMoneyPaidContext',
    )]
    public Item\Checkbox $money_paid;

    #[Attribute\H1(
        context: 'getH15Context',
    )]
    public Item\H1 $h1_5;

    #[Attribute\Select(
        defaultValue: 'getRoomsSelectorDefault',
        values: 'getRoomsSelectorValues',
        locked: 'getRoomsSelectorLocked',
        noData: true,
        helpClass: 'fixed_help',
        context: 'getRoomsSelectorContext',
    )]
    public Item\Select $rooms_selector;

    #[Attribute\Wysiwyg(
        defaultValue: ' ',
        context: 'getRoomNeighboorsContext',
    )]
    public Item\Wysiwyg $room_neighboors;

    #[Attribute\Checkbox(
        useInFilters: true,
    )]
    public Item\Checkbox $eco_money_paid;

    #[Attribute\H1(
        context: 'getH14Context',
    )]
    public Item\H1 $h1_4;

    #[Attribute\Multiselect(
        values: 'getProjectCharacterIdsValues',
        search: true,
        helpClass: 'fixed_help',
        one: true,
        useInFilters: true,
        context: 'getProjectCharacterIdsContext',
        obligatory: true,
        defaultValue: 'getProjectCharacterDefault',
    )]
    public Item\Multiselect $project_character_id;

    #[Attribute\Multiselect(
        values: 'getProjectGroupIdsValues',
        search: true,
        creator: new Item\MultiselectCreator(
            table: 'project_group',
            name: 'name',
            additional: [
                'updated_at' => 'getProjectGroupIdsMultiselectCreatorUpdatedAt',
                'created_at' => 'getProjectGroupIdsMultiselectCreatorCreatedAt',
                'parent' => 0,
                'project_id' => 'getProjectGroupIdsMultiselectCreatorProjectId',
                'rights' => 2,
                'code' => 10000,
            ],
        ),
        useInFilters: true,
        context: 'getProjectGroupIdsContext',
        obligatory: true,
    )]
    public Item\Multiselect $project_group_ids;

    #[Attribute\Multiselect(
        values: 'getUserRequestedProjectGroupIdsValues',
        useInFilters: true,
        search: true,
        context: 'getUserRequestedProjectGroupIdsContext',
    )]
    public Item\Multiselect $user_requested_project_group_ids;

    #[Attribute\Number(
        defaultValue: 'getApplicationTeamCountDefault',
        obligatory: true,
        context: 'getApplicationTeamCountContext',
    )]
    public Item\Number $application_team_count;

    #[Attribute\Number(
        defaultValue: 'getApplicationsNeededCountDefault',
        context: 'getApplicationsNeededCountContext',
    )]
    public Item\Number $applications_needed_count;

    #[Attribute\Textarea(
        useInFilters: true,
        context: [self::APPLICATION_VIEW_CONTEXT, self::APPLICATION_WRITE_CONTEXT],
    )]
    public Item\Textarea $registration_comments;

    #[Attribute\Text(
        context: [
            'application:list',
            'myapplication:list',
        ],
        useInFilters: true,
    )]
    public Item\Text $sorter;

    #[Attribute\Multiselect(
        values: 'getDistributedItemIdsValues',
        locked: ['hidden'],
        search: true,
        creator: new Item\MultiselectCreator(
            table: 'resource',
            name: 'name',
            additional: [
                'updated_at' => 'getDistributedItemIdsCreatedAtUpdatedAt',
                'created_at' => 'getDistributedItemIdsCreatedAtUpdatedAt',
                'project_id' => 'getDistributedItemIdsMultiselectCreatorProjectId',
                'creator_id' => 'getDistributedItemIdsMultiselectCreatorCreatorId',
                'distributed_item' => '1',
                'price' => 0,
                'quantity_needed' => 1,
                'quantity' => 0,
            ],
        ),
        useInFilters: true,
        context: [self::APPLICATION_VIEW_CONTEXT, self::APPLICATION_WRITE_CONTEXT],
    )]
    public Item\Multiselect $distributed_item_ids;

    #[Attribute\Multiselect(
        values: 'getQrpgKeyValues',
        images: 'getQrpgKeyImages',
        search: true,
        useInFilters: true,
        context: [
            self::APPLICATION_VIEW_CONTEXT,
            self::APPLICATION_WRITE_CONTEXT,
            ['myapplication:view'],
        ],
    )]
    public Item\Multiselect $qrpg_key;

    #[Attribute\Wysiwyg(
        defaultValue: 'getPlayersBankValuesDefault',
        context: [
            'application:view',
            'myapplication:view',
        ],
    )]
    public Item\Wysiwyg $players_bank_values;

    #[Attribute\Checkbox(
        useInFilters: true,
        context: [
            self::APPLICATION_VIEW_CONTEXT,
            self::APPLICATION_WRITE_CONTEXT,
        ],
    )]
    public Item\Checkbox $player_got_info;

    #[Attribute\Textarea(
        defaultValue: 'getUserSicknessDefault',
        context: [self::APPLICATION_VIEW_CONTEXT],
    )]
    public Item\Textarea $user_sickness;

    #[Attribute\H1(
        context: ['application:update', 'myapplication:update'],
    )]
    public Item\H1 $plots;

    #[Attribute\Wysiwyg(
        defaultValue: 'getPlotsDataDefault',
        context: ['application:viewIfNotNull', 'myapplication:viewIfNotNull'],
    )]
    public Item\Wysiwyg $plots_data;

    #[Attribute\Checkbox(
        useInFilters: true,
        context: [null],
    )]
    public Item\Checkbox $deleted_by_player;

    #[Attribute\Checkbox(
        context: [null],
    )]
    public Item\Checkbox $deleted_by_gamemaster;

    public function getProjectId(): ?int
    {
        return KIND === 'application' ? RightsHelper::getActivatedProjectId() : (int) $_REQUEST['project_id'];
    }
}
