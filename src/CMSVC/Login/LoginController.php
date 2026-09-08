<?php

declare(strict_types=1);

namespace App\CMSVC\Login;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC};
use Fraym\Enum\{ApiParamTypeEnum, ResponseErrorCodeEnum};
use Fraym\Helper\{AuthHelper, CookieHelper, DataHelper, LocaleHelper, ResponseHelper};
use Fraym\Interface\Response;
use Fraym\Service\AuthTokenService;

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
            $LOCALE = $this->LOCALE['messages'];
            ResponseHelper::error($LOCALE['need_to_login_or_register_for_that']);
        }

        return $this->Default();
    }

    /** Восстановление пароля */
    public function remind(): void
    {
        if (!AuthHelper::validatePreAuthCsrfToken()) {
            ResponseHelper::response403();
        }

        /** @var LoginService $loginService */
        $loginService = $this->CMSVC->service;
        $loginService->remindPassword();
    }

    /** Обновление JWT: пишет токен в httpOnly cookie authToken (тело ответа пустое — токен недоступен JS) */
    public function refreshToken(): void
    {
        $refreshToken = AuthHelper::getRefreshTokenCookie();

        if (!is_null($refreshToken)) {
            $loginData = AuthTokenService::findUserByRefreshToken($refreshToken);

            if (!is_null($loginData)) {
                CURRENT_USER->authSetUserData($loginData);
                AuthTokenService::prolongRefreshToken($refreshToken);
                AuthHelper::setAuthTokenCookie(AuthHelper::generateAuthToken());
            } else {
                AuthHelper::removeRefreshTokenCookie();
                AuthHelper::removeAuthTokenCookie();
            }
        }

        ResponseHelper::terminate();
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('login', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('password', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function apiToken(): void
    {
        $LOCALE = LocaleHelper::getLocale(['fraym', 'basefunc']);

        $login = $this->param('login');
        $password = $this->param('password');

        if ($login === '' || $password === '') {
            ResponseHelper::responseOneBlock('error', $LOCALE['wrong_login_or_password'], [], ResponseErrorCodeEnum::unauthorized);
        }

        $retryAfter = AuthTokenService::getRetryAfter($login);

        if (!is_null($retryAfter)) {
            header('Retry-After: ' . $retryAfter);
            ResponseHelper::responseOneBlock('error', $LOCALE['too_many_auth_attempts'], [], ResponseErrorCodeEnum::rateLimited);
        }

        $loginData = CURRENT_USER->authenticateForApi($login, $password);

        if (is_null($loginData)) {
            AuthTokenService::registerFailedAttempt($login);
            ResponseHelper::responseOneBlock('error', $LOCALE['wrong_login_or_password'], [], ResponseErrorCodeEnum::unauthorized);
        }

        AuthTokenService::clearAttempts($login);

        $this->printTokens($loginData, AuthTokenService::issueRefreshToken($loginData['id']));
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('refresh_token', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function apiRefreshToken(): void
    {
        $LOCALE = LocaleHelper::getLocale(['fraym', 'basefunc']);

        $refreshToken = $this->param('refresh_token');
        $loginData = $refreshToken === '' ? null : AuthTokenService::findUserByRefreshToken($refreshToken);

        if (is_null($loginData)) {
            ResponseHelper::responseOneBlock('error', $LOCALE['must_revalidate_user'], [], ResponseErrorCodeEnum::unauthorized);
        }

        AuthTokenService::revokeRefreshToken($refreshToken);

        $this->printTokens($loginData, AuthTokenService::issueRefreshToken($loginData['id']));
    }

    private function printTokens(array $loginData, string $refreshToken): never
    {
        CURRENT_USER->authSetUserData($loginData);

        ResponseHelper::setCorsHeaders();

        print DataHelper::jsonFixedEncode(ResponseHelper::buildEnvelope([
            'response' => 'success',
            'response_data' => [
                'token_type' => 'Bearer',
                'access_token' => AuthHelper::generateAuthToken(),
                'expires_in' => AuthTokenService::ACCESS_TOKEN_TTL,
                'refresh_token' => $refreshToken,
            ],
        ]));

        ResponseHelper::terminate();
    }
}
