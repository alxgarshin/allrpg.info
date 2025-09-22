<?php

declare(strict_types=1);

namespace App\CMSVC\People;

use App\CMSVC\Notion\NotionService;
use App\CMSVC\Portfolio\PortfolioModel;
use App\CMSVC\Publication\PublicationService;
use App\CMSVC\User\UserService;
use App\Helper\{DateHelper, DesignHelper, MessageHelper, RightsHelper, TextHelper};
use Fraym\BaseObject\{BaseView, Controller, DependencyInjection};
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Response;

/** @extends BaseView<PeopleService> */
#[Controller(PeopleController::class)]
class PeopleView extends BaseView
{
    #[DependencyInjection]
    public UserService $userService;

    #[DependencyInjection]
    public NotionService $notionService;

    #[DependencyInjection]
    public PublicationService $publicationService;

    public function Response(): ?Response
    {
        $peopleService = $this->getService();
        $userService = $this->userService;
        $notionService = $this->notionService;
        $publicationService = $this->publicationService;

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_ACHIEVEMENT = $LOCALE_GLOBAL['achievement'];
        $LOCALE_USER = LocaleHelper::getLocale(['user', 'global']);
        $LOCALE_PUBLICATION = LocaleHelper::getLocale(['publication', 'global']);

        $objData = $peopleService->getUserData();

        $myOwnProfile = $peopleService->checkMyOwnProfile($objData);
        $birthDate = $peopleService->checkBirthDate($objData);
        $cityName = $peopleService->getCityName($objData);
        $achievementsData = $peopleService->getAchievementsData($objData);
        $checkCrossedProjects = $peopleService->checkCrossedProjects($objData);
        $checkContact = $peopleService->checkContact($objData);
        $checkGamemaster = $peopleService->checkGamemaster($objData);
        $masterPlayedData = $peopleService->getMasterPlayedData($objData);
        $supportPlayedData = $peopleService->getSupportPlayedData($objData);
        $playerPlayedData = $peopleService->getPlayerPlayedData($objData);
        $friendsCount = count($peopleService->getFriendsData($objData));
        $reportsData = $peopleService->getReportsData($objData);
        $publicationsData = $peopleService->getPublicationsData($objData);
        $checkNotion = $peopleService->checkNotion($objData);
        $notionsData = $peopleService->getNotions($objData);

        $projectsData = [];
        $communitiesData = [];

        if ($checkContact) {
            $projectsData = $peopleService->getProjectsData($objData);
            $communitiesData = $peopleService->getCommunitiesData($objData);
        }

        $portfolioModel = new PortfolioModel();
        $portfolioModel->construct()->init();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($userService->showName($objData));
        $RESPONSE_DATA = '';

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
    <div class="page_block">
        <div class="object_info">
            <div class="object_info_1">
                <a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->sid->get() . '/" class="object_avatar"><div style="' . DesignHelper::getCssBackgroundImage($userService->photoUrl($objData)) . '"></div></a>
            </div>
            <div class="object_info_2">';

        $RESPONSE_DATA .= '
                <h1><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $objData->sid->get() . '/">' . $userService->showName($objData) . '</a></h1>
                <span class="user_status_switcher' . ($objData->id->getAsInt() === CURRENT_USER->id() ? ' active' : '') . '">' . ($objData->status->get() !== null ? $objData->status->get() : (CURRENT_USER->id() === $objData->id->getAsInt() ? '<i>' . $LOCALE['click_to_change_status'] . '</i>' : '')) . '</span>

                <div class="object_info_2_additional">
                    <span class="gray">' . TextHelper::mb_ucfirst($LOCALE_GLOBAL['user_id']) . ':</span>' . $objData->sid->get() . '<br>';

        if ($birthDate) {
            $RESPONSE_DATA .= '
		            <span class="gray">' . $LOCALE['birth_date'] . ':</span>' . $birthDate->format('d') . ' ' . DateHelper::monthname($birthDate->format('m')) . ($objData->gender->get() === 2 || in_array('108', $objData->hidesome->get()) ? '' : ' ' . $birthDate->format('Y')) . '<br>';
        }

        if ($cityName) {
            $RESPONSE_DATA .= '
	                <span class="gray">' . $LOCALE['city'] . ':</span>' . $cityName . '<br>';
        }

        if ($myOwnProfile || $checkCrossedProjects || $checkContact || $checkGamemaster) {
            $contactContent = '';

            if ((!in_array('2', $objData->hidesome->get()) || $checkGamemaster) && $objData->em->get()) {
                $contactContent .= '<span class="gray">' . $objData->em->getShownName() . ':</span><span><a href="mailto:' . $objData->em->asHTML(false) . '">' . $objData->em->asHTML(false) . '</a></span><br>';
            }

            $showFields = [
                [
                    'hidesome' => 5,
                    'sname' => 'phone',
                ],
                [
                    'hidesome' => 107,
                    'sname' => 'telegram',
                ],
                [
                    'hidesome' => 7,
                    'sname' => 'skype',
                ],
                [
                    'hidesome' => 101,
                    'sname' => 'facebook_visible',
                ],
                [
                    'hidesome' => 102,
                    'sname' => 'vkontakte_visible',
                ],
                [
                    'hidesome' => 105,
                    'sname' => 'livejournal',
                ],
            ];

            foreach ($showFields as $fieldData) {
                $elem = $objData->{$fieldData['sname']};

                if ((!in_array($fieldData['hidesome'], $objData->hidesome->get()) || $checkGamemaster) && ((is_array($elem->get()) && count($elem->get()) > 0) || $elem->get() !== null)) {
                    $contactContent .= '
                    <span class="gray">' . $elem->getShownName() . ':</span><span>' . ($elem->getName() === 'phone' ? '<a href="tel:' . $elem->asHTML(false) . '">' . $elem->asHTML(false) . '</a>' : $userService->socialShow($elem->get(), str_replace('_visible', '', $elem->getName()))) . '</span><br>';
                }
            }

            if ($contactContent !== '') {
                $RESPONSE_DATA .= '
                    <a class="show_hidden">' . $LOCALE['show_contacts'] . '</a>
	                <div class="hidden">' . $contactContent . '</div>';
            }

            if ($myOwnProfile || $checkContact || $checkGamemaster) {
                $RESPONSE_DATA .= '
                    <a class="show_hidden">' . $LOCALE_GLOBAL['show_next'] . '</a>
	                <div class="hidden">';

                $showFields = [
                    [
                        'sname' => 'prefer',
                    ],
                    [
                        'sname' => 'prefer2',
                    ],
                    [
                        'sname' => 'prefer3',
                    ],
                    [
                        'sname' => 'prefer4',
                    ],
                    [
                        'sname' => 'speciality',
                    ],
                    [
                        'sname' => 'gender',
                    ],
                ];

                foreach ($showFields as $fieldData) {
                    $elem = $objData->{$fieldData['sname']};

                    if ((is_array($elem->get()) && count($elem->get()) > 0) || $elem->get() !== null) {
                        $RESPONSE_DATA .= '
                        <span class="gray bigger">' . $elem->getShownName() . ':</span><span>' . $elem->asHTML(false) . '</span><br>';
                    }
                }

                $RESPONSE_DATA .= '</div>';
            }
        }

        $RESPONSE_DATA .= '
                </div>
            </div>
            <div class="object_info_3">
                <div class="achievementsData"><span>' . $LOCALE['achievements'] . ':</span><div class="achievements">';

        foreach ($achievementsData as $achievementItem) {
            $RESPONSE_DATA .= '
                    <span class="sbi sbi-star achievement type_' . $achievementItem['type'] . '" title="' . $LOCALE_ACHIEVEMENT['names'][(string) $achievementItem['id']] . '"></span>';
        }

        $RESPONSE_DATA .= '
                </div></div>
                <div class="actions_list_switcher">';

        if (CURRENT_USER->id() === $objData->id->getAsInt()) {
            $RESPONSE_DATA .= '
                    <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/profile/"><span>' . $LOCALE['edit_profile'] . '</span></a></div>';
        } else {
            if (CURRENT_USER->isAdmin() || $userService->isModerator()) {
                $RESPONSE_DATA .= '
                    <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                    <div class="actions_list_items">';
                $RESPONSE_DATA .= '
                        <a href="' . ABSOLUTE_PATH . '/profile/adm_user=' . $objData->id->getAsInt() . '">' . $LOCALE['edit_profile'] . '</a>
                        <a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $objData->id->getAsInt() . '">' . $LOCALE['contact_user'] . '</a>';
                $RESPONSE_DATA .= '
                    </div>';
            } else {
                $RESPONSE_DATA .= '
                    <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $objData->id->getAsInt() . '"><span>' . $LOCALE['contact_user'] . '</span></a></div>';
            }
        }

        $RESPONSE_DATA .= '
                    <div class="user_was_online"><span>' . sprintf($LOCALE_USER['was_online'], LocaleHelper::declineVerb($objData)) . ':</span>' . ($objData->updated_at->get()->getTimestamp() < time() - 180 ? DateHelper::showDateTime($objData->updated_at->get()->getTimestamp()) : $LOCALE_USER['online']) . '</div>
                    <div class="user_was_registered"><span>' . sprintf($LOCALE_USER['was_registered'], LocaleHelper::declineVerb($objData)) . ':</span>' . $objData->created_at->getAsUsualDate() . '</div>';

        $RESPONSE_DATA .= '
                </div>
            </div>
        </div>
    </div>
    <div class="page_block">';

        $RESPONSE_DATA .= '
            <div class="fraymtabs">
            <ul>
                <li><a id="portfolio">' . $LOCALE['portfolio'] . '<sup>' . (count($masterPlayedData) + count($supportPlayedData) + count($playerPlayedData)) . '</sup></a></li>
                <li><a id="friends">' . $LOCALE['colleagues'] . '<sup>' . $friendsCount . '</sup></a></li>
                <li><a id="notions">' . $LOCALE['notion'] . '<sup>' . count($notionsData) . '</sup></a></li>
                <li><a id="reports">' . $LOCALE['report'] . '<sup>' . count($reportsData) . '</sup></a></li>
                <li><a id="publications">' . $LOCALE['publications'] . '<sup>' . count($publicationsData) . '</sup></a></li>
                ' . ($checkContact && count($projectsData) > 0 ? '<li><a id="projects">' . $LOCALE['projects'] . '<sup>' . count($projectsData) . '</sup></a></li>' : '') . '
                ' . ($checkContact && count($communitiesData) > 0 ? '<li><a id="communities">' . $LOCALE['communities'] . '<sup>' . count($communitiesData) . '</sup></a></li>' : '') . '
            </ul>';

        $RESPONSE_DATA .= '
            <div id="fraymtabs-portfolio">
            <div class="block">';

        if (CURRENT_USER->id() === $objData->id->getAsInt()) {
            $RESPONSE_DATA .= '<a class="inner_add_something_button" href="' . ABSOLUTE_PATH . '/portfolio/act=add"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['add_portfolio'] . '</span></a>
    <div class="tabs_horizontal_shadow"></div>';
        }

        $playedCount = count($masterPlayedData);

        if ($playedCount > 0 || CURRENT_USER->id() === $objData->id->getAsInt()) {
            $RESPONSE_DATA .= '
	<h2>' . $LOCALE['master'] . '<sup>' . $playedCount . '</sup></h2>';

            if ($playedCount > 0) {
                $RESPONSE_DATA .= '
	<div class="multi_objects_table excel played maininfotable" style="--mot_columns_count: 3"><div class="tr menu"><div class="th">' . $LOCALE['portfolio_fields']['event'] . '</div><div class="th">' . $LOCALE['portfolio_fields']['dates'] . '</div><div class="th">' . $LOCALE['portfolio_fields']['speciality'] . '</div><div class="th centered">' . $LOCALE['portfolio_fields']['report'] . '</div><div class="th">' . $LOCALE['portfolio_fields']['photo'] . '</div></div>';
            }

            $stringNum = 0;

            foreach ($masterPlayedData as $playedData) {
                $portfolioModel->specializ2->set($playedData['specializ2']);
                $RESPONSE_DATA .= '<div class="tr string' . ($stringNum % 2 === 0 ? '1' : '2') . ($stringNum > 5 ? ' hidden' : '') . ($playedData['active'] === '1' ? '' : ' not_visible') . '"><div class="td"><a href="' . ABSOLUTE_PATH . '/calendar_event/' . $playedData['calendar_event_id'] . '/">' . DataHelper::escapeOutput($playedData['name']) . '</a>' . ($objData->id->getAsInt() === CURRENT_USER->id() ? '<span class="small"><a href="' . ABSOLUTE_PATH . '/portfolio/' . $playedData['id'] . '/">' . $LOCALE_FRAYM['functions']['edit'] . '</a></span>' : '') . '</div><div class="td">' . DateHelper::dateFromToEvent($playedData['date_from'], $playedData['date_to']) . '</div><div class="td">' . $portfolioModel->specializ2->asHTML(false) . '</div><div class="td centered">' . ($playedData['report_id'] > 0 ? '<a class="add_something sbi sbi-check inverted" href="' . ABSOLUTE_PATH . '/report/'
                    . $playedData['report_id'] . '/"></a>' : ($playedData['notion_id'] > 0 ? '<a class="add_something sbi sbi-check inverted" href="' . ABSOLUTE_PATH . '/calendar_event/' . $playedData['calendar_event_id'] . '/#notion_' . $playedData['notion_id'] . '"></a>' : ($objData->id->getAsInt() === CURRENT_USER->id() ? '<a class="add_something sbi sbi-plus inverted" href="' . ABSOLUTE_PATH . '/report/act=add&calendar_event_id=' . $playedData['calendar_event_id'] . '"></a>' : '&nbsp;'))) . '</div><div class="td centered">' . (($playedData['photo'] ?? false) ? '<a class="add_something sbi sbi-check inverted" href="' . DataHelper::escapeOutput($playedData['photo']) . '" target="_blank"></a>' : ($objData->id->getAsInt() === CURRENT_USER->id() ? '<a class="add_something sbi sbi-plus inverted" href="' . ABSOLUTE_PATH . '/portfolio/' . $playedData['id'] . '/"></a>' : '&nbsp;')) . '</div></div>';

                if ($stringNum === 5 && $playedCount > 6) {
                    $RESPONSE_DATA .= '<div class="tr full_width"><div class="td"><a class="show_hidden_table">' . $LOCALE_GLOBAL['show_hidden'] . '</a></div></div>';
                }
                ++$stringNum;
            }

            if ($playedCount > 0) {
                $RESPONSE_DATA .= '</div>';
            }
        }

        $playedCount = count($supportPlayedData);

        if ($playedCount > 0 || CURRENT_USER->id() === $objData->id->getAsInt()) {
            $RESPONSE_DATA .= '
	<h2>' . $LOCALE['support'] . '<sup>' . $playedCount . '</sup></h2>';

            if ($playedCount > 0) {
                $RESPONSE_DATA .= '
	<div class="multi_objects_table excel played maininfotable" style="--mot_columns_count: 3"><div class="tr menu"><div class="th">' . $LOCALE['portfolio_fields']['event'] . '</div><div class="th">' . $LOCALE['portfolio_fields']['dates'] . '</div><div class="th">' . $LOCALE['portfolio_fields']['speciality'] . '</div><div class="th centered">' . $LOCALE['portfolio_fields']['report'] . '</div><div class="th">' . $LOCALE['portfolio_fields']['photo'] . '</div></div>';
            }

            $stringNum = 0;

            foreach ($supportPlayedData as $playedData) {
                $portfolioModel->specializ3->set($playedData['specializ3']);
                $RESPONSE_DATA .= '<div class="tr string' . ($stringNum % 2 === 0 ? '1' : '2') . ($stringNum > 5 ? ' hidden' : '') . ($playedData['active'] === '1' ? '' : ' not_visible') . '"><div class="td"><a href="' . ABSOLUTE_PATH . '/calendar_event/' . $playedData['calendar_event_id'] . '/">' . DataHelper::escapeOutput($playedData['name']) . '</a>' . ($objData->id->getAsInt() === CURRENT_USER->id() ? '<span class="small"><a href="' . ABSOLUTE_PATH . '/portfolio/' . $playedData['id'] . '/">' . $LOCALE_FRAYM['functions']['edit'] . '</a></span>' : '') . '</div><div class="td">' . DateHelper::dateFromToEvent($playedData['date_from'], $playedData['date_to']) . '</div><div class="td">' . $portfolioModel->specializ3->asHTML(false) . '</div><div class="td centered">' . ($playedData['report_id'] > 0 ? '<a class="add_something sbi sbi-check inverted" href="' . ABSOLUTE_PATH . '/report/'
                    . $playedData['report_id'] . '/"></a>' : ($playedData['notion_id'] > 0 ? '<a class="add_something sbi sbi-check inverted" href="' . ABSOLUTE_PATH . '/calendar_event/' . $playedData['calendar_event_id'] . '/#notion_' . $playedData['notion_id'] . '"></a>' : ($objData->id->getAsInt() === CURRENT_USER->id() ? '<a class="add_something sbi sbi-plus inverted" href="' . ABSOLUTE_PATH . '/report/act=add&calendar_event_id=' . $playedData['calendar_event_id'] . '"></a>' : '&nbsp;'))) . '</div><div class="td centered">' . (($playedData['photo'] ?? false) ? '<a class="add_something sbi sbi-check inverted" href="' . DataHelper::escapeOutput($playedData['photo']) . '" target="_blank"></a>' : ($objData->id->getAsInt() === CURRENT_USER->id() ? '<a class="add_something sbi sbi-plus inverted" href="' . ABSOLUTE_PATH . '/portfolio/' . $playedData['id'] . '/"></a>' : '&nbsp;')) . '</div></div>';

                if ($stringNum === 5 && $playedCount > 6) {
                    $RESPONSE_DATA .= '<div class="tr full_width"><div class="td"><a class="show_hidden_table">' . $LOCALE_GLOBAL['show_hidden'] . '</a></div></div>';
                }
                ++$stringNum;
            }

            if ($playedCount > 0) {
                $RESPONSE_DATA .= '</div>';
            }
        }

        $playedCount = count($playerPlayedData);

        if ($playedCount > 0 || CURRENT_USER->id() === $objData->id->getAsInt()) {
            $RESPONSE_DATA .= '	
	<h2>' . $LOCALE['participant'] . '<sup>' . $playedCount . '</sup></h2>';

            if ($playedCount > 0) {
                $RESPONSE_DATA .= '<div class="multi_objects_table excel played maininfotable" style="--mot_columns_count: 4"><div class="tr menu"><div class="th">' . $LOCALE['portfolio_fields']['event'] . '</div><div class="th">' . $LOCALE['portfolio_fields']['dates'] . '</div><div class="th">' . $LOCALE['portfolio_fields']['role_and_location'] . '</div><div class="th hide_on_small">' . $LOCALE['portfolio_fields']['speciality'] . '</div><div class="th centered">' . $LOCALE['portfolio_fields']['report'] . '</div><div class="th centered">' . $LOCALE['portfolio_fields']['photo'] . '</div></div>';
            }

            $stringNum = 0;

            foreach ($playerPlayedData as $playedData) {
                $portfolioModel->specializ->set($playedData['specializ']);
                $RESPONSE_DATA .= '<div class="tr string' . ($stringNum % 2 === 0 ? '1' : '2') . ($stringNum > 5 ? ' hidden' : '') . ($playedData['active'] === '1' ? '' : ' not_visible') . '"><div class="td"><a href="' . ABSOLUTE_PATH . '/calendar_event/' . $playedData['calendar_event_id'] . '/">' . DataHelper::escapeOutput($playedData['name']) . '</a>' . ($objData->id->getAsInt() === CURRENT_USER->id() ? '<span class="small"><a href="' . ABSOLUTE_PATH . '/portfolio/' . $playedData['id'] . '/">' . $LOCALE_FRAYM['functions']['edit'] . '</a></span>' : '') . '</div><div class="td">' . DateHelper::dateFromToEvent($playedData['date_from'], $playedData['date_to']) . '</div><div class="td">' . DataHelper::escapeOutput($playedData['role']) . (DataHelper::escapeOutput($playedData['role']) !== '' ? '<span class="small">' . DataHelper::escapeOutput($playedData['locat']) . '</span>' : DataHelper::escapeOutput($playedData['locat'])) . '</div><div class="td hide_on_small">' . $portfolioModel->specializ->asHTML(false) . '</div><div class="td centered">' . ($playedData['report_id'] > 0 ? '<a class="add_something sbi sbi-check inverted" href="' . ABSOLUTE_PATH . '/report/' . $playedData['report_id'] . '/"></a>' : ($playedData['notion_id'] > 0 ? '<a class="add_something sbi sbi-check inverted" href="' . ABSOLUTE_PATH . '/calendar_event/' . $playedData['calendar_event_id'] . '/#notion_' . $playedData['notion_id'] . '"></a>' : ($objData->id->getAsInt() === CURRENT_USER->id() ? '<a class="add_something sbi sbi-plus inverted" href="' . ABSOLUTE_PATH . '/report/act=add&calendar_event_id=' . $playedData['calendar_event_id']
                    . '"></a>' : '&nbsp;'))) . '</div><div class="td centered">' . (($playedData['photo'] ?? false) ? '<a class="add_something sbi sbi-check inverted" href="' . DataHelper::escapeOutput($playedData['photo']) . '" target="_blank"></a>' : ($objData->id->getAsInt() === CURRENT_USER->id() ? '<a class="add_something sbi sbi-plus inverted" href="' . ABSOLUTE_PATH . '/portfolio/' . $playedData['id'] . '/"></a>' : '&nbsp;')) . '</div></div>';

                if ($stringNum === 5 && $playedCount > 6) {
                    $RESPONSE_DATA .= '<div class="tr full_width"><div class="td"><a class="show_hidden_table">' . $LOCALE_GLOBAL['show_hidden'] . '</a></div></div>';
                }
                ++$stringNum;
            }

            if ($playedCount > 0) {
                $RESPONSE_DATA .= '</div>';
            }
        }

        $RESPONSE_DATA .= '
            </div>
            </div>';

        $RESPONSE_DATA .= '
            <div id="fraymtabs-friends">
            <div class="block user_users_list" id="user_users_list">
            ' . (CURRENT_USER->id() === $objData->id->getAsInt() ? '<a class="inner_add_something_button" href="' . ABSOLUTE_PATH . '/search/"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['find_a_friend'] . '</span></a>' : '') . '
            <input type="text" name="user_rights_lookup" placehold="' . $LOCALE_GLOBAL['input_fio_id_for_search'] . '">
            <div class="tabs_horizontal_shadow"></div>
            <div class="users_list_wrapper">
                <a class="load_users_list" obj_type="user" obj_id="' . $objData->id->getAsInt() . '" limit="0" shown_limit="50">' . $LOCALE_GLOBAL['show_next'] . '</a>
            </div>';

        $RESPONSE_DATA .= '
            </div>
            </div>';

        $RESPONSE_DATA .= '
            <div id="fraymtabs-notions">
            <div class="block">';

        $RESPONSE_DATA .= '
    ' . ($checkNotion || $objData->id->getAsInt() === CURRENT_USER->id() ? '' : MessageHelper::conversationForm(
            null,
            '{user_notion}',
            $objData->id->getAsInt(),
            $LOCALE['wall_input_text'],
        )) . '
    ';

        $stringNum = 0;

        foreach ($notionsData as $notionItem) {
            ++$stringNum;

            $notionItem['object_admin_id'] = $objData->id->getAsInt();
            $RESPONSE_DATA .= $notionService->conversationNotion($notionItem, false);

            if ($stringNum === 4 && count($notionsData) > 4) {
                $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>
<div class="hidden">';
            }
        }

        if ($stringNum > 4) {
            $RESPONSE_DATA .= '</div>';
        }

        $RESPONSE_DATA .= '
            </div>
            </div>';

        $RESPONSE_DATA .= '
            <div id="fraymtabs-reports">
            <div class="block">';

        $RESPONSE_DATA .= (CURRENT_USER->id() === $objData->id->getAsInt() ? '<a class="inner_add_something_button" href="' . ABSOLUTE_PATH . '/report/act=add"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['report_add'] . '</span></a>
    <div class="tabs_horizontal_shadow"></div>' : '');

        if (count($reportsData) > 0) {
            $RESPONSE_DATA .= '<div class="multi_objects_table excel report_in_profile maininfotable" style="--mot_columns_count: 3"><div class="tr menu"><div class="th">' . $LOCALE['name'] . '</div><div class="th">' . $LOCALE['portfolio_fields']['event'] . '</div><div class="th">' . $LOCALE['date'] . '</div></div>';

            $stringNum = 0;

            foreach ($reportsData as $reportItem) {
                $RESPONSE_DATA .= '<div class="tr string' . ($stringNum % 2 === 0 ? '1' : '2') . ($stringNum > 5 ? ' hidden' : '') . '"><div class="td"><a href="'
                    . ABSOLUTE_PATH . '/report/'
                    . $reportItem['id'] . '/">' . (DataHelper::escapeOutput($reportItem['name']) !== '' ? DataHelper::escapeOutput($reportItem['name']) : $LOCALE_PUBLICATION['no_name']) . '</a></div><div class="td"><a href="' . ABSOLUTE_PATH . '/report/' . $reportItem['id'] . '/">' . DataHelper::escapeOutput($reportItem['calendar_event_name']) . '</a></div><div class="td"><a href="' . ABSOLUTE_PATH . '/report/' . $reportItem['id'] . '/">' . date(
                        'd.m.Y ' . $LOCALE_FRAYM['datetime']['at'] . ' H:i',
                        $reportItem['created_at'],
                    ) . '</a></div></div>';

                if ($stringNum === 5) {
                    $RESPONSE_DATA .= '<div class="tr full_width"><div class="td"><a class="show_hidden_table">' . $LOCALE_GLOBAL['show_hidden'] . '</a></div></div>';
                }
                ++$stringNum;
            }

            $RESPONSE_DATA .= '</div>';
        }

        $RESPONSE_DATA .= '
            </div>
            </div>';

        $RESPONSE_DATA .= '
            <div id="fraymtabs-publications">
            <div class="block">';

        $RESPONSE_DATA .= (CURRENT_USER->id() === $objData->id->getAsInt() ? '<a class="inner_add_something_button" href="' . ABSOLUTE_PATH . '/publications_edit/act=add"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['publication_add'] . '</span></a>
    <div class="tabs_horizontal_shadow"></div>' : '');

        if (count($publicationsData) > 0) {
            $stringNum = 0;

            foreach ($publicationsData as $publicationItem) {
                ++$stringNum;
                $RESPONSE_DATA .= $publicationService->drawPublicationShort($publicationItem);

                if ($stringNum === 3 && count($publicationsData) > 3) {
                    $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>
				<div class="hidden">';
                }
            }

            if ($stringNum > 3) {
                $RESPONSE_DATA .= '</div>';
            }
        }

        $RESPONSE_DATA .= '
            </div>
            </div>';

        if ($checkContact && count($projectsData) > 0) {
            $RESPONSE_DATA .= '
            <div id="fraymtabs-projects">
            <div class="block">';

            $RESPONSE_DATA .= (CURRENT_USER->id() === $objData->id->getAsInt() ? '<a class="inner_add_something_button" href="' . ABSOLUTE_PATH . '/project/act=add"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['project_add'] . '</span></a>
    <div class="tabs_horizontal_shadow"></div>' : '');

            $RESPONSE_DATA .= '
                <div class="overflown_content em15">
                    <div class="navitems_plates">';

            foreach ($projectsData as $projectItem) {
                $members_count = 0;
                $members_count_data = RightsHelper::findByRights(null, '{project}', $projectItem['id'], '{user}', false);

                if (is_array($members_count_data)) {
                    $members_count = count(array_unique($members_count_data));
                }

                $RESPONSE_DATA .= DesignHelper::drawPlate('project', [
                    'id' => $projectItem['id'],
                    'attachments' => $projectItem['attachments'],
                    'name' => DataHelper::escapeOutput($projectItem['name']),
                    'members_count' => $members_count,
                ]);
            }
            $RESPONSE_DATA .= '
                    </div>
                 </div>';

            if (count($projectsData) > 4) {
                $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>';
            }

            $RESPONSE_DATA .= '
            </div>
            </div>';
        }

        if ($checkContact && count($communitiesData) > 0) {
            $RESPONSE_DATA .= '
            <div id="fraymtabs-communities">
            <div class="block">';

            $RESPONSE_DATA .= (CURRENT_USER->id() === $objData->id->getAsInt() ? '<a class="inner_add_something_button" href="' . ABSOLUTE_PATH . '/community/act=add"><span class="sbi sbi-add-something"></span><span class="inner_add_something_button_text">' . $LOCALE['community_add'] . '</span></a>
    <div class="tabs_horizontal_shadow"></div>' : '');

            $RESPONSE_DATA .= '
                <div class="overflown_content em15">
                    <div class="navitems_plates">';

            foreach ($communitiesData as $communityItem) {
                $members_count = 0;
                $members_count_data = RightsHelper::findByRights(null, '{community}', $communityItem['id'], '{user}', false);

                if (is_array($members_count_data)) {
                    $members_count = count(array_unique($members_count_data));
                }

                $RESPONSE_DATA .= DesignHelper::drawPlate('community', [
                    'id' => $communityItem['id'],
                    'attachments' => $communityItem['attachments'],
                    'name' => DataHelper::escapeOutput($communityItem['name']),
                    'members_count' => $members_count,
                ]);
            }
            $RESPONSE_DATA .= '
                    </div>
                </div>';

            if (count($communitiesData) > 4) {
                $RESPONSE_DATA .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>';
            }

            $RESPONSE_DATA .= '
            </div>
            </div>';
        }

        $RESPONSE_DATA .= '
    </div>
    </div>
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
