<?php

declare(strict_types=1);

namespace App\CMSVC\HelperGamesList;

use App\Helper\DateHelper;
use Fraym\BaseObject\{BaseHelper, BaseModel};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;

class HelperGamesListController extends BaseHelper
{
    private const TABLE = 'calendar_event';
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
            $returnArr[] = [
                'id' => $entityItem['id'],
                'value' => $entityItem[self::NAME] .
                    ' (' . DateHelper::dateFromToEvent($entityItem['date_from'], $entityItem['date_to']) . ')',
            ];
        }

        return $this->asArray($returnArr);
    }

    public function printOut(int|string|null $id): string
    {
        $content = '';

        if (!is_null($id)) {
            $entityItem = DB->select(
                self::TABLE,
                ['id' => $id],
                true,
            );
            $content = DataHelper::escapeOutput($entityItem[self::NAME]);
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
