<?php

declare(strict_types=1);

use Fraym\Helper\DataHelper;

require_once __DIR__ . '/../../public/fraym.php';

/** Делаем выборку дат изменения опций взноса */
$feesChangedData = DB->query('SELECT * FROM project_fee WHERE date_from = CURDATE() AND parent > 0', []);

foreach ($feesChangedData as $feeChangedData) {
    /* находим правильную по дате опцию вне зависимости от текущего апдейта */
    $feeChangedDataParent = DB->select('project_fee', ['id' => $feeChangedData['parent']], true);
    $projectId = $feeChangedDataParent['project_id'];

    $feeOptionDateData = DB->query(
        'SELECT * FROM project_fee WHERE parent=:parent AND date_from <= CURDATE() ORDER BY date_from DESC LIMIT 1',
        [
            ['parent', $feeChangedData['parent']],
        ],
        true,
    );

    if (isset($feeOptionDateData['id'])) {
        /** Выбираем все варианты дат, которые только есть в опции */
        $allFeeOptions = [];
        $allFeeOptionsData = DB->select(
            tableName: 'project_fee',
            criteria: [
                'parent' => $feeChangedData['parent'],
            ],
        );

        foreach ($allFeeOptionsData as $allFeeOptionData) {
            $allFeeOptions[] = $allFeeOptionData;
        }

        $applicationsData = DB->select(
            tableName: 'project_application',
            criteria: [
                'project_id' => $projectId,
                'money_paid' => '0',
            ],
        );

        foreach ($applicationsData as $applicationData) {
            $projectFeeIds = DataHelper::multiselectToArray($applicationData['project_fee_ids']);
            $money = (int) $applicationData['money'];

            /** Вычитаем все варианты денег по датам из данной опции */
            foreach ($allFeeOptions as $allFeeOption) {
                if (in_array($allFeeOption['id'], $projectFeeIds)) {
                    $money -= $allFeeOption['cost'];
                    unset($projectFeeIds[array_search($allFeeOption['id'], $projectFeeIds)]);
                }
            }

            /** Добавляем единственно верную дату */
            $projectFeeIds[] = $feeOptionDateData['id'];
            $money += (int) $feeOptionDateData['cost'];

            $moneyPaid = $applicationData['money_provided'] >= $money;
            DB->update(
                tableName: 'project_application',
                data: [
                    'project_fee_ids' => implode('-', $projectFeeIds),
                    'money' => $money,
                    'money_paid' => ($moneyPaid ? '1' : '0'),
                ],
                criteria: [
                    'id' => $applicationData['id'],
                ],
            );
        }
    }
}

/** Выводим результат работы скрипта */
echo 'done.';
