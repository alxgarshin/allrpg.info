<?php

declare(strict_types=1);

namespace App\CMSVC\Tasklist;

use App\Helper\{DesignHelper, RightsHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Helper\LocaleHelper;
use Fraym\Interface\Response;

#[Controller(TasklistController::class)]
class TasklistView extends BaseView
{
    public function Response(): ?Response
    {
        $LOCALE = LocaleHelper::getLocale(['project', 'global']);
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<a class="outer_add_something_button" href="' . ABSOLUTE_PATH . '/task/task/act=add&obj_type=project&obj_id=all"><span class="sbi sbi-add-something"></span><span class="outer_add_something_button_text">' . $LOCALE['add_task'] . '</span></a>
<h1 class="page_header"><a href="/' . KIND . '/">' . $LOCALE['title'] . '</a></h1>
<div class="page_blocks">
<div class="page_block">
';
        $my_groups = [];
        $hasSomething = false;

        $my_projects = RightsHelper::findByRights(null, '{project}');

        if ($my_projects) {
            foreach ($my_projects as $value) {
                $pro = DB->findObjectById($value, 'project');

                if ($pro && strtotime((string) $pro['date_to']) >= strtotime('today')) {
                    $my_groups['project'][$pro['id']] = $pro;
                    $hasSomething = true;
                }
            }
        }

        $my_communities = RightsHelper::findByRights(['{admin}', '{gamemaster}', '{moderator}'], '{community}');

        if ($my_communities) {
            foreach ($my_communities as $value) {
                $com = DB->findObjectById($value, 'community');

                if ($com) {
                    $my_groups['community'][$com['id']] = $com;
                    $hasSomething = true;
                }
            }
        }

        if (!$hasSomething) {
            $RESPONSE_DATA .= $LOCALE['no_projects_or_communities'] . '<br><br>';
        } else {
            $RESPONSE_DATA .= '
<div class="fraymtabs">
	<ul>
		<li><a id="task_mine">' . $LOCALE['tasks_mine'] . '<sup id="new_tasks_counter_mine"></sup></a></li>
		<li><a id="task_membered">' . $LOCALE['tasks_membered'] . '<sup id="new_tasks_counter_membered"></sup></a></li>
		<li><a id="task_notmembered">' . $LOCALE['tasks_notmembered'] . '</a></li>
		<li><a id="task_delayed">' . $LOCALE['tasks_delayed'] . '<sup id="new_tasks_counter_delayed"></sup></a></li>
		<li><a id="task_closed">' . $LOCALE['tasks_closed'] . '<sup id="new_tasks_counter_closed"></sup></a></li>
	</ul>
    <div id="fraymtabs-task_mine"><a class="load_tasks_list" obj_type="mine" obj_id="all">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
    <div id="fraymtabs-task_membered"><a class="load_tasks_list" obj_type="membered" obj_id="all">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
    <div id="fraymtabs-task_notmembered"><a class="load_tasks_list" obj_type="notmembered" obj_id="all">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
    <div id="fraymtabs-task_delayed"><a class="load_tasks_list" obj_type="delayed" obj_id="all">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
    <div id="fraymtabs-task_closed"><a class="load_tasks_list" obj_type="closed" obj_id="all">' . $LOCALE_GLOBAL['show_next'] . '</a></div>
</div>';
        }

        if (count($my_groups) > 0) {
            // генерим адрес ical пользователя
            $hash = md5(CURRENT_USER->id() . '.ical.' . ABSOLUTE_PATH) . CURRENT_USER->id();
            $RESPONSE_DATA .= '<div class="ical"><a href="' . ABSOLUTE_PATH . '/ical/' . $hash . '/" target="_blank">' . $LOCALE['ical_link'] . '</a></div>';
        }

        $RESPONSE_DATA .= '
</div>
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
