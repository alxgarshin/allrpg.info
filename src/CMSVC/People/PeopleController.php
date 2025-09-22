<?php

declare(strict_types=1);

namespace App\CMSVC\People;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Helper\{CMSVCHelper, DataHelper, ResponseHelper};
use Fraym\Interface\Response;

/** @extends BaseController<PeopleService> */
#[IsAccessible(
    '/login/',
    [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
    ],
)]
#[CMSVC(
    service: PeopleService::class,
    view: PeopleView::class,
)]
class PeopleController extends BaseController
{
    public function Response(): ?Response
    {
        $userId = DataHelper::getId();

        if ($userId === 0) {
            $userId = CURRENT_USER->sid();
        }

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $userData = $userService->get(null, ['sid' => $userId]);

        if (is_null($userData)) {
            ResponseHelper::redirect(ABSOLUTE_PATH . '/start/');
        } else {
            $this->getService()->setUserData($userData);
        }

        /** @var PeopleView */
        $peopleView = $this->getCMSVC()->getView();

        return $peopleView->Response();
    }
}
