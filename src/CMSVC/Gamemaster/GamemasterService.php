<?php

declare(strict_types=1);

namespace App\CMSVC\Gamemaster;

use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Enum\OperandEnum;

#[Controller(GamemasterController::class)]
class GamemasterService extends BaseService
{
    private array $replaceArray = [
        '&quot;',
        '&open;',
        '&close;',
        '"',
        "'",
        'МГ ',
        'МО ',
        '#',
        'ТГ ',
        'ТО ',
        'ТК ',
        'ТМ ',
    ];

    private array $deleteGamemasterGroupsList = [
        'нет',
        '-',
        '--',
        '---',
        'не состою',
    ];

    public function getSearchGroup(): string
    {
        return trim(str_ireplace($this->replaceArray, '', str_replace('-and-', '&', $_REQUEST['id'] ?? '')));
    }

    public function getGroupsList(): array
    {
        $bazecount = CURRENT_USER->getBazeCount();

        $searchForGamemasterGroup = $this->getSearchGroup();

        $userWhereQueryData = [];
        $calendarEventWhereQueryData = [];

        if ($searchForGamemasterGroup !== '') {
            $userWhereQueryData[] = ['ingroup', '%' . $searchForGamemasterGroup . '%', [OperandEnum::LIKE]];
            $calendarEventWhereQueryData[] = ['mg', '%' . $searchForGamemasterGroup . '%', [OperandEnum::LIKE]];
        } else {
            $userWhereQueryData[] = ['ingroup', null, [OperandEnum::NOT_NULL]];
            $calendarEventWhereQueryData[] = ['mg', null, [OperandEnum::NOT_NULL]];
        }

        $gamemasterGroupsList = [];
        $gamemasterGroupsListPrepare = [];
        $usersData = DB->select('user', $userWhereQueryData, false, null, null, null, false, ['id', 'sid', 'ingroup', 'fio', 'nick', 'hidesome']);

        foreach ($usersData as $userData) {
            if ($userData['ingroup'] !== null) {
                $gamemasterGroups = explode(',', $userData['ingroup']);

                foreach ($gamemasterGroups as $gamemasterGroup) {
                    $gamemasterGroup = trim(str_ireplace($this->replaceArray, '', $gamemasterGroup));

                    if ($searchForGamemasterGroup === '' || preg_match('#' . preg_quote($searchForGamemasterGroup) . '#i', $gamemasterGroup)) {
                        $gamemasterGroupsListPrepare[mb_strtolower($gamemasterGroup)]['name'] = $gamemasterGroup;
                        $gamemasterGroupsListPrepare[mb_strtolower($gamemasterGroup)]['user'][] = $userData;
                    }
                }
            }
        }

        $calendarEventsData = DB->select('calendar_event', $calendarEventWhereQueryData, false, null, null, null, false, ['id', 'mg', 'name']);

        foreach ($calendarEventsData as $calendarEventData) {
            if ($calendarEventData['mg'] !== null) {
                $gamemasterGroups = explode(',', $calendarEventData['mg']);

                foreach ($gamemasterGroups as $gamemasterGroup) {
                    $gamemasterGroup = trim(str_ireplace($this->replaceArray, '', $gamemasterGroup));

                    if ($searchForGamemasterGroup === '' || preg_match('#' . preg_quote($searchForGamemasterGroup) . '#i', $gamemasterGroup)) {
                        $gamemasterGroupsListPrepare[mb_strtolower($gamemasterGroup)]['name'] = $gamemasterGroup;
                        $gamemasterGroupsListPrepare[mb_strtolower($gamemasterGroup)]['calendar_event'][] = $calendarEventData;
                    }
                }
            }
        }
        ksort($gamemasterGroupsListPrepare);

        foreach ($gamemasterGroupsListPrepare as $key => $value) {
            if (!in_array($key, $this->deleteGamemasterGroupsList)) {
                $gamemasterGroupsList[] = $value;
            }
        }

        return [count($gamemasterGroupsList), array_splice($gamemasterGroupsList, PAGE * $bazecount, $bazecount)];
    }
}
