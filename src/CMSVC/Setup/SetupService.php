<?php

declare(strict_types=1);

namespace App\CMSVC\Setup;

use App\CMSVC\Group\GroupService;
use App\Helper\{DateHelper, RightsHelper};
use Fraym\BaseObject\{BaseModel, BaseService, Controller};
use Fraym\Element\Attribute\Multiselect;
use Fraym\Entity\{PostChange, PostCreate, PostDelete};
use Fraym\Enum\{ActEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper};

/** @extends BaseService<SetupModel> */
#[Controller(SetupController::class)]
#[PostCreate]
#[PostChange]
#[PostDelete]
class SetupService extends BaseService
{
    /** Изменение последовательности полей в настройке заявки */
    public function changeProjectFieldCode(int $objId, int $newCode): array
    {
        $returnArr = [];

        if ($newCode > 0) {
            DB->update(
                'project_application_field',
                [
                    'field_code' => $newCode,
                    'updated_at' => DateHelper::getNow(),
                ],
                [
                    'id' => $objId,
                ],
            );

            $code = 1;

            $projectApplicationFieldsData = DB->select(
                'project_application_field',
                [
                    'project_id' => RightsHelper::getActivatedProjectId(),
                    'application_type' => $this->getApplicationType(),
                    ['id', $objId, [OperandEnum::NOT_EQUAL]],
                ],
                false,
                [
                    'field_code',
                    'updated_at DESC',
                ],
            );

            foreach ($projectApplicationFieldsData as $projectApplicationFieldData) {
                if ($code === $newCode) {
                    ++$code;
                }

                DB->update(
                    'project_application_field',
                    [
                        'field_code' => $code,
                    ],
                    [
                        'id' => $projectApplicationFieldData['id'],
                    ],
                );

                ++$code;
            }

            $returnArr = [
                'response' => 'success',
            ];
        }

        return $returnArr;
    }

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            DB->update(
                'project_application_field',
                [
                    'project_id' => RightsHelper::getActivatedProjectId(),
                    'application_type' => $this->getApplicationType(),
                    'created_at' => DateHelper::getNow(),
                ],
                [
                    'id' => $successfulResultsId,
                ],
            );
        }

        $this->updateCodes();
    }

    public function postChange(array $successfulResultsIds): void
    {
        $this->updateCodes();
    }

    public function postDelete(array $successfulResultsIds): void
    {
        $this->updateCodes();
    }

    public function getSortFieldType(): array
    {
        $LOCALE = $this->entity->LOCALE;

        return $LOCALE['elements']['field_type']['values'];
    }

    public function getSortFieldRights(): array
    {
        $LOCALE = $this->entity->LOCALE;

        return $LOCALE['elements']['field_rights']['values'];
    }

    public function checkRightsView(): bool
    {
        return RightsHelper::hasProjectActivated();
    }

    public function checkRights(): bool
    {
        if (RightsHelper::hasProjectActivated()) {
            return RightsHelper::checkProjectRights('{admin}');
        }

        return false;
    }

    public function checkRightsRestrict(): string
    {
        return 'project_id=' . RightsHelper::getActivatedProjectId() . ' AND application_type="' . $this->getApplicationType() . '"';
    }

    public function getShowIfValues(): array
    {
        $LOCALE = $this->LOCALE;

        /** @var GroupService $groupService */
        $groupService = CMSVCHelper::getService('group');

        $showIfValues = [];
        $showIfValuesData = DB->query(
            'SELECT id, field_name, field_values FROM project_application_field WHERE project_id=:project_id AND application_type=:application_type AND (field_type="select" OR field_type="multiselect")' . (DataHelper::getId() > 0 ? ' AND id != :id' : ''),
            [
                ['project_id', RightsHelper::getActivatedProjectId()],
                ['application_type', $this->getApplicationType()],
                ['id', DataHelper::getId() > 0 ? DataHelper::getId() : null],
            ],
        );

        foreach ($showIfValuesData as $showIfValueData) {
            $showIfValueData['field_values'] = DataHelper::escapeOutput($showIfValueData['field_values']);
            preg_match_all('#\[(\d+)]\[([^]]+)]#', ($showIfValueData['field_values'] ?? ''), $matches);

            foreach ($matches[1] as $key => $value) {
                $showIfValues[] = [
                    $showIfValueData['id'] . ':' . $value,
                    '<b>' . DataHelper::escapeOutput($showIfValueData['field_name']) . '</b>: ' . $matches[2][$key],
                ];
            }
        }

        $locationsData = DB->getTreeOfItems(
            false,
            'project_group',
            'parent',
            null,
            ' AND project_id=' . RightsHelper::getActivatedProjectId(),
            'code ASC, name ASC',
            0,
            'id',
            'name',
            1000000,
        );

        foreach ($locationsData as $key => $locationData) {
            $locationsData[$key][1] = $groupService->createGroupPath($key, $locationsData);
        }

        foreach ($locationsData as $locationData) {
            $showIfValues[] = [
                'locat:' . $locationData[0],
                '<b><span class="sbi sbi-users" title="' . $LOCALE['location'] . '"></span></b> ' . $locationData[1],
                $locationData[2],
            ];
        }

        return $showIfValues;
    }

    public function getCodeDefault(): int
    {
        $nextFieldCode = 1;

        if ($this->act === ActEnum::add) {
            $topFieldCodeData = DB->select(
                'project_application_field',
                [
                    'project_id' => RightsHelper::getActivatedProjectId(),
                    'application_type' => $this->getApplicationType(),
                ],
                true,
                [
                    'field_code DESC',
                ],
                1,
                null,
                false,
                [
                    'field_code',
                ],
            );

            if (($topFieldCodeData['field_code'] ?? 0) > 0) {
                $nextFieldCode = ++$topFieldCodeData['field_code'];
            }
        }

        return $nextFieldCode;
    }

    public function getApplicationType(): int
    {
        return (int) CookieHelper::getCookie('application_type');
    }

    public function setApplicationType(): void
    {
        $applicationType = $_REQUEST['application_type'] ?? null;

        if (!is_null($applicationType) && $applicationType >= 0 && $applicationType <= 1) {
            CookieHelper::batchSetCookie(['application_type' => (string) $applicationType]);
        }
    }

    public function postModelInit(BaseModel $model): BaseModel
    {
        $removeWysiwyg = true;

        if (DataHelper::getId() > 0) {
            $fieldData = DB->findObjectById(DataHelper::getId(), 'project_application_field');

            if ($fieldData['field_type'] === 'wysiwyg') {
                $removeWysiwyg = false;
            }
        }

        if ($removeWysiwyg) {
            /** @var Multiselect $fieldType */
            $fieldType = $model->getElement('field_type')->getAttribute();
            $fieldTypeValues = $fieldType->values;
            unset($fieldTypeValues[6]);
            $fieldType->values = $fieldTypeValues;
        }

        return $model;
    }

    private function updateCodes(): void
    {
        $code = 1;
        $projectApplicationFieldsData = DB->select(
            'project_application_field',
            [
                'project_id' => RightsHelper::getActivatedProjectId(),
                'application_type' => $this->getApplicationType(),
            ],
            false,
            [
                'field_code',
                'updated_at DESC',
            ],
        );

        foreach ($projectApplicationFieldsData as $projectApplicationFieldData) {
            DB->update(
                'project_application_field',
                [
                    'field_code' => $code,
                ],
                [
                    'id' => $projectApplicationFieldData['id'],
                ],
            );
            ++$code;
        }
    }
}
