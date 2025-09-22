<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgHistory;

use App\CMSVC\QrpgCode\QrpgCodeService;
use App\CMSVC\Trait\{ProjectDataTrait, UserServiceTrait};
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Helper\DataHelper;

/** @extends BaseService<QrpgHistoryModel> */
#[Controller(QrpgHistoryController::class)]
class QrpgHistoryService extends BaseService
{
    use ProjectDataTrait;
    use UserServiceTrait;

    #[DependencyInjection]
    public QrpgCodeService $qrpgCodeService;

    private ?array $codesReaders = null;
    private ?array $codesData = null;
    private ?array $codesSuccesses = null;
    private ?array $removeCopiesSuccesses = null;
    private ?array $currenciesSuccesses = null;

    public function getCreatorIdValues(): array
    {
        if ($this->codesReaders === null) {
            $this->prepareAllData();
        }

        return $this->codesReaders;
    }

    public function getQrpgCodeIdValues(): array
    {
        if ($this->codesData === null) {
            $this->prepareAllData();
        }

        return $this->codesData;
    }

    public function getCodesSuccessesValues(): array
    {
        if ($this->codesSuccesses === null) {
            $this->prepareAllData();
        }

        return $this->codesSuccesses;
    }

    public function getRemoveCopiesSuccessesValues(): array
    {
        if ($this->removeCopiesSuccesses === null) {
            $this->prepareAllData();
        }

        return $this->removeCopiesSuccesses;
    }

    public function getCurrenciesSuccessesValues(): array
    {
        if ($this->currenciesSuccesses === null) {
            $this->prepareAllData();
        }

        return $this->currenciesSuccesses;
    }

    private function prepareAllData(): void
    {
        $codesReaders = [];
        $codesData = [];
        $codesSuccesses = [];
        $removeCopiesSuccesses = [];
        $currenciesSuccesses = [];

        $userService = $this->getUserService();

        $fullHistoryData = DB->query(
            "SELECT u.*, qh.id as history_id, qh.application_id, qh.success, qh.remove_copies_success, qh.currencies_success, pa.sorter, qc.id as code_id, qc.location as code_name, qc.sid as code_sid, qc.removes_copies_of_qrpg_codes FROM qrpg_history AS qh LEFT JOIN user AS u ON u.id=qh.creator_id LEFT JOIN project_application AS pa ON pa.id=qh.application_id LEFT JOIN qrpg_code AS qc ON qc.id=qh.qrpg_code_id WHERE qh.project_id=:project_id",
            [
                ['project_id', $this->getActivatedProjectId()],
            ],
        );

        foreach ($fullHistoryData as $historyData) {
            if ($historyData['id'] && !($codesReaders[$historyData['id']] ?? false)) {
                $codesReaders[$historyData['id']] = [
                    $historyData['id'],
                    '<a href="' . ABSOLUTE_PATH . '/application/' . $historyData['application_id'] . '/" target="_blank">' . DataHelper::escapeOutput($historyData['sorter']) . '</a><span> (</span>' . $userService->showNameWithId($userService->arrayToModel($historyData), true) . '<span>)</span>',
                ];
            }

            if ($historyData['code_id'] && !($codesData[$historyData['code_id']] ?? false)) {
                $codesData[$historyData['code_id']] = [
                    $historyData['code_id'],
                    '<a href="' . ABSOLUTE_PATH . '/qrpg_code/' . $historyData['code_id'] . '/" target="_blank">' . DataHelper::escapeOutput($historyData['code_sid']) .
                        '</a> <a href="' . ABSOLUTE_PATH . '/qrpg_code/' . $historyData['code_id'] . '/" target="_blank"><span class="small">' . DataHelper::escapeOutput($historyData['code_name']) . '</span></a>',
                ];
            }

            $successData = DataHelper::jsonFixedDecode($historyData['success']);

            foreach ($successData as $key => $value) {
                $successString = '';

                if ($value === '1') {
                    $successString = '<span class="sbi sbi-check"></span>';
                } elseif ($value === '-') { //необходим взлом, а он еще не произошел
                    $successString = '<span class="sbi sbi-question"></span>';
                } elseif ($value === '0' || $value === '') {
                    $successString = '<span class="sbi sbi-times"></span>';
                } elseif ($value === '?') { //необходим текст, а он еще не введен
                    $successString = '<span class="sbi sbi-time"></span>';
                }
                $successData[$key] = $successString;
            }
            $codesSuccesses[] = [$historyData['history_id'], implode('', $successData)];

            $removeCopiesSuccessData = DataHelper::jsonFixedDecode($historyData['remove_copies_success']);
            $removesCopiesOfQrpgCodes = DataHelper::jsonFixedDecode($historyData['removes_copies_of_qrpg_codes']);

            foreach ($removeCopiesSuccessData as $key => $value) {
                $successString = '';

                if ($value === '1') {
                    $successString = '<span class="sbi sbi-check"></span>';

                    if (count($removesCopiesOfQrpgCodes[$key]) > 0) {
                        $codesIds = [];

                        foreach ($removesCopiesOfQrpgCodes[$key] as $codeKey => $uselessValue) {
                            $codesIds[] = $codeKey;
                        }

                        $qrpgCodesData = $this->qrpgCodeService->getAll(
                            criteria: ['id' => $codesIds],
                        );

                        $successString = '';

                        foreach ($qrpgCodesData as $qrpgCodeData) {
                            $successString .= '<a href="' . ABSOLUTE_PATH . '/qrpg_code/' . $qrpgCodeData->id->get() . '/" title="' . DataHelper::escapeOutput($qrpgCodeData->location->get()) . '" target="_blank">' . $qrpgCodeData->sid->get() . '</a>';
                        }
                    }
                } elseif ($value === '-') { //уменьшение количества копий уже произошло ранее, не требуется
                    $successString = '<span class="sbi sbi-minus"></span>';
                } elseif ($value === '0' || $value === '') {
                    $successString = '<span class="sbi sbi-times"></span>';
                }
                $removeCopiesSuccessData[$key] = $successString;
            }
            $removeCopiesSuccesses[] = [$historyData['history_id'], implode('', $removeCopiesSuccessData)];

            $currenciesSuccessData = DataHelper::jsonFixedDecode($historyData['currencies_success']);

            foreach ($currenciesSuccessData as $key => $value) {
                $successString = '';

                if ($value === '1') {
                    $successString = '<span class="sbi sbi-check"></span>';
                } elseif ($value === '0' || $value === '') {
                    $successString = '<span class="sbi sbi-times"></span>';
                }
                $currenciesSuccessData[$key] = $successString;
            }
            $currenciesSuccesses[] = [$historyData['history_id'], implode('', $currenciesSuccessData)];
        }

        $this->codesReaders = $codesReaders;
        $this->codesData = $codesData;
        $this->codesSuccesses = $codesSuccesses;
        $this->removeCopiesSuccesses = $removeCopiesSuccesses;
        $this->currenciesSuccesses = $currenciesSuccesses;
    }
}
