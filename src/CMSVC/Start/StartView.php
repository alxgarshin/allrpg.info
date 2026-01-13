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
        $LOCALE = $this->LOCALE;

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
            $applicationsFullData = [];
            $alreadyFoundProject = [];
            $moreThanOneApplicationOnAProject = [];
            $applicationsData = DB->query(
                "SELECT pa.creator_id, pa.id, pa.sorter, p.name as project_name, p.id as project_id, p.attachments as project_attachments FROM project_application pa LEFT JOIN project p ON p.id=pa.project_id WHERE pa.creator_id=:creator_id AND p.date_to >= :date_to AND pa.deleted_by_player='0' ORDER BY p.name, pa.sorter",
                [
                    ['creator_id', CURRENT_USER->id()],
                    ['date_to', date('Y-m-d')],
                ],
            );

            foreach ($applicationsData as $applicationData) {
                $applicationsFullData[] = $applicationData;

                if (in_array($applicationData['project_id'], $alreadyFoundProject)) {
                    $moreThanOneApplicationOnAProject[] = $applicationData['project_id'];
                }
                $alreadyFoundProject[] = $applicationData['project_id'];
            }
            unset($alreadyFoundProject);

            foreach ($applicationsFullData as $applicationData) {
                if ($applicationData['id'] > 0) {
                    $result = DB->query(
                        "SELECT c.id as c_id, c.name as c_name, c.sub_obj_type as c_sub_obj_type, cm.* FROM conversation c LEFT JOIN conversation_message cm ON cm.conversation_id=c.id LEFT JOIN conversation_message_status cms ON cms.message_id=cm.id AND cms.user_id=:user_id WHERE c.obj_type='{project_application_conversation}' AND c.obj_id=:obj_id AND (c.sub_obj_type='{from_player}' OR c.sub_obj_type='{to_player}' OR c.sub_obj_type IS NULL) AND (cms.message_read='0' OR cms.message_read IS NULL) GROUP BY cm.id, c.id",
                        [
                            ['user_id', CURRENT_USER->id()],
                            ['obj_id', $applicationData['id']],
                        ],
                    );
                    $conversationsDataCount = count($result);

                    $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/myapplication/' . $applicationData['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                        FileHelper::getImagePath($applicationData['project_attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ??
                            ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_application.svg',
                    ) . '"><div class="mainpage_block_body_item_avatar_counter' .
                        ($conversationsDataCount > 0 ? ' filled' : '') . '">' . $conversationsDataCount . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/myapplication/' . $applicationData['id'] . '/" class="mainpage_block_body_item_name">' . $applicationData['project_name'] . (in_array(
                            $applicationData['project_id'],
                            $moreThanOneApplicationOnAProject,
                        ) ? ' (' . $applicationData['sorter'] . ')' : '') . '</a>
                <a href="' . ABSOLUTE_PATH . '/roles/' . $applicationData['project_id'] . '/" class="mainpage_block_body_additional">' . TextHelper::mb_ucfirst(
                            $LOCALE['roleslist'],
                        ) . '</a>
            </div>';
                    ++$i;
                }
            }
        } else {
            /* список последний появившихся проектов */
            $projectInfo = DB->query(
                "SELECT p.id, p.name, p.date_from, p.date_to, p.external_link, p.attachments FROM project p LEFT JOIN project_application_field paf ON paf.project_id=p.id AND paf.application_type='0' LEFT JOIN project_application_field paf2 ON paf2.project_id=p.id AND paf2.application_type='1' LEFT JOIN project_group pg ON pg.project_id=p.id AND (pg.rights=0 OR pg.rights=1) WHERE p.status='1' AND p.date_to>=:date_to AND (paf.id IS NOT NULL OR paf2.id IS NOT NULL) GROUP BY p.id ORDER BY p.name",
                [
                    ['date_to', date('Y-m-d')],
                ],
            );

            foreach ($projectInfo as $projectInfoData) {
                $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/myapplication/act=add&project_id=' . $projectInfoData['id'] . '&application_type=0" class="mainpage_block_body_item_avatar" style="' .
                    DesignHelper::getCssBackgroundImage(
                        FileHelper::getImagePath($projectInfoData['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ??
                            ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg',
                    ) .
                    '"></a>
                <a href="' . ABSOLUTE_PATH . '/myapplication/act=add&project_id=' . $projectInfoData['id'] . '&application_type=0" class="mainpage_block_body_item_name">' . $projectInfoData['name'] . '</a>
                <a href="' . ABSOLUTE_PATH . '/roles/' . $projectInfoData['id'] . '/" class="mainpage_block_body_additional">' . TextHelper::mb_ucfirst(
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
                $projectsData = DB->select('project', [['id', $projects]]);
                $projectsDataSort = [];
                $projectsDataSort2 = [];
                $projectsDataSort3 = [];

                if (is_array($projectsData)) {
                    foreach ($projectsData as $key => $projectData) {
                        if (is_null($projectData['date_to'])) {
                            $projectData['date_to'] = '';
                        }

                        if ($projectData['id'] !== '' && strtotime($projectData['date_to']) >= strtotime('today')) {
                            $projectsData[$key]['new_count'] = UniversalHelper::checkForUpdates('{project}', (int) $projectData['id']);
                            $projectsDataSort[$key] = $projectsData[$key]['new_count'];
                            $projectsDataSort2[$key] = $projectsData[$key]['name'];
                            $projectsDataSort3[$key] = $projectData['date_to'];
                        } else {
                            unset($projectsData[$key]);
                        }
                    }
                }

                if ($projectsData) {
                    array_multisort($projectsDataSort, SORT_DESC, $projectsDataSort3, SORT_DESC, $projectsDataSort2, SORT_ASC, $projectsData);

                    foreach ($projectsData as $projectData) {
                        if ($projectData['id'] > 0) {
                            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/project/' . $projectData['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                                FileHelper::getImagePath($projectData['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ?? ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg',
                            ) . '"><div class="mainpage_block_body_item_avatar_counter' . ($projectData['new_count'] > 0 ? ' filled' : '') . '">' . $projectData['new_count'] . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/project/' . $projectData['id'] . '/" class="mainpage_block_body_item_name">' . $projectData['name'] . '</a>
                <a href="' . ABSOLUTE_PATH . '/roles/' . $projectData['id'] . '/" class="mainpage_block_body_additional">' . TextHelper::mb_ucfirst(
                                $LOCALE['roleslist'],
                            ) . '</a>
            </div>';
                            ++$i;
                        }
                    }
                }
            }
        } else {
            $projectsData = DB->select(
                tableName: 'project',
                order: [
                    'id DESC',
                ],
                limit: 10,
            );

            foreach ($projectsData as $projectData) {
                $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/project/' . $projectData['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                    FileHelper::getImagePath($projectData['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ??
                        ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg',
                ) . '"></a>
                <a href="' . ABSOLUTE_PATH . '/project/' . $projectData['id'] . '/" class="mainpage_block_body_item_name">' . $projectData['name'] . '</a>
                <a href="' . ABSOLUTE_PATH . '/roles/' . $projectData['id'] . '/" class="mainpage_block_body_additional">' . TextHelper::mb_ucfirst(
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
                $communitiesData = DB->select('community', [['id', $communities]]);
                $communitiesDataSort = [];
                $communitiesDataSort2 = [];

                if (is_array($communitiesData)) {
                    foreach ($communitiesData as $key => $communityData) {
                        if ($communityData['id'] !== '') {
                            $communitiesData[$key]['new_count'] = UniversalHelper::checkForUpdates('{community}', (int) $communityData['id']);
                            $communitiesDataSort[$key] = $communitiesData[$key]['new_count'];
                            $communitiesDataSort2[$key] = $communitiesData[$key]['name'];
                        } else {
                            unset($communitiesData[$key]);
                        }
                    }
                }

                if ($communitiesData) {
                    array_multisort(
                        $communitiesDataSort,
                        SORT_DESC,
                        $communitiesDataSort2,
                        SORT_ASC,
                        $communitiesData,
                    );

                    foreach ($communitiesData as $communityData) {
                        if ($communityData['id'] > 0) {
                            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/community/' . $communityData['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                                FileHelper::getImagePath($communityData['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ??
                                    ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_community.svg',
                            ) . '"><div class="mainpage_block_body_item_avatar_counter' . ($communityData['new_count'] > 0 ? ' filled' : '') . '">' . $communityData['new_count'] . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/community/' . $communityData['id'] . '/" class="mainpage_block_body_item_name">' . $communityData['name'] . '</a>
            </div>';
                            ++$i;
                        }
                    }
                }
            }
        } else {
            $communitiesData = DB->select(
                tableName: 'community',
                order: [
                    'id DESC',
                ],
                limit: 10,
            );

            foreach ($communitiesData as $communityData) {
                $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/community/' . $communityData['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                    FileHelper::getImagePath($communityData['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'), true) ?? ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_community.svg',
                ) . '"></a>
                <a href="' . ABSOLUTE_PATH . '/community/' . $communityData['id'] . '/" class="mainpage_block_body_item_name">' . $communityData['name'] . '</a>
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
        $calendarData = DB->select(
            tableName: 'calendar_event',
            order: [
                'id DESC',
            ],
            limit: 10,
        );

        foreach ($calendarData as $calendarItemData) {
            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendarItemData['id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage(
                FileHelper::getImagePath($calendarItemData['logo'], FileHelper::getUploadNumByType('calendar_event'), true) ??
                    ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_event.svg',
            ) . '"></a>
                <a href="' . ABSOLUTE_PATH . '/calendar_event/' . $calendarItemData['id'] . '/" class="mainpage_block_body_item_name">' . $calendarItemData['name'] . '</a>
                <span class="mainpage_block_body_additional inverted">' . DateHelper::dateFromToEvent(
                $calendarItemData['date_from'],
                $calendarItemData['date_to'],
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
        $exchangeData = DB->query(
            'SELECT e.name, e.id AS exchange_item_id, e.price_buy, e.price_lease, e.currency, u.* FROM exchange_item AS e LEFT JOIN user AS u ON u.id=e.creator_id ORDER BY e.id DESC LIMIT 5',
            [],
        );

        foreach ($exchangeData as $exchangeItemData) {
            $userModel = $userService->arrayToModel($exchangeItemData);
            $filepath = $userService->photoUrl($userModel, true);
            $inFilePath = ABSOLUTE_PATH . '/thumbnails' . $_ENV['UPLOADS_PATH'] . $_ENV['UPLOADS'][16]['path'] . $exchangeItemData['exchange_item_id'] . '.jpg';

            if (FileHelper::checkImageExists($inFilePath)) {
                $filepath = $inFilePath;
            }

            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/exchange/' . $exchangeItemData['exchange_item_id'] . '/" class="mainpage_block_body_item_avatar" style="'
                . DesignHelper::getCssBackgroundImage($filepath) . '"></a>
                <a href="' . ABSOLUTE_PATH . '/exchange/' . $exchangeItemData['exchange_item_id'] . '/" class="mainpage_block_body_item_name">' . $exchangeItemData['name'] . '</a>
                <span class="mainpage_block_body_additional inverted">' . ($exchangeItemData['price_lease'] > 0 ? $exchangeItemData['price_lease'] :
                    $exchangeItemData['price_buy']) . TextHelper::currencyNameToSign($exchangeItemData['currency']) . '</span>
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
        $reportsData = DB->query(
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

        foreach ($reportsData as $reportData) {
            $userModel = $userService->arrayToModel($reportData);

            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/report/' . $reportData['report_id'] . '/" class="mainpage_block_body_item_avatar" style="'
                . DesignHelper::getCssBackgroundImage($userService->photoUrl($userModel, true)) . '"><div class="mainpage_block_body_item_avatar_counter">'
                . $reportData['rating'] . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/report/' . $reportData['report_id'] . '/" class="mainpage_block_body_item_name">' . ($reportData['report_name'] !== '' ? $reportData['report_name'] : '<i>' . $LOCALE['publication_no_name'] . '</i>') . '</a>
                <a class="mainpage_block_body_additional inverted" href="' . ABSOLUTE_PATH . '/calendar_event/' . $reportData['event_id'] . '/">' . $reportData['event_name'] . '</a>
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
        $rulingItemsData = DB->query(
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

        foreach ($rulingItemsData as $rulingItemData) {
            $authorResult = false;
            $authorsArray = DataHelper::multiselectToArray($rulingItemData['ruling_item_author']);

            foreach ($authorsArray as $author) {
                if ((int) trim($author) > 0) {
                    $authorResult = $userService->get(trim($author));
                    break;
                }
            }

            $userModel = $userService->getModelInstance($userService->model);
            $userModel->photo->set($rulingItemData['photo']);

            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/ruling/' . $rulingItemData['ruling_item_id'] . '/" class="mainpage_block_body_item_avatar" style="' .
                DesignHelper::getCssBackgroundImage(
                    $authorResult ? $userService->photoUrl($authorResult, true) : $userService->photoUrl($userModel, true),
                ) .
                '"><div class="mainpage_block_body_item_avatar_counter">' . $rulingItemData['rating'] . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/ruling/' . $rulingItemData['ruling_item_id'] . '/" class="mainpage_block_body_item_name">' . $rulingItemData['ruling_item_name'] . '</a>
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

        $interestingPublications = DB->query(
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

        foreach ($interestingPublications as $interestingPublicationData) {
            $userModel = $userService->getModelInstance($userService->model);
            $userModel->photo->set($interestingPublicationData['photo']);

            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/publication/' . $interestingPublicationData['publication_id'] . '/" class="mainpage_block_body_item_avatar" style="' . DesignHelper::getCssBackgroundImage($userService->photoUrl($userModel, true)) . '"><div class="mainpage_block_body_item_avatar_counter">' . $interestingPublicationData['rating'] . '</div></a>
                <a href="' . ABSOLUTE_PATH . '/publication/' . $interestingPublicationData['publication_id'] . '/" class="mainpage_block_body_item_name">' . $interestingPublicationData['publication_name'] . '</a>
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

        $newsData = DB->query(
            "SELECT * FROM news WHERE active='1' AND ((show_date<NOW() AND (from_date IS NULL OR from_date<CURDATE()) AND to_date IS NULL) OR to_date<CURDATE()) ORDER BY show_date DESC, updated_at DESC LIMIT 4",
            [],
        );
        $i = 0;

        foreach ($newsData as $newsItemData) {
            $news_date = DateHelper::dateFromTo($newsItemData);
            $RESPONSE_DATA .= '
            <div class="mainpage_block_body_item string' . ($i % 2 === 0 ? '2' : '1') . '">
                <a href="' . ABSOLUTE_PATH . '/news/' . $newsItemData['id'] . '/" class="mainpage_block_body_item_name">' . strip_tags(
                $newsItemData['annotation'],
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
