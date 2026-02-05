<?php

declare(strict_types=1);

namespace App\CMSVC\Trait;

use App\CMSVC\Application\{ApplicationModel};
use App\CMSVC\BankTransaction\BankTransactionService;
use App\CMSVC\Character\{CharacterModel, CharacterService};
use App\CMSVC\Group\GroupService;
use App\CMSVC\Plot\PlotService;
use App\Helper\{MessageHelper, RightsHelper, TextHelper};
use Fraym\BaseObject\BaseModel;
use Fraym\Element\{Attribute, Item};
use Fraym\Enum\{ActEnum, ActionEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper, ResponseHelper};
use Fraym\Interface\{ElementItem, HasDefaultValue};

/** Общие функции для сервисов мастерской и игроцкой работы с заявкой */
trait ApplicationServiceTrait
{
    use ProjectDataTrait;
    use UserServiceTrait;

    public ?array $profileFieldsList = [
        'fio',
        'nick',
        'em',
        'phone',
        'vkontakte_visible',
        'facebook_visible',
        'telegram',
    ];

    private ?int $projectId = null;
    private ?array $applicationData = null;
    private int $applicationType = 0;

    private int $applicationsNeededCount = 0;
    private ?bool $hasTeamApplications = null;
    private ?bool $canSetFee = null;

    private ?bool $deletedView = null;
    private ?bool $excelView = null;

    private ?bool $historyView = null;
    private array $historyViewIds = [
        'prev' => null,
        'now' => null,
        'next' => null,
    ];

    private ?ApplicationModel $historyViewNowData = null;

    private ?CharacterModel $characterData = null;

    /** @var array[] */
    private array $deletedApplicationsData = [];

    /** Переменные модели */
    private ?array $usersDataTableView = null;
    private ?array $usersDataTableViewShort = null;

    private ?array $applicationFields = null;
    private ?array $dependentFields = null;
    private ?array $dependentFieldsToEnsureMustbe = null;

    private ?array $fullProjectGroupsData = null;
    private ?array $projectGroupsData = null;
    private ?array $masterGroupSelectorValues = null;
    private ?array $projectGroupsDataById = null;
    private ?array $projectGroupsPlayerNotSee = null;
    private ?array $userRequestedProjectGroupsData = null;
    private ?array $projectCharacterIds = null;

    private ?array $feeOptions = null;
    private ?array $feeLockedRoom = null;
    private ?array $feePaidFixedRooms = null;
    private ?array $roomsData = null;
    private ?int $roomSelected = null;
    private ?array $lockedRooms = null;

    private ?array $qrpgKeysValues = null;
    private ?array $qrpgKeysImages = null;

    public function init(): static
    {
        if (DataHelper::getId() && in_array(KIND, ['application', 'myapplication', 'ingame'])) {
            $this->getApplicationData((int) DataHelper::getId());
        } elseif (KIND === 'ingame' && CookieHelper::getCookie('ingame_application_id')) {
            $this->getApplicationData((int) CookieHelper::getCookie('ingame_application_id'));
        } else {
            $this->applicationType = (int) ($_REQUEST['team_application_myapplication_add'][0] ?? $_REQUEST['application_type'] ?? 0);
        }

        $this->getProjectData();

        return $this;
    }

    public function getApplicationData(?int $applicationId = null): ?array
    {
        if (is_null($this->applicationData) && $applicationId) {
            $applicationData = $this->applicationData = DB->findObjectById($applicationId, 'project_application');

            if (!$applicationData) {
                ResponseHelper::redirect('/myapplication/');
            } else {
                /** Проверяем: возможно, это игрок кинул ссылку мастеру, и надо перевести мастера в раздел просмотра заявок по проекту */
                if (
                    KIND === 'myapplication' &&
                    !(
                        $applicationData['offer_to_user_id'] === CURRENT_USER->id() ||
                        $applicationData['creator_id'] === CURRENT_USER->id()
                    ) &&
                    $applicationData['project_id'] > 0 &&
                    RightsHelper::checkRights(
                        ["{admin}", "{gamemaster}", "{newsmaker}", "{fee}", "{budget}"],
                        '{project}',
                        $applicationData['project_id'],
                    )
                ) {
                    ResponseHelper::redirect('/application/application/' . $applicationData['id'] . '/act=edit&project_id=' . $applicationData['project_id']);
                }

                $this->applicationType = (int) $applicationData['team_application'];

                if ($this->applicationType === 1 && $applicationData['project_character_id'] > 0) {
                    $this->applicationsNeededCount = DB->select(
                        tableName: 'project_character',
                        criteria: [
                            'id' => $applicationData['project_character_id'],
                        ],
                        oneResult: true,
                        fieldsSet: [
                            'applications_needed_count',
                        ],
                    )['applications_needed_count'];
                }
            }
        }

        return $this->applicationData;
    }

    public function getDeletedView(): bool
    {
        if (is_null($this->deletedView)) {
            $this->deletedView = ($_REQUEST['deleted'] ?? false) === '1';
        }

        return $this->deletedView;
    }

    public function getHistoryView(): bool
    {
        if (is_null($this->historyView)) {
            $this->historyView = ($_REQUEST['history_view'] ?? false) === '1' && $this->act === ActEnum::view;
        }

        return $this->historyView;
    }

    public function getHistoryViewIds(): array
    {
        if (is_null($this->historyViewIds['now'])) {
            $this->getHistoryViewNowDataAndIds();
        }

        return $this->historyViewIds;
    }

    public function getHistoryViewNowData(): ?ApplicationModel
    {
        if (is_null($this->historyViewNowData)) {
            $this->getHistoryViewNowDataAndIds();
        }

        return $this->historyViewNowData;
    }

    public function getCharacterData(?int $characterId = null): ?CharacterModel
    {
        if ($characterId) {
            /** @var CharacterService */
            $characterService = CMSVCHelper::getService('character');
            $this->characterData = $characterService->get(
                $characterId,
                [
                    'project_id' => $this->getActivatedProjectId(),
                ],
            );
        }

        return $this->characterData;
    }

    /** Динамические поля.
     * @return array<int, ElementItem>
     */
    public function getApplicationFields(): array
    {
        if (is_null($this->applicationFields)) {
            $this->applicationFields = iterator_to_array(
                DataHelper::virtualStructure(
                    'SELECT * FROM project_application_field WHERE project_id=:project_id AND application_type=:application_type' . ($this->act === ActEnum::add ? ' AND (hide_field_on_application_create IS NULL OR hide_field_on_application_create="0")' : '') . ' ORDER BY field_code',
                    [
                        ['project_id', $this->getActivatedProjectId()],
                        ['application_type', $this->getExcelType() > 0 ? $this->getExcelType() : $this->applicationType],
                    ],
                    'field_',
                    [
                        'field_height',
                        'show_if',
                        'show_in_table',
                        'ingame_settings',
                    ],
                ),
            );
        }

        return $this->applicationFields;
    }

    /** Зависимые поля */
    public function getDependentFields(): ?array
    {
        if (is_null($this->dependentFields)) {
            $model = $this->model;

            foreach ($this->getApplicationFields() as $applicationField) {
                if (in_array($this->act, [ActEnum::edit, ActEnum::add])) {
                    $showIf = $applicationField->getAttribute()->additionalData['show_if'];

                    if (!is_null($showIf) && str_replace('-', '', $showIf) !== '') {
                        $dependentFields = [];

                        unset($matches);
                        preg_match_all('#-(\d+):(\d+)#', $showIf, $matches);

                        foreach ($matches[1] as $key => $value) {
                            $dependingOnField = $model->getElement('virtual' . $value);

                            $virtualFieldData = $_REQUEST['virtual' . $value][0] ?? null;

                            if (
                                in_array(ACTION, [ActionEnum::create, ActionEnum::change])
                                && $virtualFieldData
                                && (
                                    ($dependingOnField instanceof Item\Multiselect && ($virtualFieldData[$matches[2][$key]] ?? null) === 'on') ||
                                    ($dependingOnField instanceof Item\Select && $virtualFieldData === $matches[2][$key])
                                )
                            ) {
                                $this->dependentFieldsToEnsureMustbe[$applicationField->name] = true;
                            }

                            if ($dependingOnField instanceof Item\Multiselect) {
                                $dependentFields[] = [
                                    'type' => 'multiselect',
                                    'name' => 'virtual' . $value . '[0]',
                                    'value' => $matches[2][$key],
                                ];
                            } elseif ($dependingOnField instanceof Item\Select) {
                                $dependentFields[] = [
                                    'type' => 'select',
                                    'name' => 'virtual' . $value . '[0]',
                                    'value' => $matches[2][$key],
                                ];
                            }
                        }

                        unset($matches);
                        preg_match_all('#-locat:(\d+)#', $showIf, $matches);

                        foreach ($matches[1] as $key => $value) {
                            if (
                                in_array(ACTION, [ActionEnum::create, ActionEnum::change]) &&
                                ($_REQUEST['project_group_ids'][0][$value] ?? false) === 'on'
                            ) {
                                $this->dependentFieldsToEnsureMustbe[$applicationField->name] = true;
                            }

                            $dependentFields[] = [
                                'type' => 'multiselect',
                                'name' => 'project_group_ids[0]',
                                'value' => $value,
                            ];
                        }

                        $this->dependentFields[$applicationField->name] = [
                            'dependentFields' => $dependentFields,
                        ];
                    }
                }
            }
        }

        return $this->dependentFields;
    }

    public function getDependentFieldsToEnsureMustbe(): ?array
    {
        if (is_null($this->dependentFieldsToEnsureMustbe)) {
            $this->getDependentFields();
        }

        return $this->dependentFieldsToEnsureMustbe;
    }

    /** Группы */
    public function getProjectGroupsData(): ?array
    {
        if ($this->getProjectData() && is_null($this->projectGroupsData)) {
            /** @var GroupService */
            $groupService = CMSVCHelper::getService('group');

            $fullProjectGroupsData = [];
            $projectGroupsData = [];
            $masterGroupSelectorValues = [];
            $projectGroupsDataById = [];
            $projectGroupsPlayerNotSee = [];
            $userRequestedProjectGroupsData = [];

            if ($this->getActivatedProjectId()) {
                $tempProjectGroupsData = DB->getTreeOfItems(
                    false,
                    'project_group',
                    'parent',
                    null,
                    ' AND project_id=' . $this->getActivatedProjectId(),
                    'code, name',
                    0,
                    'id',
                    'name',
                    1000000,
                    false,
                );

                foreach ($tempProjectGroupsData as $key => $data) {
                    $data[1] = $groupService->createGroupPath($key, $tempProjectGroupsData);
                    $fullProjectGroupsData[$key] = $data;

                    if (
                        $this->getServiceEntityName() !== 'myapplication' ||
                        (
                            (
                                ($this->act === ActEnum::add || ACTION === ActionEnum::create) &&
                                $data[3]['rights'] < 2 &&
                                (int) $data[3]['disallow_applications'] === 0
                            ) || (
                                $this->act !== ActEnum::add &&
                                ACTION !== ActionEnum::create &&
                                $data[3]['rights'] < 3
                            )
                        )
                    ) {
                        $projectGroupsData[$key] = $data;
                        $projectGroupsDataById[$data[0]] = [$data[1], $data[2], $data[3]];

                        if ($data[3]['rights'] === 3 || $data[3]['rights'] === 2) {
                            $masterGroupSelectorValues[] = [$data[0], $groupService->createGroupPath($key, $projectGroupsData)];
                        }

                        if ($data[3]['rights'] === 3) { // если это группа типа "мастерская группа: не показывать даже игроку", добавляем ее в переменную, чтобы потом, например, не писать о ней игроку
                            $projectGroupsPlayerNotSee[] = $data[0];
                        } elseif ((int) $data[3]['user_can_request_access'] === 1) { // группа не "мастерская" и к ней можно запрашивать доступ игрокам
                            $userRequestedProjectGroupsData[] = [$data[0], $groupService->createGroupPath($key, $projectGroupsData), $data[2]];
                        }
                    }
                }
            }

            $this->fullProjectGroupsData = $fullProjectGroupsData;
            $this->projectGroupsData = $projectGroupsData;
            $this->projectGroupsDataById = $projectGroupsDataById;
            $this->projectGroupsPlayerNotSee = $projectGroupsPlayerNotSee;
            $this->userRequestedProjectGroupsData = $userRequestedProjectGroupsData;
            $this->masterGroupSelectorValues = $masterGroupSelectorValues;
        }

        return $this->projectGroupsData;
    }

    public function getFullProjectGroupsData(): array
    {
        if (is_null($this->fullProjectGroupsData)) {
            $this->getProjectGroupsData();
        }

        return $this->fullProjectGroupsData;
    }

    public function getProjectGroupIdsValues(): ?array
    {
        return $this->getProjectGroupsData();
    }

    public function getProjectGroupsDataById(): array
    {
        if (is_null($this->projectGroupsDataById)) {
            $this->getProjectGroupsData();
        }

        return $this->projectGroupsDataById;
    }

    public function getUserRequestedProjectGroupsData(): ?array
    {
        if (is_null($this->userRequestedProjectGroupsData)) {
            $this->getProjectGroupsData();
        }

        return $this->userRequestedProjectGroupsData;
    }

    public function getMasterGroupSelectorValues(): array
    {
        if (is_null($this->masterGroupSelectorValues)) {
            $this->getProjectGroupsData();
        }

        return $this->masterGroupSelectorValues;
    }

    public function getProjectGroupsPlayerNotSee(): array
    {
        if (is_null($this->projectGroupsPlayerNotSee)) {
            $this->getProjectGroupsData();
        }

        return $this->projectGroupsPlayerNotSee;
    }

    public function getProjectGroupIdsContext(): array
    {
        return $this->getProjectGroupIdsValues() ? [
            ApplicationModel::APPLICATION_VIEW_CONTEXT,
            ApplicationModel::APPLICATION_WRITE_CONTEXT,
            ApplicationModel::MYAPPLICATION_VIEW_CONTEXT,
            ['myapplication:create'],
        ] : [];
    }

    public function getUserRequestedProjectGroupIdsValues(): ?array
    {
        return $this->getUserRequestedProjectGroupsData();
    }

    public function getUserRequestedProjectGroupIdsContext(): array
    {
        return $this->getProjectGroupIdsValues() && $this->getUserRequestedProjectGroupIdsValues() ? [
            ApplicationModel::APPLICATION_VIEW_CONTEXT,
            ApplicationModel::MYAPPLICATION_VIEW_CONTEXT,
            ApplicationModel::MYAPPLICATION_WRITE_CONTEXT,
        ] : [];
    }

    /** Персонажи */
    public function getProjectCharacterIds(): ?array
    {
        if ($this->getProjectData() && is_null($this->projectCharacterIds)) {
            /** @var CharacterService */
            $characterService = CMSVCHelper::getService('character');

            $projectCharacterIds = [];

            if ($this->getActivatedProjectId()) {
                $projectCharacterIdsSort = [];
                $charactersApplicationTakenCount = [];

                $projectGroupsDataById = $this->getProjectGroupsDataById();
                $fullProjectGroupsDataById = $this->getFullProjectGroupsData();

                $params = [
                    'project_id' => $this->getActivatedProjectId(),
                ];

                if (DataHelper::getId() || ($_REQUEST['application_type'] ?? false)) {
                    $params['team_character'] = $this->applicationType;
                }

                if ($this->getServiceEntityName() === 'myapplication') {
                    $params['disallow_applications'] = 0;

                    if ($this->act === ActEnum::add) {
                        $charactersApplicationTakenCountQuery = DB->query(
                            "SELECT pc.id, COUNT(pa.id) AS taken_count FROM project_character AS pc LEFT JOIN project_application AS pa ON pa.project_character_id = pc.id AND pa.project_id = pc.project_id AND pa.status = 3 AND pa.deleted_by_player = '0' AND pa.deleted_by_gamemaster = '0' WHERE pc.project_id = :project_id GROUP BY pc.id",
                            [
                                ['project_id', $this->getActivatedProjectId()],
                            ],
                        );

                        foreach ($charactersApplicationTakenCountQuery as $charactersApplicationTakenCountData) {
                            $charactersApplicationTakenCount[$charactersApplicationTakenCountData['id']] = $charactersApplicationTakenCountData['taken_count'];
                        }
                    }
                }

                $projectCharactersData = $characterService->getAll($params);

                foreach ($projectCharactersData as $projectCharacterData) {
                    $projectGroupId = GroupService::getLowestGroup(
                        $projectGroupsDataById,
                        $projectCharacterData->project_group_ids->get(),
                    );

                    $characterId = $projectCharacterData->id->getAsInt();
                    $characterName = $projectCharacterData->name->get();
                    $characterGroups = $projectCharacterData->project_group_ids->get();

                    if (
                        $this->getServiceEntityName() !== 'myapplication' ||
                        (
                            (
                                $projectGroupId > 0 &&
                                (
                                    $this->act !== ActEnum::add ||
                                    (
                                        ($fullProjectGroupsDataById[$projectGroupId][2]['disallow_applications'] ?? null) !== '1' &&
                                        $projectCharacterData->applications_needed_count->get() > ($charactersApplicationTakenCount[$characterId] ?? 0) &&
                                        isset($projectGroupsDataById[$projectGroupId])
                                    )
                                )
                            ) || (
                                $projectGroupId === 0 &&
                                !$characterGroups
                            )
                        )
                    ) {
                        if ($projectGroupId > 0) {
                            $characterName .= ' <span class="small">(' . $projectGroupsDataById[$projectGroupId][0] . ')</span>';
                        }
                        $projectCharacterIds[] = [$characterId, $characterName];
                        $projectCharacterIdsSort[] = mb_strtolower($characterName);
                    }
                }

                array_multisort($projectCharacterIdsSort, SORT_ASC, $projectCharacterIds);
            }

            $this->projectCharacterIds = $projectCharacterIds;
        }

        return $this->projectCharacterIds;
    }

    public function getProjectCharacterIdsValues(): ?array
    {
        return $this->getProjectCharacterIds();
    }

    public function getProjectCharacterDefault(): ?int
    {
        return ($_REQUEST['character_id'] ?? false) ? (int) $_REQUEST['character_id'] : null;
    }

    public function getProjectCharacterIdsContext(): array
    {
        return $this->getProjectCharacterIds() ? [
            ApplicationModel::APPLICATION_VIEW_CONTEXT,
            ApplicationModel::APPLICATION_WRITE_CONTEXT,
            ApplicationModel::MYAPPLICATION_VIEW_CONTEXT,
            ['myapplication:create'],
        ] : [];
    }

    public function getApplicationsNeededCountDefault(): int
    {
        return $this->applicationsNeededCount;
    }

    public function getApplicationsNeededCountContext(): array
    {
        return DataHelper::getId() && $this->applicationType === 1 ? [
            ApplicationModel::APPLICATION_VIEW_CONTEXT,
            ApplicationModel::MYAPPLICATION_VIEW_CONTEXT,
        ] : [];
    }

    public function getSortStatus(): array
    {
        $LOCALE = LocaleHelper::getLocale(['application', 'fraym_model']);

        return $LOCALE['elements']['status']['values'];
    }

    public function getH13Context(): array
    {
        return $this->verifyFeesAvailable() ? [
            ApplicationModel::APPLICATION_VIEW_CONTEXT,
            ApplicationModel::MYAPPLICATION_VIEW_CONTEXT,
        ] : [];
    }

    public function getMoneyContext(): array
    {
        return $this->getFeeOptions() ?
            (
                $this->getServiceEntityName() === 'myapplication' ?
                [ApplicationModel::MYAPPLICATION_IF_NOTNULL_VIEW_CONTEXT] : (
                    $this->getCanSetFee() ?
                    [ApplicationModel::APPLICATION_VIEW_CONTEXT, ApplicationModel::APPLICATION_WRITE_CONTEXT] :
                    [ApplicationModel::APPLICATION_VIEW_CONTEXT]
                )
            ) :
            [];
    }

    public function getMoneyProvidedContext(): array
    {
        return $this->getFeeOptions() ?
            (
                $this->getServiceEntityName() === 'myapplication' ?
                (
                    $this->act !== ActEnum::add ?
                    [ApplicationModel::MYAPPLICATION_VIEW_CONTEXT] :
                    []
                ) : (
                    $this->getCanSetFee() ?
                    [ApplicationModel::APPLICATION_VIEW_CONTEXT, ApplicationModel::APPLICATION_WRITE_CONTEXT] :
                    [ApplicationModel::APPLICATION_VIEW_CONTEXT]
                )
            ) :
            [];
    }

    public function getMoneyPaidContext(): array
    {
        return $this->verifyFeesAvailable() ? [
            ApplicationModel::APPLICATION_VIEW_CONTEXT,
            $this->act !== ActEnum::add ? ['myapplication:view'] : [],
        ] : [];
    }

    public function getProjectFeeIdsValues(): ?array
    {
        return $this->getFeeOptions();
    }

    public function getProjectFeeIdsContext(): array
    {
        $contexts = [ApplicationModel::APPLICATION_VIEW_CONTEXT];

        if ($this->getServiceEntityName() === 'myapplication') {
            $applicationData = $this->getApplicationData();
            $contexts[] = ApplicationModel::MYAPPLICATION_VIEW_CONTEXT;

            if (!$applicationData || !($applicationData['project_fee_ids'] !== '' && $applicationData['money_paid'])) {
                $contexts[] = ApplicationModel::MYAPPLICATION_WRITE_CONTEXT;
            }
        } elseif ($this->getCanSetFee()) {
            $contexts[] = ApplicationModel::APPLICATION_WRITE_CONTEXT;
        }

        return $this->getFeeOptions() && count($this->getFeeOptions()) > 1 ? $contexts : [];
    }

    public function getH15Context(): array
    {
        return
            !$this->verifyFeesAvailable() &&
            $this->verifyRoomsAvailable() ?
            [
                ApplicationModel::APPLICATION_VIEW_CONTEXT,
                ApplicationModel::MYAPPLICATION_VIEW_CONTEXT,
            ] :
            [];
    }

    public function getH14Context(): array
    {
        return array_merge(
            [
                ApplicationModel::APPLICATION_VIEW_CONTEXT,
            ],
            ($this->getProjectCharacterIds() || $this->getProjectGroupsData() ? [ApplicationModel::MYAPPLICATION_VIEW_CONTEXT] : []),
        );
    }

    public function getApplicationTeamCountDefault(): ?int
    {
        if ($this->getProjectData() && $this->getProjectCharacterDefault()) {
            $projectCharacterData = $this->getCharacterData($this->getProjectCharacterDefault());

            return $projectCharacterData->team_applications_needed_count->get();
        }

        return null;
    }

    public function getApplicationTeamCountContext(): array
    {
        return $this->applicationType ? [
            ApplicationModel::APPLICATION_VIEW_CONTEXT,
            ApplicationModel::APPLICATION_WRITE_CONTEXT,
            ApplicationModel::MYAPPLICATION_VIEW_CONTEXT,
            ApplicationModel::MYAPPLICATION_WRITE_CONTEXT,
        ] : [];
    }

    public function getQrpgKeyValues(): ?array
    {
        if (is_null($this->qrpgKeysValues)) {
            $this->getQrpgKeyValuesAndImages();
        }

        return $this->qrpgKeysValues;
    }

    public function getQrpgKeyImages(): ?array
    {
        if (is_null($this->qrpgKeysImages)) {
            $this->getQrpgKeyValuesAndImages();
        }

        return $this->qrpgKeysImages;
    }

    public function getPlayersBankValuesDefault(): ?string
    {
        $bankBalanceText = null;
        $applicationData = $this->getApplicationData();

        if ($applicationData) {
            $LOCALE_INGAME = LocaleHelper::getLocale(['ingame', 'global']);

            $bankBalanceText = '';
            $currenciesInverted = [];
            $currencies = DB->getArrayOfItems('bank_currency WHERE project_id=' . $this->getActivatedProjectId() . ' ORDER BY name', 'id', 'name');

            foreach ($currencies as $value) {
                $currenciesInverted[$value[0]] = TextHelper::mb_ucfirst($value[1]);
            }
            $bankBalance = BankTransactionService::getApplicationBalances((int) $applicationData['id']);

            foreach ($bankBalance as $currencyId => $summ) {
                $summText = KIND === 'ingame' ? '<span>' . $summ . '</span>' : ' ' . $summ;

                $bankBalanceText .= '<div>' . ($currencyId > 0 && ($currenciesInverted[$currencyId] ?? false) ? $currenciesInverted[$currencyId] . ':' : $LOCALE_INGAME['balance']) . $summText . '</div>';
            }
        }

        return $bankBalanceText;
    }

    public function getViewScripts(): string
    {
        $blockRoomsScript = '';

        if ($this->getApplicationData()) {
            $blockRoomsScript .= '<script>';

            foreach ($this->getFeeLockedRoom() as $feeId => $feeLockedRoomsData) {
                $blockRoomsScript .= '
    window.feeLockedRoom[' . $feeId . '] = [' . implode(',', $feeLockedRoomsData) . ']';
            }
            $blockRoomsScript .= '
    window.lockedRoomsIds = [' . implode(',', array_filter($this->getLockedRooms())) . '];
    window.feesDone = [' . implode(',', array_filter(DataHelper::multiselectToArray($this->getApplicationData()['project_fee_ids']))) . '];
</script>';
        }

        /** Скрытие-показ полей в зависимости от выборов */
        $showHideFieldsScript = '';

        if ($this->getServiceEntityName() === 'application') {
            /** Добавление раздатки, если выставляется группа */
            $showHideFieldsScript .= '<script>
    //поле раздатки
		';

            foreach ($this->getProjectGroupsData() as $data) {
                $distributedItemAutoset = DataHelper::multiselectToArray($data[3]['distributed_item_autoset']);

                if ($distributedItemAutoset) {
                    $showHideFieldsScript .= "
    _(document).on('change', 'input[id=\"project_group_ids[0][" . $data[0] . "]\"]', function () {
        const ths = _(this);

        if (ths.is(':checked')) {
            ";

                    foreach ($distributedItemAutoset as $distributedItemAutosetId) {
                        $showHideFieldsScript .= "_(convertName('input#distributed_item_ids[0][" . $distributedItemAutosetId . "]')).checked(true).change();
            ";
                    }
                    $showHideFieldsScript .= '
        }
    });';
                }
            }

            $showHideFieldsScript .= '
</script>';
        }

        $dependentFields = $this->getDependentFields();

        $showHideFieldsScript .= '<script>
    //динамические поля
		';

        foreach ($this->model->elementsList as $applicationField) {
            $applicationFieldName = $applicationField->name;

            if ($dependentFields[$applicationFieldName] ?? false) {
                $fieldDependentFields = $dependentFields[$applicationFieldName]['dependentFields'];

                $showHideItemData = [
                    'name' => $applicationFieldName . '[0]',
                    'dependencies' => [],
                ];

                foreach ($fieldDependentFields as $dependentField) {
                    $showHideItemData['dependencies'][] = [[
                        'type' => $dependentField['type'],
                        'name' => $dependentField['name'],
                        'value' => $dependentField['value'],
                    ]];
                }

                $showHideFieldsScript .= '
    dynamicFieldsList.push(' . DataHelper::jsonFixedEncode($showHideItemData) . ');';
            }
        }

        $showHideFieldsScript .= '
</script>';

        return $blockRoomsScript . $showHideFieldsScript;
    }

    public function postModelInit(BaseModel $model): BaseModel
    {
        /** @var ApplicationModel $model */

        $LOCALE = $this->LOCALE;
        $LOCALE_ELEMENTS = $this->entity->getElementsLocale();

        $applicationData = $this->getApplicationData();

        if ($this->entity->name === 'application') {
            /** @var Attribute\Timestamp */
            $updatedAt = $model->getElement('updated_at')->getAttribute();
            $updatedAt->showInObjects = true;
            $updatedAt->customAsHTMLRenderer = 'getUpdatedAtCustomAsHTMLRenderer';

            $model = $model->changeElementsOrder('updated_at', 'player_registered');

            $model->getElement('h1_3')->shownName = $LOCALE_ELEMENTS['h1_3'][$this->getRoomsData() ? 'with_rooms' : 'no_rooms'];

            if (!$this->getExcelView()) {
                $projectData = $this->getProjectData();
                $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

                if ($projectData) {
                    $sorterFieldName = '';
                    $sortersData = DB->query(
                        'SELECT * FROM project_application_field WHERE id=:sorter' . ($projectData->sorter2->get() ? ' OR id=:sorter2' : '') . ' ORDER BY application_type',
                        [
                            ['sorter', $projectData->sorter->get()],
                            ['sorter2', $projectData->sorter2->get()],
                        ],
                    );

                    foreach ($sortersData as $sorterData) {
                        $sorterFieldName .= $sorterFieldName === '' ? DataHelper::escapeOutput($sorterData['field_name']) : ' / ' . DataHelper::escapeOutput($sorterData['field_name']);
                    }

                    if ($sorterFieldName === '') {
                        $sorterFieldName = $LOCALE_FRAYM['basefunc']['not_set'];
                    }

                    $model->getElement('sorter')->shownName = $sorterFieldName;
                }
            }

            $model->getElement('created_at')->getAttribute()->useInFilters = true;
        } elseif ($this->entity->name === 'myapplication') {
            $LOCALE_APPLICATION_ELEMENTS = LocaleHelper::getLocale(['application', 'fraym_model', 'elements']);

            /** @var Item\Multiselect */
            $projectGroupIdsElement = $model->getElement('project_group_ids');
            $projectGroupIdsElement->getAttribute()->creator = null;

            if ($this->act === ActEnum::add || ACTION === ActionEnum::create) {
                /** Убираем лишние поля из модели */
                if (!$this->getProjectGroupsData() && !$this->getProjectCharacterIds()) {
                    $model->removeElement('h1_2');
                }

                /** Добавляем часть полей профиля пользователя при подаче заявки */
                if (CURRENT_USER->id()) {
                    $userData = $this->getUserService()->get(CURRENT_USER->id());

                    $model->initElement(
                        'h1_contacts',
                        Item\H1::class,
                        new Attribute\H1(
                            context: [
                                ':view',
                                ':create',
                            ],
                        ),
                    );
                    $model->getElement('h1_contacts')->shownName = $LOCALE['check_contacts'];

                    foreach ($this->profileFieldsList as $profileField) {
                        foreach ($userData->elementsList as $element) {
                            if ($element->name === $profileField) {
                                $attribute = $element->getAttribute();

                                if ($attribute instanceof HasDefaultValue) {
                                    $attribute->noData = true;
                                    $attribute->context = [
                                        ':view',
                                        ':create',
                                    ];

                                    $attribute->defaultValue = $userData->getElement($element->name)->get();

                                    $model->initElement(
                                        $element->name,
                                        $element::class,
                                        $attribute,
                                    );
                                    $model->getElement($element->name)->shownName = $element->shownName;
                                }

                                break;
                            }
                        }
                    }
                }
            } else {
                if (DataHelper::getId() && $applicationData && $applicationData['money_paid'] !== '1') {
                    $model->getElement('money_provided')->getAttribute()->linkAt = new Item\LinkAt(null, ' <a id="provide_payment">' . $LOCALE['provide_payment'] . '</a>');

                    $model->getElement('project_group_ids')->getAttribute()->linkAt = new Item\LinkAt('<span id="project_group_ids[0][{value}]">', '</span>');
                }
            }

            $model->getElement('h1_3')->shownName = $LOCALE_APPLICATION_ELEMENTS['h1_3'][$this->getRoomsData() ? 'with_rooms' : 'no_rooms'];

            $model = $model
                ->changeElementsOrder('h1_2', 'project_id_myapplication')
                ->changeElementsOrder('team_application_myapplication', 'status');

            $model->getElement('responsible_gamemaster_id')->getAttribute()->useInFilters = false;
            $model->getElement('sorter')->getAttribute()->useInFilters = true;
        }

        if ($this->getHistoryView()) {
            /** Убираем лишние поля из модели */
            $model
                ->removeElement('responsible_gamemaster_id')
                ->removeElement('sorter')
                ->removeElement('application_team_count')
                ->removeElement('applications_needed_count')
                ->removeElement('distributed_item_ids')
                ->removeElement('qrpg_key')
                ->removeElement('plots')
                ->removeElement('plots_data');

            if ($this->entity->name === 'application' && $this->getHasTeamApplications()) {
                $model->removeElement('team_application');
            }

            $model->getElement('updated_at')->getAttribute()->context = [];

            foreach ($model->elementsList as $element) {
                $element->helpText = null;
            }
        }

        foreach ($this->getApplicationFields() as $applicationField) {
            $context = $applicationField->getAttribute()->context;
            $ingameSettings = DataHelper::multiselectToArray($applicationField->getAttribute()->additionalData['ingame_settings']);
            $readRight = $context[0];
            $writeRight = $context[1];
            $context = [];

            if ($readRight <= 100) {
                $context[] = ApplicationModel::APPLICATION_VIEW_CONTEXT;
            }

            if ($writeRight <= 100) {
                $context[] = ApplicationModel::APPLICATION_WRITE_CONTEXT;
            }

            if ($readRight <= 10) {
                $context[] = ApplicationModel::MYAPPLICATION_VIEW_CONTEXT;
            }

            if ($writeRight <= 10) {
                $context[] = ApplicationModel::MYAPPLICATION_WRITE_CONTEXT;
            }

            if (in_array('game', $ingameSettings)) {
                $context[] = ['ingame:game:view'];
            }

            if (in_array('out_of_game', $ingameSettings)) {
                $context[] = ['ingame:out_of_game:view'];
            }

            $applicationField->getAttribute()->context = $context;

            if ($applicationField instanceof Item\Textarea) {
                $height = $applicationField->getAttribute()->additionalData['field_height'];

                if ($height) {
                    $applicationField->getAttribute()->rows = (int) ($height / 20);
                }
            }

            if ($this->entity->name === 'application') {
                /** Добавляем к полю сортировки возможность изменить имя персонажа при несовпадении */
                if ($this->act === ActEnum::edit && $this->applicationType === 0 && $applicationField->name === 'virtual' . $this->projectData->sorter->get() && (int) ($applicationData['project_character_id'] ?? 0) > 0) {
                    $fixCharacterNameBySorterData = DB->select(
                        'project_character',
                        [
                            'id' => $applicationData['project_character_id'],
                            'project_id' => $this->getActivatedProjectId(),
                        ],
                        true,
                    );

                    if ($fixCharacterNameBySorterData && (int) $fixCharacterNameBySorterData['applications_needed_count'] === 1 && $fixCharacterNameBySorterData['name'] !== $applicationData['sorter']) {
                        $applicationField->helpText = sprintf(
                            $LOCALE_ELEMENTS['sorter']['fix_character_name_by_sorter'],
                            $fixCharacterNameBySorterData['id'],
                        );
                        $applicationField->getAttribute()->helpClass = 'fixed_help';
                    }
                }
            }

            if ($this->getHistoryView()) {
                $applicationField->helpText = '';
            }

            $model->initElement(
                $applicationField,
            );
            $model = $model->changeElementsOrder($applicationField->name, 'plots');
        }

        /** Если это действие сохранения и у нас есть поля, зависящие от других, нам надо снять с них обязательность перед сохранением, если вдруг они не видны из-за выборов, а после сохранения данных вернуть на место */
        if (in_array(ACTION, [ActionEnum::create, ActionEnum::change])) {
            $dependentFieldsToEnsureMustbe = $this->getDependentFieldsToEnsureMustbe();

            foreach ($this->getApplicationFields() as $applicationField) {
                $showIf = $applicationField->getAttribute()->additionalData['show_if'];

                if (
                    !is_null($showIf) && str_replace('-', '', $showIf) !== ''
                    && $applicationField->getAttribute()->obligatory
                    && !($dependentFieldsToEnsureMustbe[$applicationField->name] ?? null)
                ) {
                    $model->getElement($applicationField->name)->getAttribute()->obligatory = false;
                }
            }
        }

        return $model;
    }

    public function getPlotsDataDefault(): ?string
    {
        /** @var PlotService */
        $plotService = CMSVCHelper::getService('plot');

        return $plotService->generateAllPlots($this->getActivatedProjectId(), '{application}', (int) DataHelper::getId(), $this->getServiceEntityName() === 'myapplication');
    }

    /** Проверка соседей по комнате */
    public function getListOfRoomNeighboors(?int $objId): array
    {
        $responseData = '';

        if (!is_null($objId)) {
            $usersInRoom = DB->query(
                "SELECT u.*, pa.id AS application_id, pa.sorter FROM relation AS r LEFT JOIN project_application AS pa ON pa.id=r.obj_id_from LEFT JOIN user AS u ON u.id=pa.creator_id WHERE r.obj_id_to=:obj_id_to AND r.type='{member}' AND r.obj_type_to='{room}' AND r.obj_type_from='{application}' ORDER BY u.id",
                [
                    ['obj_id_to', $objId],
                ],
            );
            $i = 0;

            foreach ($usersInRoom as $userInRoom) {
                $responseData .= ($this->getServiceEntityName() === 'application' ? '<a href="/application/' . $userInRoom['application_id'] . '/" class="' . ($i % 2 === 0 ? 'string1' : 'string2') . '" target="_blank">' . DataHelper::escapeOutput($userInRoom['sorter']) . '</a>' : '') .
                    preg_replace(
                        '#<a([^>]+)>#',
                        '<a$1 target="_blank">',
                        $this->getUserService()->showNameExtended(
                            $this->getUserService()->arrayToModel($userInRoom),
                            $this->getServiceEntityName() === 'application',
                            true,
                            $i % 2 === 0 ? 'string1' : 'string2',
                            false,
                            true,
                            true,
                        ),
                    );
                ++$i;
            }
        }

        return [
            'response' => 'success',
            'response_data' => $responseData,
        ];
    }

    /** Взносы */
    public function getFeeOptions(): ?array
    {
        if ($this->getProjectData() && is_null($this->feeOptions)) {
            $feeOptions = [];
            $feeLockedRoom = [];
            $feePaidFixedRooms = [];

            $feeOptionsData = DB->select(
                tableName: 'project_fee',
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                    'content' => '{menu}',
                ],
            );

            foreach ($feeOptionsData as $feeOptionData) {
                $feeOptionDateData = null;

                if (is_array($this->applicationData) && $this->applicationData['project_fee_ids'] !== '' && $this->applicationData['money_paid'] === '1') {
                    // если у нас уже были сохранены определенные idшки опций и при этом заявка оплачена, то выводим их
                    $savedFeeIds = DataHelper::multiselectToArray($this->applicationData['project_fee_ids']);

                    if (count($savedFeeIds) > 0) {
                        $feeOptionDateData = DB->select(
                            'project_fee',
                            [
                                'parent' => $feeOptionData['id'],
                                'id' => $savedFeeIds,
                            ],
                            true,
                        );

                        $feeOptionDateData = $feeOptionDateData === false ? null : $feeOptionDateData;

                        if ($feeOptionDateData) {
                            //если взнос уже оплачен, нам нужно зафиксировать, какие опции комнат доступны для взноса и блокировать все остальные
                            $feePaidFixedRooms = array_merge($feePaidFixedRooms, DataHelper::multiselectToArray($feeOptionData['project_room_ids']));
                        }
                    }
                }

                if (is_null($feeOptionDateData)) {
                    $feeOptionDateData = DB->query(
                        'SELECT * FROM project_fee WHERE parent=:parent AND date_from <= CURDATE() ORDER BY date_from DESC LIMIT 1',
                        [
                            ['parent', $feeOptionData['id']],
                        ],
                        true,
                    );
                }

                if (($feeOptionDateData['id'] ?? false) > 0) {
                    $feeOptions[] = [
                        $feeOptionDateData['id'],
                        DataHelper::escapeOutput($feeOptionData['name']) . ': ' . DataHelper::escapeOutput($feeOptionDateData['cost']),
                    ];

                    $feeLockedRoom[$feeOptionDateData['id']] = DataHelper::multiselectToArray($feeOptionData['project_room_ids']);
                }
            }

            $this->feeOptions = $feeOptions;
            $this->feeLockedRoom = $feeLockedRoom;
            $this->feePaidFixedRooms = $feePaidFixedRooms;
        }

        return $this->feeOptions;
    }

    public function verifyFeesAvailable(): bool
    {
        return $this->getFeeOptions() && (count($this->getFeeOptions()) > 1 || $this->act !== ActEnum::add);
    }

    public function getFeeLockedRoom(): array
    {
        if (is_null($this->feeLockedRoom)) {
            $this->getFeeOptions();
        }

        return $this->feeLockedRoom;
    }

    public function getFeePaidFixedRooms(): array
    {
        if (is_null($this->feePaidFixedRooms)) {
            $this->getFeeOptions();
        }

        return $this->feePaidFixedRooms;
    }

    /** Настройки проживания */
    public function getRoomsData(): ?array
    {
        if ($this->getProjectData() && is_null($this->roomsData)) {
            $LOCALE_APPLICATION = LocaleHelper::getLocale(['application', 'global']);

            $lockedRooms = [];
            $roomsData = [];

            $allRoomsData = DB->select(
                tableName: 'project_room',
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                ],
                order: [
                    'one_place_price',
                    'name',
                ],
            );

            foreach ($allRoomsData as $roomData) {
                $roomsTaken = RightsHelper::findByRights('{member}', '{room}', $roomData['id'], '{application}');
                $roomsTaken = (is_array($roomsTaken) ? count($roomsTaken) : 0);

                $roomsData[] = [
                    $roomData['id'],
                    sprintf(
                        $LOCALE_APPLICATION['room_description'],
                        DataHelper::escapeOutput($roomData['name']),
                        $roomData['one_place_price'],
                        $roomsTaken,
                        $roomData['places_count'],
                    ),
                ];

                if (($roomsTaken >= $roomData['places_count'] || ($this->getServiceEntityName() === 'myapplication' && ($roomData['allow_player_select'] !== '1' || ($this->getFeePaidFixedRooms() && !in_array($roomData['id'], $this->getFeePaidFixedRooms()))))) && $roomData['id'] !== $this->getRoomSelected()) {
                    $lockedRooms[] = $roomData['id'];
                }
            }

            if ($this->getServiceEntityName() === 'myapplication' && $roomsData) {
                $roomsData[] = ['', 'не определено'];
                $lockedRooms[] = '';
            }

            $this->roomsData = $roomsData;
            $this->lockedRooms = $lockedRooms;
        }

        return $this->roomsData;
    }

    public function verifyRoomsAvailable(): bool
    {
        return $this->getRoomsData() && (count($this->getLockedRooms()) < count($this->getRoomsData()) || $this->act !== ActEnum::add);
    }

    public function getRoomSelected(): ?int
    {
        if (is_null($this->roomSelected)) {
            $this->roomSelected = DataHelper::getId() ? RightsHelper::findOneByRights('{member}', '{room}', null, '{application}', DataHelper::getId()) : null;
        }

        return $this->roomSelected;
    }

    public function getLockedRooms(): ?array
    {
        if (is_null($this->lockedRooms)) {
            $this->getRoomsData();
        }

        return $this->lockedRooms;
    }

    public function getRoomsSelectorDefault(): ?int
    {
        return $this->getRoomSelected();
    }

    public function getRoomsSelectorValues(): ?array
    {
        return $this->getRoomsData();
    }

    public function getRoomsSelectorLocked(): ?array
    {
        return $this->getLockedRooms();
    }

    public function getRoomsSelectorContext(): array
    {
        return $this->verifyRoomsAvailable() ?
            array_merge(
                [
                    ApplicationModel::APPLICATION_VIEW_CONTEXT,
                    ApplicationModel::APPLICATION_WRITE_CONTEXT,
                    ApplicationModel::MYAPPLICATION_VIEW_CONTEXT,
                ],
                ($this->getLockedRooms() < $this->getRoomsData() ? [ApplicationModel::MYAPPLICATION_WRITE_CONTEXT] : []),
            ) :
            [];
    }

    public function getRoomNeighboorsContext(): array
    {
        return $this->verifyRoomsAvailable() ? [ApplicationModel::APPLICATION_VIEW_CONTEXT, DataHelper::getId() ? ApplicationModel::MYAPPLICATION_VIEW_CONTEXT : []] : [];
    }

    public function getCanSetFee(): bool
    {
        if ($this->getServiceEntityName() === 'application') {
            if (is_null($this->canSetFee)) {
                $this->canSetFee = RightsHelper::checkAllowProjectActions(PROJECT_RIGHTS, ['{gamemaster}', '{fee}']);
            }

            return $this->canSetFee;
        }

        return false;
    }

    public function getExcelView(): bool
    {
        if ($this->getServiceEntityName() === 'application') {
            if (is_null($this->excelView)) {
                $this->excelView = ($_REQUEST['export_to_excel'] ?? 0) > 0;
            }

            return $this->excelView;
        }

        return false;
    }

    public function getHasTeamApplications(): bool
    {
        if ($this->getServiceEntityName() === 'application') {
            if (is_null($this->hasTeamApplications)) {
                $checkTeamApplications = DB->count(
                    tableName: 'project_application',
                    criteria: [
                        'project_id' => $this->getActivatedProjectId(),
                        'team_application' => '1',
                    ],
                );
                $this->hasTeamApplications = $checkTeamApplications > 0;
            }

            return $this->hasTeamApplications;
        }

        return false;
    }

    public function postChange(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            $this->updateApplication((int) $successfulResultsId);
        }

        /** Если у нас есть поля, зависящие от других, нам надо вернуть на них обязательность после сохранения, если вдруг они не были видны из-за выборов */
        $dependentFieldsToEnsureMustbe = $this->getDependentFieldsToEnsureMustbe();

        foreach ($this->getApplicationFields() as $applicationField) {
            $showIf = $applicationField->getAttribute()->additionalData['show_if'];

            if (
                !is_null($showIf) && str_replace('-', '', $showIf) !== ''
                && $applicationField->getAttribute()->obligatory
                && !$dependentFieldsToEnsureMustbe[$applicationField->name]
            ) {
                $this->model->getElement($applicationField->name)->getAttribute()->obligatory = true;
            }
        }
    }

    public function postDelete(array $successfulResultsIds): void
    {
        $LOCALE = $this->LOCALE['messages'];
        $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);

        $updatingUserData = $this->getUserService()->get(CURRENT_USER->id());

        foreach ($successfulResultsIds as $successfulResultsId) {
            $applicationData = $this->arrayToModel($this->deletedApplicationsData[$successfulResultsId]) ?? $this->get($successfulResultsId);
            $applicationName = DataHelper::escapeOutput($applicationData->sorter->get());

            $projectData = $this->getProjectData($applicationData->project_id->getAsInt());
            $projectName = DataHelper::escapeOutput($projectData->name->get());

            if ($applicationData->creator_id->getAsInt() > 0) {
                $creatorUserData = $this->getUserService()->get($applicationData->creator_id->getAsInt());
            } else {
                $creatorUserData = $updatingUserData;
            }

            $userIds = [];

            if ($applicationData->responsible_gamemaster_id->get() > 0) {
                $userIds[] = $applicationData->responsible_gamemaster_id->get();
            } else {
                $userIds = RightsHelper::findByRights(
                    ['{admin}', '{gamemaster}'],
                    '{project}',
                    $this->getActivatedProjectId(),
                    '{user}',
                    false,
                );
            }

            $subject = sprintf(
                $LOCALE['application_delete_to_gamemasters_subject'],
                $applicationName,
                $projectName,
            );

            if ($this->getServiceEntityName() === 'myapplication') {
                $message = sprintf(
                    $LOCALE['application_delete_to_gamemasters_message'],
                    ABSOLUTE_PATH,
                    $successfulResultsId,
                    $projectData->id->getAsInt(),
                    $applicationName,
                    ABSOLUTE_PATH,
                    $creatorUserData->sid->get(),
                    $this->getUserService()->showName($creatorUserData),
                ) . $LOCALE_CONVERSATION['subscription']['base_text2'];
            } else {
                $message = sprintf(
                    $LOCALE['application_delete_to_gamemasters_message'],
                    ABSOLUTE_PATH,
                    $successfulResultsId,
                    $projectData->id->getAsInt(),
                    $applicationName,
                    ABSOLUTE_PATH,
                    $creatorUserData->sid->get(),
                    $this->getUserService()->showName($creatorUserData),
                    ABSOLUTE_PATH,
                    $updatingUserData->sid->get(),
                    $this->getUserService()->showName($updatingUserData),
                ) . '<br><br><a href="' . ABSOLUTE_PATH . '/application/' . $successfulResultsId . '/act=edit&project_id=' . $this->getActivatedProjectId() . '">' . ABSOLUTE_PATH . '/application/' . $successfulResultsId . '/act=edit&project_id=' . $this->getActivatedProjectId() . '</a>' . $LOCALE_CONVERSATION['subscription']['base_text2'];
            }

            if (count($userIds) > 0) {
                MessageHelper::prepareEmails($userIds, [
                    'author_name' => $this->getUserService()->showNameExtended($updatingUserData, false),
                    'author_email' => $updatingUserData->em->get(),
                    'name' => $subject,
                    'content' => $message,
                    'obj_type' => 'project_application',
                    'obj_id' => $successfulResultsId,
                ]);

                MessageHelper::preparePushs($userIds, [
                    'user_id_from' => $updatingUserData->id->getAsInt(),
                    'message_img' => $this->getUserService()->photoLink($updatingUserData),
                    'header' => $applicationName . ' (' . $projectName . ')',
                    'content' => trim(strip_tags(str_replace('<br>', "\n", $subject))),
                    'obj_type' => 'application',
                    'obj_id' => $successfulResultsId,
                ]);
            }

            /** Удалить связи заявки с проживанием из базы */
            RightsHelper::deleteRights('{member}', '{room}', null, '{application}', $successfulResultsId);

            if ($this->getServiceEntityName() === 'myapplication') {
                /** Удалить игрока из соответствующего проекта, если, конечно, он не админ */
                if (!RightsHelper::checkRights(
                    ['{admin}', '{gamemaster}', '{newsmaker}', '{fee}', '{budget}'],
                    '{project}',
                    $projectData->id->getAsInt(),
                )) {
                    RightsHelper::deleteRights(
                        '{member}',
                        '{project}',
                        $projectData->id->getAsInt(),
                        '{user}',
                        CURRENT_USER->id(),
                    );
                }
            }
        }
    }

    /** Просмотр истории заявок */
    private function getHistoryViewNowDataAndIds(): void
    {
        if (is_null($this->historyViewIds['now']) || is_null($this->historyViewNowData)) {
            $historyViewPrevId = null;
            $historyViewNowId = null;
            $historyViewNextId = null;
            $historyViewNowData = null;

            if (DataHelper::getId() > 0) {
                if ($this->getHistoryView()) {
                    $historyViewNowId = $_REQUEST['history_view_now_id'] ?? null;

                    if ($historyViewNowId > 0) {
                        $historyViewNowData = DB->select(
                            tableName: 'project_application_history',
                            criteria: [
                                'project_application_id' => DataHelper::getId(),
                                'id' => $historyViewNowId,
                            ],
                            oneResult: true,
                        );
                    }

                    if (!($historyViewNowData['id'] ?? false) || is_null($historyViewNowId)) {
                        $historyViewNowData = DB->select(
                            tableName: 'project_application_history',
                            criteria: [
                                'project_application_id' => DataHelper::getId(),
                            ],
                            oneResult: true,
                            order: [
                                'updated_at DESC',
                            ],
                            limit: 1,
                        );

                        if ($historyViewNowData['id'] ?? false) {
                            $historyViewNowId = $historyViewNowData['id'];
                        }
                    }

                    if ($historyViewNowData['updated_at'] ?? false) {
                        $historyViewNextData = DB->select(
                            tableName: 'project_application_history',
                            criteria: [
                                'project_application_id' => DataHelper::getId(),
                                ['updated_at', $historyViewNowData['updated_at'], [OperandEnum::MORE]],
                            ],
                            oneResult: true,
                            order: [
                                'updated_at',
                            ],
                            limit: 1,
                        );

                        if ($historyViewNextData['id'] ?? false) {
                            $historyViewNextId = $historyViewNextData['id'];
                        }

                        $historyViewPrevData = DB->select(
                            tableName: 'project_application_history',
                            criteria: [
                                'project_application_id' => DataHelper::getId(),
                                ['updated_at', $historyViewNowData['updated_at'], [OperandEnum::LESS]],
                            ],
                            oneResult: true,
                            order: [
                                'updated_at DESC',
                            ],
                            limit: 1,
                        );

                        if ($historyViewPrevData['id'] ?? false) {
                            $historyViewPrevId = $historyViewPrevData['id'];
                        }
                    }
                } else {
                    $historyViewNowData = DB->select(
                        tableName: 'project_application_history',
                        criteria: [
                            'project_application_id' => DataHelper::getId(),
                        ],
                        oneResult: true,
                        order: [
                            'updated_at DESC',
                        ],
                        limit: 1,
                    );

                    if ($historyViewNowData['id'] ?? false) {
                        $historyViewNowId = $historyViewNowData['id'];
                    }
                }
            }

            $this->historyViewIds = [
                'prev' => $historyViewPrevId,
                'now' => $historyViewNowId,
                'next' => $historyViewNextId,
            ];
            $this->historyViewNowData = $historyViewNowData ? $this->arrayToModel($historyViewNowData) : null;
        }
    }

    private function getQrpgKeyValuesAndImages(): void
    {
        if ($this->getProjectData() && (is_null($this->qrpgKeysValues) || is_null($this->qrpgKeysImages))) {
            $qrpgKeysValues = [];
            $qrpgKeysValuesSort = [];
            $qrpgKeysImgsSort = [];
            $qrpgKeysImages = [];

            $qrpgKeysData = DB->query(
                'SELECT qk.*, qk2.property_name AS double_name FROM qrpg_key AS qk LEFT JOIN qrpg_key AS qk2 ON qk2.property_name=qk.property_name AND qk2.id!=qk.id WHERE qk.project_id=:project_id',
                [
                    ['project_id', $this->getActivatedProjectId()],
                ],
            );

            foreach ($qrpgKeysData as $qrpgKeyData) {
                $qrpgKeyName = DataHelper::escapeOutput(
                    $qrpgKeyData['property_name'] !== '' ? $qrpgKeyData['property_name'] . ($qrpgKeyData['double_name'] !== '' ? ' (' . $qrpgKeyData['name'] . ')' : '') : $qrpgKeyData['name'],
                );
                $qrpgKeysValues[] = [$qrpgKeyData['id'], $qrpgKeyName];
                $qrpgKeysValuesSort[] = $qrpgKeyName;
                $qrpgKeysImgsSort[] = $qrpgKeyName;

                if ($qrpgKeyData['img'] > 0) {
                    $qrpgKeysImages[] = [
                        $qrpgKeyData['id'],
                        ABSOLUTE_PATH . '/design/qrpg/' . $qrpgKeyData['img'] . '.svg',
                    ];
                } else {
                    $qrpgKeysImages[] = '';
                }
            }
            array_multisort($qrpgKeysValuesSort, SORT_ASC, $qrpgKeysValues);
            array_multisort($qrpgKeysImgsSort, SORT_ASC, $qrpgKeysImages);

            $this->qrpgKeysValues = $qrpgKeysValues;
            $this->qrpgKeysImages = $qrpgKeysImages;
        }
    }

    private function getServiceEntityName(): string
    {
        return $this->view->entity->name;
    }

    private function updateGroupsInRelation(string|int $successfulResultsId): void
    {
        /** Апдейтим записи в relation, если есть. Если нет, создаем и выставляем самый низкий код в каждой группе. */
        $application = $this->get($successfulResultsId, refresh: true);
        $applicationGroups = $application->project_group_ids->get();

        if ($applicationGroups) {
            /** Удаляем связи со всеми группами, которых более нет у нас */
            RightsHelper::deleteRights(
                '{member}',
                '{group}',
                null,
                '{application}',
                $successfulResultsId,
                ' AND obj_id_to NOT IN (' . implode(',', $applicationGroups) . ')',
            );

            /**  Добавляем связи с нужным code */
            foreach ($applicationGroups as $applicationGroup) {
                RightsHelper::addRights('{member}', '{group}', $applicationGroup, '{application}', $successfulResultsId);
            }
        } else {
            RightsHelper::deleteRights('{member}', '{group}', null, '{application}', $successfulResultsId);
        }
    }

    private function preChangeHelper(): void
    {
        $this->getCharacterData((int) ($_REQUEST['project_character_id'][0] ?? null));

        $applicationData = $this->getApplicationData();

        /** Если другой пользователь менял ранее заявку, отправляем его данные в историю изменений */
        if ($applicationData['last_update_user_id'] !== CURRENT_USER->id()) {
            DB->query(
                query: 'INSERT INTO project_application_history (
					project_application_id,
					creator_id,
					allinfo,
					project_character_id,
					money,
					money_paid,
					project_group_ids,
					status,
					deleted_by_player,
					player_got_info,
					updated_at,
					created_at
				) VALUES (
					:project_application_id,
					:creator_id,
					:allinfo,
					:project_character_id,
					:money,
					:money_paid,
                    :project_group_ids,
					:status,
					:deleted_by_player,
					:player_got_info,
					:updated_at,
					:created_at
				) ON DUPLICATE KEY UPDATE
					allinfo=VALUES(allinfo),
					project_character_id=VALUES(project_character_id),
					money=VALUES(money),
					money_paid=VALUES(money_paid),
					project_group_ids=VALUES(project_group_ids),
					status=VALUES(status),
					deleted_by_player=VALUES(deleted_by_player),
					player_got_info=VALUES(player_got_info),
					updated_at=VALUES(updated_at)
				',
                data: [
                    ['project_application_id', $applicationData['id']],
                    ['creator_id', $applicationData['last_update_user_id']],
                    ['allinfo', $applicationData['allinfo']],
                    ['project_character_id', (int) $applicationData['project_character_id'] > 0 ? $applicationData['project_character_id'] : 0],
                    ['money', $applicationData['money']],
                    ['money_paid', $applicationData['money_paid']],
                    ['project_group_ids', $applicationData['project_group_ids']],
                    ['status', $applicationData['status']],
                    ['deleted_by_player', $applicationData['deleted_by_player']],
                    ['player_got_info', $applicationData['player_got_info']],
                    ['updated_at', $applicationData['updated_at']],
                    ['created_at', $applicationData['updated_at']],
                ],
            );
        }
    }
}
