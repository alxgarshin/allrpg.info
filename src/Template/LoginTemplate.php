<?php

declare(strict_types=1);

namespace App\Template;

use App\CMSVC\User\UserService;
use App\Helper\DesignHelper;
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Template;

final class LoginTemplate implements Template
{
    public static function asHTML(): string
    {
        $LOCALE = LocaleHelper::getLocale(['global']);

        if (CURRENT_USER->isLogged()) {
            /** @var UserService $userService */
            $userService = CMSVCHelper::getService('user');
            $userData = $userService->get(CURRENT_USER->id(), null, null, true, true);

            // выпадающее меню на аватарке пользователя
            $userMenuHtml = '';

            foreach ($LOCALE['user_logged_menu'] as $userLoggedMenuData) {
                if (DesignHelper::checkMenuItemVisibility($userLoggedMenuData)) {
                    if ($userLoggedMenuData['class'] !== 'hr') {
                        if (isset($userLoggedMenuData['edit'])) {
                            $userLoggedMenuData['edit'] = DesignHelper::replaceVarsInMenu($userLoggedMenuData['edit']);
                        }

                        $userMenuHtml .= '
	<div class="menu_item_wrapper ' . $userLoggedMenuData['class'] . '">';

                        if (isset($userLoggedMenuData['add'])) {
                            $userMenuHtml .= '<a class="add" href="' . ABSOLUTE_PATH . '/' . $userLoggedMenuData['add'] . '"></a>';
                        }

                        if (isset($userLoggedMenuData['edit'])) {
                            $userMenuHtml .= '<a class="edit" href="' . ABSOLUTE_PATH . '/' . $userLoggedMenuData['edit'] . '"></a>';
                        }

                        if (isset($userLoggedMenuData['counter'])) {
                            $userMenuHtml .= '<span id="' . $userLoggedMenuData['counter'] . '" class="menu_float_right">0</span>';
                        }
                        $keyInKinds = array_search(KIND, $userLoggedMenuData['kind']);

                        $userMenuHtml .= '
		<a class="menu ' . $userLoggedMenuData['class'] .
                            ($keyInKinds !== false && (
                                !isset($userLoggedMenuData['id'][$keyInKinds])
                                || $userLoggedMenuData['id'][$keyInKinds] === DataHelper::getId()
                            ) && (
                                !isset($userLoggedMenuData['not_id'][$keyInKinds])
                                || $userLoggedMenuData['not_id'][$keyInKinds] !== DataHelper::getId()
                            ) ? ' menu_selected' : '') .
                            '" href="' . ABSOLUTE_PATH . '/' . $userLoggedMenuData['kind'][0] . '/' .
                            (isset($userLoggedMenuData['id'][0]) ? DesignHelper::replaceVarsInMenu($userLoggedMenuData['id'][0]) . '/' : '') .
                            ($userLoggedMenuData['params'][0] ?? '') .
                            '"><span class="menu_name">' . $userLoggedMenuData['name'] . '</span></a>
	</div>';
                    } else {
                        $userMenuHtml .= '
	<div class="menu_item_wrapper hr">
		<hr>
	</div>';
                    }
                }
            }

            $userMenuHtml = '<div class="user_menu">
<div class="menu_wrapper">
<div class="menu_item_wrapper user_link">
    <a href="' . ABSOLUTE_PATH . '/profile/">' . $userService->showNameExtended(
                $userData,
                true,
                false,
                '',
                false,
                false,
                true,
            ) . '<span></span></a>
</div>
<div class="menu_item_wrapper hr">
    <hr>
</div>
' . $userMenuHtml . '
</div>
</div>';

            $file = $userService->photoUrl($userData, true);
            $RESPONSE_LOGIN = '<div class="login_user_data">' . $userMenuHtml . '<span id="new_personal_counter"></span><div class="photoName"><div class="photoName_photo_wrapper"><div class="photoName_photo" style="';
            $RESPONSE_LOGIN .= DesignHelper::getCssBackgroundImage($file);
            $RESPONSE_LOGIN .= '"></div></div></div></div>';
        } else {
            $RESPONSE_LOGIN = '<a class="login_btn" href="' . ABSOLUTE_PATH . '/login/"><span class="menu_name">' . $LOCALE['login_word'] . '</span></a>';
        }

        return $RESPONSE_LOGIN;
    }
}
