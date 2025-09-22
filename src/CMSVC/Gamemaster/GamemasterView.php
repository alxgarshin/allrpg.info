<?php

declare(strict_types=1);

namespace App\CMSVC\Gamemaster;

use App\CMSVC\User\UserService;
use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\Trait\PageCounter;
use Fraym\Helper\{CMSVCHelper, DataHelper};
use Fraym\Interface\Response;

#[Controller(GamemasterController::class)]
class GamemasterView extends BaseView
{
    use PageCounter;

    public function Response(): ?Response
    {
        /** @var GamemasterService $gamemasterService */
        $gamemasterService = $this->getCMSVC()->getService();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->getLOCALE();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);

        $gamemasterGroupsList = $gamemasterService->getGroupsList();
        $searchForGamemasterGroup = $gamemasterService->getSearchGroup();

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<h1 class="page_header">' . $LOCALE['title'] . '</h1>
<div class="page_blocks margin_top">
<div class="page_block">
<table class="menutable">
<thead>
<tr class="menu">
<th>
' . $LOCALE['name'] . '
</th>
<th>
' . $LOCALE['users'] . '
</th>
<th>
' . $LOCALE['events'] . '
</th>
</tr>
</thead>
<tbody>';

        $stringnum = 0;

        foreach ($gamemasterGroupsList[1] as $gamemasterGroupsListData) {
            if (($gamemasterGroupsListData['name'] ?? '') !== '') {
                $RESPONSE_DATA .= '<tr class="string' . ($stringnum % 2 === 0 ? '1' : '2') . '"><td>' . $gamemasterGroupsListData['name'] . '</td><td>';

                if (is_array($gamemasterGroupsListData['user'] ?? false)) {
                    $userDatas = $userService->arraysToModels($gamemasterGroupsListData['user']);

                    foreach ($userDatas as $userData) {
                        $RESPONSE_DATA .= $userService->showName($userData, true) . '<br>';
                    }
                }
                $RESPONSE_DATA .= '</td><td>';

                if (is_array($gamemasterGroupsListData['calendar_event'] ?? false)) {
                    foreach ($gamemasterGroupsListData['calendar_event'] as $calendarEventData) {
                        $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendarEventData['id'] . '/">' .
                            DataHelper::escapeOutput($calendarEventData['name']) . '</a><br>';
                    }
                }
                $RESPONSE_DATA .= '</td></tr>';
                ++$stringnum;
            }
        }
        $RESPONSE_DATA .= '</tbody>
</table>
<br>';

        $RESPONSE_DATA .= $this->drawPageCounter(
            '',
            PAGE,
            $gamemasterGroupsList[0],
            CURRENT_USER->getBazeCount(),
            $searchForGamemasterGroup !== '' ? '&id=' . str_replace('&', '-and-', $searchForGamemasterGroup) : '',
        );

        $RESPONSE_DATA .= '</div>
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
