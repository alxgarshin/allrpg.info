<?php

declare(strict_types=1);

namespace App\CMSVC\Community;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\{PostChange, PostCreate, PostDelete};
use Fraym\Helper\DataHelper;
use Generator;

/** @extends BaseService<CommunityModel> */
#[Controller(CommunityController::class)]
#[PostCreate]
#[PostChange]
#[PostDelete]
class CommunityService extends BaseService
{
    /** Выборка сообществ на основе запроса.
     * @return array{0: Generator<int|string, CommunityModel>, 1:int}
     */
    public function getCommunities(): array
    {
        if ($_REQUEST['search'] ?? false) {
            $communitiesData = $this->arraysToModels(
                DB->query(
                    'SELECT * FROM community WHERE (name LIKE :input1 OR description LIKE :input2) ORDER BY name',
                    [
                        ['input1', '%' . $_REQUEST['search'] . '%'],
                        ['input2', '%' . $_REQUEST['search'] . '%'],
                    ],
                ),
            );

            $communitiesDataCount = DB->query(
                'SELECT COUNT(id) FROM community WHERE (name LIKE :input1 OR description LIKE :input2) ORDER BY name',
                [
                    ['input1', '%' . $_REQUEST['search'] . '%'],
                    ['input2', '%' . $_REQUEST['search'] . '%'],
                ],
                true,
            )[0];
        } else {
            $communitiesData = $this->getAll(
                [],
                false,
                ['id DESC'],
                12,
            );

            $communitiesDataCount = DB->count('community');
        }

        return [$communitiesData, $communitiesDataCount];
    }

    public function PostCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $id) {
            RightsHelper::addRights('{admin}', '{community}', $id);
            RightsHelper::addRights('{member}', '{community}', $id);

            foreach ($_REQUEST['communities'][0] as $key => $value) {
                RightsHelper::addRights('{child}', '{community}', $key, '{community}', $id);
            }
        }
    }

    public function PostChange(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $id) {
            $notdelete = [];

            foreach ($_REQUEST['communities'][0] as $key => $value) {
                RightsHelper::addRights('{child}', '{community}', $key, '{community}', $id);
                $notdelete[] = $key;
            }
            RightsHelper::deleteRights(null, '{community}', null, '{community}', $id, count($notdelete) > 0 ? ' AND obj_id_to NOT IN (' . implode(',', $notdelete) . ')' : '');
        }
    }

    public function PostDelete(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $id) {
            RightsHelper::deleteRights(null, '{community}', $id);
            RightsHelper::deleteRights(null, null, null, '{community}', $id);
        }
    }

    public function isCommunityAdmin(int $communityId): ?bool
    {
        return RightsHelper::checkRights('{admin}', '{community}', $communityId);
    }

    public function isCommunityModerator(int $communityId): ?bool
    {
        return $this->isCommunityAdmin($communityId) || RightsHelper::checkRights('{moderator}', '{community}', $communityId);
    }

    public function isCommunityMember(int $communityId): ?bool
    {
        return $this->isCommunityAdmin($communityId) || RightsHelper::checkRights('{member}', '{community}', $communityId);
    }

    public function hasCommunityAccess(int $communityId): ?bool
    {
        return $this->isCommunityAdmin($communityId) || RightsHelper::checkAnyRights('{community}', $communityId) || ($this->getCommunityData()['type'] ?? '') === '{open}';
    }

    public function getCommunitiesDefault(): ?array
    {
        $communitiesDefault = [];

        if (DataHelper::getId() > 0) {
            $result = DB->query(
                "SELECT DISTINCT c.id FROM community AS c LEFT JOIN relation AS r ON r.obj_id_to=c.id WHERE r.obj_type_to='{community}' AND r.obj_type_from='{community}' AND r.obj_id_from=:obj_id_from AND r.type='{child}'",
                [
                    ['obj_id_from', DataHelper::getId()],
                ],
            );

            foreach ($result as $communityData) {
                $communitiesDefault[] = $communityData['id'];
            }
        }

        return $communitiesDefault;
    }

    public function getCommunitiesValues(): ?array
    {
        $id = DataHelper::getId();

        $communitiesValues = [];
        $communitiesFound = [];

        if (CURRENT_USER->isLogged()) {
            $result = DB->query(
                "SELECT DISTINCT c.id, c.name FROM community c LEFT JOIN relation r ON r.obj_id_to=c.id WHERE r.obj_type_to='{community}' AND r.obj_type_from='{user}' AND r.obj_id_from=:obj_id_from AND r.type NOT IN ('" . implode('\',\'', RightsHelper::getBannedTypes()) . "') ORDER BY c.name",
                [
                    ['obj_id_from', CURRENT_USER->id()],
                ],
            );

            foreach ($result as $communityData) {
                if ($communityData['id'] !== $id) {
                    $communitiesValues[] = [$communityData['id'], DataHelper::escapeOutput($communityData['name'])];
                    $communitiesFound[] = $communityData['id'];
                }
            }
        }

        if ($id > 0) {
            $result = DB->query(
                "SELECT DISTINCT c.id, c.name FROM community c LEFT JOIN relation r ON r.obj_id_to=c.id WHERE r.obj_type_to='{community}' AND r.obj_type_from='{community}' AND r.obj_id_from=:obj_id_from AND r.type='{child}' ORDER BY c.name",
                [
                    ['obj_id_from', $id],
                ],
            );

            foreach ($result as $communityData) {
                if (!in_array($communityData['id'], $communitiesFound)) {
                    $communitiesValues[] = [$communityData['id'], DataHelper::escapeOutput($communityData['name'])];
                }
            }
        }

        return $communitiesValues;
    }

    public function checkRights(): bool
    {
        return CURRENT_USER->isLogged();
    }

    public function checkRightsRestrict(): string
    {
        $id = DataHelper::getId();

        return (CURRENT_USER->isAdmin() || ($id > 0 && $this->isCommunityAdmin($id))) ? '' : 'creator_id=' . CURRENT_USER->id();
    }

    private function getCommunityData(): ?array
    {
        return DB->findObjectById(DataHelper::getId(), 'community');
    }
}
