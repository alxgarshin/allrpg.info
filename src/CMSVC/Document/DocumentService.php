<?php

declare(strict_types=1);

namespace App\CMSVC\Document;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Plot\PlotService;
use App\CMSVC\Trait\{ProjectDataTrait};
use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Element\{Attribute, Item};
use Fraym\Helper\DataHelper;
use Fraym\Interface\ElementItem;

/** @extends BaseService<DocumentModel> */
#[Controller(DocumentController::class)]
class DocumentService extends BaseService
{
    use ProjectDataTrait;

    /** @var ElementItem[] */
    public array $fields = [];
    public array $fieldsShowIf = [];
    public array $listOfRolesValues = [];
    public array $fullApplicationsData = [];
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
        $LOCALE = $this->getLOCALE();

        /** Собираем список всех допустимых полей для подсказки */
        $fields = [];
        $fieldsShowIf = [];
        $listOfRolesValues = [];
        $fullApplicationsData = [];

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

        $userModel = $this->userService->getModel();
        $fields[] = $userModel->getElement('fio');
        $fields[] = $userModel->getElement('nick');
        $fields[] = $userModel->getElement('sid');
        $fields[] = $userModel->getElement('photo');
        $fields[] = $userModel->getElement('sickness');

        $applicationModel = $this->applicationService->getModel();
        $fields[] = $applicationModel->getElement('money_paid');

        /** @var Item\Multiselect */
        $projectGroupIds = $applicationModel->getElement('project_group_ids');
        $projectGroupIds->getAttribute()->setValues($projectGroupsData);
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
                if ($applicationField->getShownName()) {
                    $fields[] = $applicationField;

                    if (str_replace('-', '', $applicationField->getAttribute()->getAdditionalData()['show_if'] ?? '') !== '') {
                        $fieldsShowIf[$applicationField->getName()] = $applicationField->getAttribute()->getAdditionalData()['show_if'];
                    }
                }
            }

            $searchQuerySql = $this->applicationService->getEntity()->getFilters()->getPreparedSearchQuerySql();

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
                ],
            );

            foreach ($result as $applicationData) {
                $listOfRolesValues[] = [
                    $applicationData['project_application_id'],
                    DataHelper::escapeOutput($applicationData['sorter']) . ' (' . $this->userService->showNameWithId($this->userService->arrayToModel($applicationData), false) . ')',
                ];
                $fullApplicationsData[$applicationData['project_application_id']] = array_merge(
                    $applicationData,
                    DataHelper::unmakeVirtual($applicationData['allinfo']),
                );
            }
        }

        $fieldsNames = [];

        foreach ($fields as $elem) {
            if ($elem->getShownName()) {
                $fieldsNames[] = $elem->getShownName();
            }
        }

        $fieldsSet = '[' . implode('], [', $fieldsNames) . ']';

        $fieldsSet .= $LOCALE['pagebreak'];

        $this->fields = $fields;
        $this->fieldsShowIf = $fieldsShowIf;
        $this->listOfRolesValues = $listOfRolesValues;
        $this->fullApplicationsData = $fullApplicationsData;
        $this->fieldsSet = $fieldsSet;
        $this->fieldsNames = $fieldsNames;

        return parent::init();
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
        $listOfRoles = new Item\Multiselect();
        $attribute = new Attribute\Multiselect(
            values: $this->listOfRolesValues,
            search: true,
            lineNumber: 0,
        );
        $listOfRoles->setAttribute($attribute)
            ->setName('application_id')
            ->setShownName('');

        return $listOfRoles;
    }
}
