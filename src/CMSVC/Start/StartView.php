<?php

declare(strict_types=1);

namespace App\CMSVC\Start;

use App\CMSVC\User\UserService;
use App\Helper\{DateHelper, DesignHelper, FileHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Enum\EscapeModeEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper, RightsHelper};
use Fraym\Interface\Response;
use WideImage;

#[Controller(StartController::class)]
class StartView extends BaseView
{
    public function Response(): ?Response
    {
        $LOCALE = $this->getLOCALE();

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $PAGETITLE = null;

        $RESPONSE_DATA = '
<div class="maincontent_data kind_' . KIND . '">
' . (CURRENT_USER->isAdmin() ? '<a class="edit_button" href="' . ABSOLUTE_PATH . '/banners_edit/">' . $LOCALE['edit_banners'] . '</a>' : '') . '
<div class="mainpage_blocks">';

        $RESPONSE_DATA .= '
    <div class="mainpage_block" id="applications">
        <div class="mainpage_block_header special application">
            <div class="icon"></div>
            <a class="name" href="' . ABSOLUTE_PATH . '/myapplication/">' . TextHelper::mb_ucfirst($LOCALE[(CURRENT_USER->isLogged() ? 'my_' : '') . 'applications']) . '</a>
            <a class="command small" href="' . ABSOLUTE_PATH . '/myapplication/">' . TextHelper::mb_ucfirst($LOCALE['all']) . '</a>
            <a class="command" href="' . ABSOLUTE_PATH . '/myapplication/act=add">' . TextHelper::mb_ucfirst($LOCALE['add_application']) . '</a>
        </div>
        <div class="mainpage_block_body">';

        $i = 0;

        if (CURRENT_USER->isLogged()) {
            $applications_full_data = [];
            $already_found_project = [];
            $more_than_one_application_on_a_project = [];
            $applications_data = DB->query(
                "SELECT pa.creator_id, pa.id, pa.sorter, p.name as project_name, p.id as project_id, p.attachments as project_attachments FROM project_application pa LEFT JOIN project p ON p.id=pa.project_id WHERE pa.creator_id=:creator_id AND p.date_to >= :date_to AND pa.deleted_by_player='0' ORDER BY p.name, pa.sorter",
                [
                    ['creator_id', CURRENT_USER->id()],
                    ['date_to', date('Y-m-d')],
                ],
            );

            foreach ($applications_data as $application_data) {
                $applications_full_data[] = $application_data;

                if (in_array($application_data['project_id'], $already_found_project)) {
                    $more_than_one_application_on_a_project[] = $application_data['project_id'];
                }
                $already_found_project[] = $application_data['project_id'];
            }
            unset($already_found_project);

            foreach ($applications_full_data as $application_data) {
                if ($application_data['id'] > 0) {
                    $result = DB->query(
                        "SELECT c.id as c_id, c.name as c_name, c.sub_obj_type as c_sub_obj_type, cm.* FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND cms.user_id=:user_id WHERE c.obj_type='{project_application_conversation}' AND c.obj_id=:obj_id AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{to_player}' OR c.sub_obj_type IS NULL) AND (cms.message_read='0' OR cms.message_read IS NULL) GROUP BY cm.id, c.id",
                        [
                            ['user_id', CURRENT_USER->id()],
                            ['obj_id', $application_data['id']],
                        ],
                    );
                    $conversations_data_count = count($result);

                    $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/myapplication/' . $application_data['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                        FileHelper::getImagePath($application_data['project_attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ??
                            ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_application.svg',
                    ) . '"><div class="mainpage_block_body_item_avatar_counter' .
                        ($conversations_data_count > 0 ? ' filled' : '') . '">' . $conversations_data_count . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/myapplication/' . $application_data['id'] . '/" class="mainpage_block_body_item_name">' . $application_data['project_name'] . (in_array(
                            $application_data['project_id'],
                            $more_than_one_application_on_a_project,
                        ) ? ' (' . $application_data['sorter'] . ')' : '') . '</a>
                <a href="' . ABSOLUTE_PATH . '/roles/' . $application_data['project_id'] . '/" class="mainpage_block_body_additional">' . TextHelper::mb_ucfirst(
                            $LOCALE['roleslist'],
                        ) . '</a>
            </div>';
                    ++$i;
                }
            }
        } else {
            /* список последний появившихся проектов */
            $project_info = DB->query(
                "SELECT p.id, p.name, p.date_from, p.date_to, p.external_link, p.attachments FROM project p LEFT JOIN project_application_field paf ON paf.project_id=p.id AND paf.application_type='0' LEFT JOIN project_application_field paf2 ON paf2.project_id=p.id AND paf2.application_type='1' LEFT JOIN project_group pg ON pg.project_id=p.id AND (pg.rights=0 OR pg.rights=1) WHERE p.status='1' AND p.date_to>=:date_to AND (paf.id IS NOT NULL OR paf2.id IS NOT NULL) GROUP BY p.id ORDER BY p.name",
                [
                    ['date_to', date('Y-m-d')],
                ],
            );

            foreach ($project_info as $project_info_data) {
                $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/myapplication/act=add&project_id=' . $project_info_data['id'] . '&application_type=0" class="mainpage_block_body_item_avatar" style="' .
                    DesignHelper::getCssBackgroundImage(
                        FileHelper::getImagePath($project_info_data['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ??
                            ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg',
                    ) .
                    '"></a>
                <a href="' . ABSOLUTE_PATH . '/myapplication/act=add&project_id=' . $project_info_data['id'] . '&application_type=0" class="mainpage_block_body_item_name">' . $project_info_data['name'] . '</a>
                <a href="' . ABSOLUTE_PATH . '/roles/' . $project_info_data['id'] . '/" class="mainpage_block_body_additional">' . TextHelper::mb_ucfirst(
                        $LOCALE['roleslist'],
                    ) . '</a>
            </div>';
                ++$i;
            }
        }

        $RESPONSE_DATA .= '
        </div>
    </div>';

        $RESPONSE_DATA .= '
    <div class="mainpage_block" id="projects">
        <div class="mainpage_block_header special project">
            <div class="icon"></div>
            <a class="name" href="' . ABSOLUTE_PATH . '/project/">' . TextHelper::mb_ucfirst($LOCALE[(CURRENT_USER->isLogged() ? 'my_' : 'all_') . 'projects']) . '</a>
            <a class="command small" href="' . ABSOLUTE_PATH . '/project/">' . TextHelper::mb_ucfirst($LOCALE['all']) . '</a>
            <a class="command" href="' . ABSOLUTE_PATH . '/project/act=add">' . TextHelper::mb_ucfirst($LOCALE['add_project']) . '</a>
        </div>
        <div class="mainpage_block_body">';

        $i = 0;

        if (CURRENT_USER->isLogged()) {
            $projects = RightsHelper::findByRights(null, '{project}', null);

            if ($projects) {
                $projects = array_unique($projects);
                $projects_data = DB->select('project', [['id', $projects]]);
                $projects_data_sort = [];
                $projects_data_sort2 = [];
                $projects_data_sort3 = [];

                if (is_array($projects_data)) {
                    foreach ($projects_data as $key => $project_data) {
                        if (is_null($project_data['date_to'])) {
                            $project_data['date_to'] = '';
                        }

                        if ($project_data['id'] !== '' && strtotime($project_data['date_to']) >= strtotime('today')) {
                            $projects_data[$key]['new_count'] = UniversalHelper::checkForUpdates('{project}', (int) $project_data['id']);
                            $projects_data_sort[$key] = $projects_data[$key]['new_count'];
                            $projects_data_sort2[$key] = $projects_data[$key]['name'];
                            $projects_data_sort3[$key] = $project_data['date_to'];
                        } else {
                            unset($projects_data[$key]);
                        }
                    }
                }

                if ($projects_data) {
                    array_multisort($projects_data_sort, SORT_DESC, $projects_data_sort3, SORT_DESC, $projects_data_sort2, SORT_ASC, $projects_data);

                    foreach ($projects_data as $project_data) {
                        if ($project_data['id'] > 0) {
                            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/project/' . $project_data['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                                FileHelper::getImagePath($project_data['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ?? ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg',
                            ) . '"><div class="mainpage_block_body_item_avatar_counter' . ($project_data['new_count'] > 0 ? ' filled' : '') . '">' . $project_data['new_count'] . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/project/' . $project_data['id'] . '/" class="mainpage_block_body_item_name">' . $project_data['name'] . '</a>
                <a href="' . ABSOLUTE_PATH . '/roles/' . $project_data['id'] . '/" class="mainpage_block_body_additional">' . TextHelper::mb_ucfirst(
                                $LOCALE['roleslist'],
                            ) . '</a>
            </div>';
                            ++$i;
                        }
                    }
                }
            }
        } else {
            $projects_data = DB->select(
                tableName: 'project',
                order: [
                    'id DESC',
                ],
                limit: 10,
            );

            foreach ($projects_data as $project_data) {
                $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/project/' . $project_data['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                    FileHelper::getImagePath($project_data['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ??
                        ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg',
                ) . '"></a>
                <a href="' . ABSOLUTE_PATH . '/project/' . $project_data['id'] . '/" class="mainpage_block_body_item_name">' . $project_data['name'] . '</a>
                <a href="' . ABSOLUTE_PATH . '/roles/' . $project_data['id'] . '/" class="mainpage_block_body_additional">' . TextHelper::mb_ucfirst(
                    $LOCALE['roleslist'],
                ) . '</a>
            </div>';
                ++$i;
            }
        }

        $RESPONSE_DATA .= '
        </div>
    </div>';

        $photoData = DB->select(
            tableName: 'banner',
            criteria: [
                'type' => 1,
                'active' => 1,
            ],
            oneResult: true,
            order: [
                'RAND()',
            ],
            limit: 1,
        );

        if ($photoData) {
            $fileAbsolutePath = FileHelper::getImagePath($photoData['img'], 6);
            $fileInnerPath = FileHelper::getImagePath($photoData['img'], 6, false, true);
            $checkImageExists = FileHelper::checkImageExists($fileInnerPath);
            $fileWebpAbsolutePath = FileHelper::getFileFullPath('id' . $photoData['id'] . '.webp', 6);
            $fileWebpInnerPath = FileHelper::getFileFullPath('id' . $photoData['id'] . '.webp', 6, false, true);
            $checkWebpExists = FileHelper::checkImageExists($fileWebpInnerPath);

            if (!$checkWebpExists && $fileInnerPath) {
                $image = WideImage\WideImage::loadFromFile($fileInnerPath);
                $image->resize($_ENV['UPLOADS'][6]['thumbwidth'], $_ENV['UPLOADS'][6]['thumbheight'])->saveToFile($fileWebpInnerPath);

                $checkWebpExists = true;
            }

            if ($checkImageExists) {
                $RESPONSE_DATA .= '
    <div class="mainpage_block fullsize_background" id="photos">
        <div class="mainpage_block_header">
            <a class="name">' . $LOCALE['photo'] . '</a>
        </div>
        <div class="mainpage_block_body" style="' . DesignHelper::getCssBackgroundImage(($checkWebpExists ? $fileWebpAbsolutePath : $fileAbsolutePath)) . '">
            <a href="' . $fileAbsolutePath . '" target="_blank" class="photo_link"></a>
            <div class="photo_description">' . DataHelper::escapeOutput($photoData['description'], EscapeModeEnum::plainHTML) . '</div>
            <a class="command" href="' . ABSOLUTE_PATH . '/photo/">' . TextHelper::mb_ucfirst($LOCALE['photo_title']) . '</a>
        </div>
    </div>';
            }
        }

        $RESPONSE_DATA .= '
    <div class="mainpage_block" id="communities">
        <div class="mainpage_block_header special community">
            <div class="icon"></div>
            <a class="name" href="' . ABSOLUTE_PATH . '/community/">' . TextHelper::mb_ucfirst(
            $LOCALE[(CURRENT_USER->isLogged() ? 'my_' : 'all_') . 'communities'],
        )
            . '</a>
            <a class="command small" href="' . ABSOLUTE_PATH . '/community/">' . TextHelper::mb_ucfirst($LOCALE['all']) . '</a>
            <a class="command" href="' . ABSOLUTE_PATH . '/community/act=add">' . TextHelper::mb_ucfirst($LOCALE['add_community']) . '</a>
        </div>
        <div class="mainpage_block_body">';

        $i = 0;

        if (CURRENT_USER->isLogged()) {
            $communities = RightsHelper::findByRights(null, '{community}', null);

            if ($communities) {
                $communities = array_unique($communities);
                $communities_data = DB->select('community', [['id', $communities]]);
                $communities_data_sort = [];
                $communities_data_sort2 = [];

                if (is_array($communities_data)) {
                    foreach ($communities_data as $key => $community_data) {
                        if ($community_data['id'] !== '') {
                            $communities_data[$key]['new_count'] = UniversalHelper::checkForUpdates('{community}', (int) $community_data['id']);
                            $communities_data_sort[$key] = $communities_data[$key]['new_count'];
                            $communities_data_sort2[$key] = $communities_data[$key]['name'];
                        } else {
                            unset($communities_data[$key]);
                        }
                    }
                }

                if ($communities_data) {
                    array_multisort(
                        $communities_data_sort,
                        SORT_DESC,
                        $communities_data_sort2,
                        SORT_ASC,
                        $communities_data,
                    );

                    foreach ($communities_data as $community_data) {
                        if ($community_data['id'] > 0) {
                            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/community/' . $community_data['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                                FileHelper::getImagePath($community_data['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ??
                                    ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_community.svg',
                            ) . '"><div class="mainpage_block_body_item_avatar_counter' . ($community_data['new_count'] > 0 ? ' filled' : '') . '">' . $community_data['new_count'] . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/community/' . $community_data['id'] . '/" class="mainpage_block_body_item_name">' . $community_data['name'] . '</a>
            </div>';
                            ++$i;
                        }
                    }
                }
            }
        } else {
            $communities_data = DB->select(
                tableName: 'community',
                order: [
                    'id DESC',
                ],
                limit: 10,
            );

            foreach ($communities_data as $community_data) {
                $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/community/' . $community_data['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                    FileHelper::getImagePath($community_data['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ?? ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_community.svg',
                ) . '"></a>
                <a href="' . ABSOLUTE_PATH . '/community/' . $community_data['id'] . '/" class="mainpage_block_body_item_name">' . $community_data['name'] . '</a>
            </div>';
                ++$i;
            }
        }

        $RESPONSE_DATA .= '
        </div>
    </div>';

        $RESPONSE_DATA .= '
    <div class="mainpage_block" id="calendar">
        <div class="mainpage_block_header">
            <a class="name" href="' . ABSOLUTE_PATH . '/calendar/">' . $LOCALE['calendar'] . '</a>
            <a class="command small" href="' . ABSOLUTE_PATH . '/calendar/">' . TextHelper::mb_ucfirst($LOCALE['all']) . '</a>
            <a class="command" href="' . ABSOLUTE_PATH . '/calendar_event/act=add">' . TextHelper::mb_ucfirst($LOCALE['add_event']) . '</a>
        </div>
        <div class="mainpage_block_body">';

        $i = 0;
        $calendar_data = DB->select(
            tableName: 'calendar_event',
            order: [
                'id DESC',
            ],
            limit: 10,
        );

        foreach ($calendar_data as $calendar_item_data) {
            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendar_item_data['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                FileHelper::getImagePath($calendar_item_data['logo'], FileHelper::getUploadNumByType('calendar_event'), true) ??
                    ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_event.svg',
            ) . '"></a>
                <a href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendar_item_data['id'] . '/" class="mainpage_block_body_item_name">' . $calendar_item_data['name'] . '</a>
                <span class="mainpage_block_body_additional inverted">' . DateHelper::dateFromToEvent(
                $calendar_item_data['date_from'],
                $calendar_item_data['date_to'],
            ) . '</span>
            </div>';
            ++$i;
        }

        $RESPONSE_DATA .= '
        </div>
    </div>';

        $RESPONSE_DATA .= '
    <div class="mainpage_block" id="exchange">
        <div class="mainpage_block_header">
            <a class="name" href="' . ABSOLUTE_PATH . '/exchange/">' . $LOCALE['exchange_title'] . '</a>
            <a class="command small" href="' . ABSOLUTE_PATH . '/exchange/">' . TextHelper::mb_ucfirst($LOCALE['all']) . '</a>
            <a class="command" href="' . ABSOLUTE_PATH . '/exchange_item_edit/act=add">' . TextHelper::mb_ucfirst($LOCALE['exchange_add_item']) . '</a>
        </div>
        <div class="mainpage_block_body">';

        $i = 0;
        $exchange_data = DB->query(
            'SELECT e.name, e.id AS exchange_item_id, e.price_buy, e.price_lease, e.currency, u.* FROM exchange_item AS e LEFT JOIN user AS u ON u.id=e.creator_id ORDER BY e.id DESC LIMIT 5',
            [],
        );

        foreach ($exchange_data as $exchange_item_data) {
            $userModel = $userService->arrayToModel($exchange_item_data);
            $filepath = $userService->photoUrl($userModel, true);
            $inFilePath = ABSOLUTE_PATH . 'thumbnails' . $_ENV['UPLOADS_PATH'] . $_ENV['UPLOADS'][16]['path'] . $exchange_item_data['exchange_item_id'] . '.jpg';

            if (FileHelper::checkImageExists($inFilePath)) {
                $filepath = $inFilePath;
            }

            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/exchange/' . $exchange_item_data['exchange_item_id'] . '/" class="mainpage_block_body_item_avatar" style="'
                . DesignHelper::getCssBackgroundImage($filepath) . '"></a>
                <a href="' . ABSOLUTE_PATH . '/exchange/' . $exchange_item_data['exchange_item_id'] . '/" class="mainpage_block_body_item_name">' . $exchange_item_data['name'] . '</a>
                <span class="mainpage_block_body_additional inverted">' . ($exchange_item_data['price_lease'] > 0 ? $exchange_item_data['price_lease'] :
                    $exchange_item_data['price_buy']) . TextHelper::currencyNameToSign($exchange_item_data['currency']) . '</span>
            </div>';
            ++$i;
        }

        $RESPONSE_DATA .= '
        </div>
    </div>';

        $RESPONSE_DATA .= '
    <div class="mainpage_block" id="reports">
        <div class="mainpage_block_header">
            <a class="name" href="' . ABSOLUTE_PATH . '/report/">' . $LOCALE['report_title'] . '</a>
            <a class="command small" href="' . ABSOLUTE_PATH . '/report/">' . TextHelper::mb_ucfirst($LOCALE['all']) . '</a>
            <a class="command" href="' . ABSOLUTE_PATH . '/report/act=add">' . TextHelper::mb_ucfirst($LOCALE['report_add']) . '</a>
        </div>
        <div class="mainpage_block_body">';

        $i = 0;
        $reports_data = DB->query(
            "SELECT r.id AS report_id, r.name AS report_name, count(r2.id) as rating, ce.name AS event_name, ce.id AS event_id, u.*
        FROM report AS r
        LEFT JOIN calendar_event AS ce ON ce.id=r.calendar_event_id
        LEFT JOIN relation r2 ON r2.obj_id_to=r.id AND
            r2.obj_type_to='{report}'
            AND r2.type='{important}'
            AND r2.obj_type_from='{user}'
        LEFT JOIN user u ON u.id=r.creator_id
        GROUP BY r.id
        ORDER BY
            r.id DESC
        LIMIT 5",
            [],
        );

        foreach ($reports_data as $report_data) {
            $userModel = $userService->arrayToModel($report_data);

            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/report/' . $report_data['report_id'] . '/" class="mainpage_block_body_item_avatar" style="'
                . DesignHelper::getCssBackgroundImage($userService->photoUrl($userModel, true)) . '"><div class="mainpage_block_body_item_avatar_counter">'
                . $report_data['rating'] . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/report/' . $report_data['report_id'] . '/" class="mainpage_block_body_item_name">' . ($report_data['report_name'] !== '' ? $report_data['report_name'] : '<i>' . $LOCALE['publication_no_name'] . '</i>') . '</a>
                <a class="mainpage_block_body_additional inverted" href="' . ABSOLUTE_PATH . '/calendar_event/' . $report_data['event_id'] . '/">' . $report_data['event_name'] . '</a>
            </div>';
            ++$i;
        }

        $RESPONSE_DATA .= '
        </div>
    </div>';

        $RESPONSE_DATA .= '
    <div class="mainpage_block" id="ruling">
        <div class="mainpage_block_header">
            <a class="name" href="' . ABSOLUTE_PATH . '/ruling/">' . $LOCALE['ruling_title'] . '</a>
            <a class="command small" href="' . ABSOLUTE_PATH . '/ruling/">' . TextHelper::mb_ucfirst($LOCALE['all']) . '</a>
        </div>
        <div class="mainpage_block_body">';

        $i = 0;
        $ruling_items_data = DB->query(
            "SELECT r.id AS ruling_item_id, r.name AS ruling_item_name, r.author AS ruling_item_author, count(r2.id) as rating, u.*
        FROM ruling_item AS r
        LEFT JOIN relation r2 ON r.id=r2.obj_id_to AND
            r2.obj_type_to='{ruling_item}'
            AND r2.type='{important}'
        LEFT JOIN user u ON u.id=r.creator_id
        GROUP BY r.id
        ORDER BY
            r.id DESC
        LIMIT 5",
            [],
        );

        foreach ($ruling_items_data as $ruling_item_data) {
            $author_result = false;
            $authors_array = DataHelper::multiselectToArray($ruling_item_data['ruling_item_author']);

            foreach ($authors_array as $author) {
                if ((int) trim($author) > 0) {
                    $author_result = $userService->get(trim($author));
                    break;
                }
            }

            $userModel = $userService->getModelInstance($userService->getModel());
            $userModel->photo->set($ruling_item_data['photo']);

            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/ruling/' . $ruling_item_data['ruling_item_id'] . '/" class="mainpage_block_body_item_avatar" style="' .
                DesignHelper::getCssBackgroundImage(
                    $author_result ? $userService->photoUrl($author_result, true) : $userService->photoUrl($userModel, true),
                ) .
                '"><div class="mainpage_block_body_item_avatar_counter">' . $ruling_item_data['rating'] . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/ruling/' . $ruling_item_data['ruling_item_id'] . '/" class="mainpage_block_body_item_name">' . $ruling_item_data['ruling_item_name'] . '</a>
            </div>';
            ++$i;
        }

        $RESPONSE_DATA .= '
        </div>
    </div>';

        $RESPONSE_DATA .= '
    <div class="mainpage_block" id="publications">
        <div class="mainpage_block_header">
            <a class="name" href="' . ABSOLUTE_PATH . '/publication/">' . $LOCALE['publication_title'] . '</a>
            <a class="command small" href="' . ABSOLUTE_PATH . '/publication/">' . TextHelper::mb_ucfirst($LOCALE['all']) . '</a>
            <a class="command" href="' . ABSOLUTE_PATH . '/publications_edit/act=add">' . TextHelper::mb_ucfirst($LOCALE['add_publication']) . '</a>
        </div>
        <div class="mainpage_block_body">';

        $interesting_publications = DB->query(
            "SELECT p.id AS publication_id, p.name AS publication_name, count(r.id) as rating, u.*
        FROM publication p
        LEFT JOIN relation r ON p.id=r.obj_id_to
        LEFT JOIN user u ON u.id=p.creator_id
        WHERE p.active =  '1'
            AND r.obj_type_to='{publication}'
            AND r.type='{important}'
        GROUP BY p.id
        ORDER BY rating DESC
        LIMIT 3",
            [],
        );
        $i = 0;

        foreach ($interesting_publications as $interesting_publication_data) {
            $userModel = $userService->getModelInstance($userService->getModel());
            $userModel->photo->set($interesting_publication_data['photo']);

            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/publication/' . $interesting_publication_data['publication_id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                $userService->photoUrl(
                    $userModel,
                    true,
                ),
            ) . '"><div class="mainpage_block_body_item_avatar_counter">' . $interesting_publication_data['rating'] . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/publication/' . $interesting_publication_data['publication_id'] . '/" class="mainpage_block_body_item_name">' . $interesting_publication_data['publication_name'] . '</a>
            </div>';
            ++$i;
        }

        $RESPONSE_DATA .= '
        </div>
    </div>';

        $RESPONSE_DATA .= '
    <div class="mainpage_block" id="news">
        <div class="mainpage_block_header">
            <a class="name" href="' . ABSOLUTE_PATH . '/news/">' . $LOCALE['news'] . '</a>
            <a class="command small" href="' . ABSOLUTE_PATH . '/news/">' . TextHelper::mb_ucfirst($LOCALE['all']) . '</a>
        </div>
        <div class="mainpage_block_body">';

        $news_data = DB->query(
            "SELECT * FROM news WHERE active='1' AND ((show_date<NOW() AND (from_date IS NULL OR from_date<CURDATE()) AND to_date IS NULL) OR to_date<CURDATE()) ORDER BY show_date DESC, updated_at DESC LIMIT 4",
            [],
        );
        $i = 0;

        foreach ($news_data as $news_item_data) {
            $news_date = DateHelper::dateFromTo($news_item_data);
            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/news/' . $news_item_data['id'] . '/" class="mainpage_block_body_item_name">' . strip_tags(
                $news_item_data['annotation'],
            ) . '</a>
                <span class="mainpage_block_body_additional inverted">' . $news_date['date'] . '</span>
            </div>';
            ++$i;
        }

        $RESPONSE_DATA .= '
        </div>
    </div>';

        $RESPONSE_DATA .= '
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
