<?php

declare(strict_types=1);

namespace App\CMSVC\Register;

use App\CMSVC\User\{UserModel, UserService};
use Fraym\BaseObject\{BaseController, CMSVC, DependencyInjection};
use Fraym\Enum\ActEnum;
use Fraym\Helper\ResponseHelper;
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseController<RegisterService> */
#[CMSVC(
    model: UserModel::class,
    service: RegisterService::class,
    view: RegisterView::class,
)]
class RegisterController extends BaseController
{
    #[DependencyInjection]
    public UserService $userService;

    protected function Default(): ?Response
    {
        $LOCALE = $this->LOCALE['messages'];

        if (CURRENT_USER->isLogged()) {
            ResponseHelper::redirect('/start/');
        }

        if (!$this->userService->registrationIsOpen()) {
            ResponseHelper::error($LOCALE['registration_is_closed']);
            ResponseHelper::redirect('/login/');
        }

        $responseData = $this->entity->view(ActEnum::add);

        if ($responseData instanceof HtmlResponse) {
            $this->entity->view->postViewHandler($responseData);

            ResponseHelper::info($LOCALE['you_will_be_able_to_update_your_info_after_registration']);
        }

        return $responseData;
    }
}
