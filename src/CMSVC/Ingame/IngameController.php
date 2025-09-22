<?php

declare(strict_types=1);

namespace App\CMSVC\Ingame;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Helper\CookieHelper;
use Fraym\Interface\Response;

/** @extends BaseController<IngameService> */
#[CMSVC(
    service: IngameService::class,
    view: IngameView::class,
)]
#[IsAccessible]
class IngameController extends BaseController
{
    public function Response(): ?Response
    {
        /** @var IngameView */
        $ingameView = $this->getCMSVC()->getView();

        if (!$this->getService()->getApplicationData()) {
            return $ingameView->chooseProjectsList();
        }

        return $ingameView->applicationView();
    }

    public function createTransaction(): ?Response
    {
        return $this->getService()->createBankTransaction();
    }

    public function qrpgHackingStart(): ?Response
    {
        if (!CookieHelper::getCookie('ingame_application_id')) {
            $LOCALE = $this->getLOCALE();

            return $this->asArray(
                [
                    'response' => 'error',
                    'response_text' => $LOCALE['messages']['need_to_reload_page'],
                ],
            );
        } else {
            return $this->asArray(
                $this->getService()->QRpgHackingStart(
                    (int) ($_REQUEST['qha_id'] ?? false),
                ),
            );
        }
    }

    public function qrpgBankPay(): ?Response
    {
        if (!CookieHelper::getCookie('ingame_application_id')) {
            $LOCALE = $this->getLOCALE();

            return $this->asArray(
                [
                    'response' => 'error',
                    'response_text' => $LOCALE['messages']['need_to_reload_page'],
                ],
            );
        } else {
            return $this->asArray(
                $this->getService()->qrpgBankPay(
                    (int) ($_REQUEST['account_num_to'] ?? false),
                    (int) ($_REQUEST['bank_currency_id'] ?? false),
                    (int) ($_REQUEST['amount'] ?? false),
                    $_REQUEST['name'] ?? '',
                ),
            );
        }
    }

    public function prepareQRpgBankCode(): ?Response
    {
        if (CookieHelper::getCookie('ingame_application_id')) {
            return $this->asArray(
                $this->getService()->prepareQRpgBankCode(
                    (int) ($_REQUEST['bank_currency_id'] ?? false),
                    (int) ($_REQUEST['amount'] ?? false),
                    $_REQUEST['name'] ?? '',
                ),
            );
        }

        return null;
    }

    public function setGeoposition(): ?Response
    {
        if (CookieHelper::getCookie('ingame_application_id')) {
            return $this->asArray(
                $this->getService()->setGeoposition(
                    $_REQUEST['lat'] ?? false,
                    $_REQUEST['long'] ?? false,
                    $_REQUEST['acc'] ?? false,
                ),
            );
        }

        return $this->asArray([]);
    }

    public function qrpgGetKeysAndProperties(): ?Response
    {
        if (CookieHelper::getCookie('ingame_application_id')) {
            return $this->asArray(
                $this->getService()->qrpgGetKeysAndProperties(),
            );
        }

        return null;
    }

    public function qrpgDecode(): ?Response
    {
        if (!CookieHelper::getCookie('ingame_application_id')) {
            $LOCALE = $this->getLOCALE();

            return $this->asArray(
                [
                    'response' => 'error',
                    'response_text' => $LOCALE['messages']['need_to_reload_page'],
                ],
            );
        } else {
            return $this->asArray(
                $this->getService()->qrpgDecode(
                    ($_REQUEST['data'] ?? false) ? json_decode($_REQUEST['data'], true) : null,
                    ($_REQUEST['hacking_sequence'] ?? false) ? json_decode($_REQUEST['hacking_sequence'], true) : null,
                    (int) ($_REQUEST['qha_id'] ?? false),
                    $_REQUEST['text_to_access'] ?? null,
                    (int) ($_REQUEST['qhi_id'] ?? false),
                ),
            );
        }
    }
}
