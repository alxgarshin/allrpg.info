<?php

declare(strict_types=1);

namespace App\CMSVC\Wall2;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseService, Controller};

#[Controller(Wall2Controller::class)]
class Wall2Service extends BaseService
{
    public function getMyGroups(): array
    {
        $myGroups = [];

        if (CURRENT_USER->isLogged()) {
            $myCommunities = RightsHelper::findByRights(null, '{community}');
            $myProjects = RightsHelper::findByRights(null, '{project}');
            $myGroups = [];

            if ($myCommunities) {
                foreach ($myCommunities as $value) {
                    $comm = DB->findObjectById($value, 'community');

                    if ($comm) {
                        $myGroups[] = array_merge(['group' => 'community'], $comm);
                    }
                }
            }

            if ($myProjects) {
                foreach ($myProjects as $value) {
                    $pro = DB->findObjectById($value, 'project');

                    if ($pro) {
                        $myGroups[] = array_merge(['group' => 'project'], $pro);
                    }
                }
            }

            if (count($myGroups) > 0) {
                $myGroupsSort = [];

                foreach ($myGroups as $key => $row) {
                    $myGroupsSort[$key] = mb_strtolower($row['name']);
                }
                array_multisort($myGroupsSort, SORT_ASC, $myGroups);
            }
        }

        return $myGroups;
    }
}
