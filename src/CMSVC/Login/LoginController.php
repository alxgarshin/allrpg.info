<?php

declare(strict_types=1);

namespace App\CMSVC\Login;

use DateTime;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\{AuthHelper, CookieHelper, ResponseHelper};
use Fraym\Interface\Response;

/** @extends BaseController<LoginService> */
#[CMSVC(
    service: LoginService::class,
    view: LoginView::class,
)]
class LoginController extends BaseController
{
    public function Response(): ?Response
    {
        if (CURRENT_USER->isLogged()) {
            ResponseHelper::redirect('/start/');
        }

        if (!is_null(CookieHelper::getCookie('redirectToKind'))) {
            $LOCALE = $this->getLOCALE()['messages'];
            ResponseHelper::error($LOCALE['need_to_login_or_register_for_that']);
        }

        return $this->Default();
    }

    /** Восстановление пароля */
    public function remind(): void
    {
        /** @var LoginService $loginService */
        $loginService = $this->getCMSVC()->getService();
        $loginService->remindPassword();
    }

    /** Получение JWT-токена */
    public function refreshToken(): void
    {
        $refreshToken = AuthHelper::getRefreshTokenCookie();

        if (!is_null($refreshToken)) {
            $loginData = DB->select('user', ['refresh_token' => $refreshToken], true);

            if ($loginData) {
                CURRENT_USER->authSetUserData($loginData);

                if (($loginData['refresh_token_exp'] ?? false) && strtotime($loginData['refresh_token_exp']) > time()) {
                    DB->update('user', [['refresh_token_exp', new DateTime('+30 days')]], ['id' => CURRENT_USER->id()]);
                }

                echo AuthHelper::generateAuthToken();
            } else {
                AuthHelper::removeRefreshTokenCookie();
            }
        }
        exit;
    }
}
