<?php

declare(strict_types=1);

namespace App\CMSVC\People;

use App\CMSVC\Publication\PublicationService;
use App\CMSVC\PublicationsEdit\PublicationsEditService;
use App\CMSVC\User\UserModel;
use App\Helper\RightsHelper;
use DateTime;
use DateTimeImmutable;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\{CMSVCHelper, DataHelper};

#[Controller(PeopleController::class)]
class PeopleService extends BaseService
{
    protected UserModel $userData;

    public function getUserData(): UserModel
    {
        return $this->userData;
    }

    public function setUserData(UserModel $userData): void
    {
        $this->userData = $userData;
    }

    public function checkBirthDate(UserModel $userData): DateTimeImmutable|false
    {
        if (!is_null($userData->birth->get()) && $userData->birth->get() < new DateTime('-2 years')) {
            return $userData->birth->get();
        }

        return false;
    }

    public function getCityName(UserModel $userData): string|false
    {
        if ($userData->city->get() > 0) {
            $cityData = DB->select(
                'geography',
                [
                    'id' => $userData->city->get(),
                ],
                true,
            );

            return DataHelper::escapeOutput($cityData['name']);
        }

        return false;
    }

    public function getAchievementsData(UserModel $userData): array
    {
        return DB->query(
            "SELECT DISTINCT a.* FROM achievement a LEFT JOIN relation r ON a.id=r.obj_id_to WHERE r.obj_type_to='{achievement}' AND r.obj_type_from='{user}' AND r.obj_id_from=:obj_id_from AND r.type='{has}' AND r.comment='done' ORDER BY a.type, a.id",
            [
                ['obj_id_from', $userData->id->getAsInt()],
            ],
        );
    }

    public function getMasterPlayedData(UserModel $userData): array
    {
        return DB->query(
            "SELECT p.*, ce.date_from, ce.date_to, ce.name, r.id as report_id, n.id as notion_id FROM played p LEFT JOIN calendar_event ce ON ce.id=p.calendar_event_id LEFT JOIN report r ON r.calendar_event_id=ce.id AND r.creator_id=:creator_id_1 LEFT JOIN notion n ON n.calendar_event_id=ce.id AND n.creator_id=:creator_id_2 WHERE p.creator_id=:creator_id_3 AND p.specializ2 != ''" . (CURRENT_USER->id() === $userData->id->getAsInt() ? '' : " AND p.active='1'") . ' ORDER BY ce.date_from DESC',
            [
                ['creator_id_1', $userData->id->getAsInt()],
                ['creator_id_2', $userData->id->getAsInt()],
                ['creator_id_3', $userData->id->getAsInt()],
            ],
        );
    }

    public function getSupportPlayedData(UserModel $userData): array
    {
        return DB->query(
            "SELECT p.*, ce.date_from, ce.date_to, ce.name, r.id as report_id, n.id as notion_id FROM played p LEFT JOIN calendar_event ce ON ce.id=p.calendar_event_id LEFT JOIN report r ON r.calendar_event_id=ce.id AND r.creator_id=:creator_id_1 LEFT JOIN notion n ON n.calendar_event_id=ce.id AND n.creator_id=:creator_id_2 WHERE p.creator_id=:creator_id_3 AND p.specializ3 != ''" . (CURRENT_USER->id() === $userData->id->getAsInt() ? '' : " AND p.active='1'") . ' ORDER BY ce.date_from DESC',
            [
                ['creator_id_1', $userData->id->getAsInt()],
                ['creator_id_2', $userData->id->getAsInt()],
                ['creator_id_3', $userData->id->getAsInt()],
            ],
        );
    }

    public function getPlayerPlayedData(UserModel $userData): array
    {
        return DB->query(
            "SELECT p.*, ce.date_from, ce.date_to, ce.name, r.id as report_id, n.id as notion_id FROM played p LEFT JOIN calendar_event ce ON ce.id=p.calendar_event_id LEFT JOIN report r ON r.calendar_event_id=ce.id AND r.creator_id=:creator_id_1 LEFT JOIN notion n ON n.calendar_event_id=ce.id AND n.creator_id=:creator_id_2 WHERE p.creator_id=:creator_id_3 AND (p.specializ != '' OR (p.specializ2 = '' AND p.specializ3 = ''))" . (CURRENT_USER->id() === $userData->id->getAsInt() ? '' : " AND p.active='1'") . ' ORDER BY ce.date_from DESC',
            [
                ['creator_id_1', $userData->id->getAsInt()],
                ['creator_id_2', $userData->id->getAsInt()],
                ['creator_id_3', $userData->id->getAsInt()],
            ],
        );
    }

    public function getFriendsData(UserModel $userData): array
    {
        $result = RightsHelper::findByRights('{friend}', '{user}', null, '{user}', $userData->id->getAsInt());

        return is_null($result) ? [] : $result;
    }

    public function getReportsData(UserModel $userData): array
    {
        return DB->query(
            'SELECT r.*, ce.name AS calendar_event_name FROM report r LEFT JOIN calendar_event ce ON ce.id=r.calendar_event_id WHERE r.creator_id=:creator_id ORDER BY r.created_at DESC',
            [
                ['creator_id', $userData->id->getAsInt()],
            ],
        );
    }

    public function getPublicationsData(UserModel $userData): array
    {
        /** @var PublicationsEditService */
        $publicationEditService = CMSVCHelper::getService('publications_edit');

        /** @var PublicationService */
        $publicationService = CMSVCHelper::getService('publication');

        $data = DB->query(
            'SELECT * FROM publication WHERE creator_id=:creator_id_1 OR author LIKE :creator_id_2 ORDER BY name',
            [
                ['creator_id_1', $userData->id->getAsInt()],
                ['creator_id_2', '%-' . $userData->id->getAsInt() . '-%'],
            ],
        );

        $publications = [];

        foreach ($data as $item) {
            $publicationService->publicationsIds[] = (int) $item['id'];
            $publications[] = $publicationEditService->arrayToModel($item);
        }

        $publicationService->prepareImportantCounters();
        $publicationService->prepareMessagesCounters();

        return $publications;
    }

    public function checkNotion(UserModel $userData): bool
    {
        return (bool) DB->select(
            'notion',
            [
                'user_id' => $userData->id->getAsInt(),
                'creator_id' => CURRENT_USER->id(),
            ],
            true,
        );
    }

    public function getNotions(UserModel $userData): array
    {
        return DB->query(
            'SELECT * FROM notion WHERE user_id=:user_id ' . (CURRENT_USER->isAdmin() || $userData->id->getAsInt() === CURRENT_USER->id() ? '' : " AND active='1'") . ' ORDER BY created_at DESC',
            [
                ['user_id', $userData->id->getAsInt()],
            ],
        );
    }

    public function getProjectsData(UserModel $userData): array
    {
        $projectsData = [];
        $projects = RightsHelper::findByRights(null, '{project}', null, '{user}', $userData->id->getAsInt());

        if ($projects) {
            $projectsDataSort = [];
            $projectsData = DB->select('project', ['id' => $projects]);

            foreach ($projectsData as $key => $projectData) {
                if ($projectData['id'] !== '') {
                    $projectsDataSort[$key] = strtotime($projectData['date_to'] ?? '');
                } else {
                    unset($projectsData[$key]);
                }
            }
            array_multisort($projectsDataSort, SORT_DESC, $projectsData);
        }

        return $projectsData;
    }

    public function getCommunitiesData(UserModel $userData): array
    {
        $communitiesData = [];
        $communities = RightsHelper::findByRights(null, '{community}', null, '{user}', $userData->id->getAsInt());

        if ($communities) {
            $communitiesDataSort = [];
            $communitiesData = DB->select('community', ['id' => $communities]);

            foreach ($communitiesData as $key => $communityData) {
                if ($communityData['id'] !== '') {
                    $communitiesDataSort[$key] = str_replace(
                        ['"', '«'],
                        '',
                        DataHelper::escapeOutput($communityData['name']),
                    );
                } else {
                    unset($communitiesData[$key]);
                }
            }
            array_multisort($communitiesDataSort, SORT_ASC, $communitiesData);
        }

        return $communitiesData;
    }

    public function checkMyOwnProfile(UserModel $userData): bool
    {
        return $userData->id->getAsInt() === CURRENT_USER->id();
    }

    public function checkCrossedProjects(UserModel $userData): bool
    {
        $checkCrossedProjectsId = DB->query(
            "SELECT pa2.id FROM project_application pa2 LEFT JOIN project_application pa ON pa.project_id=pa2.project_id AND pa.creator_id=:creator_id_1 AND pa.deleted_by_player='0' AND pa.deleted_by_gamemaster='0' WHERE pa2.creator_id=:creator_id_2 AND pa2.deleted_by_player='0' AND pa2.deleted_by_gamemaster='0' AND pa.id IS NOT NULL",
            [
                ['creator_id_1', $userData->id->getAsInt()],
                ['creator_id_2', CURRENT_USER->id()],
            ],
            true,
        );

        return (bool) $checkCrossedProjectsId;
    }

    public function checkContact(UserModel $userData): bool
    {
        return RightsHelper::checkRights(
            '{friend}',
            '{user}',
            $userData->id->getAsInt(),
            '{user}',
            CURRENT_USER->id(),
        ) || $this->checkMyOwnProfile($userData);
    }

    public function checkGamemaster(UserModel $userData): bool
    {
        if ($this->checkMyOwnProfile($userData)) {
            return false;
        }

        $checkGamemasterId = DB->query(
            "SELECT r.obj_id_to FROM relation r LEFT JOIN project_application pa ON pa.project_id=r.obj_id_to AND pa.creator_id=:creator_id AND pa.deleted_by_player='0' AND pa.deleted_by_gamemaster='0' WHERE r.obj_type_from='{user}' AND r.obj_id_from=:obj_id_from AND r.obj_type_to='{project}' AND (r.type='{gamemaster}' OR r.type='{admin}') AND pa.id IS NOT NULL",
            [
                ['creator_id', $userData->id->getAsInt()],
                ['obj_id_from', CURRENT_USER->id()],
            ],
            true,
        );

        return (bool) $checkGamemasterId;
    }
}
