<?php

declare(strict_types=1);

namespace App\CMSVC\Login;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Helper\LocaleHelper;
use Fraym\Interface\Response;

#[Controller(LoginController::class)]
class LoginView extends BaseView
{
    public function Response(): ?Response
    {
        $LOCALE = $this->LOCALE;
        $LOCALE_REGISTER = LocaleHelper::getLocale(['register', 'global']);
        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<div class="mainpage_login">
<div id="login_choices">
<div class="text">' . $LOCALE['enter'] . '</div>
<form action="' . ABSOLUTE_PATH . '/login/" method="POST" enctype="multipart/form-data" id="login_form" no_dynamic_content>
<input type="hidden" name="action" value="login">
<input type="text" name="login" id="login_global" placehold="' . $LOCALE['email'] . '" tabindex="1">
<input type="password" name="password" id="pass_global" placehold="' . $LOCALE['password'] . '" tabindex="2">
<a class="nonimportant" id="btn_remind">' . $LOCALE['remind_button'] . '</a>
<div class="buttons">
    <button class="main" id="btn_login" tabindex="3">' . $LOCALE['login_button'] . '</button>
    <button class="nonimportant" href="' . ABSOLUTE_PATH . '/register/">' . $LOCALE_REGISTER['button_replace_text_to'] . '</button>
</div>
</form>
';

        if (!$_ENV['OFFLINE_VERSION']) {
            $RESPONSE_DATA .= '
<hr>
<div class="text2">' . $LOCALE['enter_using'] . '</div>
<div class="or_use_social_network_list">
	<a href="' . ABSOLUTE_PATH . '/vkauth/" title="' . $LOCALE['enter_using_vkontakte'] . '" class="social_1"><span class="sbi sbi-vk"></span></a>
	<a href="' . ABSOLUTE_PATH . '/fbauth/" title="' . $LOCALE['enter_using_facebook'] . '" class="social_2"><span class="sbi sbi-fb"></span></a>
</div>
';
        }
        $RESPONSE_DATA .= '
</div>
<div id="login_remind">
<div class="text">' . $LOCALE['remind'] . '</div>
<form action="' . ABSOLUTE_PATH . '/login/" method="POST" enctype="multipart/form-data" id="remind_form">
<input type="hidden" name="action" value="remind">
<input type="text" name="em" id="em_global" placehold="' . $LOCALE['input_your_email'] . '">
<div class="buttons">
    <button class="main" id="btn_make_remind">' . $LOCALE['do_remind'] . '</button>
</div>
</form>
</div>
</div>

</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
