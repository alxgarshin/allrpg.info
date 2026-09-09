<?php

declare(strict_types=1);

namespace App\CMSVC\HelperUsersList;

use App\CMSVC\User\{UserModel, UserService};
use Fraym\BaseObject\{ApiAction, ApiParam, BaseHelper, BaseModel};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, MultiselectSqlHelper, RightsHelper};
use Fraym\Interface\Response;

class HelperUsersListController extends BaseHelper
{
    private const TABLE = 'user';
    private const NAME = 'fio';
    private const NICKNAME = 'nick';
    private const SID = 'sid';
    private const HIDESOME = 'hidesome';

    #[ApiAction(params: [
        new ApiParam('input', ApiParamTypeEnum::string, default: ''),
        new ApiParam('term', ApiParamTypeEnum::string, default: ''),
        new ApiParam('no_id', ApiParamTypeEnum::bool, default: false),
        new ApiParam('full_search', ApiParamTypeEnum::bool, default: false),
        new ApiParam('obj_type', ApiParamTypeEnum::string, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function Response(): ?Response
    {
        $input = $this->param('input') ?: $this->param('term');
        $input = str_replace([':', ',', '.', '-'], '', $input);
        $isInputInt = is_numeric($input);
        $noId = $this->param('no_id');
        $fullSearch = $this->param('full_search') || CURRENT_USER->isAdmin();

        $returnArr = [];
        $sort = [];

        if (CURRENT_USER->isLogged() && ($isInputInt || mb_strlen($input) >= 3)) {
            $foundUsers = [];

            if ('' !== OBJ_TYPE && OBJ_ID > 0) {
                if (RightsHelper::checkAnyRights(DataHelper::addBraces(OBJ_TYPE), OBJ_ID)) {
                    $foundUsers = RightsHelper::findByRights(null, DataHelper::addBraces(OBJ_TYPE), OBJ_ID);

                    if (is_null($foundUsers)) {
                        $foundUsers = [];
                    }
                }
            }

            $possibleUsersIds = [];

            if (in_array(OBJ_TYPE, ['task', 'project', 'event']) && OBJ_ID > 0) {
                $parents = RightsHelper::findByRights('{child}', '{project}', null, DataHelper::addBraces(OBJ_TYPE), OBJ_ID);
                $parentType = 'project';

                if (is_null($parents)) {
                    $parents = RightsHelper::findByRights('{child}', '{community}', null, DataHelper::addBraces(OBJ_TYPE), OBJ_ID);

                    if ($parents) {
                        $parentType = 'community';
                    }
                }

                if ($parents) {
                    foreach ($parents as $parentId) {
                        $usersId = RightsHelper::findByRights(null, DataHelper::addBraces($parentType), $parentId);

                        if (!is_null($usersId)) {
                            $possibleUsersIds = array_merge($possibleUsersIds, $usersId);
                        }
                    }
                }
            }

            $colleagues = RightsHelper::findByRights('{friend}', '{user}');

            if ($colleagues) {
                $possibleUsersIds = array_merge($possibleUsersIds, $colleagues);
            }

            if (!$fullSearch && $possibleUsersIds === []) {
                $possibleUsersIds = [0];
            }

            $entityData = DB->query(
                'SELECT * FROM ' . self::TABLE . ' ' . ($input === 'base' && $fullSearch ? 'ORDER BY sid DESC LIMIT 5' : 'WHERE' .
                    ($fullSearch ? '' : ' id IN (:possibleUsersIds)') .
                    ($input === 'base' ? '' : ($fullSearch ? '' : ' AND') . ($isInputInt ? ' ' . self::SID . '=:sid' : ' ((LOWER(' . self::NAME . ') LIKE :input1 AND ' . MultiselectSqlHelper::notContains(self::HIDESOME, MultiselectSqlHelper::jsonLiteral(10)) . ') OR (LOWER(' . self::NICKNAME . ') LIKE :input2 AND ' . MultiselectSqlHelper::notContains(self::HIDESOME, MultiselectSqlHelper::jsonLiteral(0)) . '))'))),
                [
                    ['sid', $input],
                    ['input1', '%' . mb_strtolower($input) . '%'],
                    ['input2', '%' . mb_strtolower($input) . '%'],
                    ['possibleUsersIds', $possibleUsersIds],
                ],
            );

            /** @var UserService $userService */
            $userService = CMSVCHelper::getService('user');

            foreach ($entityData as $entityItem) {
                if (!in_array($entityItem['id'], $foundUsers)) {
                    $value = $this->printItem($userService->arrayToModel($entityItem), $noId);
                    $returnArr[] = [
                        'id' => $entityItem['id'],
                        'sid' => $entityItem['sid'],
                        'value' => $value,
                    ];
                    $sort[] = mb_strtolower($value);
                }
            }
        }
        array_multisort($sort, SORT_ASC, $returnArr);

        return $this->asArray($returnArr);
    }

    public function printOut(int|string|null $id, bool $noId = false): string
    {
        $content = '';

        if (!is_null($id)) {
            /** @var UserService $userService */
            $userService = CMSVCHelper::getService('user');
            $entityItem = $userService->get($id);
            $content = $userService->showNameExtended($entityItem, false, false, '', false, !$noId, true);
        }

        return $content;
    }

    /** @param UserModel|null $entityItem */
    public function printItem(?BaseModel $entityItem, bool $noId = false): string
    {
        $content = '';

        if (!is_null($entityItem)) {
            /** @var UserService $userService */
            $userService = CMSVCHelper::getService('user');
            $content = $userService->showNameExtended($entityItem, false, false, '', false, !$noId, true);
        }

        return $content;
    }
}
