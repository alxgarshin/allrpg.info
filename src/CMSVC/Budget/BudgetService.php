<?php

declare(strict_types=1);

namespace App\CMSVC\Budget;

use App\CMSVC\Trait\{GamemastersListTrait, ProjectDataTrait};
use App\Helper\{DateHelper, RightsHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\PostCreate;
use Fraym\Enum\OperandEnum;
use Fraym\Helper\DataHelper;

/** @extends BaseService<BudgetModel> */
#[Controller(BudgetController::class)]
#[PostCreate]
class BudgetService extends BaseService
{
    use GamemastersListTrait;
    use ProjectDataTrait;

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            DB->update(
                tableName: 'resource',
                data: [
                    'code' => 1,
                ],
                criteria: [
                    'id' => $successfulResultsId,
                ],
            );

            $code = 1;
            $rehashData = DB->select(
                tableName: 'resource',
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                ],
                order: [
                    'code',
                    'updated_at DESC',
                ],
            );

            foreach ($rehashData as $rehashDataItem) {
                DB->update(
                    tableName: 'resource',
                    data: [
                        'code' => $code,
                    ],
                    criteria: [
                        'id' => $rehashDataItem['id'],
                    ],
                );
                ++$code;
            }
        }
    }

    public function checkRightsChangeRestrict(): string
    {
        return 'project_id=' . $this->getActivatedProjectId() . ' AND is_category="0"';
    }

    public function getResponsibleIdValues(): array
    {
        return $this->getGamemastersList(['{admin}', '{budget}']);
    }

    public function getBoughtByValues(): array
    {
        $LOCALE = $this->LOCALE;

        return array_merge([['', $LOCALE['from_fee']]], $this->getResponsibleIdValues());
    }

    /** Пересчитываем количество раздаточных материалов во всех заявках: если у нас указано меньше, чем надо, повышаем его */
    public function updateDistributedItems(): void
    {
        $distributedItems = DB->query(
            'SELECT * FROM resource WHERE project_id=' . RightsHelper::getActivatedProjectId() . " AND distributed_item='1'",
            [],
        );

        foreach ($distributedItems as $distributedItemData) {
            $applicationsWithDistributedItem = DB->select(
                tableName: 'project_application',
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                    ['distributed_item_ids', '%-' . $distributedItemData['id'] . '-%', OperandEnum::LIKE],
                ],
            );
            $applicationsWithDistributedItemCount = count($applicationsWithDistributedItem);

            if ($applicationsWithDistributedItemCount > $distributedItemData['quantity_needed']) {
                DB->update(
                    tableName: 'resource',
                    data: ['quantity_needed' => $applicationsWithDistributedItemCount],
                    criteria: ['id' => $distributedItemData['id']],
                );
            }
        }
    }

    public function getBudgetData(): array
    {
        $projectData = $this->getProjectData();

        $feeOptions = [];
        $feesTotal = 0;
        $feeOptionsData = DB->query(
            "SELECT * FROM project_fee WHERE project_id=:project_id AND content='{menu}' AND (do_not_use_in_budget IS NULL OR do_not_use_in_budget='0')",
            [
                ['project_id', $this->getActivatedProjectId()],
            ],
        );

        foreach ($feeOptionsData as $feeOptionData) {
            $feeOptionDateData = DB->select(
                tableName: 'project_fee',
                criteria: [
                    'parent' => $feeOptionData['id'],
                ],
                order: [
                    'date_from',
                ],
                limit: 1,
                oneResult: true,
            );

            if ($feeOptionDateData) {
                $feeOptions[] = [
                    $feeOptionDateData['id'],
                    DataHelper::escapeOutput($feeOptionData['name']) . ': ' . DataHelper::escapeOutput($feeOptionDateData['cost']),
                ];
                $feesTotal += $feeOptionDateData['cost'];
            }
        }

        $budgetPaidData = DB->query(
            "SELECT SUM(amount) as paid FROM project_transaction WHERE project_id=:project_id AND verified='1'",
            [
                ['project_id', $this->getActivatedProjectId()],
            ],
            true,
        );
        $budgetPaidDataApplication = DB->query(
            "SELECT SUM(money_provided) as paid_application FROM project_application WHERE project_id=:project_id AND deleted_by_gamemaster!='1' AND deleted_by_player!='1' AND status!=4",
            [
                ['project_id', $this->getActivatedProjectId()],
            ],
            true,
        );
        $budgetPaidDataTransactions = DB->query(
            "SELECT SUM(comission_value) as comission_value_to_pay FROM project_transaction WHERE project_id=:project_id AND verified='1'",
            [
                ['project_id', $this->getActivatedProjectId()],
            ],
            true,
        );
        $budgetNotPaidData = DB->query(
            "SELECT SUM(money)-SUM(money_provided) as not_paid FROM project_application WHERE project_id=:project_id AND deleted_by_gamemaster!='1' AND deleted_by_player!='1' AND status!=4",
            [
                ['project_id', $this->getActivatedProjectId()],
            ],
            true,
        );

        return [
            'total' => 0,
            'spent' => 0,
            'remaining' => 0,
            'overdraft' => 0,
            'recommended' => 0,
            'player_count' => $projectData->player_count->get(),
            'set' => $feesTotal,
            'currency' => $projectData->currency->get(),
            'paid' => $budgetPaidData['paid'],
            'paid_application' => $budgetPaidDataApplication['paid_application'],
            'comission' => $budgetPaidDataTransactions['comission_value_to_pay'],
            'not_paid' => $budgetNotPaidData['not_paid'],
        ];
    }

    /** Изменение последовательности позиций в бюджете */
    public function changeBudgetCode(int $objId, int $afterObjId): array
    {
        $returnArr = [];

        $budgetData = DB->findObjectById($objId, 'resource');

        if ($budgetData['project_id'] === $this->getActivatedProjectId()) {
            $newCode = 1;

            if ($afterObjId > 0) {
                $afterObjData = DB->findObjectById($afterObjId, 'resource');
                $newCode = (int) $afterObjData['code'] + 1;
            }

            DB->update(
                tableName: 'resource',
                data: [
                    'code' => $newCode,
                    'updated_at' => DateHelper::getNow(),
                ],
                criteria: [
                    'id' => $objId,
                ],
            );

            /* перепрокладываем коды в верную последовательность у всего бюджета */
            $code = 1;
            $rehashData = DB->select(
                tableName: 'resource',
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                ],
                order: [
                    'code',
                    'updated_at DESC',
                ],
            );

            foreach ($rehashData as $rehashDataItem) {
                if ($code === $newCode) {
                    ++$code;
                }

                DB->update(
                    tableName: 'resource',
                    data: [
                        'code' => $code,
                    ],
                    criteria: [
                        'id' => $rehashDataItem['id'],
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
}
