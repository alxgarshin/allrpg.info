<?php

declare(strict_types=1);

namespace App\CMSVC\Roles;

use App\Helper\{DesignHelper, RightsHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<RolesService> */
#[Controller(RolesController::class)]
class RolesView extends BaseView
{
    public function Response(): ?Response
    {
        $LOCALE = $this->getLOCALE();
        $LOCALE_PROJECT = LocaleHelper::getLocale(['project']);
        $LOCALE_GROUP = LocaleHelper::getLocale(['group', 'global']);

        $rolesService = $this->getService();

        $projectData = $rolesService->getProjectData(DataHelper::getId());

        if (!$projectData) {
            return null;
        }

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($projectData->name->get() ?? mb_strtolower($LOCALE['title']));

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '"><div class="page_blocks">';

        if (RightsHelper::checkAllowProjectActions(PROJECT_RIGHTS)) {
            $viewmode = 'gamemaster';
            $rightsData = DB->query("SELECT * FROM relation WHERE obj_type_to='{project}' AND obj_type_from='{user}' AND obj_id_from=" . CURRENT_USER->id() . ' AND obj_id_to=' . RightsHelper::getActivatedProjectId() . " AND type='{member}'", [], true);
            $rightsComment = json_decode($rightsData['comment'] ?? '', true);

            if (($rightsComment['view_roleslist_mode'] ?? '') === 'player') {
                $viewmode = 'player';
            }

            $RESPONSE_DATA .= '<div class="roles_helper">';
            $RESPONSE_DATA .= '<div class="roles_helper_functions">';

            if (in_array('{admin}', PROJECT_RIGHTS)) {
                $RESPONSE_DATA .= '
<b>' . sprintf($LOCALE['show_roleslist'], $LOCALE_PROJECT['fraym_model']['elements']['show_roleslist']['values'][$projectData->show_roleslist->get()][1]) . '</b><br><br>';
            }
            $RESPONSE_DATA .= '<b>' . sprintf($LOCALE['view_roleslist_mode'], $LOCALE['view_roleslist_modes'][$viewmode]) . '</b><br><br>';
            $RESPONSE_DATA .= '<b>' . $LOCALE['show_roleslist_helper'] . '</b>';
            $RESPONSE_DATA .= '<div id="roles_helper_data">
' . $LOCALE['helper'] . '
<pre>&lt;div id="allrpgRolesListDiv"' . (OBJ_TYPE ? ' data-obj-type="' . OBJ_TYPE . '"' : '') . (OBJ_ID ? ' data-obj-id="' . OBJ_ID . '"' : '') . ' project_id="' . DataHelper::getId() . '"&gt;&lt;/div&gt;&lt;script&gt;function ls(u,c){var d=document;var s=d.createElement("script");s.type="text/javascript";if(s.readyState){s.onreadystatechange=function(){if(s.readyState=="loaded"||s.readyState=="complete"){s.onreadystatechange=null;c()}}}else{s.onload=function(){c()}}s.src=u;d.getElementsByTagName("head")[0].appendChild(s)}ls("https://www.allrpg.info/js/roles.min.js", function(){allrpgRolesList("create")})&lt;/script&gt;</pre>
</div>';
            $RESPONSE_DATA .= '</div>';
            $RESPONSE_DATA .= '</div>';

            /* кнопки быстрых переходов по группам */
            $projectGroupApplicationsNeededCount = [];
            $projectGroupApplicationsNeededCountQuery = DB->query("SELECT pg.id AS group_id, SUM(pc.applications_needed_count) AS applications_needed_count_sum FROM project_group AS pg LEFT JOIN relation AS r ON r.obj_id_to=pg.id AND r.type='{member}' AND r.obj_type_to='{group}' AND r.obj_type_from='{character}' LEFT JOIN project_character AS pc ON pc.id=r.obj_id_from AND pc.project_id=pg.project_id WHERE pg.project_id=:project_id GROUP BY pg.id", [
                ['project_id', $rolesService->getActivatedProjectId()],
            ]);

            foreach ($projectGroupApplicationsNeededCountQuery as $projectGroupApplicationsNeededCountData) {
                $projectGroupApplicationsNeededCount[$projectGroupApplicationsNeededCountData['group_id']] = $projectGroupApplicationsNeededCountData['applications_needed_count_sum'];
            }

            $charactersAndApplications = [];
            $fullGroupDataQuery = DB->query("SELECT pg.id, COUNT(DISTINCT pa.id) AS application_count FROM project_group AS pg LEFT JOIN relation AS r ON r.obj_id_to=pg.id AND r.type='{member}' AND r.obj_type_to='{group}' AND r.obj_type_from='{application}' LEFT JOIN project_application AS pa ON pa.id=r.obj_id_from AND pa.project_id=pg.project_id AND pa.status!=4 AND pa.deleted_by_gamemaster='0' AND pa.deleted_by_player='0' WHERE pg.project_id=:project_id GROUP BY pg.id", [
                ['project_id', $rolesService->getActivatedProjectId()],
            ]);

            foreach ($fullGroupDataQuery as $fullGroupData) {
                $charactersAndApplications[$fullGroupData['id']] = '<span class="group_list_additional_data"><a href="/character/action=setFilters&object=character&search_project_group_ids[' . $fullGroupData['id'] . ']=on" title="' . $LOCALE_GROUP['characters'] . '">' . (int) $projectGroupApplicationsNeededCount[$fullGroupData['id']] . '<span class="sbi sbi-user"></span></a><a href="/application/action=setFilters&object=application&search_project_group_ids[' . $fullGroupData['id'] . ']=on" title="' . $LOCALE_GROUP['applications'] . '">' . $fullGroupData['application_count'] . '<span class="sbi sbi-file-filled"></span></a></span>';
            }

            $projectGroupsData = DB->getTreeOfItems(
                false,
                'project_group',
                'parent',
                null,
                ' AND project_id=' . $rolesService->getActivatedProjectId(),
                'code ASC, name ASC',
                0,
                'id',
                'name',
                1000000,
                false,
            );
            $RESPONSE_DATA .= '<div class="filter">';

            foreach ($projectGroupsData as $key => $projectGroupData) {
                if (in_array($projectGroupData[3]['rights'], [0, 1])) {
                    $hasChildren = isset($projectGroupsData[$key + 1]) && $projectGroupsData[$key + 1][3]['parent'] === $projectGroupData[0];
                    $RESPONSE_DATA .= '<div class="group_filter' . ($projectGroupData[3]['parent'] === null ? ' shown' : '') . ($hasChildren ? ' inverted' : '') . '" group_id="' . $projectGroupData[0] . '" parent_id="' . $projectGroupData[3]['parent'] . '"><a href="/roles/' . $rolesService->getActivatedProjectId() . '/group/' . $projectGroupData[0] . '/">' . DataHelper::escapeOutput($projectGroupData[1]) . '</a>' . ($hasChildren ? '<a class="expand_group" group_id="' . $projectGroupData[0] . '"><span class="sbi sbi-plus shown" title="' . $LOCALE_GROUP['unwrap'] . '"></span><span class="sbi sbi-minus" title="' . $LOCALE_GROUP['wrap'] . '"></span></a>' : '') . $charactersAndApplications[$projectGroupData[0]] . '</div>';
                }
            }

            $RESPONSE_DATA .= '</div>';
        }

        $RESPONSE_DATA .= '<div class="allrpgRolesListFilter"><input type="text" id="allrpgRolesListFilterInput" placehold="' . $LOCALE['filter'] . '" autocomplete="off"></div>';

        $RESPONSE_DATA .= '<div id="allrpgRolesListDiv"' . (OBJ_TYPE ? ' data-obj-type="' . OBJ_TYPE . '"' : '') . (OBJ_ID ? ' data-obj-id="' . OBJ_ID . '"' : '') . ' project_id="' . DataHelper::getId() . '"></div>';

        $RESPONSE_DATA .= '</div></div>';

        return new HtmlResponse($RESPONSE_DATA, $PAGETITLE);
    }
}
