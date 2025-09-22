<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgKey;

use App\CMSVC\Trait\{ProjectDataTrait};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\{PreChange, PreCreate};
use Fraym\Helper\{CookieHelper, DataHelper, ResponseHelper};
use Generator;

/** @extends BaseService<QrpgKeyModel> */
#[Controller(QrpgKeyController::class)]
#[PreCreate]
#[PreChange]
class QrpgKeyService extends BaseService
{
    use ProjectDataTrait;

    public const maximumKeyImageId = 32;

    public function getViewType(): int
    {
        $viewtype = 1;

        if (CookieHelper::getCookie('viewtype')) {
            $viewtype = (int) CookieHelper::getCookie('viewtype');
        }

        if ($_REQUEST['viewtype'] ?? false) {
            $viewtype = (int) $_REQUEST['viewtype'];
        }
        CookieHelper::batchSetCookie([
            'viewtype' => (string) $viewtype,
        ]);

        return $viewtype;
    }

    public function preChangeCheck(): void
    {
        $LOCALE = $this->getLOCALE()['messages'];

        foreach ($_REQUEST['name'] as $key => $value) {
            if ($key > 0 || $value !== '') {
                if (preg_match('#[^a-zA-Z0-9]#', $_REQUEST['keydata'][$key] ?? '')) {
                    ResponseHelper::responseOneBlock('error', $LOCALE['error_in_keydata'], [$key]);
                }

                if ((int) ($_REQUEST['img'][$key] ?? 0) > self::maximumKeyImageId || (int) ($_REQUEST['img'][$key] ?? 0) < 0) {
                    ResponseHelper::responseOneBlock('error', $LOCALE['error_in_img'], [$key]);
                }
            }
        }
    }

    public function preCreate(): void
    {
        $this->preChangeCheck();
    }

    public function preChange(): void
    {
        $this->preChangeCheck();
    }

    public function getImgValues(): array
    {
        $qrpgKeysImageData = [];

        for ($i = 1; $i <= self::maximumKeyImageId; ++$i) {
            $qrpgKeysImageData[] = [
                $i,
                '',
            ];
        }

        return $qrpgKeysImageData;
    }

    public function getImgImages(): array
    {
        $qrpgKeysImageImages = [];

        for ($i = 1; $i <= self::maximumKeyImageId; ++$i) {
            $qrpgKeysImageImages[] = [
                $i,
                ABSOLUTE_PATH . '/design/qrpg/' . $i . '.svg',
            ];
        }

        return $qrpgKeysImageImages;
    }

    public function getConsistsOfValues(): Generator
    {
        return DB->getArrayOfItems('qrpg_key WHERE project_id=' . $this->getActivatedProjectId(), 'id', 'name');
    }

    public function getUsedInCodesValues(): array
    {
        $LOCALE = $this->getLOCALE();

        $usedInCodes = [];
        $usedInCodesQuery = DB->query(
            "SELECT qk.id as qk_id, qc.* FROM qrpg_key AS qk LEFT JOIN qrpg_code AS qc ON (qc.qrpg_keys LIKE CONCAT('%\"', qk.id, '\":\"on\"%') OR qc.not_qrpg_keys LIKE CONCAT('%\"', qk.id, '\":\"on\"%') OR qc.removes_qrpg_keys_user LIKE CONCAT('%\"', qk.id, '\":\"on\"%') OR qc.removes_qrpg_keys LIKE CONCAT('%\"', qk.id, '\":\"on\"%') OR qc.gives_qrpg_keys LIKE CONCAT('%\"', qk.id, '\":\"on\"%') OR qc.gives_bad_qrpg_keys LIKE CONCAT('%\"', qk.id, '\":\"on\"%')) WHERE qk.project_id=:project_id",
            [
                ['project_id', $this->getActivatedProjectId()],
            ],
        );

        foreach ($usedInCodesQuery as $usedInCodesData) {
            if ($usedInCodesData['id']) {
                if (!isset($usedInCodes[$usedInCodesData['qk_id']])) {
                    $usedInCodes[$usedInCodesData['qk_id']] = [$usedInCodesData['qk_id'], ''];
                }

                $resultText = '<a href="/qrpg_code/' . $usedInCodesData['id'] . '/">' . DataHelper::escapeOutput($usedInCodesData['location']) . ' (' . DataHelper::escapeOutput($usedInCodesData['sid']) . ') ';

                if (preg_match('#"' . $usedInCodesData['qk_id'] . '":"on"#', $usedInCodesData['qrpg_keys'])) {
                    $resultText .= '<span class="sbi sbi-key" title="' . $LOCALE['usedInCodes']['qrpg_keys'] . '"></span>';
                }

                if (preg_match('#"' . $usedInCodesData['qk_id'] . '":"on"#', $usedInCodesData['gives_qrpg_keys'])) {
                    if (preg_match('#"' . $usedInCodesData['qk_id'] . '":"on"#', $usedInCodesData['removes_qrpg_keys'])) {
                        $resultText .= '<span class="sbi sbi-exchange" title="' . $LOCALE['usedInCodes']['transferred'] . '"></span>';
                    } else {
                        $resultText .= '<span class="sbi sbi-check" title="' . $LOCALE['usedInCodes']['gives_qrpg_keys'] . '"></span>';
                    }
                } elseif (preg_match('#"' . $usedInCodesData['qk_id'] . '":"on"#', $usedInCodesData['not_qrpg_keys'] ?? '')) {
                    $resultText .= '<span class="sbi sbi-stop" title="' . $LOCALE['usedInCodes']['not_qrpg_keys'] . '"></span>';
                } elseif (
                    preg_match(
                        '#"' . $usedInCodesData['qk_id'] . '":"on"#',
                        $usedInCodesData['removes_qrpg_keys'],
                    ) || preg_match('#"' . $usedInCodesData['qk_id'] . '":"on"#', $usedInCodesData['removes_qrpg_keys_user'] ?? '')
                ) {
                    $resultText .= '<span class="sbi sbi-crosshairs" title="' . $LOCALE['usedInCodes']['removes_qrpg_keys'] . '"></span>';
                } elseif (preg_match('#"' . $usedInCodesData['qk_id'] . '":"on"#', $usedInCodesData['gives_bad_qrpg_keys'] ?? '')) {
                    $resultText .= '<span class="sbi sbi-crosshairs" title="' . $LOCALE['usedInCodes']['gives_bad_qrpg_keys'] . '"></span>';
                }

                $resultText .= '</a><br>';
                $usedInCodes[$usedInCodesData['qk_id']][1] .= $resultText;
            }
        }

        return $usedInCodes;
    }

    public function getUsedInApplicationsValues(): array
    {
        $usedInApplications = [];
        $usedInApplicationsQuery = DB->query(
            "SELECT qk.id, COUNT(pa.id) AS application_count FROM qrpg_key AS qk LEFT JOIN project_application AS pa ON pa.qrpg_key LIKE CONCAT('%-', qk.id, '-%') AND pa.project_id=qk.project_id WHERE qk.project_id=:project_id GROUP BY qk.id, pa.id",
            [
                ['project_id', $this->getActivatedProjectId()],
            ],
        );

        foreach ($usedInApplicationsQuery as $usedInApplicationsData) {
            if ($usedInApplicationsData['application_count'] > 0) {
                $usedInApplications[] = [$usedInApplicationsData['id'], $usedInApplicationsData['application_count']];
            }
        }

        return $usedInApplications;
    }
}
