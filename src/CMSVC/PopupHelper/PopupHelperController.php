<?php

declare(strict_types=1);

namespace App\CMSVC\PopupHelper;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Interface\Response;

/** @extends BaseController<PopupHelperService> */
#[CMSVC(
    service: PopupHelperService::class,
)]
class PopupHelperController extends BaseController
{
    #[IsAccessible]
    public function getUnreadPeople(): ?Response
    {
        return $this->asArray($this->service->getUnreadPeople(OBJ_ID));
    }

    #[IsAccessible]
    public function getTaskUnreadPeople(): ?Response
    {
        return $this->asArray($this->service->getTaskUnreadPeople(OBJ_ID));
    }

    #[IsAccessible]
    public function getApplicationUnreadPeople(): ?Response
    {
        return $this->asArray($this->service->getApplicationUnreadPeople(OBJ_ID));
    }

    public function getVote(): ?Response
    {
        return $this->asArray($this->service->getVote(OBJ_ID, $_REQUEST['value'] ?? null));
    }

    public function getImportant(): ?Response
    {
        return $this->asArray($this->service->getImportant(OBJ_TYPE, OBJ_ID));
    }

    public function getAuthors(): ?Response
    {
        return $this->asArray($this->service->getAuthors(OBJ_TYPE, OBJ_ID));
    }

    public function showUserInfo(): ?Response
    {
        return $this->asArray(
            $this->service->showUserInfo(
                OBJ_TYPE,
                OBJ_ID,
                (int) ($_REQUEST['value'] ?? 0),
            ),
        );
    }

    public function showUserInfoFromRolelist(): ?Response
    {
        return $this->asArray(
            $this->service->showUserInfo(
                OBJ_TYPE,
                OBJ_ID,
                (int) ($_REQUEST['value'] ?? 0),
            ),
        );
    }
}
