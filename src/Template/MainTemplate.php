<?php

declare(strict_types=1);

namespace App\Template;

use App\CMSVC\Task\TaskService;
use App\CMSVC\User\UserService;
use App\Helper\{DesignHelper, RightsHelper};
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Template;

final class MainTemplate implements Template
{
    public static function asHTML(): string
    {
        $LOCALE = LocaleHelper::getLocale(['global']);

        $userService = null;

        if (CURRENT_USER->isLogged()) {
            /** @var UserService $userService */
            $userService = CMSVCHelper::getService('user');
        }

        /** Список локалей */
        $LOCALES_LIST = LocaleHelper::getLocalesList();

        $menuDataLists = [
            'project_application' => [],
        ];

        $anyMenuHtml = '';
        $anyMenuHtmlBlock = 1;

        $communities = [];

        if (CURRENT_USER->isLogged()) {
            $communities = RightsHelper::findByRights('{member}', '{community}');
        }

        if (KIND === 'community') {
            $communities[] = DataHelper::getId();
            $communities = array_unique($communities);
        }

        $communitiesData = [];
        $communitiesDataSort2 = [];

        if ($communities) {
            $communitiesData = DB->select('community', ['id' => $communities]);

            if ($communitiesData) {
                foreach ($communitiesData as $key => $communityData) {
                    if ($communityData['id'] !== '') {
                        $communitiesDataSort2[$key] = DataHelper::escapeOutput($communityData['name']);
                    } else {
                        unset($communitiesData[$key]);
                    }
                }
            }

            if (count($communitiesData) > 0) {
                array_multisort($communitiesDataSort2, SORT_ASC, $communitiesData);
            }
        }
        $menuDataLists['community'] = $communitiesData;
        unset($communitiesData);
        unset($communitiesDataSort2);
        unset($communities);

        $projects = [];

        if (CURRENT_USER->isLogged()) {
            $projects = RightsHelper::findByRights('{member}', '{project}');
        }

        if (KIND === 'roles' || KIND === 'project') {
            $projects[] = DataHelper::getId();
            $projects = array_unique($projects);
        }

        $projectsData = [];
        $projectsDataSort2 = [];

        if ($projects) {
            $projectsData = DB->select('project', ['id' => $projects]);

            if ($projectsData) {
                foreach ($projectsData as $key => $projectData) {
                    if (strtotime((string) $projectData['date_to']) >= strtotime('today') || $projectData['id'] === DataHelper::getId()) {
                        $projectsDataSort2[$key] = strtotime((string) $projectData['date_to']);
                    } else {
                        unset($projectsData[$key]);
                    }
                }
            }

            if (count($projectsData) > 0) {
                array_multisort($projectsDataSort2, SORT_DESC, $projectsData);
            }
        }
        $menuDataLists['project'] = $projectsData;
        unset($projectsData);
        unset($projectsDataSort2);
        unset($projects);

        $alreadyFoundProject = [];
        $moreThanOneApplicationOnAProject = [];

        if (CURRENT_USER->isLogged()) {
            $projectId = RightsHelper::getActivatedProjectId();

            if ($projectId) {
                $projectData = DB->select('project', ['id' => $projectId], true);

                if ($projectData) {
                    $anyMenuHtml .= '<script>
	window["projectControlId"]=' . (int) $projectId . ';
    window["projectControlItems"]="' . (PROJECT_RIGHTS && (in_array('{admin}', PROJECT_RIGHTS) || in_array('{gamemaster}', PROJECT_RIGHTS)) ? 'show' : 'hide') . '";
	window["projectControlItemsName"]="' . str_replace('"', '\"', DataHelper::escapeOutput($projectData['name']) ?? '') . '";
	window["projectControlItemsRights"]="' . (PROJECT_RIGHTS ? implode(' ', PROJECT_RIGHTS) : '') . '";
</script>';
                }
            }

            $applicationsData = DB->query(
                "SELECT pa.creator_id, pa.id, pa.sorter, p.name, p.id as project_id FROM project_application pa LEFT JOIN project p ON p.id=pa.project_id WHERE pa.creator_id=:creator_id AND p.date_to >= :date_to AND pa.deleted_by_player='0' ORDER BY p.name, pa.sorter",
                [
                    ['creator_id', CURRENT_USER->id()],
                    ['date_to', date('Y-m-d')],
                ],
            );

            foreach ($applicationsData as $applicationData) {
                $menuDataLists['project_application'][] = $applicationData;

                if (in_array($applicationData['project_id'], $alreadyFoundProject)) {
                    $moreThanOneApplicationOnAProject[] = $applicationData['project_id'];
                }
                $alreadyFoundProject[] = $applicationData['project_id'];
            }
            unset($alreadyFoundProject);

            $anyMenuHtml .= '
			<div id="project_control_items">';

            foreach ($LOCALE['project_control_items'] as $key => $data) {
                if (str_contains($key, 'tab')) {
                    if ($key !== 'tab1') {
                        $anyMenuHtml .= '
                </div>';
                    }
                    $anyMenuHtml .= '
				<div class="menutab level2" rights="' . $data[1] . '"><div class="menutab_name">' . $data[0] . '</div>';
                } else {
                    $anyMenuHtml .= '
				<a class="submenu level2' . (str_starts_with($key, 'roles') ? ' roles_list' : '') .
                        (
                            $key === KIND
                            || (
                                KIND === 'roles'
                                && str_starts_with($key, 'roles')
                                && DataHelper::getId() === $projectId
                            ) ? ' selected' : ''
                        ) .
                        '" rights="' . $data[1] . '" href="' . ABSOLUTE_PATH . '/' .
                        (str_starts_with($key, 'roles') ? str_replace('{id}', (string) $projectId, $key) : $key) .
                        '/">' . $data[0] . '</a>';
                }
            }
            $anyMenuHtml .= '
                </div>
			</div>';
        }

        foreach ($LOCALE['main_menu'] as $mainMenuData) {
            if (DesignHelper::checkMenuItemVisibility($mainMenuData)) {
                if ($mainMenuData['class'] !== 'hr' && !isset($mainMenuData['link'])) {
                    $mainMenuData['add'] = DesignHelper::replaceVarsInMenu($mainMenuData['add'] ?? '');
                    $mainMenuData['edit'] = DesignHelper::replaceVarsInMenu($mainMenuData['edit'] ?? '');

                    $anyMenuHtml .= '
	<div class="menu_item_wrapper ' . $mainMenuData['class'] . '">';

                    if (isset($mainMenuData['counter'])) {
                        $anyMenuHtml .= '<span id="' . $mainMenuData['counter'] . '" class="menu_float_right">0</span>';
                    }

                    $keyInKinds = array_search(KIND, $mainMenuData['kind']);

                    $anyMenuHtml .= '
		<a class="menu ' . $mainMenuData['class'] .
                        ($keyInKinds !== false && (!isset($mainMenuData['id'][$keyInKinds]) || $mainMenuData['id'][$keyInKinds] === DataHelper::getId()) && (!isset($mainMenuData['not_id'][$keyInKinds]) || $mainMenuData['not_id'][$keyInKinds] !== DataHelper::getId()) ? ' menu_selected' : '') .
                        '" href="' . ABSOLUTE_PATH . '/' . $mainMenuData['kind'][0] . '/' .
                        (isset($mainMenuData['id'][0]) ? $mainMenuData['id'][0] . '/' : '') .
                        '"><span class="menu_name">' . $mainMenuData['name'] . '</span></a>';

                    /* создаем субменю */
                    if (isset($mainMenuData['subitems'])) {
                        $anyMenuHtmlBlockSubmenuItem = 1;

                        $anyMenuHtml .= '
        <div class="submenu submenu_' . $anyMenuHtmlBlock . '">';

                        foreach ($mainMenuData['subitems'] as $mainMenuSubitemData) {
                            if (DesignHelper::checkMenuItemVisibility($mainMenuSubitemData)) {
                                if (isset($mainMenuSubitemData['list']) && count(
                                    $menuDataLists[$mainMenuSubitemData['list']],
                                ) > 0) {
                                    foreach ($menuDataLists[$mainMenuSubitemData['list']] as $myObjectData) {
                                        if (is_array($myObjectData) && $myObjectData['id'] > 0) {
                                            if (isset($mainMenuSubitemData['people'])) {
                                                $anyMenuHtml .= '<a class="people" href="' . ABSOLUTE_PATH . '/' . str_replace(
                                                    '{project_id}',
                                                    (string) $myObjectData['project_id'],
                                                    $mainMenuSubitemData['people'],
                                                ) . '"></a>';
                                            }

                                            if ($mainMenuSubitemData['edit'] ?? false) {
                                                $isAdmin = RightsHelper::checkRights('{admin}', $mainMenuSubitemData['list'], $myObjectData['id'])
                                                    || $myObjectData['creator_id'] === CURRENT_USER->id();

                                                if ($isAdmin) {
                                                    $anyMenuHtml .= '<a class="edit" href="' . ABSOLUTE_PATH . '/' . str_replace(
                                                        '{id}',
                                                        (string) $myObjectData['id'],
                                                        $mainMenuSubitemData['edit'],
                                                    ) . '" obj_id="' . $myObjectData['id'] . '"></a>';
                                                }
                                            }
                                            $anyMenuHtml .= '<a class="submenu submenu_' . $anyMenuHtmlBlock . '_item_' . $anyMenuHtmlBlockSubmenuItem .
                                                ((KIND === ($mainMenuSubitemData['list'] === 'project_application' ? 'myapplication' : $mainMenuSubitemData['list']) || (KIND === 'roles' && $mainMenuSubitemData['list'] === 'project')) && DataHelper::getId() === $myObjectData['id'] ? ' selected' : '') .
                                                '" href="' . ABSOLUTE_PATH . '/' . ($mainMenuSubitemData['list'] === 'project_application' ? 'myapplication' : $mainMenuSubitemData['list']) . '/' . $myObjectData['id'] . '/" obj_id="' . $myObjectData['id'] . '">' . (isset($myObjectData['sorter']) && ($myObjectData['project_id'] ?? false) && in_array($myObjectData['project_id'], $moreThanOneApplicationOnAProject) ? DataHelper::escapeOutput($myObjectData['name']) . ' (' . DataHelper::escapeOutput($myObjectData['sorter']) . ')' : DataHelper::escapeOutput($myObjectData['name'])) . '</a>';
                                        }
                                    }
                                } elseif (!isset($mainMenuSubitemData['list'])) {
                                    if ($mainMenuSubitemData['add'] ?? false) {
                                        $anyMenuHtml .= '<a class="add" href="' . ABSOLUTE_PATH . '/' . DesignHelper::replaceVarsInMenu($mainMenuSubitemData['add']) . '"></a>';
                                    }

                                    if ($mainMenuSubitemData['edit'] ?? false) {
                                        $anyMenuHtml .= '<a class="edit" href="' . ABSOLUTE_PATH . '/' . DesignHelper::replaceVarsInMenu($mainMenuSubitemData['edit']) . '"></a>';
                                    }
                                    $keyInKinds = array_search(KIND, $mainMenuSubitemData['kind']);

                                    $parseParams = [];

                                    if (isset($mainMenuSubitemData['params'][$keyInKinds])) {
                                        $parseParams = explode('=', $mainMenuSubitemData['params'][$keyInKinds]);
                                    }

                                    $parseNotParams = [];

                                    if (isset($mainMenuSubitemData['not_params'][$keyInKinds])) {
                                        $parseNotParams = explode('=', $mainMenuSubitemData['not_params'][$keyInKinds]);
                                    }

                                    $anyMenuHtml .= '<a class="submenu submenu_' . $anyMenuHtmlBlock . '_item_' . $anyMenuHtmlBlockSubmenuItem .
                                        ($keyInKinds !== false && (!isset($mainMenuSubitemData['id'][$keyInKinds]) || $mainMenuSubitemData['id'][$keyInKinds] === DataHelper::getId()) && (!isset($mainMenuSubitemData['not_id'][$keyInKinds]) || (DataHelper::getId() > 0 && $mainMenuSubitemData['not_id'][$keyInKinds] !== DataHelper::getId() && $mainMenuSubitemData['not_id'][$keyInKinds] !== 'any') || (DataHelper::getId() === 0 && $mainMenuSubitemData['not_id'][$keyInKinds] === 'any')) && (!isset($mainMenuSubitemData['params'][$keyInKinds]) || ($_REQUEST[$parseParams[0]] ?? false) === $parseParams[1]) && (!isset($mainMenuSubitemData['not_params'][$keyInKinds]) || ($_REQUEST[$parseNotParams[0]] ?? false) !== $parseNotParams[1]) ? ' selected' : '') .
                                        '" href="' . ABSOLUTE_PATH . '/' . $mainMenuSubitemData['kind'][0] . '/' .
                                        (isset($mainMenuSubitemData['id'][0]) ? $mainMenuSubitemData['id'][0] . '/' : '') .
                                        ($mainMenuSubitemData['params'][0] ?? '') .
                                        '">' . $mainMenuSubitemData['name'] . '</a>';
                                }
                                ++$anyMenuHtmlBlockSubmenuItem;
                            }
                        }
                        $anyMenuHtml .= '
        </div>';
                    }

                    $anyMenuHtml .= '
	</div>';
                } elseif ($mainMenuData['class'] === 'hr') {
                    $anyMenuHtml .= '
	<div class="menu_item_wrapper hr">
		<hr>
	</div>';
                } elseif (isset($mainMenuData['link'])) {
                    $anyMenuHtml .= '
	<div class="menu_item_wrapper ' . $mainMenuData['class'] . '">
		<a class="menu ' . $mainMenuData['class'] . '" href="' . $mainMenuData['link'] . '"><span class="menu_name">' . $mainMenuData['name'] . '</span></a>
	</div>';
                }

                ++$anyMenuHtmlBlock;
            }
        }

        $LOCALE_PWA = LocaleHelper::getLocale(['global', 'pwa']);

        $menuHtml = '<div class="mobile_menu">
<div class="mobile_menu_wrapper">

<a href="' . ABSOLUTE_PATH . '/start/" class="logo no_dynamic_content"></a>

' . $anyMenuHtml . '

<div class="PWAinfo">
    <div class="android">
        <div class="PWAdescription">' . $LOCALE_PWA['description'] . '</div>
        <div class="installPWA">' . $LOCALE_PWA['android']['install'] . '</div>
        <div class="PWAsuccess">' . $LOCALE_PWA['success_btn'] . '</div>
    </div>
    <div class="ios">
        <div class="PWAdescription">' . $LOCALE_PWA['description'] . '</div>
        <div class="installPWA">' . $LOCALE_PWA['ios']['install'] . '</div>
        <div class="PWAdescription">' . $LOCALE_PWA['already_installed'] . '</div>
    </div>
    <div class="undefined">
        <div class="PWAdescription">' . $LOCALE_PWA['description'] . '</div>
        <div class="installPWA">' . $LOCALE_PWA['undefined']['help'] . '</div>
        <div class="PWAdescription">' . $LOCALE_PWA['already_installed'] . '</div>
    </div>
</div>

</div>
</div>';

        $contactsOnline = '';
        $myTasks = '';

        if (CURRENT_USER->isLogged()) {
            $contactsOnline = $userService->getContactsOnline();
            /** @var TaskService $taskService */
            $taskService = CMSVCHelper::getService('task');
            $myTasks = $taskService->getTasksData('mine', true);
        }

        $LOCALE_NAME = CookieHelper::getCookie('locale');

        $RESPONSE_TEMPLATE = '<!doctype html>
<html prefix="og: https://ogp.me/ns#" lang="ru">
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="Description" Content="' . $LOCALE['meta_description'] . '">
<meta name="Keywords" Content="' . $LOCALE['meta_keywords'] . '">

<meta property="og:title" content="<!--pagetitle-->" />
<meta property="og:description" content="' . $LOCALE['meta_description'] . '" />
<meta property="og:type" content="website" />
<meta property="og:url" content="https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '" />
<meta property="og:site" content="' . $LOCALE['sitename'] . '" />
<meta property="vk:image" content="' . ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'social_network_logo.png" />
<meta property="og:image" content="' . ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'social_network_logo_box.png" />

<meta name="twitter:card" content="summary_large_image" />
<meta property="twitter:site" content="' . $LOCALE['sitename'] . '" />
<meta property="twitter:title" content="<!--pagetitle-->" />
<meta property="twitter:image" content="' . ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'social_network_logo.png" />
<meta property="twitter:url" content="https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '" />

<title><!--pagetitle--></title>

<link href="/vendor/fraym/locale/' . $LOCALE_NAME . '/locale.json" class="localeUrl" data-locale="' . $LOCALE_NAME . '" crossOrigin>
<link href="/locale/' . $LOCALE_NAME . '/locale.json" class="localeUrl" data-locale="' . $LOCALE_NAME . '" crossOrigin>
<script type="text/javascript" src="/vendor/fraym/js/global.min.js"></script>
<script type="text/javascript" src="/js/global.min.js"></script>

<link rel="stylesheet" type="text/css" href="/vendor/fraym/css/global.min.css">
<link rel="stylesheet" type="text/css" href="/css/global.min.css">
<link rel="stylesheet" type="text/css" href="/vendor/fraym/cmsvc/' . KIND . '.css">

<meta name="theme-color" id="theme-color" content="#ffffff">
<link rel="icon" href="/favicons/favicon.svg">
<link rel="mask-icon" href="/favicons/apple-mask-icon.svg" color="#55739C">
<link rel="apple-touch-icon" href="/favicons/apple-touch-icon-180x180.png">
<link rel="manifest" href="/favicons/manifest-' . mb_strtolower($LOCALE_NAME) . '.json">
<script>
    const themeColor = document.getElementById("theme-color");
    function switchthemeColor(usesDarkMode) {
        themeColor.content = usesDarkMode ? "#1d2632" : "#ffffff";
    }
    window.matchMedia("(prefers-color-scheme: dark)").addEventListener( "change", (e) => switchthemeColor(e.matches));
    switchthemeColor(window.matchMedia("(prefers-color-scheme: dark)").matches || false);
</script>
<script type="text/javascript" src="/vendor/pwacompat/pwacompat.min.js"></script>

</head>

<body class="allrpg">
' . (CURRENT_USER->isLogged() ? '
<div class="tasks_widget_list"><div class="tasks_widget_list_container"></div></div>
<div class="tasks_widget_container"><div class="tasks_widget"><span class="value">' . $myTasks . '</span><span class="sbi sbi-task-widget"></span></div></div>
<div class="conversations_widget_list"><div class="conversations_widget_list_container"></div></div>
<div class="conversations_widget_container"><div class="conversations_widget"><span class="value">' . $contactsOnline . '</span><span class="sbi sbi-conversations-widget"></span></div></div>' : '') . '<audio id="new_message_alert" src="' . ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'alert.mp3" preload="auto"></audio>' . '
<div class="fullpage">
    ' . $menuHtml . '
    
    <div class="fullpage_wrapper">
        <div class="header">
            <div class="header_left">
                <div class="mobile_menu_button">
                    <div class="mobile_menu_button_lines_wrapper">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
                <div class="qwerty_space">
                    <form action="/search/" method="POST" enctype="multipart/form-data" autocomplete="off">
                        <a class="search_image sbi sbi-search"></a><input class="search_input" name="qwerty" type="text" value="' .
            ($_REQUEST['qwerty'] ?? '') . '" placehold="' . $LOCALE['search'] . '" autocomplete="off">
                    </form>
                </div>
            </div>
            
            <div class="header_middle">
                <a href="/start/" class="logo no_dynamic_content"></a>
            </div>
            
            <div class="header_right">';

        if (in_array($LOCALE_NAME, $LOCALES_LIST)) {
            $RESPONSE_TEMPLATE .= '<div class="locale_switcher"><div class="locale_switcher_list">';

            foreach ($LOCALES_LIST as $value) {
                if ($value !== $LOCALE_NAME) {
                    $RESPONSE_TEMPLATE .= '<a href="/locale=' . $value . '" class="no_dynamic_content">' . $value . '</a>';
                }
            }
            $RESPONSE_TEMPLATE .= '</div>' . $LOCALE_NAME . '</div>';
        }

        $RESPONSE_TEMPLATE .= '
                <!--login-->
            </div>
        </div>
        
        <div class="content">
            <div class="maincontent">
                <div class="maincontent_wrapper">
                    <!--maincontent-->
                </div>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer_left">
                <div id="cetb_logo"><a href="https://www.cetb.ru/" target="_blank"><img src="' . ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'cetb_logo.svg" width="100%" height="100%"></a></div>
                <span id="allrpg_name">' . $LOCALE['bottom_sitename'] . ' </span>&copy; 2006-' . date('Y') . '
            </div>
            
            <div class="footer_middle">
                <a href="https://www.facebook.com/groups/allrpginfo/" target="_blank" class="social_network f"></a><a href="https://vk.ru/allrpginfo" target="_blank" class="social_network v"></a><a href="https://t.me/allrpginfo" target="_blank" class="social_network t"></a>
            </div>
            
            <div class="footer_right">
                ' . ($LOCALE['global_links']['your_questions_and_issues'] !== '' ? '<a href="' . ABSOLUTE_PATH . '/' . $LOCALE['global_links']['your_questions_and_issues'] . '">' . $LOCALE['your_questions_and_issues'] . '</a>' : '') . '
                ' . ($LOCALE['global_links']['about'] !== '' ? '<a href="' . ABSOLUTE_PATH . '/' . $LOCALE['global_links']['about'] . '">' . $LOCALE['about'] . '</a>' : '') . '
                ' . ($LOCALE['global_links']['contacts'] !== '' ? '<a href="' . ABSOLUTE_PATH . '/' . $LOCALE['global_links']['contacts'] . '">' . $LOCALE['contacts'] . '</a>' : '') . '
                <a href="' . ABSOLUTE_PATH . '/mobile/" class="hidden">' . $LOCALE['mobile'] . '</a>
            </div>
        </div>
    </div>
</div>

<div class="fullpage_cover">
	<div id="circleG">
		<div id="circleG_1" class="circleG"></div>
		<div id="circleG_2" class="circleG"></div>
		<div id="circleG_3" class="circleG"></div>
	</div>
	<div id="skeletons">
	    <div id="skeleton_left"></div>
	    <div id="skeleton_main">
	        <div id="skeleton_1" class="shown">
	            <div></div>
	            <div></div>
	            <div></div>
	            <div></div>
	            <div></div>
	            <div></div>
	            <div></div>
	            <div></div>
	            <div></div>
            </div>
            <div id="skeleton_2">
	            <div class="skeleton_heading"></div>
	            <div class="skeleton_table"></div>
	            <div class="skeleton_table2"></div>
            </div>
            <div id="skeleton_3">
                <div class="skeleton_avatar"></div>
	            <div class="skeleton_table"></div>
	            <div class="skeleton_table2"></div>
            </div>
        </div>
    </div>
	<div id="offlineMessage">' . $LOCALE['offlineMessage'] . '</div>
</div>

<script>
    const justAnotherVar = "' . $_ENV['ANTIBOT_CODE'] . '";
</script>

<!--google_analytics-->
<!--messages-->
</body>
</html>';

        return $RESPONSE_TEMPLATE;
    }
}
