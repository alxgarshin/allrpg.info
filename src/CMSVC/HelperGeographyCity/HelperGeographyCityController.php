<?php

declare(strict_types=1);

namespace App\CMSVC\HelperGeographyCity;

use Fraym\BaseObject\{BaseHelper, BaseModel};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;

class HelperGeographyCityController extends BaseHelper
{
    private const TABLE = 'geography';
    private const PARENT = 'parent';
    private const NAME = 'name';
    private const ORDERBY = 'name';

    public function Response(): ?Response
    {
        $input = $_REQUEST['input'] ?? '';

        $returnArr = [];

        $entityData = DB->select(
            self::TABLE,
            [
                [self::NAME, '%' . mb_strtolower($input) . '%', [OperandEnum::LOWER, OperandEnum::LIKE]],
            ],
            false,
            [self::ORDERBY],
        );

        foreach ($entityData as $entityItem) {
            $returnArr[] = ['id' => $entityItem['id'], 'value' => $this->printOut($entityItem['id'])];
        }

        return $this->asArray($returnArr);
    }

    public function printOut(int|string|null $id, bool $searchForTopParent = false): string
    {
        $content = '';

        if (!is_null($id) && $id > 0) {
            $entityItem = DB->select(
                self::TABLE,
                ['id' => $id],
                true,
            );
            $entityName = DataHelper::escapeOutput($entityItem[self::NAME]);

            if ($entityItem[self::PARENT] > 0) {
                $content = $this->printOut($entityItem[self::PARENT], true);

                if (!$searchForTopParent) {
                    $content = $entityName . ($content !== '' ? ' (' . $content . ')' : '');
                }
            } else {
                $content = $entityName;
            }
        }

        return $content;
    }

    public function printItem(?BaseModel $entityItem): string
    {
        $content = '';

        if (!is_null($entityItem) && property_exists($entityItem, self::NAME)) {
            $content = DataHelper::escapeOutput($entityItem->{self::NAME}->get());
        }

        return $content;
    }
}
