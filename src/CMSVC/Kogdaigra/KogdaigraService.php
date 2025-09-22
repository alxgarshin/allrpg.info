<?php

declare(strict_types=1);

namespace App\CMSVC\Kogdaigra;

use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Enum\OperandEnum;

#[Controller(KogdaigraController::class)]
class KogdaigraService extends BaseService
{
    public function removeBOM(string $str): string
    {
        if (substr($str, 0, 3) === pack('CCC', 0xEF, 0xBB, 0xBF)) {
            $str = substr($str, 3);
        }

        return $str;
    }

    public function loadDataFromKogdaigra(int $id): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://kogda-igra.ru/api/game/' . (int) $id);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=1');

        $requestResult = curl_exec($ch);

        if ($requestResult === false) {
            echo 'Curl failed: ' . curl_error($ch);
        }
        curl_close($ch);

        $requestResult = $this->removeBOM($requestResult);

        return json_decode($requestResult, true);
    }

    public function getRegionByName(string $region): ?int
    {
        if ($region === 'Пермский край') {
            return 761;
        }

        if (!$region) {
            return 2563;
        }
        $allrpgData = DB->select(
            tableName: 'geography',
            criteria: [
                ['name', '%' . $region . '%', [OperandEnum::LIKE]],
            ],
            oneResult: true,
        );

        return ($allrpgData['id'] ?? false) ? (int) $allrpgData['id'] : null;
    }

    public function getAreaForSync(string $name, int $kogdaIgraId): ?int
    {
        if ($name === 'Выбран' || $name === 'Неизвестен') {
            return 110;
        }

        $result = DB->query(
            'SELECT id FROM area WHERE name=:name OR kogdaigra_id=:kogdaigra_id',
            [
                ['name', $name],
                ['kogdaigra_id', $kogdaIgraId],
            ],
            true,
        );

        return $result['id'] ?? null;
    }

    public function findGameInAllrpg(int $kogdaIgraId, int $allrpgId, string $name, string $begin): false|array
    {
        $allrpgData = DB->select(
            tableName: 'calendar_event',
            criteria: [
                'kogdaigra_id' => $kogdaIgraId,
            ],
            oneResult: true,
        );

        if ($allrpgData['id'] ?? false) {
            return $allrpgData;
        }

        if ($allrpgId > 0) {
            return DB->select(
                tableName: 'calendar_event',
                criteria: [
                    'id' => $allrpgId,
                ],
                oneResult: true,
            );
        } else {
            return DB->select(
                tableName: 'calendar_event',
                criteria: [
                    ['name', mb_strtolower($name), OperandEnum::LOWER],
                    'date_from' => $begin,
                ],
                oneResult: true,
            );
        }
    }
}
