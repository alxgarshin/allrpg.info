<?php

declare(strict_types=1);

namespace App\CMSVC\Register;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<RegisterService> */
#[TableEntity(
    name: 'register',
    table: 'user',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'fio',
        ),
    ],
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: false,
    deleteRight: false,
    viewRestrict: 'checkRightsRestrict',
)]
#[Controller(RegisterController::class)]
class RegisterView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $LOCALE = $this->LOCALE;

        $registerService = $this->service;
        $hash = $registerService->getHash();

        $html = $response->getHtml();

        $register = '<input type="hidden" name="hash[0]" value="' . $hash . '" /><div class="field" id="field_regstamp[0]"><img src="' . ABSOLUTE_PATH . '/scripts/captcha/hash=' . $hash . '" style="width:200px; height:60px; float: right; margin-right: 1px;" /><div class="fieldname" id="name_regstamp[0]" tabindex="8">' . $LOCALE['captcha'] . '</div><div class="fieldvalue" id="div_regstamp[0]"><input type="text" name="regstamp[0]" minlength="6" maxlength="6" class="inputtext obligatory" /><a action_request="user/get_captcha"><span class="sbi sbi-refresh"></span></a></div></div>';

        $html = str_replace('<div class="field checkbox" id="field_agreement[0]">', $register . '<div class="field checkbox" id="field_agreement[0]">', $html);
        $html = str_replace($LOCALE['button_replace_text'], $LOCALE['button_replace_text_to'], $html);
        $html = str_replace('<form', '<form autocomplete="off" no_dynamic_content', $html);

        return $response->setHtml($html);
    }
}
