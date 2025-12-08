<?php

declare(strict_types=1);

namespace App\CMSVC\Character;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Filters, Rights, TableEntity};
use Fraym\Enum\{ActEnum, ActionEnum, SubstituteDataTypeEnum};
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<CharacterService> */
#[TableEntity(
    'character',
    'project_character',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'project_group_ids',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getProjectGroupIdsValues',
        ),
        new EntitySortingItem(
            tableFieldName: 'id',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortId',
        ),
        new EntitySortingItem(
            tableFieldName: 'comments',
            doNotUseIfNotSortedByThisField: true,
        ),
    ],
    null,
    5000,
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: true,
    deleteRight: true,
    viewRestrict: 'checkRightsViewRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(CharacterController::class)]
class CharacterView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $characterService = $this->service;

        $LOCALE = $this->LOCALE;
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $title = $LOCALE_GLOBAL['project_control_items'][KIND][0];
        $RESPONSE_DATA = $response->getHtml();

        $objData = [];

        if (DataHelper::getId() > 0) {
            $objData = DB->findObjectById(DataHelper::getId(), 'project_character');

            if ($objData['name'] !== '') {
                $title = DataHelper::escapeOutput($objData['name']);
            }
        }

        $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, $title);
        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($title);

        if ((!DataHelper::getId() || ACTION === ActionEnum::delete) && ACT !== ActEnum::add) {
            /** Составляем список моих групп */
            $mineView = true;
            $searchString = '';
            $groupsSearched = Filters::getFiltersCookieParameterByName('search_project_group_ids', KIND, 'character');

            $myGroups = [];
            $myGroupsData = DB->select(
                'project_group',
                [
                    'project_id' => $characterService->getActivatedProjectId(),
                    'responsible_gamemaster_id' => CURRENT_USER->id(),
                ],
            );

            foreach ($myGroupsData as $myGroupData) {
                $myGroups[] = $myGroupData['id'];
                $searchString .= '&search_project_group_ids[' . $myGroupData['id'] . ']=on';

                if (!is_null($groupsSearched) && !in_array($myGroupData['id'], $groupsSearched)) {
                    $mineView = false;
                }
            }

            if (is_null($groupsSearched) || count($myGroups) !== count($groupsSearched)) {
                $mineView = false;
            }

            if ($searchString !== '') {
                $RESPONSE_DATA = preg_replace('#<div class="indexer_toggle(.*?)<\/div>#', '<div class="indexer_toggle$1</div><div class="filter">' . (!$mineView ? '<a href="/character/action=setFilters&object=character' . $searchString . '" class="fixed_select">' . $LOCALE['switch_to_mine'] . '</a>' : '<a href="/character/object=character&action=clearFilters&sorting=0" class="fixed_select">' . $LOCALE['switch_to_all'] . '</a>') . (!$characterService->getFreeView() ? '<a href="/character/free=1" class="fixed_select">' . $LOCALE['switch_to_free'] . '</a>' : '<a href="/character/" class="fixed_select">' . $LOCALE['switch_to_all'] . '</a>') . (!$characterService->getDifferentGroupsInApplicationsView() ? '<a href="/character/different_groups=1" class="fixed_select">' . $LOCALE['switch_to_different_groups_in_applications'] . '</a>' : '<a href="/character/" class="fixed_select">' . $LOCALE['switch_to_all'] . '</a>') . '</div>', $RESPONSE_DATA);
            }
        }

        if (DataHelper::getId()) {
            $RESPONSE_DATA = str_replace('<h1 class="data_h1" id="field_plots[0]">', '<h1 class="data_h1" id="field_plots[0]"><a class="add_something_svg" href="' . ABSOLUTE_PATH . '/plot/act=add&character_id=' . DataHelper::getId() . '"></a>', $RESPONSE_DATA);
        }

        return $response->setHtml($RESPONSE_DATA)->setPagetitle($PAGETITLE);
    }
}
