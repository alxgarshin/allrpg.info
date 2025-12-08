<?php

declare(strict_types=1);

namespace App\CMSVC\Report;

use App\CMSVC\CalendarEvent\CalendarEventService;
use App\CMSVC\User\UserService;
use App\Helper\{DesignHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{ActEnum, EscapeModeEnum, SubstituteDataTypeEnum, TableFieldOrderEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

#[TableEntity(
    'report',
    'report',
    [
        new EntitySortingItem(
            tableFieldName: 'id',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            showFieldDataInEntityTable: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'calendar_event_id',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::TABLE,
            substituteDataTableName: 'calendar_event',
            substituteDataTableId: 'id',
            substituteDataTableField: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'name',
            doNotUseIfNotSortedByThisField: true,
        ),
        new EntitySortingItem(
            tableFieldName: 'creator_id',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortCreatorId',
        ),
        new EntitySortingItem(
            tableFieldName: 'updated_at',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            doNotUseIfNotSortedByThisField: true,
        ),
    ],
    useCustomView: true,
    defaultItemActType: ActEnum::view,
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: 'checkRights',
    deleteRight: 'checkRights',
    viewRestrict: 'checkRightsViewRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(ReportController::class)]
class ReportView extends BaseView
{
    public function Response(): ?Response
    {
        /** @var ReportService $reportService */
        $reportService = $this->CMSVC->service;

        /** @var CalendarEventService $calendarEventService */
        $calendarEventService = CMSVCHelper::getService('calendar_event');

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->LOCALE;
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_PUBLICATION = LocaleHelper::getLocale(['publication', 'global']);
        $LOCALE_PEOPLE = LocaleHelper::getLocale(['people', 'global']);

        $objData = $reportService->get(DataHelper::getId());

        if (!$objData) {
            return null;
        }
        $objType = 'report';

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE['title']);
        $RESPONSE_DATA = '';

        if ($objData->creator_id->getAsInt() === 0) {
            $objData->creator_id->set('1');
        }

        $userData = $userService->get($objData->creator_id->getAsInt());

        $calendarEventData = $calendarEventService->get($objData->calendar_event_id->get());

        $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
<div class="page_block">
    <div class="object_info">
        <div class="object_info_1">
            <div class="object_avatar small" style="' . DesignHelper::getCssBackgroundImage($userService->photoUrl($userData)) . '"></div>
        </div>
        <div class="object_info_2">
            <h1>' . (DataHelper::escapeOutput($objData->name->get()) ?? $LOCALE_PUBLICATION['no_name']) . '</h1>
            <div class="object_info_2_additional">
                ' . (!is_null($calendarEventData) ? '<span class="gray">' . $LOCALE_PUBLICATION['event'] . ':</span><a href="' . ABSOLUTE_PATH . '/calendar_event/' .
            $calendarEventData->id->getAsInt() . '/">' . DataHelper::escapeOutput($calendarEventData->name->get()) . '</a><br>' : '') . '
                <span class="gray">' . $LOCALE_GLOBAL['published_by'] . LocaleHelper::declineVerb($userData) . ':</span>' . $userService->showName($userData, true) . '
            </div>
        </div>
        <div class="object_info_3 only_like">
            ' . UniversalHelper::drawImportant($objType, $objData->id->getAsInt()) . '
	        <div class="actions_list_switcher">';

        if (CURRENT_USER->id() === $userData->id->getAsInt()) {
            $RESPONSE_DATA .= '
                <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/' . $objType . '/' . $objData->id->getAsInt() . '/act=edit"><span>' .
                TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</span></a></div>';
        } elseif (CURRENT_USER->isAdmin() || $userService->isModerator()) {
            $RESPONSE_DATA .= '
                <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                <div class="actions_list_items">';
            $RESPONSE_DATA .= '
                    <a href="' . ABSOLUTE_PATH . '/' . $objType . '/' . $objData->id->getAsInt() . '/act=edit">' . TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</a>
                    <a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $userData->id->getAsInt() . '">' . $LOCALE_PEOPLE['contact_user'] . '</a>';
            $RESPONSE_DATA .= '
                </div>';
        } else {
            $RESPONSE_DATA .= '
                <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $userData->id->getAsInt() . '"><span>' .
                $LOCALE_PEOPLE['contact_user'] . '</span></a></div>';
        }
        $RESPONSE_DATA .= '
                <div class="report_updated_at"><span>' . $LOCALE_PUBLICATION['publish_date'] . ':</span>' .
            $objData->updated_at->get()->format('d.m.Y ' . $LOCALE_FRAYM['datetime']['at'] . ' H:i') . '</div>
            </div>
        </div>
    </div>
</div>
<div class="page_block">
	<div class="publication_content">' . DataHelper::escapeHTMLData(DataHelper::escapeOutput($objData->content->get(), EscapeModeEnum::plainHTML)) . '</div>
</div>
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
