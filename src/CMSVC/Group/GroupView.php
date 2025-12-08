<?php

declare(strict_types=1);

namespace App\CMSVC\Group;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{CatalogEntity, CatalogItemEntity, EntitySortingItem, Filters, Rights};
use Fraym\Enum\{ActEnum, ActionEnum, SubstituteDataTypeEnum};
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<GroupService> */
#[CatalogEntity(
    'group',
    'project_group',
    [
        new EntitySortingItem(
            tableFieldName: 'code',
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'name',
            showFieldShownNameInCatalogItemString: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'responsible_gamemaster_id',
            showFieldShownNameInCatalogItemString: false,
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortResponsibleGamemasterId',
        ),
        new EntitySortingItem(
            tableFieldName: 'rights',
            showFieldShownNameInCatalogItemString: false,
            doNotUseIfNotSortedByThisField: true,
            removeDotAfterText: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortRights',
        ),
    ],
    null,
    100,
)]
#[CatalogItemEntity(
    'groupChild',
    'project_group',
    GroupModel::class,
    'parent',
    'content',
    [
        new EntitySortingItem(
            tableFieldName: 'code',
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
    ],
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: true,
    deleteRight: true,
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(GroupController::class)]
class GroupView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $groupService = $this->service;

        $LOCALE = $this->LOCALE;
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $title = $LOCALE_GLOBAL['project_control_items'][KIND][0];
        $RESPONSE_DATA = $response->getHtml();

        $objData = [];

        if (DataHelper::getId() > 0) {
            $objData = DB->findObjectById(DataHelper::getId(), 'project_group');

            if ($objData['name'] !== '') {
                $title = DataHelper::escapeOutput($objData['name']);
            }
        }

        $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, $title);
        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($title);

        $RESPONSE_DATA = preg_replace('#<a [^>]+><span class="sbi sbi-plus"><\/span>' . $LOCALE_FRAYM['dynamiccreate']['add'] . ' <\/a>#', '', $RESPONSE_DATA);

        if ((!DataHelper::getId() || ACTION === ActionEnum::delete) && DataHelper::getActDefault($this->entity) !== ActEnum::add) {
            $mine_view = Filters::getFiltersCookieParameterByName('search_responsible_gamemaster_id', 'group', KIND) === '-' . CURRENT_USER->id() . '-';

            $RESPONSE_DATA = preg_replace('#<div class="indexer_toggle(.*?)<\/div>#', '<div class="indexer_toggle$1</div><div class="filter">' . (!$mine_view ? '<a href="/group/action=setFilters&object=group&search_responsible_gamemaster_id[' . CURRENT_USER->id() . ']=on" class="fixed_select">' . $LOCALE['switch_to_mine'] . '</a>' : '<a href="/group/object=group&action=clearFilters&sorting=0" class="fixed_select">' . $LOCALE['switch_to_all'] . '</a>') . '</div>', $RESPONSE_DATA);

            /** Подсчет персонажей и заявок + ссылка на фильтр нужный в разделе */
            $charactersAndApplications = [];
            $projectGroupApplicationsNeededCount = [];
            $projectGroupApplicationsNeededCountQuery = DB->query(
                "SELECT pg.id AS group_id, SUM(pc.applications_needed_count) AS applications_needed_count_sum FROM project_group AS pg LEFT JOIN relation AS r ON r.obj_id_to=pg.id AND r.type='{member}' AND r.obj_type_to='{group}' AND r.obj_type_from='{character}' LEFT JOIN project_character AS pc ON pc.id=r.obj_id_from AND pc.project_id=pg.project_id WHERE pg.project_id=:project_id GROUP BY pg.id",
                [
                    ['project_id', $groupService->getActivatedProjectId()],
                ],
            );

            foreach ($projectGroupApplicationsNeededCountQuery as $projectGroupApplicationsNeededCountData) {
                $projectGroupApplicationsNeededCount[$projectGroupApplicationsNeededCountData['group_id']] = $projectGroupApplicationsNeededCountData['applications_needed_count_sum'];
            }

            $fullGroupDataQuery = DB->query(
                "SELECT pg.id, COUNT(DISTINCT pa.id) AS application_count FROM project_group AS pg LEFT JOIN relation AS r ON r.obj_id_to=pg.id AND r.type='{member}' AND r.obj_type_to='{group}' AND r.obj_type_from='{application}' LEFT JOIN project_application AS pa ON pa.id=r.obj_id_from AND pa.project_id=pg.project_id AND pa.status!=4 AND pa.deleted_by_gamemaster='0' AND pa.deleted_by_player='0' WHERE pg.project_id=:project_id GROUP BY pg.id",
                [
                    ['project_id', $groupService->getActivatedProjectId()],
                ],
            );

            foreach ($fullGroupDataQuery as $fullGroupData) {
                $charactersAndApplications[$fullGroupData['id']] = '<span class="group_list_additional_data"><a href="/character/action=setFilters&object=character&search_project_group_ids[' . $fullGroupData['id'] . ']=on" title="' . $LOCALE['characters'] . '">' . (int) $projectGroupApplicationsNeededCount[$fullGroupData['id']] . '<span class="sbi sbi-user"></span></a><a href="/application/action=setFilters&object=application&search_project_group_ids[' . $fullGroupData['id'] . ']=on" title="' . $LOCALE['applications'] . '">' . $fullGroupData['application_count'] . '<span class="sbi sbi-file-filled"></span></a><a href="/roles/' . $groupService->getActivatedProjectId() . '/group/' . $fullGroupData['id'] . '/" target="_blank" title="' . $LOCALE['roleslist'] . '" class="tooltipBottomRight"><span class="sbi sbi-list"></span></a></span>';
            }

            foreach ($charactersAndApplications as $groupId => $charactersAndApplications_data) {
                $RESPONSE_DATA = preg_replace('#<li><span class="sbi sbi-folder"><\/span><a href="\/group\/group\/' . $groupId . '\/(.*)<\/a>#', '<li><span class="sbi sbi-folder"></span>' . $charactersAndApplications_data . '<a href="/group/group/' . $groupId . '/$1</a>', $RESPONSE_DATA);
            }
        }

        if (DataHelper::getId()) {
            $RESPONSE_DATA = str_replace('<div class="field wysiwyg" id="field_link_to_characters[0]">', '<div class="field wysiwyg" id="field_link_to_characters[0]"><a class="add_something_svg" href = "' . ABSOLUTE_PATH . '/character/act=add&project_group_ids=-' . DataHelper::getId() . '-"></a>', $RESPONSE_DATA);
        }

        return $response->setHtml($RESPONSE_DATA)->setPagetitle($PAGETITLE);
    }
}
