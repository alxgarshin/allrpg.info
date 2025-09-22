<?php

declare(strict_types=1);

namespace App\CMSVC\Mobile;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Helper\LocaleHelper;
use Fraym\Interface\Response;

#[Controller(MobileController::class)]
class MobileView extends BaseView
{
    public function Response(): ?Response
    {
        $LOCALE = $this->getLOCALE();
        $LOCALE_PWA = LocaleHelper::getLocale(['global', 'pwa']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);

        $RESPONSE_DATA = '<div class="maincontent kind_' . KIND . '">
<h1 class="page_header">' . $LOCALE['title'] . '</h1>
<div class="page_block margin_top">';

        $RESPONSE_DATA .= '<div class="PWAinfo">
    <div class="android">
        <div class="PWAdescription">' . $LOCALE_PWA['description'] . '</div>
        <div class="installPWA">' . $LOCALE_PWA['android']['install'] . '</div>
        <div class="PWAsuccess">' . $LOCALE_PWA['success_btn'] . '</div>
    </div>
    <div class="ios">
        <div class="PWAdescription">' . $LOCALE_PWA['description'] . '</div>
        <div class="installPWA">' . $LOCALE_PWA['ios']['install'] . '</div>
        <div class="PWAdescription">' . $LOCALE_PWA['already_installed'] . '</div>
    </div>
    <div class="undefined">
        <div class="PWAdescription">' . $LOCALE_PWA['description'] . '</div>
        <div class="installPWA">' . $LOCALE_PWA['undefined']['help'] . '</div>
        <div class="PWAdescription">' . $LOCALE_PWA['already_installed'] . '</div>
    </div>
</div>';

        $RESPONSE_DATA .= '</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
