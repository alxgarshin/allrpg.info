<?php

declare(strict_types=1);

namespace App\CMSVC\Profile;

use App\CMSVC\User\{UserModel, UserService};
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ActEnum, ApiParamTypeEnum};
use Fraym\Helper\{CMSVCHelper, ResponseHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseController<ProfileService> */
#[IsAccessible(
    '/login/',
)]
#[CMSVC(
    model: UserModel::class,
    service: ProfileService::class,
    view: ProfileView::class,
)]
class ProfileController extends BaseController
{
    #[ApiAction(mutating: true, params: [
        new ApiParam('verify_id', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function verifyEm(): ?Response
    {
        $verifyId = $this->param('verify_id');

        if ($verifyId) {
            $LOCALE = $this->LOCALE;

            /** @var UserService $userService */
            $userService = CMSVCHelper::getService('user');

            $userData = $userService->get(CURRENT_USER->id());

            if ($userData->em_verified->get() !== '1') {
                if ($verifyId === md5($userData->id->getAsInt() . $userData->created_at->getAsTimeStamp() . $userData->em->get() . $_ENV['PROJECT_HASH_WORD'])) {
                    DB->update('user', ['em_verified' => '1'], ['id' => CURRENT_USER->id()]);
                    ResponseHelper::success($LOCALE['messages']['verification_link_good']);
                } else {
                    ResponseHelper::error($LOCALE['messages']['verification_link_bad']);
                }
            }
        }

        return $this->Default();
    }
    protected function Default(): ?Response
    {
        $responseData = $this->entity->view(ActEnum::edit, CURRENT_USER->id());

        if ($responseData instanceof HtmlResponse) {
            $this->entity->view->postViewHandler($responseData);
        }

        return $responseData;
    }
}
