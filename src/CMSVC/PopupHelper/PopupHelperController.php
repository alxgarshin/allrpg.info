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
        return $this->asArray($this->getService()->getUnreadPeople(OBJ_ID));
    }

    #[IsAccessible]
    public function getTaskUnreadPeople(): ?Response
    {
        return $this->asArray($this->getService()->getTaskUnreadPeople(OBJ_ID));
    }

    #[IsAccessible]
    public function getApplicationUnreadPeople(): ?Response
    {
        return $this->asArray($this->getService()->getApplicationUnreadPeople(OBJ_ID));
    }

    public function getVote(): ?Response
    {
        return $this->asArray($this->getService()->getVote(OBJ_ID, $_REQUEST['value'] ?? null));
    }

    public function getImportant(): ?Response
    {
        return $this->asArray($this->getService()->getImportant(OBJ_TYPE, OBJ_ID));
    }

    public function getAuthors(): ?Response
    {
        return $this->asArray($this->getService()->getAuthors(OBJ_TYPE, OBJ_ID));
    }

    public function showUserInfo(): ?Response
    {
        return $this->asArray(
            $this->getService()->showUserInfo(
                OBJ_TYPE,
                OBJ_ID,
                (int) ($_REQUEST['value'] ?? 0),
            ),
        );
    }

    public function showUserInfoFromRolelist(): ?Response
    {
        return $this->asArray(
            $this->getService()->showUserInfo(
                OBJ_TYPE,
                OBJ_ID,
                (int) ($_REQUEST['value'] ?? 0),
            ),
        );
    }
}
