<?php

declare(strict_types=1);

namespace App\CMSVC\Eventlist;

use App\Helper\{DateHelper, DesignHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Enum\EscapeModeEnum;
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Response;

/** @extends BaseView<EventlistService> */
#[Controller(EventlistController::class)]
class EventlistView extends BaseView
{
    public function Response(): ?Response
    {
        $eventListService = $this->service;

        $LOCALE = $this->LOCALE;
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_PROJECT = LocaleHelper::getLocale(['project', 'global']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
<h1 class="page_header">' . $LOCALE['events_calendar'] . '</h1>
<div class="eventlist">';

        [$date, $dateNext, $datePrev] = $eventListService->getDates();

        $RESPONSE_DATA .= '
<div class="eventlist_controls">
    <button class="nonimportant" id="prev" href="' . ABSOLUTE_PATH . '/eventlist/date=' . $datePrev . '">' . DateHelper::monthname(date('m', strtotime($datePrev)), false, true) . ' ' . date('Y', strtotime($datePrev)) . '</button>
    <h2>' . DateHelper::monthname(date('m', $date), false, true) . ' ' . date('Y', $date) . '</h2>
    <button class="nonimportant" id="next" href="' . ABSOLUTE_PATH . '/eventlist/date=' . $dateNext . '">' . DateHelper::monthname(date('m', strtotime($dateNext)), false, true) . ' ' . date('Y', strtotime($dateNext)) . '</button>
</div>';

        $RESPONSE_DATA .= '<div class="eventlist_table">';

        $content3 = '<table><tr class="eventlist_table_header"><td>' . $LOCALE['mo'] . '</td><td>' . $LOCALE['tu'] . '</td><td>' . $LOCALE['we'] . '</td><td>' . $LOCALE['th'] . '</td><td>' . $LOCALE['fr'] . '</td><td>' . $LOCALE['su'] . '</td><td>' . $LOCALE['sa'] . '</td></tr>';

        $firstDayOfWeek = date('N', $date);
        $lastDayOfMonth = date('t', $date);
        $lastDayOfPrevMonth = date('t', strtotime($datePrev));
        $drawMonth = true;
        $dayNumber = 2 - $firstDayOfWeek;

        $presentParents = [];

        while ($drawMonth) {
            $content3 .= '<tr>';

            for ($i = $dayNumber; $i < $dayNumber + 7; ++$i) {
                $curDate = date('Y-m-d', strtotime(date('Y-m-', $date) . $i));

                if ($i <= 0) {
                    $content3 .= '<td><span class="gray">' . ($lastDayOfPrevMonth + $i) . '</span></td>';
                } elseif ($i > $lastDayOfMonth) {
                    $content3 .= '<td><span class="gray">' . ($i - $lastDayOfMonth) . '</span></td>';
                    $drawMonth = false;
                } else {
                    $content3 .= '<td' . (date('Y-m-d') === $curDate ? ' class="today"' : '') . '>' . $i . '<br>';
                    $eventsData = $eventListService->getEventsData($curDate);

                    foreach ($eventsData as $eventData) {
                        $presentParents[DataHelper::clearBraces($eventData['parent_type'])][$eventData['parent_id']] = true;

                        $dateFrom = date('Y-m-d', strtotime($eventData['date_from']));
                        $dateTo = date('Y-m-d', strtotime($eventData['date_to']));
                        $class = 'time';

                        $content3 .= '<div class="eventlist_block" obj_type="' . DataHelper::clearBraces($eventData['parent_type']) . '" obj_id="' . $eventData['parent_id'] . '" title="';

                        if ($curDate !== $dateTo && ($eventData['date_from'] === '' || $curDate !== $dateFrom)) {
                            $content3 .= $LOCALE['during_day'];
                        } elseif ($curDate === $dateTo || ($eventData['date_from'] !== '' && $curDate === $dateFrom)) {
                            if ($eventData['date_from'] !== '') {
                                $content3 .= $LOCALE_FRAYM['datetime']['from'] . ' ' . date('d.m.Y H:i', strtotime($eventData['date_from']));

                                if ($eventData['date_to'] !== '') {
                                    $content3 .= ' ';
                                }
                            }

                            if ($eventData['date_to'] !== '') {
                                $content3 .= $LOCALE_FRAYM['datetime']['to'] . ' ' . date('d.m.Y H:i', strtotime($eventData['date_to']));
                            }
                            $class = DataHelper::clearBraces($eventData['type']);
                        }
                        $content3 .= '<br>' . DataHelper::escapeOutput($eventData['name'], EscapeModeEnum::forHTMLforceNewLines);
                        $content3 .= '"><div class="project_event_extended_' . $class . '"></div><div class="project_event_small_name">';

                        if ($curDate === $dateFrom) {
                            $content3 .= date('H:i', strtotime($eventData['date_from'])) . ' ';
                        } elseif ($curDate === $dateTo) {
                            $content3 .= date('H:i', strtotime($eventData['date_to'])) . ' ';
                        }
                        $content3 .= '<a href="' . ABSOLUTE_PATH . '/' . DataHelper::clearBraces($eventData['type']) . '/' . $eventData['id'] . '/">' . DataHelper::escapeOutput($eventData['name']) . '</a></div></div>';
                    }

                    $newsesData = $eventListService->getNewsData($curDate);

                    foreach ($newsesData as $newsData) {
                        $content3 .= '<div class="eventlist_block" obj_type="global" obj_id="0" title="' . DataHelper::escapeOutput($newsData['name']) . '"><div class="project_event_extended_news"></div><div class="project_event_small_name"><a href="' . ABSOLUTE_PATH . '/news/' . $newsData['id'] . '/" target="_blank">' . DataHelper::escapeOutput($newsData['name']) . '</a></div></div>';
                    }
                    $content3 .= '</td>';

                    if ($i + 1 > $lastDayOfMonth) {
                        $drawMonth = false;
                    }
                }
            }
            $dayNumber += 7;
            $content3 .= '</tr>';
        }

        $content3 .= '</table>';

        $RESPONSE_DATA .= '
<div class="eventlist_filter">' . $LOCALE['filter_tasks_and_events'] . '
<select name="eventlist_filter_obj">';

        foreach ($LOCALE['obj_filters'] as $key => $value) {
            $RESPONSE_DATA .= '<option obj_type="' . $key . '"' . ($key === 'all' ? ' selected' : '') . '>' . $value . '</option>';
        }

        $myProjects = $eventListService->getMyProjects();

        if ($myProjects !== []) {
            $myProjectsList = [];
            $projectsData = DB->select(
                tableName: 'project',
                criteria: [
                    'id' => $myProjects,
                ],
                order: [
                    'name',
                ],
                fieldsSet: [
                    'id',
                    'name',
                ],
            );

            foreach ($projectsData as $data) {
                $myProjectsList[$data['id']] = DataHelper::escapeOutput($data['name']);
            }

            $header = false;

            foreach ($myProjectsList as $key => $value) {
                if (!$header) {
                    $RESPONSE_DATA .= '<option disabled>' . $LOCALE['obj_filters_additional']['project'] . '</option>';
                    $header = true;
                }
                $RESPONSE_DATA .= '<option obj_id="' . $key . '" obj_type="project">&nbsp;&nbsp;' . $value . '</option>';
            }
        }

        $myCommunities = $eventListService->getMyCommunities();

        if ($myCommunities !== []) {
            $myCommunitiesList = [];
            $communitiesData = DB->select(
                tableName: 'community',
                criteria: [
                    'id' => $myCommunities,
                ],
                order: [
                    'name',
                ],
                fieldsSet: [
                    'id',
                    'name',
                ],
            );

            foreach ($communitiesData as $data) {
                $myCommunitiesList[$data['id']] = DataHelper::escapeOutput($data['name']);
            }

            $header = false;

            foreach ($myCommunitiesList as $key => $value) {
                if (!$header) {
                    $RESPONSE_DATA .= '<option disabled>' . $LOCALE['obj_filters_additional']['communities'] . '</option>';
                    $header = true;
                }
                $RESPONSE_DATA .= '<option obj_id="' . $key . '" obj_type="community">&nbsp;&nbsp;' . $value . '</option>';
            }
        }

        $RESPONSE_DATA .= '</select>
</div>' . $content3 . '
</div>';

        $RESPONSE_DATA .= '
</div>
';

        $RESPONSE_DATA .= '<div class="ical"><a href="' . ABSOLUTE_PATH . '/ical/' . $eventListService->generateIcalHash() . '/" target="_blank">' . $LOCALE_PROJECT['ical_link'] . '</a></div>';

        $RESPONSE_DATA .= '
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
