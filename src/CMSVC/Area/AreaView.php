<?php

declare(strict_types=1);

namespace App\CMSVC\Area;

use App\CMSVC\User\UserService;
use App\Helper\{DesignHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{ActEnum, EscapeModeEnum, SubstituteDataTypeEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

/** @extends BaseView<AreaService> */
#[TableEntity(
    'area',
    'area',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'tipe',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortTipe',
        ),
        new EntitySortingItem(
            tableFieldName: 'city',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::TABLE,
            substituteDataTableName: 'geography',
            substituteDataTableId: 'id',
            substituteDataTableField: 'name',
        ),
    ],
    useCustomView: true,
    defaultItemActType: ActEnum::view,
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: 'checkRightsChange',
    deleteRight: 'checkRightsDelete',
    viewRestrict: 'checkRightsViewRestrict',
    changeRestrict: 'checkRightsChangeRestrict',
)]
#[Controller(AreaController::class)]
class AreaView extends BaseView
{
    public function Response(): ?Response
    {
        $areaService = $this->getService();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_PEOPLE = LocaleHelper::getLocale(['people', 'global']);

        $objData = $areaService->get(DataHelper::getId());

        if (!$objData) {
            return null;
        }
        $objType = 'area';

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE['title']);
        $RESPONSE_DATA = '';

        if (in_array($objData->creator_id->getAsInt(), [0, 15])) {
            $objData->creator_id->set('1');
        }

        $userData = $userService->get($objData->creator_id->getAsInt());

        $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
<div class="page_block">
    <div class="object_info">
        <div class="object_info_1">
            <div class="object_avatar" style="' . DesignHelper::getCssBackgroundImage($userService->photoUrl($userData)) . '"></div>
        </div>
        <div class="object_info_2">
            <h1><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . DataHelper::getId() . '/">' .
            ($objData->name->get() !== null ? $objData->name->get() : $LOCALE['no_name']) .
            '</a></h1>
            <div class="object_info_2_additional">';

        $field = $objData->tipe;
        $RESPONSE_DATA .= !is_null($field->get()) ? '<span class="gray">' . $field->getShownName() . ':</span>' . $field->asHTML(false) . '<br>' : '';

        if (!is_null($objData->city->get())) {
            $region_data = DB->select('geography', ['id' => $objData->city->get()], true);
            $region_parent_data = DB->select('geography', ['id' => $region_data['parent']], true);

            $field = $objData->city;
            $RESPONSE_DATA .= ($region_data ? '<span class="gray">' . $field->getShownName() .
                ':</span><a href="' . ABSOLUTE_PATH . '/area/search_city=' . $region_data['id'] . '&action=setFilters">' .
                $region_data['name'] . ' (' . $region_parent_data['name'] . ')</a><br>' : '');
        }

        $RESPONSE_DATA .= '
                <a class="show_hidden">' . $LOCALE_GLOBAL['show_next'] . '</a>
	            <div class="hidden">';

        $field = $objData->havegood;
        $RESPONSE_DATA .= count($field->get()) > 0 ? '<span class="gray">' . $field->getShownName() . ':</span><span>' . $field->asHTML(false) . '</span><br>' : '';

        $field = $objData->havebad;
        $RESPONSE_DATA .= count($field->get()) > 0 ? '<span class="gray">' . $field->getShownName() . ':</span><span>' . $field->asHTML(false) . '</span><br>' : '';

        $RESPONSE_DATA .= '
                </div>
            </div>
        </div>
        <div class="object_info_3">
            <div class="object_author"><span>' . $LOCALE_GLOBAL['published_by'] . LocaleHelper::declineVerb($userData) . ':</span>
                <span class="sbi sbi-send"></span>' .
            $userService->showNameExtended($userData, true, true, '', false, false, true) . '</div>
             ' . UniversalHelper::drawImportant(DataHelper::addBraces($objType), (int) $objData->id->getAsInt()) . '
	        <div class="actions_list_switcher">';

        if (CURRENT_USER->id() === $userData->id->getAsInt() && CURRENT_USER->isLogged()) {
            $RESPONSE_DATA .= '
                <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/' . $objType . '/' . $objData->id->getAsInt() . '/act=edit"><span>' .
                TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</span></a></div>';
        } elseif (CURRENT_USER->isAdmin() || $userService->isModerator()) {
            $RESPONSE_DATA .= '
                <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                <div class="actions_list_items">';
            $RESPONSE_DATA .= '
                    <a href="' . ABSOLUTE_PATH . '/' . $objType . '/' . $objData->id->getAsInt() . '/act=edit">' .
                TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</a>
                    <a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $userData->id->getAsInt() . '">' . $LOCALE_PEOPLE['contact_user'] . '</a>';
            $RESPONSE_DATA .= '
                </div>';
        } else {
            $RESPONSE_DATA .= '
                <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $userData->id->getAsInt() . '"><span>' .
                $LOCALE_PEOPLE['contact_user'] . '</span></a></div>';
        }

        $RESPONSE_DATA .= '
                <div class="report_updated_at"><span>' . $LOCALE['publish_date'] . ':</span>' . $objData->updated_at->getAsUsualDateTime() . '</div>
            </div>
        </div>
    </div>
</div>
<div class="page_block margin_top">
	<h2>' . $objData->content->getShownName() . '</h2>
	<div class="publication_content">' . DataHelper::escapeOutput($objData->content->get(), EscapeModeEnum::plainHTML) . '</div>';

        $field = $objData->external_map_link;
        $external_map_link = DataHelper::escapeOutput($field->get(), EscapeModeEnum::plainHTML);

        if ($external_map_link !== null) {
            $RESPONSE_DATA .= '<h2>' . $field->getShownName() . '</h2>';

            if (preg_match('#yandex.ru#', $external_map_link)) {
                $external_map_link = str_replace('/maps/', '/map-widget/v1/', $external_map_link);
                $RESPONSE_DATA .= '<iframe src="' . $external_map_link . '" width="560" height="400" allowfullscreen="true"></iframe>';
            } elseif (preg_match('#google.com#', $external_map_link)) {
            } elseif (preg_match('#google.ru#', $external_map_link)) {
            } else {
                $RESPONSE_DATA .= TextHelper::makeURLsActive($external_map_link);
            }
        }

        $field = $objData->way;
        $RESPONSE_DATA .= !is_null($field->get()) && $field->get() !== '' ? '<h2>' . $field->getShownName() . '</h2><div class="publication_content">' .
            $field->asHTML(false) . '</div>' : '';

        $RESPONSE_DATA .= '
</div>
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
