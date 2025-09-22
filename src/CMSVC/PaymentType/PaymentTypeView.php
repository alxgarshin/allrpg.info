<?php

declare(strict_types=1);

namespace App\CMSVC\PaymentType;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights};
use Fraym\Helper\CookieHelper;
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<PaymentTypeService> */
#[MultiObjectsEntity(
    name: 'paymentType',
    table: 'project_payment_type',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'name',
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
        ),
    ],
    elementsPerPage: 5000,
)]
#[Rights(
    viewRight: true,
    addRight: 'checkRights',
    changeRight: 'checkRights',
    deleteRight: 'checkRights',
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(PaymentTypeController::class)]
class PaymentTypeView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $RESPONSE_DATA = $response->getHtml();

        $LOCALE = $this->getLOCALE();

        /** Если в проекте еще нет выставленных свойств оплаты картой, то даем диалог */
        if (count($_ENV['USE_PAYMENT_SYSTEMS']) > 0) {
            $checkLocale = CookieHelper::getCookie('locale') === 'RU';

            $checkPkProjectFieldsFilled = $this->getService()->checkProjectFieldsFilled('paykeeper');
            $checkPmProjectFieldsFilled = $this->getService()->checkProjectFieldsFilled('paymaster');
            $checkYkProjectFieldsFilled = $this->getService()->checkProjectFieldsFilled('yandex');

            if (in_array('paykeeper', $_ENV['USE_PAYMENT_SYSTEMS']) && $checkLocale) {
                $RESPONSE_DATA = preg_replace('#<form action="/' . KIND . '/"#', '<div class="payment_type_online_add"><button class="main show_hidden">' . $LOCALE['add_card_type_pk'] . '</button>
<div class="hidden">
<div class="add_card_type_text publication_content">' . preg_replace('#check_pk_payment_type_id#', !$this->getService()->checkPaymentTypeId('paykeeper') ? '' : ' class="checked"', preg_replace('#check_pk_project_fields_filled#', $checkPkProjectFieldsFilled ? ' class="checked"' : '', $LOCALE['add_card_type_text_pk'])) . '
<form action="/' . KIND . '/" method="POST" no_dynamic_content><input type="hidden" name="action" value="pk_add"><button class="main">' . $LOCALE['pk_add_btn'] . '</button></form></div>
</div></div><form action="/' . KIND . '/"', $RESPONSE_DATA);
            }

            if (in_array('paymaster', $_ENV['USE_PAYMENT_SYSTEMS']) && $checkLocale) {
                $RESPONSE_DATA = preg_replace('#<form action="/' . KIND . '/"#', '<div class="payment_type_online_add"><button class="main show_hidden">' . $LOCALE['add_card_type_pm'] . '</button>
<div class="hidden">
<div class="add_card_type_text publication_content">' . preg_replace('#check_pm_payment_type_id#', !$this->getService()->checkPaymentTypeId('paymaster') ? '' : ' class="checked"', preg_replace('#check_pm_project_fields_filled#', $checkPmProjectFieldsFilled ? ' class="checked"' : '', $LOCALE['add_card_type_text_pm'])) . '
<form action="/' . KIND . '/" method="POST" no_dynamic_content><input type="hidden" name="action" value="pm_add"><button class="main">' . $LOCALE['pm_add_btn'] . '</button></form></div>
</div></div><form action="/' . KIND . '/"', $RESPONSE_DATA);
            }

            if (in_array('yandex', $_ENV['USE_PAYMENT_SYSTEMS']) && $checkLocale) {
                $RESPONSE_DATA = preg_replace('#<form action="/' . KIND . '/"#', '<div class="payment_type_online_add"><button class="main show_hidden">' . $LOCALE['add_card_type_yk'] . '</button>
<div class="hidden">
<div class="add_card_type_text publication_content">' . preg_replace('#check_yk_payment_type_id#', !$this->getService()->checkPaymentTypeId('yandex') ? '' : ' class="checked"', preg_replace('#check_yk_project_fields_filled#', $checkYkProjectFieldsFilled ? ' class="checked"' : '', $LOCALE['add_card_type_text_yk'])) . '
<form action="/' . KIND . '/" method="POST" no_dynamic_content><input type="hidden" name="action" value="yk_add"><button class="main">' . $LOCALE['yk_add_btn'] . '</button></form></div>
</div></div><form action="/' . KIND . '/"', $RESPONSE_DATA);
            }

            if (in_array('payanyway', $_ENV['USE_PAYMENT_SYSTEMS'])) {
                $RESPONSE_DATA = preg_replace('#<form action="/' . KIND . '/"#', '<div class="payment_type_online_add"><button class="main show_hidden">' . $LOCALE['add_card_type_paw'] . '</button>
<div class="hidden">
<div class="add_card_type_text publication_content">' . $LOCALE['add_card_type_text_paw'] . '
<form action="/' . KIND . '/" method="POST" no_dynamic_content><input type="hidden" name="action" value="paw_add"><button class="main">' . $LOCALE['paw_add_btn'] . '</button></form></div>
</div></div><form action="/' . KIND . '/"', $RESPONSE_DATA);
            }
        }

        return $response->setHtml($RESPONSE_DATA);
    }
}
