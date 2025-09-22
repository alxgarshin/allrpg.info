<?php

declare(strict_types=1);

namespace App\CMSVC\Calendar;

use App\CMSVC\User\UserService;
use App\Helper\{DateHelper, DesignHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

#[Controller(CalendarController::class)]
class CalendarView extends BaseView
{
    public function Response(): ?Response
    {
        /** @var CalendarService $calendarService */
        $calendarService = $this->getCMSVC()->getService();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->getLOCALE();
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_CALENDAR_EVENT = LocaleHelper::getLocale(['calendar_event', 'global']);

        $year = $calendarService->getYear();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink(sprintf($LOCALE['title'], $year));

        $calendarEventsData = $calendarService->getAllAsArray();
        $regionsIds = $calendarService->getRegionsIds($calendarEventsData);

        [$regionsList, $regionsListRehashed] = $calendarService->getRegionsLists();
        $settingsList = $calendarService->getSettingsList();
        [$typesList, $typesListRehashed] = $calendarService->getTypesLists();
        $minDate = $calendarService->getMinDate();
        $maxDate = $calendarService->getMaxDate();
        $minYear = $calendarService->getMinYear();
        $maxYear = $calendarService->getMaxYear();

        $marked_dates = [];
        $marked_dates_mark = [];
        $stringnum = 0;
        $previous_calendar_event_month = 0;

        $calendarStyle = 0;

        if (CURRENT_USER->isLogged()) {
            $userData = $userService->get(CURRENT_USER->id());
            $calendarStyle = $userData->calendarstyle->get();
        }

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<h1 class="form_header">' . sprintf($LOCALE['title'], $year) . '</h1>
<div class="indexer_toggle"><span class="indexer_toggle_text">' . $LOCALE_FRAYM['filters']['filter'] . '</span><span class="sbi sbi-search"></span></div>
<div class="page_blocks">
<div class="page_block calendar">

<a href="' . ABSOLUTE_PATH . '/calendar_event/act=add" class="ctrlink"><span class="sbi sbi-plus"></span>' . $LOCALE['add_event'] . '</a>

<div class="filter filter_year">
<div class="name">
' . $LOCALE['filter_year'] . '
</div>
<select id="filter_year">';
        $filterYearValues = $minYear;

        while ($filterYearValues <= $maxYear) {
            $RESPONSE_DATA .= '<option value="' . $filterYearValues . '" ' . ($filterYearValues === $year ? 'selected' : '') . '>' . $filterYearValues . '</option>';
            ++$filterYearValues;
        }
        $RESPONSE_DATA .= '
</select>
<div class="fixed_selects">
' . ($minYear <= ($year - 1) ? '<div class="fixed_select" value="' . ($year - 1) . '">' . ($year - 1) . '</div>' : '') . '
<div class="fixed_select" value="' . $year . '">' . $year . '</div>
' . ($maxYear >= ($year + 1) ? '<div class="fixed_select" value="' . ($year + 1) . '">' . ($year + 1) . '</div>' : '') . '
</div>
</div>

<div class="calendar_event_cards">';

        $curYear = $year === (int) date('Y');

        foreach ($calendarEventsData as $calendar_event_data) {
            /** @var array $calendar_event_data */
            $calendar_datestart = strtotime($calendar_event_data['date_from']);

            $mark = '';

            if ($calendar_datestart > strtotime($calendar_event_data['date_to'])) {
                $calendar_event_data['date_to'] = $calendar_event_data['date_from'];
            }

            if (date('n', $calendar_datestart) > $previous_calendar_event_month || $previous_calendar_event_month > date('n', $calendar_datestart)) {
                $RESPONSE_DATA .= '<div class="calendar_event_month' . ($calendarStyle ? ' hidden' : '') . '">' . $LOCALE_FRAYM['months_base'][date(
                    'n',
                    $calendar_datestart,
                )] . '</div>';
                $previous_calendar_event_month = date('n', $calendar_datestart);
            }

            $RESPONSE_DATA .= '<div class="calendar_event_card string' . ($stringnum % 2 === 1 ? 1 : 2);

            if ($calendar_event_data['played_id'] ?? false) {
                $mark = 'player';

                if (!in_array($calendar_event_data['specializ2'], ['', '-'])) {
                    $mark = 'master';
                } elseif (!in_array($calendar_event_data['specializ2'], ['', '-'])) {
                    $mark = 'tech';
                }
                $RESPONSE_DATA .= ' ' . $mark;
            } else {
                $RESPONSE_DATA .= ' event';
            }

            if ($calendarStyle) {
                $RESPONSE_DATA .= ' hidden';
            }

            if ($calendar_event_data['moved'] === '1' || $calendar_event_data['wascancelled'] === '1') {
                $RESPONSE_DATA .= ' cancelled_moved hidden';
                --$stringnum;
            }
            $RESPONSE_DATA .= '" dates="';
            $timestamp = $calendar_datestart;

            while ($timestamp <= strtotime($calendar_event_data['date_to'])) {
                if ($timestamp !== $calendar_datestart) {
                    $RESPONSE_DATA .= ' ';
                }
                $RESPONSE_DATA .= $timestamp;

                if (!($marked_dates[$timestamp] ?? false)) {
                    $marked_dates[$timestamp] = 0;
                }
                ++$marked_dates[$timestamp];

                if (!($marked_dates_mark[$timestamp] ?? false)) {
                    $marked_dates_mark[$timestamp] = '';
                }

                if ($mark === 'master' || ($mark === 'tech' && $marked_dates_mark[$timestamp] !== 'master') || ($mark === 'player' && $marked_dates_mark[$timestamp] !== 'master' && $marked_dates_mark[$timestamp] !== 'tech')) {
                    $marked_dates_mark[$timestamp] = $mark;
                }

                $timestamp += 24 * 3600;
            }
            $RESPONSE_DATA .= '" month="' . date('n', $calendar_datestart) . '" region="' . $calendar_event_data['region'] . '" setting="' . str_replace(
                '-',
                '',
                (string) $calendar_event_data['gametype3'],
            ) . '" gametype2="' . str_replace('-', '', $calendar_event_data['gametype2']) . '">';

            $RESPONSE_DATA .= '<div class="name">';

            if ($calendar_event_data['site'] !== null) {
                $RESPONSE_DATA .= '<a class="link' . (preg_match('#vk.com#', DataHelper::escapeOutput($calendar_event_data['site'])) ? ' v' : '') .
                    '" href="' . DataHelper::fixURL(DataHelper::escapeOutput($calendar_event_data['site'])) . '" target="_blank"></a>';
            }
            $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendar_event_data['id'] . '/">' .
                DataHelper::escapeOutput($calendar_event_data['name']) . '</a></div>';

            $RESPONSE_DATA .= '<div class="people" title="' . $LOCALE['players'] . '">' . ($calendar_event_data['playernum'] > 0 ? $calendar_event_data['playernum'] : '?') . '</div>';

            $RESPONSE_DATA .= '<div class="place">' . ($regionsListRehashed[$calendar_event_data['region']] ?? '') . '</div>';

            $RESPONSE_DATA .= '<div class="dates">' . DateHelper::dateFromToEvent(
                $calendar_event_data['date_from'],
                $calendar_event_data['date_to'],
                $curYear,
            ) . ($calendar_event_data['moved'] === '1' ? '<span class="small">' . $LOCALE['small_moved'] . '</span>' : '') . ($calendar_event_data['wascancelled'] === '1' ? '<span class="small">' . $LOCALE['small_cancelled'] . '</span>' : '') . '</div>';

            $RESPONSE_DATA .= '<div class="show_hidden">' . $LOCALE['more'] . '</div><div class="hidden">';

            $RESPONSE_DATA .= '<div class="misc">';

            $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendar_event_data['id'] . '/#report" class="rating" title="' . $LOCALE_CALENDAR_EVENT['rating'] . '">' . $calendar_event_data['notion_rating'] . '</a><a href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendar_event_data['id'] . '/#report" class="notion" title="' . $LOCALE_CALENDAR_EVENT['notion'] . '">' . $calendar_event_data['notion_count'] . '</a>';

            $RESPONSE_DATA .= '</div>';

            $RESPONSE_DATA .= '<div class="mg">';

            if ($calendar_event_data['mg'] !== null) {
                $hisgroups = explode(',', $calendar_event_data['mg']);

                foreach ($hisgroups as $key => $hisgroup) {
                    $hisgroup = trim($hisgroup);
                    $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/gamemaster/' . str_replace('&', '-and-', $hisgroup) . '/">' . $hisgroup . '</a>';

                    if ($hisgroups[$key + 1] ?? false) {
                        $RESPONSE_DATA .= ', ';
                    }
                }
            } else {
                $RESPONSE_DATA .= '&nbsp;';
            }
            $RESPONSE_DATA .= '</div><div class="gametype2">' . str_replace('-', '', $typesListRehashed[$calendar_event_data['gametype2']] ?? '') . '</div>';

            $RESPONSE_DATA .= '<div class="misc2">';

            if ($calendar_event_data['date_from'] <= date('Y-m-d')) {
                $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendar_event_data['id'] . '/#report" class="report" title="' . $LOCALE_CALENDAR_EVENT['report'] . '">' . $calendar_event_data['report_count'] . '</a>';

                $RESPONSE_DATA .= '<a href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendar_event_data['id'] . '/#gallery" class="gallery" title="' . $LOCALE_CALENDAR_EVENT['gallery'] . '">' . $calendar_event_data['gallery_count'] . '</a>';
            }

            $RESPONSE_DATA .= '</div>';

            $RESPONSE_DATA .= '</div>';

            $RESPONSE_DATA .= '</div>';
            ++$stringnum;
        }
        $RESPONSE_DATA .= '</div>
<div class="calendar_tables_container' . ($calendarStyle ? ' shown' : '') . '">';

        $startMonth = (int) date('m', strtotime($minDate));
        $startYear = (int) date('Y', strtotime($minDate));
        $finishMonth = (int) date('m', strtotime($maxDate));
        $finishYear = (int) date('Y', strtotime($maxDate));

        while ($startYear <= $finishYear) {
            while (($startMonth <= $finishMonth && $startYear === $finishYear) || ($startMonth <= 12 && $startYear < $finishYear)) {
                $RESPONSE_DATA .= '<table class="calendar_table">
<thead>
<tr>
<th colspan=7>
' . DateHelper::monthname($startMonth, false, true) . '
</th>
</tr>
<tr>
<th>' . $LOCALE_FRAYM['days_of_week']['mo'] . '</th><th>' . $LOCALE_FRAYM['days_of_week']['tu'] . '</th><th>' . $LOCALE_FRAYM['days_of_week']['we'] . '</th><th>' . $LOCALE_FRAYM['days_of_week']['th'] . '</th><th>' . $LOCALE_FRAYM['days_of_week']['fr'] . '</th><th>' . $LOCALE_FRAYM['days_of_week']['sa'] . '</th><th>' . $LOCALE_FRAYM['days_of_week']['su'] . '</th>
</tr>
</thead>
<tbody>';
                $daysInMonth = $calendarService->getMonths()[$startMonth][1];

                if ($startYear % 4 === 0 && $startMonth === 2) {
                    $daysInMonth = 29;
                } elseif ($startMonth === 2) {
                    $daysInMonth = 28;
                }
                $j = 1;
                $firstDayOfMonth = date('N', strtotime($startYear . '-' . $startMonth . '-01'));
                $lastDayOfMonth = date('N', strtotime($startYear . '-' . $startMonth . '-' . $daysInMonth));

                if ($firstDayOfMonth > 1) {
                    $RESPONSE_DATA .= '<tr>';

                    for ($i = 1; $i < $firstDayOfMonth; ++$i) {
                        $RESPONSE_DATA .= '<td></td>';
                        ++$j;
                    }
                }

                for ($i = 1; $i <= $daysInMonth; ++$i) {
                    if ($j === 1) {
                        $RESPONSE_DATA .= '<tr>';
                    }

                    $RESPONSE_DATA .= '<td';

                    if (isset($marked_dates_mark[strtotime($startYear . '-' . $startMonth . '-' . $i)])) {
                        $RESPONSE_DATA .= ' class="' . $marked_dates_mark[strtotime($startYear . '-' . $startMonth . '-' . $i)] . '"';
                    }

                    if (($marked_dates[strtotime($startYear . '-' . $startMonth . '-' . ($i))] ?? 0) > 0) {
                        $RESPONSE_DATA .= ' rel-date="' . strtotime($startYear . '-' . $startMonth . '-' . $i) . '">' . $i . '<sup>' . $marked_dates[strtotime($startYear . '-' . $startMonth . '-' . $i, )] . '</sup>';
                    } else {
                        $RESPONSE_DATA .= '>' . $i;
                    }
                    $RESPONSE_DATA .= '</td>';

                    ++$j;

                    if ($j === 8) {
                        $j = 1;
                        $RESPONSE_DATA .= '</tr>';
                    }
                }

                if ($lastDayOfMonth < 7) {
                    for ($i = $j; $i <= $lastDayOfMonth; ++$i) {
                        $RESPONSE_DATA .= '<td></td>';
                    }
                    $RESPONSE_DATA .= '</tr>';
                }
                $RESPONSE_DATA .= '
</tbody>
</table>';
                ++$startMonth;
            }
            $startMonth = 1;
            ++$startYear;
        }

        $RESPONSE_DATA .= '
</div>
<div class="legend">
' . $LOCALE['legend'] . '
</div>

</div>
</div>
</div>

<div class="indexer">
<div id="filters_calendar" class="calendar">
<form>
<div class="filter filters_cancelled_calendarstyle">
<a id="change_calendarstyle" action_request="' . KIND . '/change_calendarstyle">' . ($calendarStyle ? $LOCALE['calendarstyle_0'] : $LOCALE['calendarstyle_1']) . '</a>

<div class="filter filter_cancelled_moved">
<div class="name">
<input type="checkbox" class="inputcheckbox" id="filter_cancelled_moved">
<label for="filter_cancelled_moved">' . $LOCALE['filter_cancelled_moved'] . '</label>
</div>
</div>

</div>

<div class="filter filter_month" ' . ($calendarStyle ? 'style="display: none"' : '') . '>
<div class="name">
' . $LOCALE['filter_month'] . '
</div>
<select id="filter_month">
<option value="all">' . $LOCALE['all'] . '</option>';

        foreach ($LOCALE_FRAYM['months_base'] as $key => $monthname) {
            $RESPONSE_DATA .= '<option value="' . $key . '">' . $monthname . '</option>';
        }
        $RESPONSE_DATA .= '
</select><br>' . ($year === (int) date('Y') ? '
<div class="fixed_selects">
<div class="fixed_select">' . $LOCALE['filter_month_fixed_selects']['closest'] . '</div>
</div>' : '') . '
</div>

<div class="filter filter_region" ' . ($calendarStyle ? 'style="display: none"' : '') . '>
<div class="name">
' . $LOCALE['filter_region'] . '
</div>
<select id="filter_region">
<option value="all">' . $LOCALE['all'] . '</option>';

        foreach ($regionsList as $region_data) {
            if (count($regionsIds) === 0 || in_array($region_data[0], $regionsIds)) {
                if ($region_data[0] === 2) {
                    $region_data[0] = '2,13';
                } elseif ($region_data[0] === 89) {
                    $region_data[0] = '89,119';
                }
                $RESPONSE_DATA .= '<option value="[' . $region_data[0] . ']">' . DataHelper::escapeOutput($region_data[1]) . '</option>';
            }
        }
        $RESPONSE_DATA .= '</select>
<div class="fixed_selects">';

        foreach ($LOCALE['filter_region_fixed_selects'] as $key => $region_name) {
            $RESPONSE_DATA .= '<div class="fixed_select" value="[' . $key . ']">' . $region_name . '</div>';
        }
        $RESPONSE_DATA .= '</div>
</div>

<div class="filter filter_gametype2" ' . ($calendarStyle ? 'style="display: none"' : '') . '>
<div class="name">
' . $LOCALE['filter_gametype2'] . '
</div>
<select id="filter_gametype2">
<option value="all">' . $LOCALE['all'] . '</option>';

        foreach ($typesList as $type_data) {
            $RESPONSE_DATA .= '<option value="' . $type_data[0] . '">' . DataHelper::escapeOutput($type_data[1]) . '</option>';
        }
        $RESPONSE_DATA .= '</select>
<div class="fixed_selects">';

        foreach ($LOCALE['filter_gametype2_fixed_selects'] as $key => $type_name) {
            $RESPONSE_DATA .= '<div class="fixed_select" value="' . $key . '">' . $type_name . '</div>';
        }
        $RESPONSE_DATA .= '</div>
</div>

<div class="filter filter_setting" ' . ($calendarStyle ? 'style="display: none"' : '') . '>
<div class="name">
' . $LOCALE['filter_setting'] . '
</div>
<select id="filter_setting">
<option value="all">' . $LOCALE['all'] . '</option>';

        foreach ($settingsList as $setting_data) {
            $RESPONSE_DATA .= '<option value="' . $setting_data[0] . '">' . DataHelper::escapeOutput($setting_data[1]) . '</option>';
        }
        $RESPONSE_DATA .= '</select>
</div>
<div class="filtersBlock"></div>
</form>
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
