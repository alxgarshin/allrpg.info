<?php

declare(strict_types=1);

namespace App\CMSVC\Registration;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Helper\LocaleHelper;
use Fraym\Interface\Response;

/** @extends BaseView<RegistrationService> */
#[Controller(RegistrationController::class)]
class RegistrationView extends BaseView
{
    public function Response(): ?Response
    {
        $registrationService = $this->getService();

        $LOCALE = $this->getLOCALE();
        $LOCALE_PROJECT = LocaleHelper::getLocale(['project', 'global']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '"><h1 class="page_header">' . $LOCALE['title'] . '</h1><a class="ctrlink" href="' . ABSOLUTE_PATH . '/myapplication/act=add&project_id=' . $registrationService->getActivatedProjectId() . '"><span class="sbi sbi-plus"></span>' . $LOCALE_PROJECT['send_application'] . '</a><div class="page_blocks margin_top">';

        $RESPONSE_DATA .= '<input type="text" id="registration_search" placehold="' . $LOCALE['type_data_to_search'] . '" autocomplete="off">
<div id="registration_result"></div>';

        $RESPONSE_DATA .= '</div></div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
