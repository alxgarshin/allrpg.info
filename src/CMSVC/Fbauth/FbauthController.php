<?php

declare(strict_types=1);

namespace App\CMSVC\Fbauth;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\{AuthHelper, CMSVCHelper, DataHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseController<FbauthService> */
#[CMSVC(
    service: FbauthService::class,
)]
class FbauthController extends BaseController
{
    public function Response(): ?Response
    {
        $LOCALE = $this->getLOCALE()['messages'];
        $fbAuthService = $this->getService();
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $authError = false;
        $authErrorDescription = '';
        $RESPONSE_DATA = '';

        if (!CURRENT_USER->isLogged()) {
            if ($_REQUEST['code'] ?? false) {
                $params = [
                    'client_id' => $_ENV['FB_APP_ID'],
                    'client_secret' => $_ENV['FB_APP_SECRET'],
                    'grant_type' => 'client_credentials',
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/oauth/access_token');
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $html = curl_exec($ch);
                curl_close($ch);

                $app_response = json_decode($html, true);

                $params = [
                    'client_id' => $_ENV['FB_APP_ID'],
                    'client_secret' => $_ENV['FB_APP_SECRET'],
                    'code' => $_REQUEST['code'],
                    'redirect_uri' => ABSOLUTE_PATH . '/fb_auth/',
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/oauth/access_token');
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $html = curl_exec($ch);
                curl_close($ch);

                $response = json_decode($html, true);

                $params = [
                    'input_token' => $response['access_token'],
                    'access_token' => $app_response['access_token'],
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v2.2/debug_token?' . http_build_query($params));
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $html = curl_exec($ch);
                curl_close($ch);

                $debug_response = json_decode($html, true);
                $debug_response = $debug_response['data'];

                if (isset($response['access_token'])) {
                    $fbEmail = $response['email'] ?? null;

                    $params = [
                        'access_token' => $response['access_token'],
                        'fields' => 'email,name,first_name,last_name',
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v2.2/me?' . http_build_query($params));
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $html = curl_exec($ch);
                    curl_close($ch);

                    $response = json_decode($html, true);

                    if ($response['id'] !== '') {
                        $checkUser = DB->query(
                            'SELECT * FROM user WHERE facebook=:facebook',
                            [
                                ['facebook', $response['id']],
                            ],
                            true,
                        );

                        if ($checkUser) {
                            CURRENT_USER->authSetUserData($checkUser);

                            AuthHelper::generateAndSaveRefreshToken();

                            return $fbAuthService->outputRedirect();
                        } else {
                            $checkUser = DB->select(
                                'user',
                                ['em' => $fbEmail],
                                true,
                            );

                            if ($fbEmail && $checkUser) {
                                $authError = true;
                                $authErrorDescription = $LOCALE['email_already_registered'];
                            } else {
                                DB->insert(
                                    'user',
                                    [
                                        'login' => $fbEmail,
                                        'em' => $fbEmail,
                                        'fio' => $response['first_name'] . ' ' . $response['last_name'],
                                        'gender' => ($response['gender'] === 'male' ? '1' : '2'),
                                        'birth' => $response['birthday'] !== '' ? date('Y-m-d', strtotime($response['birthday'])) : null,
                                        'facebook' => $response['id'],
                                        'subs_type' => 1,
                                        'subs_objects' => DataHelper::arrayToMultiselect($userService->getSubsObjectsList()),
                                        'updated_at' => time(),
                                        'created_at' => time(),
                                    ],
                                );

                                $id = DB->lastInsertId();

                                if ($id > 0) {
                                    $userService->postRegister((int) $id);
                                    $userData = DB->select('user', ['id' => $id], true);
                                    CURRENT_USER->authSetUserData($userData);

                                    AuthHelper::generateAndSaveRefreshToken();

                                    return $fbAuthService->outputRedirect();
                                } else {
                                    $authError = true;
                                }
                            }
                        }
                    } else {
                        $authError = true;
                    }
                } else {
                    $authError = true;
                }
            } elseif ($_REQUEST['error_description'] ?? false) {
                $authError = true;
            } else {
                $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
' . $LOCALE['redirect_to_auth'] . '
</div>
<script>
	window.location="https://www.facebook.com/dialog/oauth?client_id=' . $_ENV['FB_APP_ID'] . '&scope=public_profile,email&redirect_uri=' . ABSOLUTE_PATH . '/fb_auth/&response_type=code";
</script>
</div>';
            }
        } else {
            $userData = $userService->get(CURRENT_USER->id());

            if ($userData->facebook->get() === '') {
                $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
' . sprintf($LOCALE['already_logged'], ABSOLUTE_PATH . '/profile/') . '
</div>
</div>';
            } else {
                return $fbAuthService->outputRedirect();
            }
        }

        if ($authError) {
            $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
' . $LOCALE['couldnt_auth'] . '<br><br>
' . $authErrorDescription . '
</div>
</div>';
        }

        return new HtmlResponse($RESPONSE_DATA);
    }
}
