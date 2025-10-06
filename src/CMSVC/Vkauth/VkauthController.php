<?php

declare(strict_types=1);

namespace App\CMSVC\Vkauth;

use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\{AuthHelper, CMSVCHelper, DataHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseController<VkauthService> */
#[CMSVC(
    service: VkauthService::class,
)]
class VkauthController extends BaseController
{
    public function Response(): ?Response
    {
        $LOCALE = $this->getLOCALE()['messages'];
        $vkAuthService = $this->getService();
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $authError = false;
        $authErrorDescription = '';
        $RESPONSE_DATA = '';

        if (!CURRENT_USER->isLogged()) {
            if ($_REQUEST['code'] ?? false) {
                $params = [
                    'client_id' => $_ENV['VK_APP_ID'],
                    'client_secret' => $_ENV['VK_APP_SECRET'],
                    'code' => $_REQUEST['code'],
                    'redirect_uri' => ABSOLUTE_PATH . '/vkauth/',
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://oauth.vk.ru/access_token');
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $html = curl_exec($ch);
                curl_close($ch);

                $response = json_decode($html, true);

                if (isset($response['access_token'])) {
                    $vkEmail = $response['email'] ?? null;

                    $params = [
                        'user_ids' => $response['user_id'],
                        'access_token' => $response['access_token'],
                        'fields' => 'sex,bdate,domain',
                        'v' => '5.131',
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.vk.ru/method/users.get');
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $html = curl_exec($ch);
                    curl_close($ch);

                    $response = json_decode($html, true);
                    $response = $response['response'][0];

                    if ($response['id'] !== '') {
                        $checkUser = DB->query(
                            'SELECT * FROM user WHERE vkontakte=:vkontakte',
                            [
                                ['vkontakte', $response['id']],
                            ],
                            true,
                        );

                        //TODO: решение на время перехода авторизации со старой механики на новую
                        if (!$checkUser) {
                            $checkUser = DB->query(
                                'SELECT * FROM user WHERE vkontakte_visible=:vkontakte OR vkontakte_visible=:vkontakte_id' . ($vkEmail ? ' OR em=:vk_email' : ''),
                                [
                                    ['vkontakte', $response['domain']],
                                    ['vkontakte_id', 'id' . $response['id']],
                                    ['vk_email', $vkEmail],
                                ],
                                true,
                            );

                            if ($checkUser) {
                                DB->update(
                                    'user',
                                    ['vkontakte' => $response['id']],
                                    ['id' => $checkUser['id']],
                                );
                            }
                        }

                        if ($checkUser) {
                            CURRENT_USER->authSetUserData($checkUser);

                            AuthHelper::generateAndSaveRefreshToken();

                            return $vkAuthService->outputRedirect();
                        } else {
                            $checkUser = DB->select(
                                'user',
                                ['em' => $vkEmail],
                                true,
                            );

                            if ($vkEmail !== '' && $checkUser) {
                                $authError = true;
                                $authErrorDescription = $LOCALE['email_already_registered'];
                            } else {
                                DB->insert(
                                    'user',
                                    [
                                        'login' => $vkEmail,
                                        'em' => $vkEmail,
                                        'fio' => $response['first_name'] . ' ' . $response['last_name'],
                                        'gender' => ($response['sex'] === 1 ? '2' : '1'),
                                        'birth' => $response['bdate'] !== '' ? date('Y-m-d', strtotime($response['bdate'])) : null,
                                        'vkontakte_visible' => $response['domain'],
                                        'vkontakte' => $response['id'],
                                        'subs_type' => 1,
                                        'subs_objects' => DataHelper::arrayToMultiselect($userService->getSubsObjectsList()),
                                        'updated_at' => time(),
                                        'created_at' => time(),
                                    ],
                                );
                                $id = DB->lastInsertId();

                                if ($id > 0) {
                                    unset($vkEmail);

                                    $userService->postRegister((int) $id);
                                    $userData = DB->select('user', ['id' => $id], true);
                                    CURRENT_USER->authSetUserData($userData);

                                    AuthHelper::generateAndSaveRefreshToken();

                                    return $vkAuthService->outputRedirect();
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
	window.location="https://oauth.vk.ru/authorize?client_id=' . $_ENV['VK_APP_ID'] . '&scope=email&redirect_uri=' . ABSOLUTE_PATH . '/vkauth/&response_type=code&v=5.131";
</script>
</div>';
            }
        } else {
            $userData = $userService->get(CURRENT_USER->id());

            if ($userData->vkontakte->get() === '') {
                $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
' . sprintf($LOCALE['already_logged'], ABSOLUTE_PATH . '/profile/') . '
</div>
</div>';
            } else {
                return $vkAuthService->outputRedirect();
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
