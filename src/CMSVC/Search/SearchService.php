<?php

declare(strict_types=1);

namespace App\CMSVC\Search;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Enum\{EscapeModeEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper};

#[Controller(SearchController::class)]
class SearchService extends BaseService
{
    public function checkIfSearch(): bool
    {
        return mb_strlen($_REQUEST['qwerty'] ?? '') > 2 || is_array($_REQUEST['tags_contain'] ?? false) || is_array($_REQUEST['tags_not_contain'] ?? false) || is_array($_REQUEST['region'] ?? false);
    }

    public function getRegionsList(): array
    {
        $list = [];
        $result = DB->select(
            tableName: 'geography',
            criteria: [
                ['parent', 0, [OperandEnum::NOT_EQUAL]],
                'content' => '{menu}',
            ],
            order: ['name'],
        );

        foreach ($result as $a) {
            $list[] = [$a['id'], $a['name']];
        }

        return $list;
    }

    public function getSearchResults(): array
    {
        $LOCALE = $this->getLOCALE()['messages'];
        $qwerty = $_REQUEST['qwerty'] ?? '';

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $searchResults = [];

        $tagsContain = '';
        $tagsNotContain = '';
        $region = '';
        $qwertySearch = "!=''";

        /* убираем id из $qwerty */
        if (!is_numeric($qwerty)) {
            $qwerty = preg_replace('# \([^)]+\)#', '', $qwerty);
        }

        if (mb_strlen($qwerty) > 2) {
            $qwertySearch = " LIKE '%" . $qwerty . "%'";
        }

        if ($_REQUEST['tags_contain'] ?? false) {
            $tagsContain = ' AND (';

            foreach ($_REQUEST['tags_contain'] as $key => $value) {
                $tagsContain .= 's.tags LIKE "%-' . $key . '-%" OR ';
            }
            $tagsContain = mb_substr($tagsContain, 0, mb_strlen($tagsContain) - 4);
            $tagsContain .= ')';
        }

        if ($_REQUEST['tags_not_contain'] ?? false) {
            $tagsNotContain = ' AND (';

            foreach ($_REQUEST['tags_not_contain'] as $key => $value) {
                $tagsNotContain .= 's.tags NOT LIKE "%-' . $key . '-%" AND ';
            }
            $tagsNotContain = mb_substr($tagsNotContain, 0, mb_strlen($tagsNotContain) - 5);
            $tagsNotContain .= ')';
        }

        if ($_REQUEST['region'] ?? false) {
            $region = ' AND s.city IN (';
            $regionsFound = false;

            foreach ($_REQUEST['region'] as $key => $value) {
                $result = DB->select(
                    tableName: 'geography',
                    criteria: [
                        'parent' => $key,
                    ],
                );

                foreach ($result as $a) {
                    $region .= $a['id'] . ',';
                    $regionsFound = true;
                }
            }

            if ($regionsFound) {
                $region = mb_substr($region, 0, mb_strlen($region) - 1);
            }
            $region .= ')';
        }

        $i = 0;

        if (CURRENT_USER->isLogged()) {
            $result = DB->query(
                'SELECT s.* FROM user AS s WHERE (s.fio' . $qwertySearch . " AND (s.hidesome NOT LIKE '%-10-%' OR s.hidesome IS NULL)) OR (s.nick" . $qwertySearch . " AND (s.hidesome NOT LIKE '%-0-%' OR s.hidesome IS NULL))" . (is_numeric($qwerty) ? ' OR s.sid=' . $qwerty : '') . $region . $tagsContain . $tagsNotContain,
                [],
            );

            foreach ($result as $a) {
                $searchResults[$i] = ['<b>' . $userService->showNameWithId($userService->arrayToModel($a), true) . '</b>'];

                /*$result3 = DB->query("SELECT s.* FROM publication AS s WHERE s.author LIKE '%-" . $a["id"] . "-%' OR s.creator_id=" . $a["id"] . $tagsContain . $tagsNotContain . " ORDER BY s.updated_at DESC", []);
                foreach ($result3 as $c) {
                    $author = '';

                    if ($c["author"] != '' && $c["author"] != '-') {
                        $author = ', автор(-ы): ';
                        $tryauthor = multiselectToArray(DataHelper::escapeOutput($c["author"]));
                        $tryauthor = array_filter($tryauthor);
                        if (count($tryauthor) > 1) {
                            $searchResults[$i][1][] = $LOCALE['publication_co_author'] . ' «<a href="' . ABSOLUTE_PATH . '/publication/' . $c["id"] . '/">' . DataHelper::escapeOutput($c["name"]) . '</a>»';
                        } else {
                            $searchResults[$i][1][] = $LOCALE['publication_author'] . ' «<a href="' . ABSOLUTE_PATH . '/publication/' . $c["id"] . '/">' . DataHelper::escapeOutput($c["name"]) . '</a>»';
                        }
                    }
                }*/

                ++$i;
            }
        }

        $result3 = DB->select(
            tableName: 'tag',
        );

        $result = DB->query(
            'SELECT s.* FROM publication AS s WHERE (s.content' . $qwertySearch . ' OR s.name' . $qwertySearch . ')' . $tagsContain . $tagsNotContain . ' ORDER BY s.name ASC',
            [],
        );

        foreach ($result as $a) {
            $author = '';

            if (DataHelper::escapeOutput($a['author']) !== '') {
                $author = '. Автор(-ы): ';
                $tryauthor = DataHelper::multiselectToArray(DataHelper::escapeOutput($a['author']));
                $tryauthor = array_filter($tryauthor);

                foreach ($tryauthor as $value) {
                    $checker = $value;

                    if (is_numeric($value)) {
                        $author .= $userService->showName($userService->get($value), true);
                    } else {
                        $author .= DataHelper::escapeOutput($checker);
                    }
                    $author .= ', ';
                }
            }
            $author = mb_substr($author, 0, mb_strlen($author) - 2);
            $author .= '.';

            $tags = '';

            foreach ($result3 as $c) {
                if (mb_stripos($a['tags'] ?? '', '-' . $c['id'] . '-') !== false) {
                    $tags .= DataHelper::escapeOutput($c['name']) . ', ';
                }
            }

            if ($tags !== '') {
                $tags = ' ' . $LOCALE['tags'] . ': ' . mb_substr($tags, 0, mb_strlen($tags) - 2) . '.';
            }

            if ($qwerty !== '' && mb_stripos($a['content'] ?? '', $qwerty) !== false) {
                $searchResults[] = $LOCALE['publication_text'] . ' «<a href="' . ABSOLUTE_PATH . '/publication/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $author . $tags . $this->showPhrase($a['content'], $qwerty);
            } else {
                $searchResults[] = $LOCALE['publication'] . ' «<a href="' . ABSOLUTE_PATH . '/publication/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $author . $tags;
            }
        }

        $result = DB->query(
            'SELECT s.* FROM project AS s WHERE (s.annotation' . $qwertySearch . ' OR s.name' . $qwertySearch . ')' . $tagsContain . $tagsNotContain . ' ORDER BY s.name ASC',
            [],
        );

        foreach ($result as $a) {
            $tags = '';

            foreach ($result3 as $c) {
                if (mb_stripos($a['tags'] ?? '', '-' . $c['id'] . '-') !== false) {
                    $tags .= DataHelper::escapeOutput($c['name']) . ', ';
                }
            }

            if ($tags !== '') {
                $tags = ' ' . $LOCALE['tags'] . ': ' . mb_substr($tags, 0, mb_strlen($tags) - 2) . '.';
            }

            if ($qwerty !== '' && mb_stripos($a['annotation'] ?? '', $qwerty) !== false) {
                $searchResults[] = $LOCALE['project_text'] . ' «<a href="' . ABSOLUTE_PATH . '/project/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $tags . $this->showPhrase($a['annotation'], $qwerty);
            } else {
                $searchResults[] = $LOCALE['project'] . ' «<a href="' . ABSOLUTE_PATH . '/project/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $tags;
            }
        }

        $result = DB->query(
            'SELECT s.* FROM calendar_event AS s WHERE (s.content' . $qwertySearch . ' OR s.name' . $qwertySearch . ') ORDER BY s.name',
            [],
        );

        foreach ($result as $a) {
            $searchResults[] = $LOCALE['calendar_event'] . ' «<a href="' . ABSOLUTE_PATH . '/calendar_event/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . ($a['content'] !== '' ? $this->showPhrase($a['content'], '') : '');

            if ($qwerty !== '' && mb_stripos($a['content'] ?? '', $qwerty) !== false) {
                $searchResults[] = $LOCALE['calendar_event_text'] . ' «<a href="' . ABSOLUTE_PATH . '/calendar_event/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $this->showPhrase($a['content'], $qwerty);
            }
        }

        $result = DB->query('SELECT s.* FROM area AS s WHERE (s.name' . $qwertySearch . ') ORDER BY s.name', []);

        foreach ($result as $a) {
            $searchResults[] = $LOCALE['area'] . ' «<a href="' . ABSOLUTE_PATH . '/area/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»';
        }

        $replaceStuffArray = [
            '&quot;',
            '"',
            "'",
            'МГ ',
            'МО ',
            '#',
            'ТГ ',
            'ТО ',
            'ТК ',
            'ТМ ',
        ];
        $gamemasterGroupsList = [];
        $result = DB->query('SELECT * FROM user WHERE ingroup' . $qwertySearch, []);

        foreach ($result as $userData) {
            $gamemasterGroups = explode(',', $userData['ingroup']);

            foreach ($gamemasterGroups as $gamemasterGroup) {
                $gamemasterGroup = trim(str_ireplace($replaceStuffArray, '', $gamemasterGroup));

                if (preg_match('#' . $qwerty . '#iu', $gamemasterGroup)) {
                    $gamemasterGroupsList[mb_strtolower($gamemasterGroup)] = $gamemasterGroup;
                }
            }
        }
        $result = DB->query('SELECT * FROM calendar_event WHERE mg' . $qwertySearch, []);

        foreach ($result as $calendarEventData) {
            $gamemasterGroups = explode(',', $calendarEventData['mg']);

            foreach ($gamemasterGroups as $gamemasterGroup) {
                $gamemasterGroup = trim(str_ireplace($replaceStuffArray, '', $gamemasterGroup));

                if (preg_match('#' . $qwerty . '#iu', $gamemasterGroup)) {
                    $gamemasterGroupsList[mb_strtolower($gamemasterGroup)] = $gamemasterGroup;
                }
            }
        }
        ksort($gamemasterGroupsList);

        foreach ($gamemasterGroupsList as $value) {
            $searchResults[] = $LOCALE['gamemaster'] . ' «<a href="' . ABSOLUTE_PATH . '/gamemaster/' . $value . '/"><b>' . $value . '</b></a>»';
        }
        unset($gamemasterGroupsList);

        $result = DB->query(
            'SELECT s.* FROM community AS s WHERE (s.description' . $qwertySearch . ' OR s.name' . $qwertySearch . ')' . $tagsContain . $tagsNotContain . ' ORDER BY s.name',
            [],
        );

        foreach ($result as $a) {
            $tags = '';

            foreach ($result3 as $c) {
                if (mb_stripos($a['tags'] ?? '', '-' . $c['id'] . '-') !== false) {
                    $tags .= DataHelper::escapeOutput($c['name']) . ', ';
                }
            }

            if ($tags !== '') {
                $tags = ' ' . $LOCALE['tags'] . ': ' . mb_substr($tags, 0, mb_strlen($tags) - 2) . '.';
            }

            if ($qwerty !== '' && mb_stripos($a['description'] ?? '', $qwerty) !== false) {
                $searchResults[] = $LOCALE['community_text'] . ' «<a href="' . ABSOLUTE_PATH . '/community/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $tags . $this->showPhrase($a['description'], $qwerty);
            } else {
                $searchResults[] = $LOCALE['community'] . ' «<a href="' . ABSOLUTE_PATH . '/community/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $tags;
            }
        }

        if (CURRENT_USER->isLogged()) {
            $result = DB->query(
                "SELECT s.* FROM task_and_event AS s LEFT JOIN relation AS r2 ON r2.obj_id_to=s.id AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=" . CURRENT_USER->id() . " WHERE r2.type='{member}' AND (s.description" . $qwertySearch . ' OR s.name' . $qwertySearch . ' OR s.result' . $qwertySearch . ') AND s.status IS NOT NULL' . $tagsContain . $tagsNotContain . ' ORDER BY s.name ASC',
                [],
            );

            foreach ($result as $a) {
                $tags = '';

                foreach ($result3 as $c) {
                    if (mb_stripos($a['tags'] ?? '', '-' . $c['id'] . '-') !== false) {
                        $tags .= DataHelper::escapeOutput($c['name']) . ', ';
                    }
                }

                if ($tags !== '') {
                    $tags = ' ' . $LOCALE['tags'] . ': ' . mb_substr($tags, 0, mb_strlen($tags) - 2) . '.';
                }

                if ($qwerty !== '' && mb_stripos($a['description'] ?? '', $qwerty) !== false) {
                    $searchResults[] = $LOCALE['task_text'] . ' «<a href="' . ABSOLUTE_PATH . '/task/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $tags . $this->showPhrase($a['description'], $qwerty);
                } elseif ($qwerty !== '' && mb_stripos($a['result'] ?? '', $qwerty) !== false) {
                    $searchResults[] = $LOCALE['task_text'] . ' «<a href="' . ABSOLUTE_PATH . '/task/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $tags . $this->showPhrase($a['result'], $qwerty);
                } else {
                    $searchResults[] = $LOCALE['task'] . ' «<a href="' . ABSOLUTE_PATH . '/task/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $tags;
                }
            }

            $result = DB->query(
                "SELECT s.* FROM task_and_event AS s LEFT JOIN relation AS r2 ON r2.obj_id_to=s.id AND r2.obj_type_from='{user}' AND r2.obj_type_to='{event}' AND r2.obj_id_from=" . CURRENT_USER->id() . " WHERE r2.type='{member}' AND (s.description" . $qwertySearch . ' OR s.name' . $qwertySearch . ') AND s.status IS NULL' . $tagsContain . $tagsNotContain . ' ORDER BY s.name ASC',
                [],
            );

            foreach ($result as $a) {
                $tags = '';

                foreach ($result3 as $c) {
                    if (mb_stripos($a['tags'] ?? '', '-' . $c['id'] . '-') !== false) {
                        $tags .= DataHelper::escapeOutput($c['name']) . ', ';
                    }
                }

                if ($tags !== '') {
                    $tags = ' ' . $LOCALE['tags'] . ': ' . mb_substr($tags, 0, mb_strlen($tags) - 2) . '.';
                }

                if ($qwerty !== '' && mb_stripos($a['description'] ?? '', $qwerty) !== false) {
                    $searchResults[] = $LOCALE['event_text'] . ' «<a href="' . ABSOLUTE_PATH . '/event/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $tags . $this->showPhrase($a['description'], $qwerty);
                } else {
                    $searchResults[] = $LOCALE['event'] . ' «<a href="' . ABSOLUTE_PATH . '/event/' . $a['id'] . '/"><b>' . DataHelper::escapeOutput($a['name']) . '</b></a>»' . $tags;
                }
            }
        }

        return $searchResults;
    }

    public function showPhrase($content, $phrase)
    {
        $content = strip_tags(DataHelper::escapeOutput($content ?? '', EscapeModeEnum::plainHTML));

        if ($content && $phrase) {
            $phrasepos = mb_stripos($content, $phrase);

            if ($phrasepos > 10) {
                $content = '&#8230;' . mb_substr($content, $phrasepos - 10);
            }

            if (mb_strlen($content) - 150 > 0) {
                $content = mb_substr($content, 0, 150) . '&#8230;';
            }
            $content = mb_substr($content, 0, mb_stripos($content, $phrase)) . '<b>' . mb_substr(
                $content,
                mb_stripos($content, $phrase),
                mb_strlen($phrase),
            ) . '</b>' . mb_substr($content, mb_stripos($content, $phrase) + mb_strlen($phrase));
            $content = '<div>' . $content . '</div>';
        }

        return $content;
    }
}
