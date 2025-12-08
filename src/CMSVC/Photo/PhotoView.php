<?php

declare(strict_types=1);

namespace App\CMSVC\Photo;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{Rights, TableEntity};
use Fraym\Helper\LocaleHelper;
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<PhotoService> */
#[TableEntity(
    name: 'photo',
    table: 'photo',
    sortingData: [],
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: false,
    deleteRight: false,
)]
#[Controller(PhotoController::class)]
class PhotoView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $LOCALE = $this->LOCALE;
        $LOCALE_HELP = LocaleHelper::getLocale(['help', 'global']);

        $photoService = $this->service;
        $hash = $photoService->getHash();

        $html = $response->getHtml();

        $help = '<input type="hidden" name="hash[0]" value="' . $hash . '" /><div class="field" id="field_regstamp[0]"><img src="' . ABSOLUTE_PATH . '/scripts/captcha/hash=' . $hash . '" style="width:200px; height:60px; float: right; margin-right: 1px;" /><div class="fieldname" id="name_regstamp[0]" tabindex="8">' . $LOCALE_HELP['captcha'] . '</div><div class="fieldvalue" id="div_regstamp[0]"><input type="text" name="regstamp[0]" minlength="6" maxlength="6" class="inputtext obligatory" /><a action_request="user/get_captcha"><span class="sbi sbi-refresh"></span></a></div></div>';

        $html = str_replace('<div class="field checkbox" id="field_go_back_after_save[0]">', $help . '<div class="field checkbox" id="field_go_back_after_save[0]">', $html);
        $html = str_replace($LOCALE['auto_button_text_to_replace'], $LOCALE['button_text'], $html);

        return $response->setHtml($html);
    }
}
