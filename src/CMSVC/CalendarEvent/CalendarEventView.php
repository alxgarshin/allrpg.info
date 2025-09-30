<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEvent;

use App\CMSVC\Area\AreaService;
use App\CMSVC\Notion\NotionService;
use App\CMSVC\User\UserService;
use App\Helper\{DesignHelper, FileHelper, MessageHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{ActEnum, EscapeModeEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper, ResponseHelper};
use Fraym\Interface\Response;

#[TableEntity(
    'calendarEvent',
    'calendar_event',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'date_from',
        ),
        new EntitySortingItem(
            tableFieldName: 'date_to',
        ),
        new EntitySortingItem(
            tableFieldName: 'updated_at',
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
#[Controller(CalendarEventController::class)]
class CalendarEventView extends BaseView
{
    public function Response(): ?Response
    {
        /** @var CalendarEventService $calendarEventService */
        $calendarEventService = $this->getService();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        /** @var AreaService $areaService */
        $areaService = CMSVCHelper::getService('area');

        /** @var NotionService $notionService */
        $notionService = CMSVCHelper::getService('notion');

        $LOCALE = $this->getLOCALE();
        $LOCALE_MESSAGES = $this->getMessages();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_PUBLICATION = LocaleHelper::getLocale(['publication', 'global']);
        $LOCALE_PEOPLE = LocaleHelper::getLocale(['people', 'global']);

        $objData = $calendarEventService->get(DataHelper::getId());

        if (!$objData) {
            return null;
        }
        $objType = 'calendar_event';

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE['title']);
        $RESPONSE_DATA = '';

        if (in_array($objData->creator_id->getAsInt(), [0, 15])) {
            $objData->creator_id->set(1);
        }

        $userData = $userService->get($objData->creator_id->getAsInt());

        $inPortfolio = false;
        $reportRecordId = false;

        if (CURRENT_USER->isLogged()) {
            $portfolioRecord = DB->select(
                'played',
                [
                    'creator_id' => CURRENT_USER->id(),
                    'calendar_event_id' => $objData->id->getAsInt(),
                ],
                true,
            );
            $inPortfolio = $portfolioRecord ? ($portfolioRecord['id'] ?? false) : false;

            $reportRecord = DB->select(
                'report',
                [
                    'creator_id' => CURRENT_USER->id(),
                    'calendar_event_id' => $objData->id->getAsInt(),
                ],
                true,
            );
            $reportRecordId = $reportRecord ? ($reportRecord['id'] ?? false) : false;
            /*$galleryRecord = DB->select(
                'calendar_event_gallery',
                [
                    'creator_id' => CURRENT_USER->id(),
                    'calendar_event_id' => $objData->id->getAsInt(),
                ],
                true
            );
            $galleryRecordId = $galleryRecord ? ($galleryRecord['id'] ?? false) : false;*/
        }

        $areaId = $objData->area->get()[0] ?? null;
        $areaData = $areaService->get($areaId);
        $regionData = DB->select('geography', ['id' => $objData->region->get()], true);

        $regionParentData = null;

        if ($regionData) {
            $regionParentData = DB->select('geography', ['id' => $regionData['parent']], true);
        }

        $mgData = '';

        if (!is_null($objData->mg->get())) {
            $mgGroups = explode(',', $objData->mg->get());
            $mgData = implode(
                ', ',
                array_map(
                    static fn (string $value): string => '<a href="' . ABSOLUTE_PATH . '/gamemaster/' . str_replace('&', '-and-', trim($value)) . '/">' .
                        str_replace('&', '-and-', trim($value)) . '</a>',
                    $mgGroups,
                ),
            );
        }

        $allrpgProjectData = DB->select(
            'project',
            [
                'name' => $objData->name->get(),
                'date_from' => $objData->date_from->get(),
                'date_to' => $objData->date_to->get(),
            ],
            true,
        );
        $allrpgProjectId = $allrpgProjectData ? $allrpgProjectData['id'] : false;

        if ($objData->wascancelled->get()) {
            ResponseHelper::error($LOCALE_MESSAGES['wascancelled']);
        }

        if ($objData->moved->get()) {
            ResponseHelper::error($LOCALE_MESSAGES['moved']);
        }

        $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
<div class="page_block">
    <div class="object_info">
        <div class="object_info_1">
            <div class="object_avatar" style="' . DesignHelper::getCssBackgroundImage(
            FileHelper::getImagePath($objData->logo->get(), 13) ?? ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg',
        ) . '"></div>
        </div>
        <div class="object_info_2">
            <div class="object_dates">
                <div>' . $objData->date_from->getAsUsualDate() . '</div>
	            <div>' . $objData->date_to->getAsUsualDate() . '</div>
	        </div>
            <h1><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . DataHelper::getId() . '/">' . (DataHelper::escapeOutput($objData->name->get()) ?? $LOCALE['no_name']) . '</a></h1>
            <div class="object_info_2_additional">
                ' . ($mgData !== '' ? '<span class="gray">' . $objData->mg->getShownName() . ':</span>' . $mgData . '<br>' : '') . '
                ' . ($objData->site->get() !== null ? '<span class="gray">' . $objData->site->getShownName() . ':</span><a href="' .
            DataHelper::fixURL(DataHelper::escapeOutput($objData->site->get())) . '" target="_blank">' . DataHelper::escapeOutput($objData->site->get()) . '</a><br>' : '') . '
                ' . ($objData->orderpage->get() !== '' ? '<span class="gray">' . TextHelper::mb_ucfirst($LOCALE['apply']) . ':</span><a href="' .
            DataHelper::escapeOutput($objData->orderpage->get()) . '" target="_blank">' . DataHelper::escapeOutput($objData->orderpage->get()) . '</a><br>' : '') . '
                ' . ($allrpgProjectId > 0 ? '<span class="gray">' . $LOCALE['allrpg_project'] . ':</span><a href="' . ABSOLUTE_PATH . '/project/' . $allrpgProjectId . '/">'
            . $LOCALE['allrpg_project2'] . '</a><br>' : '') . '
                ' . ($regionData ? '<span class="gray">' . $objData->region->getShownName() .
            ':</span><a href="' . ABSOLUTE_PATH . '/calendar_event/search_region=' . $regionData['id'] . '&action=setFilters">' .
            DataHelper::escapeOutput($regionData['name']) . ($regionParentData ? ' (' . DataHelper::escapeOutput($regionParentData['name']) . ')</a>' : '') : '<br>') . '
                ' . ($objData->playernum->get() > 0 ? '<span class="gray">' . $objData->playernum->getShownName() . ':</span><span>' .
            DataHelper::escapeOutput($objData->playernum->get()) . '</span><br>' : '') . '
                <a class="show_hidden">' . $LOCALE_GLOBAL['show_next'] . '</a>
	            <div class="hidden">
                    ' . ($areaData ? '<span class="gray">' . $objData->area->getShownName() . ':</span><a href="' . ABSOLUTE_PATH . '/area/' . $areaData->id->getAsInt() . '/">' .
            DataHelper::escapeOutput($areaData->name->get()) . '</a><br>' : '');

        $field = $objData->gametype;
        $RESPONSE_DATA .= count($field->get()) > 0 ? '
                    <span class="gray">' . $field->getShownName() . ':</span><span>' . $field->asHTML(false) . '</span><br>' : '';

        $field = $objData->gametype2;
        $RESPONSE_DATA .= count($field->get()) > 0 ? '
                    <span class="gray">' . $field->getShownName() . ':</span><span>' . $field->asHTML(false) . '</span><br>' : '';

        $field = $objData->gametype3;
        $RESPONSE_DATA .= !is_null($field->get()) ? '
                    <span class="gray">' . $field->getShownName() . ':</span><a href="' . ABSOLUTE_PATH . '/calendar_event/search_gametype3[' .
            $field->get() . ']=on&action=setFilters">' . $field->asHTML(false) . '</a><br>' : '';

        $field = $objData->gametype4;
        $RESPONSE_DATA .= count($field->get()) > 0 ? '
                    <span class="gray">' . $field->getShownName() . ':</span><span>' . $field->asHTML(false) . '</span><br>' : '';

        $field = $objData->date_arrival;
        $RESPONSE_DATA .= !is_null($field->get()) ? '
                    <span class="gray">' . $field->getShownName() . ':</span><span>' . $field->get()->format('d.m.Y') . '</span><br>' : '';

        $RESPONSE_DATA .= '
                </div>
            </div>
        </div>
        <div class="object_info_3">
            <div class="object_author"><span>' . $LOCALE_GLOBAL['published_by'] . LocaleHelper::declineVerb($userData) . ':</span>
                <span class="sbi sbi-send"></span>' .
            $userService->showNameExtended($userData, true, true, '', false, false, true) . '</div>';

        $result = DB->select(
            'played',
            [
                'calendar_event_id' => $objData->id->getAsInt(),
                'active' => 1,
            ],
            false,
            null,
            null,
            null,
            true,
        );
        $members_count = $result[0];
        $RESPONSE_DATA .= '
                <div class="object_members"><span>' . $LOCALE['participant'] . ':</span>
                <div>' . $members_count . '</div></div>
            ' . UniversalHelper::drawImportant(DataHelper::addBraces($objType), $objData->id->getAsInt()) . '
	        <div class="actions_list_switcher">';

        if (CURRENT_USER->id() === $objData->creator_id->getAsInt()) {
            $RESPONSE_DATA .= '
                <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/calendar_event/' . DataHelper::getId() . '/act=edit"><span>' .
                TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</span></a></div>';
        } else {
            $RESPONSE_DATA .= '
                <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                <div class="actions_list_items">';

            if (CURRENT_USER->isAdmin() || $userService->isModerator() || CURRENT_USER->checkAllrights('info')) {
                $RESPONSE_DATA .= ' <a class="main" href="' . ABSOLUTE_PATH . '/calendar_event/' . DataHelper::getId() . '/act=edit">' .
                    TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</a>';
            }

            $RESPONSE_DATA .= '
                <a class="main" href="' . ABSOLUTE_PATH . '/portfolio/' . ($inPortfolio ? $inPortfolio . '/' : 'act=add&calendar_event_id=' . $objData->id->getAsInt()) . '">' .
                ($inPortfolio ? $LOCALE['already_in_portfolio'] : $LOCALE['add_to_portfolio']) . '</a>';

            if ($objData->date_to->get()->getTimestamp() < time()) {
                $RESPONSE_DATA .= '
                <a class="main" href="' . ABSOLUTE_PATH . '/report/' . ($reportRecordId ? $reportRecordId . '/' : 'act=add&calendar_event_id=' .
                    $objData->id->getAsInt()) . '">' . ($reportRecordId ? $LOCALE['report_done'] : $LOCALE['add_report']) . '</a>';
            }

            if ($objData->orderpage->get() && $objData->date_from->get()->getTimestamp() > time()) {
                $RESPONSE_DATA .= '
                <a class="main" href="' . DataHelper::escapeOutput($objData->orderpage->get()) . '">' . TextHelper::mb_ucfirst($LOCALE['apply']) . '</a>';
            }

            /*if ($objData->kogdaigra_id->get()) {
                $RESPONSE_DATA .= '
                <a class="main" href="http://kogda-igra.ru/game/' . $objData->kogdaigra_id->get() . '/">' . $LOCALE['go_to_kogdaigra'] . '</a>';
            }*/
            if ($allrpgProjectId > 0) {
                $RESPONSE_DATA .= '
                <a class="main" href="' . ABSOLUTE_PATH . '/project/' . $allrpgProjectId . '/">' . $LOCALE['go_to_project'] . '</a>';
            }

            $google_cal_data = [
                'text' => DataHelper::escapeOutput($objData->name->get()),
                'dates' => $objData->date_from->get()->format('Ymd') . 'T000000/' . $objData->date_to->get()->format('Ymd') . 'T235900',
                'trp' => 'true',
                'sprop' => 'allrpg.info',
                'details' => ABSOLUTE_PATH . '/calendar_event/' . DataHelper::getId() . '/',
                'sf' => 'true',
            ];
            $RESPONSE_DATA .= '
                <a class="main" href="https://calendar.google.com/calendar/r/eventedit?' . http_build_query($google_cal_data) . '" target="_blank">' .
                $LOCALE['create_google_cal'] . '</a>';

            $RESPONSE_DATA .= '
                </div>';
        }
        $RESPONSE_DATA .= '
                <div class="report_updated_at"><span>' . $LOCALE_PUBLICATION['publish_date'] . ':</span>' .
            $objData->updated_at->get()->format('d.m.Y ' . $LOCALE_FRAYM['datetime']['at'] . ' H:i') . '</div>
            </div>
        </div>
    </div>
</div>
<div class="page_block">';

        $reports_data = DB->select('report', ['calendar_event_id' => $objData->id->getAsInt()], false, ['created_at DESC']);
        $reports_count = count($reports_data);

        $RESPONSE_DATA .= '
    <div class="fraymtabs">
        <ul>
            <li><a id="description">' . $LOCALE['description'] . '</a></li>
            <li><a id="report">' . $LOCALE['report'] . '<sup>' . $reports_count . '</sup></a></li>
            <li><a id="gallery">' . $LOCALE['gallery'] . '</a></li>
        </ul>';

        $RESPONSE_DATA .= '
        <div id="fraymtabs-description">';

        $RESPONSE_DATA .= '
            <div class="block">
                ' . (DataHelper::escapeOutput($objData->content->get()) !== '' ? '<h2>' . $LOCALE['description'] . '</h2><div class="publication_content">' .
            DataHelper::escapeOutput($objData->content->get(), EscapeModeEnum::plainHTML) . '</div>' : '');

        $result = DB->query(
            'SELECT u.* FROM played p LEFT JOIN user u ON u.id=p.creator_id WHERE p.calendar_event_id=:calendar_event_id AND p.specializ2 != :specializ2 AND p.specializ2 != :specializ2_2 AND p.active=:active',
            [
                ['calendar_event_id', $objData->id->getAsInt()],
                ['specializ2', '-'],
                ['specializ2_2', ''],
                ['active', 1],
            ],
        );
        $userCount = count($result);

        if ($userCount > 0) {
            $RESPONSE_DATA .= '
                <h2>' . $LOCALE['master'] . '<sup>' . $userCount . '</sup></h2>
                <div class="calendar_event_users_list">
                    <div class="users_list_wrapper">';

            foreach ($userService->sortPhotoNameLinks($result) as $userData) {
                $user_img = $userService->photoNameLink($userData, '', true, '', '', true);
                $RESPONSE_DATA .= mb_substr($user_img, 0, mb_strlen($user_img) - 12) .
                    '<div class="user_rights_bar"><a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' .
                    $userData->id->getAsInt() . '" class="user_rights_bar">' . $LOCALE_PEOPLE['contact_user'] . '</a></div></div></div>';
            }
            $RESPONSE_DATA .= '
                    </div>
                </div>';
        }

        $result = DB->query(
            'SELECT u.* FROM played p LEFT JOIN user u ON u.id=p.creator_id WHERE p.calendar_event_id=:calendar_event_id AND p.specializ3 != :specializ3 AND p.specializ3 != :specializ3_2 AND p.active=:active',
            [
                ['calendar_event_id', $objData->id->getAsInt()],
                ['specializ3', '-'],
                ['specializ3_2', ''],
                ['active', 1],
            ],
        );
        $userCount = count($result);

        if ($userCount > 0) {
            $RESPONSE_DATA .= '
                <h2>' . $LOCALE['support'] . '<sup>' . $userCount . '</sup></h2>
                <div class="calendar_event_users_list">
                    <div class="users_list_wrapper">';

            foreach ($userService->sortPhotoNameLinks($result) as $userData) {
                $user_img = $userService->photoNameLink($userData, '', true, '', '', true);
                $RESPONSE_DATA .= $user_img;
            }
            $RESPONSE_DATA .= '
	                </div>
	            </div>';
        }

        $result = DB->query(
            'SELECT u.* FROM played p LEFT JOIN user u ON u.id=p.creator_id WHERE p.calendar_event_id=:calendar_event_id AND p.specializ2 = :specializ2 AND p.specializ3 = :specializ3 AND p.active=:active',
            [
                ['calendar_event_id', $objData->id->getAsInt()],
                ['specializ2', '-'],
                ['specializ3', '-'],
                ['active', 1],
            ],
        );
        $userCount = count($result);

        if ($userCount > 0) {
            $RESPONSE_DATA .= '
                <h2>' . $LOCALE['participant'] . '<sup>' . $userCount . '</sup></h2>
                <div class="calendar_event_users_list">
                    <div class="users_list_wrapper">';

            foreach ($userService->sortPhotoNameLinks($result) as $userData) {
                $user_img = $userService->photoNameLink($userData, '', true, '', '', true);
                $RESPONSE_DATA .= $user_img;
            }
            $RESPONSE_DATA .= '
                    </div>
                </div>';
        }

        $RESPONSE_DATA .= '
            </div>
        </div>';

        $RESPONSE_DATA .= '
        <div id="fraymtabs-report">';

        $RESPONSE_DATA .= '
            <div class="block">
                ' . ($reportRecordId ? '' : '<a class="inner_add_something_button" href="' . ABSOLUTE_PATH . '/report/act=add&calendar_event_id=' . $objData->id->getAsInt() . '"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['add_report'] . '</span></a>
                <div class="tabs_horizontal_shadow"></div>');

        if ($reports_count > 0) {
            $RESPONSE_DATA .= '
                <h2>' . $LOCALE['report'] . '<sup>' . $reports_count . '</sup></h2>
                <table class="menutable calendar_event">
                    <thead>
                        <tr class="menu">
                            <th class="th">
                            ' . $LOCALE['name'] . '
                            </th>
                            <th class="th">
                            ' . $LOCALE['author'] . '
                            </th>
                            <th class="th">
                            ' . $LOCALE['date'] . '
                            </th>
                        </tr>
                    </thead>
                    <tbody>';

            $string_num = 0;

            foreach ($reports_data as $report_data) {
                $RESPONSE_DATA .= '
                        <tr class="string' . ($string_num % 2 === 0 ? '1' : '2') . '">
                            <td><a href="' . ABSOLUTE_PATH . '/report/' . $report_data['id'] . '/">' . (DataHelper::escapeOutput($report_data['name']) !== '' ?
                    DataHelper::escapeOutput(
                        $report_data['name'],
                    ) : $LOCALE_PUBLICATION['no_name']) . '</a></td>
                            <td><a href="' . ABSOLUTE_PATH . '/report/' . $report_data['id'] . '/">' . $userService->showNameExtended(
                        $userService->get($report_data['creator_id']),
                        true,
                        false,
                        '',
                        false,
                        false,
                        true,
                    ) . '</a></td>
                            <td><a href="' . ABSOLUTE_PATH . '/report/' . $report_data['id'] . '/">' . date(
                        'd.m.Y ' . $LOCALE_FRAYM['datetime']['at'] . ' H:i',
                        $report_data['created_at'],
                    ) . '</a></td>
                        </tr>';
                ++$string_num;
            }

            $RESPONSE_DATA .= '
                    </tbody>
                </table>';
        }

        $notion = DB->query("SELECT SUM(IF(rating = '1', 1, IF(rating = '-1', -1, 0))) FROM notion WHERE calendar_event_id=:calendar_event_id AND active=:active", [
            ['calendar_event_id', $objData->id->getAsInt()],
            ['active', 1],
        ], true);
        $rating = $notion[0] ?? 0;

        if ($rating > 0) {
            $rating = '+' . $rating;
        }

        $checkNotion = (CURRENT_USER->isLogged() ? DB->select(
            'notion',
            [
                'calendar_event_id' => $objData->id->getAsInt(),
                'creator_id' => CURRENT_USER->id(),
            ],
            true,
        ) : false);
        $RESPONSE_DATA .= '
                <h2>' . $LOCALE['notion'] . '<sup>' . $rating . '</sup></h2>
                <div class="block_data">' . ($checkNotion ? '' : MessageHelper::conversationForm(null, '{calendar_event_notion}', $objData->id->getAsInt(), $LOCALE['wall_input_text']));

        if (CURRENT_USER->isAdmin() || CURRENT_USER->checkAllrights('info') || $objData->creator_id->getAsInt() === CURRENT_USER->id()) {
            $notion = DB->select('notion', ['calendar_event_id' => $objData->id->getAsInt()], false, ['created_at DESC']);
        } else {
            $notion = DB->select('notion', ['calendar_event_id' => $objData->id->getAsInt(), 'active' => 1], false, ['created_at DESC']);
        }
        $notion_count = count($notion);

        $i = 0;

        foreach ($notion as $notionData) {
            ++$i;

            $notionData['object_admin_id'] = $objData->creator_id->getAsInt();
            $RESPONSE_DATA .= $notionService->conversationNotion($notionData);

            if ($i === 4 && $notion_count > 4) {
                $RESPONSE_DATA .= '
                    <a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>
                    <div class="hidden">';
            }
        }

        if ($i > 4) {
            $RESPONSE_DATA .= '
                    </div>';
        }

        $RESPONSE_DATA .= '
                </div>
		    </div>';

        $RESPONSE_DATA .= '
        </div>';

        $RESPONSE_DATA .= '
        <div id="fraymtabs-gallery">';

        $result = DB->select('calendar_event_gallery', ['calendar_event_id' => $objData->id->getAsInt()], false, ['created_at DESC']);
        $RESPONSE_DATA .= '
            <div class="page_block">
                <a class="inner_add_something_button add_photo" obj_id="' .
            $objData->id->getAsInt() . '"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['add_photo'] . '</span></a>
                <div class="tabs_horizontal_shadow"></div>
                <div class="calendar_event_photos_videos_container">';

        foreach ($result as $calendarEventGalleryData) {
            $RESPONSE_DATA .= '<div class="calendar_event_photo_video" obj_id="' . $calendarEventGalleryData['id'] . '" author="' .
                DataHelper::escapeOutput($calendarEventGalleryData['author']) . '">';

            if (
                CURRENT_USER->isAdmin() || CURRENT_USER->checkAllrights('info') || $objData->creator_id->getAsInt() === CURRENT_USER->id()
                || ($calendarEventGalleryData['user_id'] ?? null) === CURRENT_USER->id()
            ) {
                $RESPONSE_DATA .= '<div class="calendar_event_photo_video_controls"><a class="calendar_event_photo_video_edit" title="' . $LOCALE_FRAYM['classes']['file']['edit'] . '"></a><a class="calendar_event_photo_video_delete trash careful action_request" action_request="calendar_event_gallery/delete_calendar_event_gallery" obj_id="' . $calendarEventGalleryData['id'] . '" title="' . $LOCALE_FRAYM['classes']['file']['delete'] . '"></a></div>';
            }
            $RESPONSE_DATA .= '<a href="' . DataHelper::escapeOutput($calendarEventGalleryData['link']) . '" target="_blank" title="';
            $RESPONSE_DATA .= $userService->showNameExtended(
                $userService->get($calendarEventGalleryData['creator_id']),
                true,
                false,
                '',
                false,
                false,
                true,
            ) . ' ' . date('d.m.Y ' . $LOCALE_FRAYM['datetime']['at'] . ' H:i', $calendarEventGalleryData['created_at']);
            $RESPONSE_DATA .= '" class="container">';

            if ($calendarEventGalleryData['thumb'] !== '') {
                $RESPONSE_DATA .= '<img src="' . DataHelper::escapeOutput($calendarEventGalleryData['thumb']) . '">';
            } else {
                $RESPONSE_DATA .= '<img src="" />';
            }
            $RESPONSE_DATA .= '</a>';
            $RESPONSE_DATA .= '<div class="calendar_event_photo_video_description">';

            if ($calendarEventGalleryData['name'] !== '') {
                $RESPONSE_DATA .= '<a href="' . DataHelper::escapeOutput($calendarEventGalleryData['link']) . '"><span>' .
                    DataHelper::escapeOutput($calendarEventGalleryData['name']) . '</span></a><br>';
            }

            if ($calendarEventGalleryData['author'] !== '') {
                if (is_numeric($calendarEventGalleryData['author'])) {
                    $RESPONSE_DATA .= $userService->showNameExtended(
                        $userService->get(null, ['sid' => $calendarEventGalleryData['author']]),
                        true,
                        true,
                        '',
                        false,
                        false,
                        true,
                    );
                } else {
                    $RESPONSE_DATA .= $userService->social2(DataHelper::escapeOutput($calendarEventGalleryData['author']), '', true);
                }
            }
            $RESPONSE_DATA .= '</div></div>';
        }
        $RESPONSE_DATA .= '</div>
	        </div>';

        $RESPONSE_DATA .= '
        </div>';

        $RESPONSE_DATA .= '
    </div>
</div>
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
