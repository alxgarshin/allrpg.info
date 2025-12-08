<?php

declare(strict_types=1);

namespace App\CMSVC\Fee;

use App\CMSVC\Fee\FeeOption\FeeOptionModel;
use App\CMSVC\Trait\{GetUpdatedAtCustomAsHTMLRendererTrait, ProjectDataTrait};
use App\Helper\DateHelper;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\{PostChange, PostCreate, PreChange, PreDelete};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{DataHelper, ResponseHelper};
use Generator;

/** @extends BaseService<FeeModel|FeeOptionModel> */
#[Controller(FeeController::class)]
#[PostCreate]
#[PostChange]
#[PreDelete]
#[PreChange]
class FeeService extends BaseService
{
    use GetUpdatedAtCustomAsHTMLRendererTrait;
    use ProjectDataTrait;

    private ?array $oldFeeData = null;

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            if ($this->entity->name === CMSVC) {
                DB->insert(
                    tableName: 'project_fee',
                    data: [
                        'creator_id' => CURRENT_USER->id(),
                        'project_id' => $this->getActivatedProjectId(),
                        'parent' => $successfulResultsId,
                        'date_from' => date('Y-m-d'),
                        'cost' => 0,
                        'last_update_user_id' => CURRENT_USER->id(),
                        'created_at' => DateHelper::getNow(),
                        'updated_at' => DateHelper::getNow(),
                    ],
                );

                $feeOptionId = DB->lastInsertId();

                if ($_REQUEST['add_to_unpaid_applications'][0] === 'on' && $feeOptionId > 0) {
                    $this->updateFee($feeOptionId, true);
                }
            } else {
                $this->updateFee($successfulResultsId);
            }
        }
    }

    public function preChange(): void
    {
        if ($this->entity->name !== CMSVC) {
            $this->oldFeeData = [];

            foreach ($_REQUEST['id'] as $id) {
                $this->oldFeeData[$id] = DB->findObjectById(DataHelper::getId(), 'project_fee', true);
            }
        }
    }

    public function postChange(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            if ($this->entity->name === CMSVC) {
                if ($_REQUEST['add_to_unpaid_applications'][0] === 'on') {
                    $feeData = DB->query(
                        'SELECT * FROM project_fee WHERE parent=:parent AND date_from <= CURDATE() ORDER BY date_from DESC LIMIT 1',
                        [
                            ['parent', $successfulResultsId],
                        ],
                        true,
                    );

                    if ($feeData) {
                        $this->oldFeeData[$feeData['id']] = DB->findObjectById($feeData['id'], 'project_fee', true);
                        $this->updateFee($feeData['id'], true);
                    }
                }
            } else {
                $this->updateFee($successfulResultsId);
            }
        }
    }

    public function preDelete(): void
    {
        /** Если это удаление, то нам надо в неоплаченных заявках убрать данную опцию и снизить взнос. Если вдруг money_provided превысит, то проставить оплату */
        $feeIds = [];
        $feeCosts = [];
        $feeData = DB->findObjectById(DataHelper::getId(), 'project_fee', true);

        if ($feeData['parent'] === 0) {
            $feesData = DB->select(
                tableName: 'project_fee',
                criteria: [
                    'parent' => $feeData['id'],
                ],
            );

            foreach ($feesData as $feeData) {
                $feeIds[] = $feeData['id'];
                $feeCosts[] = $feeData['cost'];
            }
            $feeOptionDateData = false;
        } else {
            $feeIds[] = $feeData['id'];
            $feeCosts[] = $feeData['cost'];

            /** Если удаляем дату опции, то надо попробовать подставить в заявки другую дату этой же опции, если есть */
            $feeOptionDateData = DB->query(
                query: 'SELECT * FROM project_fee WHERE parent=:parent AND date_from <= CURDATE() AND id!=:id ORDER BY date_from DESC LIMIT 1',
                data: [
                    ['parent', $feeData['parent']],
                    ['id', $feeData['id']],
                ],
                oneResult: true,
            );
        }

        foreach ($feeIds as $key => $feeId) {
            if ($feeId > 0) {
                $applicationsData = DB->select(
                    tableName: 'project_application',
                    criteria: [
                        'project_id' => $this->getActivatedProjectId(),
                        'money_paid' => '0',
                        ['project_fee_ids', '%-' . $feeId . '-%', OperandEnum::LIKE],
                    ],
                );

                foreach ($applicationsData as $applicationData) {
                    $projectFeeIds = DataHelper::multiselectToArray($applicationData['project_fee_ids']);
                    unset($projectFeeIds[array_search($feeId, $projectFeeIds)]);

                    $money = (int) ($applicationData['money'] - $feeCosts[$key] + $feeOptionDateData['cost']);

                    if ($feeOptionDateData['id'] > 0) {
                        $projectFeeIds[] = $feeOptionDateData['id'];
                    }

                    $moneyPaid = $applicationData['money_provided'] >= $money;

                    DB->update(
                        tableName: 'project_application',
                        data: [
                            'project_fee_ids' => implode('-', $projectFeeIds),
                            'money' => $money,
                            'money_paid' => $moneyPaid ? '1' : '0',
                        ],
                        criteria: [
                            'id' => $applicationData['id'],
                        ],
                    );
                }
            }
        }
    }

    public function getProjectRoomIdsValues(): Generator
    {
        return DB->getArrayOfItems('project_room WHERE project_id=' . $this->getActivatedProjectId(), 'id', 'name');
    }

    public function getParentDefault(): ?string
    {
        return $_REQUEST['parent'] ?? null;
    }

    public function getParentValuesForChild(): Generator
    {
        return DB->getArrayOfItems('project_fee WHERE project_id=' . $this->getActivatedProjectId() . ' AND parent IS NULL ORDER BY name', 'id', 'name');
    }

    private function updateFee($updateId, $addInAnyCase = false): void
    {
        $LOCALE = $this->LOCALE;

        $feeData = DB->findObjectById($updateId, 'project_fee', true);

        if ($feeData['parent'] > 0) {
            /** Находим правильную по дате опцию вне зависимости от текущего апдейта */
            $feeOptionDateData = DB->query(
                query: 'SELECT * FROM project_fee WHERE parent=:parent AND date_from <= CURDATE() ORDER BY date_from DESC LIMIT 1',
                data: [
                    ['parent', $feeData['parent']],
                ],
                oneResult: true,
            );

            if (isset($feeOptionDateData['id'])) {
                $moneyChanged = false;

                /** Выбираем все варианты дат, которые только есть в опции */
                $allFeeOptions = [];
                $projectFeeIdsSelector = [];
                $allFeeOptionsData = DB->select(
                    tableName: 'project_fee',
                    criteria: [
                        'parent' => $feeData['parent'],
                    ],
                );

                foreach ($allFeeOptionsData as $allFeeOptionData) {
                    $allFeeOptions[] = $allFeeOptionData;
                    $projectFeeIdsSelector[] = "project_fee_ids LIKE '%-" . $allFeeOptionData['id'] . "-%'";
                }

                $applicationsData = DB->query(
                    query: "SELECT * FROM project_application WHERE project_id=:project_id AND money_paid='0'" .
                        (
                            $addInAnyCase ?
                            '' :
                            ' AND (' . implode(' OR ', $projectFeeIdsSelector) . ')'
                        ),
                    data: [
                        ['project_id', $this->getActivatedProjectId()],
                    ],
                );

                foreach ($applicationsData as $applicationData) {
                    $projectFeeIds = DataHelper::multiselectToArray($applicationData['project_fee_ids']);
                    $money = (int) $applicationData['money'];

                    /** Вычитаем все варианты денег по датам из данной опции */
                    foreach ($allFeeOptions as $allFeeOption) {
                        if (in_array($allFeeOption['id'], $projectFeeIds)) {
                            if ($allFeeOption['id'] === $updateId) {
                                /** Если это изменяющаяся сейчас опция, то вычитаем старое значение, а не текущее */
                                $money -= $this->oldFeeData[$updateId]['cost'];
                            } else {
                                $money -= $allFeeOption['cost'];
                            }

                            unset($projectFeeIds[array_search($allFeeOption['id'], $projectFeeIds)]);
                        }
                    }

                    /** Добавляем единственно верную дату */
                    $projectFeeIds[] = $feeOptionDateData['id'];
                    $money += $feeOptionDateData['cost'];

                    $moneyPaid = $applicationData['money_provided'] >= $money;
                    DB->update(
                        tableName: 'project_application',
                        data: [
                            'project_fee_ids' => implode('-', $projectFeeIds),
                            'money' => $money,
                            'money_paid' => $moneyPaid ? '1' : '0',
                        ],
                        criteria: [
                            'id' => $applicationData['id'],
                        ],
                    );

                    if ($money !== (int) $applicationData['money']) {
                        $moneyChanged = true;
                    }
                }

                if ($moneyChanged) {
                    ResponseHelper::success($LOCALE['messages']['money_change']);
                }
            }
        }
    }
}
