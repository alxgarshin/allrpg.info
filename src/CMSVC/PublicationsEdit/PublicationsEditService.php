<?php

declare(strict_types=1);

namespace App\CMSVC\PublicationsEdit;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\{CMSVCHelper, DataHelper};
use Generator;

/** @extends BaseService<PublicationsEditModel> */
#[Controller(PublicationsEditController::class)]
class PublicationsEditService extends BaseService
{
    public function checkRights(): bool
    {
        return CURRENT_USER->isLogged();
    }

    public function checkRightsRestrict(): string
    {
        return (CURRENT_USER->isAdmin() || CURRENT_USER->checkAllRights('info')) ? '' : 'creator_id=' . CURRENT_USER->id();
    }

    public function getAuthorValues(): array
    {
        $authorsList = [];

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $authorIds = [];

        $authorIds[] = CURRENT_USER->id();

        if (DataHelper::getId() > 0) {
            $publicationData = DB->findObjectById(DataHelper::getId(), 'publication');
            $authorsData = DataHelper::multiselectToArray($publicationData['author'] ?? '');

            if ($authorsData) {
                foreach ($authorsData as $authorId) {
                    if (is_numeric($authorId)) {
                        $authorIds[] = (int) $authorId;
                    }
                }
            }
        }

        $contacts = $userService->getCurrentUserContactsIds();

        if ($contacts) {
            foreach ($contacts as $contactId) {
                $authorIds[] = (int) $contactId;
            }
        }

        $authorIds = array_unique($authorIds);

        $usersData = $userService->getAll(['id' => $authorIds]);

        foreach ($usersData as $userData) {
            $authorsList[] = [
                $userData->id->getAsInt(),
                $userService->showNameWithId($userData),
            ];
        }

        return $authorsList;
    }

    public function getTagsValues(): Generator
    {
        return DB->getArrayOfItems('tag ORDER BY code, name', 'id', 'name');
    }
}
