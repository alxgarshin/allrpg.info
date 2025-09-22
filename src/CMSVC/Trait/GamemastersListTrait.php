<?php

declare(strict_types=1);

namespace App\CMSVC\Trait;

use App\Helper\RightsHelper;

/** Функция получения списка мастеров проекта */
trait GamemastersListTrait
{
    use ProjectDataTrait;
    use UserServiceTrait;

    private ?array $gamemastersList = null;

    public function getGamemastersList(array $projectRights = ['{admin}', '{gamemaster}']): ?array
    {
        if (is_null($this->gamemastersList)) {
            $gamemastersList = [];

            if ($this->getActivatedProjectId() > 0) {
                $userService = $this->getUserService();

                $allusersDataSort = [];
                $gamemasters = RightsHelper::findByRights(
                    $projectRights,
                    '{project}',
                    $this->getActivatedProjectId(),
                    '{user}',
                    false,
                ) ?? [];

                foreach ($gamemasters as $gamemaster) {
                    $gamemastersList[] = [
                        $gamemaster,
                        $userService->showNameWithId($userService->get($gamemaster)),
                    ];
                    $allusersDataSort[$gamemaster] = mb_strtolower(
                        $userService->showNameWithId($userService->get($gamemaster)),
                    );
                }
                array_multisort($allusersDataSort, SORT_ASC, $gamemastersList);
            }

            $this->gamemastersList = $gamemastersList;
        }

        return $this->gamemastersList;
    }
}
