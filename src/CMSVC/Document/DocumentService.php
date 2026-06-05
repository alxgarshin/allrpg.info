<?php

declare(strict_types=1);

namespace App\CMSVC\Document;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Plot\PlotService;
use App\CMSVC\Trait\{ProjectDataTrait};
use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Element\{Attribute, Item};
use Fraym\Helper\{DataHelper, ObjectsHelper};
use Fraym\Interface\ElementItem;

/** @extends BaseService<DocumentModel> */
#[Controller(DocumentController::class)]
class DocumentService extends BaseService
{
    use ProjectDataTrait;

    /** @var ElementItem[] */
    public array $fields = [];
    public array $fieldsShowIf = [];
    public string $fieldsSet = '';
    public array $fieldsNames = [];

    #[DependencyInjection]
    public PlotService $plotService;

    #[DependencyInjection]
    public UserService $userService;

    #[DependencyInjection]
    public ApplicationService $applicationService;

    public function init(): static
    {
        $LOCALE = $this->LOCALE;

        /** Собираем список всех допустимых полей для подсказки */
        $fields = [];
        $fieldsShowIf = [];

        $projectGroupsData = DB->getTreeOfItems(
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

        $userModel = $this->userService->model;
        $fields[] = $userModel->getElement('fio');
        $fields[] = $userModel->getElement('nick');
        $fields[] = $userModel->getElement('sid');
        $fields[] = $userModel->getElement('photo');
        $fields[] = $userModel->getElement('sickness');

        $applicationModel = $this->applicationService->model;
        $fields[] = $applicationModel->getElement('money_paid');

        /** @var Item\Multiselect */
        $projectGroupIds = $applicationModel->getElement('project_group_ids');
        $projectGroupIds->getAttribute()->values = $projectGroupsData;
        $fields[] = $projectGroupIds;

        $fields[] = $applicationModel->getElement('plots_data');
        $fields[] = $applicationModel->getElement('distributed_item_ids');
        $fields[] = $applicationModel->getElement('qrpg_key');

        $projectData = $this->getProjectData();
        $applicationFields = [];

        if ($projectData) {
            $applicationFields = DataHelper::virtualStructure(
                "SELECT * FROM project_application_field WHERE project_id=:project_id AND field_type != 'h1' ORDER BY application_type, field_code",
                [
                    ['project_id', $projectData->id->get()],
                ],
                "field_",
                [
                    'show_if',
                ],
            );

            foreach ($applicationFields as $applicationField) {
                if ($applicationField->shownName) {
                    $fields[] = $applicationField;
                    $showIf = $applicationField->getAttribute()->additionalData['show_if'];
                    $fieldsData = DataHelper::multiselectToArray($showIf);

                    if ($fieldsData) {
                        $fieldsShowIf[$applicationField->name] = $fieldsData;
                    }
                }
            }
        }

        $fieldsNames = [];

        foreach ($fields as $elem) {
            if ($elem->shownName) {
                $fieldsNames[] = $elem->shownName;
            }
        }

        $fieldsSet = '[' . implode('], [', $fieldsNames) . ']';

        $fieldsSet .= $LOCALE['pagebreak'];

        $this->fields = $fields;
        $this->fieldsShowIf = $fieldsShowIf;
        $this->fieldsSet = $fieldsSet;
        $this->fieldsNames = $fieldsNames;

        return parent::init();
    }

    /**
     * Тяжёлая выборка заявок проекта — один и тот же набор нужен и для списка ролей (getListOfRolesElem),
     * и для генерации документов (DocumentView). Намеренно НЕ кэшируется в свойство сервиса и НЕ
     * вызывается из init(): каждый из двух сценариев дёргает её сам, по требованию, и разворачивает
     * только то, что ему нужно (имена / allinfo). Это держит пик памяти на одном наборе, а не на нескольких.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getApplicationsData(): array
    {
        $projectData = $this->getProjectData();

        if (!$projectData) {
            return [];
        }

        $searchQuerySql = $this->applicationService->entity->filters->getPreparedSearchQuerySql('application');

        $result = DB->query(
            "SELECT
		t1.*,
		u.*,
		t1.id AS project_application_id
	FROM
		project_application AS t1 LEFT JOIN
		user AS u ON u.id=t1.creator_id
	WHERE
		t1.project_id=:project_id AND
		t1.status != 4 AND
		t1.deleted_by_player='0' AND
		t1.deleted_by_gamemaster='0'" . ($this->getApplicationsByFilter() ? ($searchQuerySql ? " AND " . $searchQuerySql : "") : (count($this->getAapplicationsByIds()) > 0 ? " AND t1.id IN (" . implode(",", $this->getAapplicationsByIds()) . ")" : "")) . "
	ORDER BY
		t1.team_application,
		t1.sorter",
            [
                ['project_id', $this->getActivatedProjectId()],
                ...$this->applicationService->entity->filters->getPreparedSearchQueryParams('application'),
            ],
        );

        return $result ?: [];
    }

    public function getApplicationsByFilter(): bool
    {
        return ($_REQUEST['application_id'][0] ?? false) === 'filter';
    }

    public function getAapplicationsByIds(): array
    {
        $applicationsByIds = [];

        $applicationRequestedIds = $_REQUEST['application_id'][0] ?? [];

        foreach ($applicationRequestedIds as $applicationRequestedId => $value) {
            $applicationsByIds[] = $applicationRequestedId;
        }

        return $applicationsByIds;
    }

    public function getPossibleFieldsDefault(): string
    {
        $result = $this->fieldsSet;

        return $result;
    }

    public function getContentDefault(): string
    {
        $result = '<table><tr><td>' . $this->fieldsNames[0] . ': [' . $this->fieldsNames[0] . ']</td><td>' . $this->fieldsNames[1] . ': [' . $this->fieldsNames[1] . ']</td></tr><tr><td>[' . $this->fieldsNames[2] . ']</td><td>' . $this->fieldsNames[3] . ':<br>[' . $this->fieldsNames[3] . ']</td></tr></table>';

        return $result;
    }

    public function getListOfRolesElem(): Item\Multiselect
    {
        $listOfRolesValues = [];

        /** Короткое имя класса модели для ключа кэша _MODELINSTANCES (см. освобождение ниже) */
        $userModelClass = ObjectsHelper::getClassShortNameFromCMSVCObject($this->userService->model);

        foreach ($this->getApplicationsData() as $applicationData) {
            $userModelInstance = $this->userService->arrayToModel($applicationData);

            $listOfRolesValues[] = [
                $applicationData['project_application_id'],
                DataHelper::escapeOutput($applicationData['sorter']) . ' (' . $this->userService->showNameWithId($userModelInstance, false) . ')',
            ];

            /**
             * arrayToModel() сохраняет модель в CACHE['_MODELINSTANCES'][$userModelClass][$id]
             * навсегда — обычный unset() не освобождает память, т.к. кэш держит ссылку.
             * На больших проектах это N клонов UserModel = утечка. Принудительно
             * освобождаем слот кэша (метода удаления в CacheService нет, перезаписываем на null).
             */
            if (isset($applicationData['id'])) {
                CACHE->setToCache('_MODELINSTANCES', $applicationData['id'], null, $userModelClass);
            }

            unset($userModelInstance);
        }

        $listOfRoles = new Item\Multiselect();
        $attribute = new Attribute\Multiselect(
            values: $listOfRolesValues,
            search: true,
            lineNumber: 0,
        );
        $listOfRoles->setAttribute($attribute);
        $listOfRoles->name = 'application_id';
        $listOfRoles->shownName = '';

        return $listOfRoles;
    }
}
