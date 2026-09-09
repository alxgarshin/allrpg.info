<?php

declare(strict_types=1);

namespace App\CMSVC\Ingame;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\ApiParamTypeEnum;
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
        $ingameView = $this->CMSVC->view;

        if (!$this->service->getApplicationData()) {
            return $ingameView->chooseProjectsList();
        }

        return $ingameView->applicationView();
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('to_project_application_id[0]', ApiParamTypeEnum::int, obligatory: true),
        new ApiParam('from_bank_currency_id[0]', ApiParamTypeEnum::int, obligatory: true),
        new ApiParam('amount_from[0]', ApiParamTypeEnum::int, obligatory: true),
        new ApiParam('bank_currency_id[0]', ApiParamTypeEnum::int, obligatory: true),
        new ApiParam('name[0]', ApiParamTypeEnum::string),
    ])]
    public function createTransaction(): ?Response
    {
        return $this->service->createBankTransaction();
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('qha_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function qrpgHackingStart(): ?Response
    {
        if (!CookieHelper::getCookie('ingame_application_id')) {
            $LOCALE = $this->LOCALE;

            return $this->asArray(
                [
                    'response' => 'error',
                    'response_text' => $LOCALE['messages']['need_to_reload_page'],
                ],
            );
        } else {
            return $this->asArray(
                $this->service->QRpgHackingStart(
                    $this->param('qha_id'),
                ),
            );
        }
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('account_num_to', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('bank_currency_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('amount', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('name', ApiParamTypeEnum::string, default: ''),
    ])]
    public function qrpgBankPay(): ?Response
    {
        if (!CookieHelper::getCookie('ingame_application_id')) {
            $LOCALE = $this->LOCALE;

            return $this->asArray(
                [
                    'response' => 'error',
                    'response_text' => $LOCALE['messages']['need_to_reload_page'],
                ],
            );
        } else {
            return $this->asArray(
                $this->service->qrpgBankPay(
                    $this->param('account_num_to'),
                    $this->param('bank_currency_id'),
                    $this->param('amount'),
                    $this->param('name'),
                ),
            );
        }
    }

    #[ApiAction(params: [
        new ApiParam('bank_currency_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('amount', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('name', ApiParamTypeEnum::string, default: ''),
    ])]
    public function prepareQRpgBankCode(): ?Response
    {
        if (CookieHelper::getCookie('ingame_application_id')) {
            return $this->asArray(
                $this->service->prepareQRpgBankCode(
                    $this->param('bank_currency_id'),
                    $this->param('amount'),
                    $this->param('name'),
                ),
            );
        }

        return null;
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('lat', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('long', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('acc', ApiParamTypeEnum::string, default: ''),
    ])]
    public function setGeoposition(): ?Response
    {
        if (CookieHelper::getCookie('ingame_application_id')) {
            return $this->asArray(
                $this->service->setGeoposition(
                    $this->param('lat'),
                    $this->param('long'),
                    $this->param('acc'),
                ),
            );
        }

        return $this->asArray([]);
    }

    #[ApiAction]
    public function qrpgGetKeysAndProperties(): ?Response
    {
        if (CookieHelper::getCookie('ingame_application_id')) {
            return $this->asArray(
                $this->service->qrpgGetKeysAndProperties(),
            );
        }

        return null;
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('data', ApiParamTypeEnum::string),
        new ApiParam('hacking_sequence', ApiParamTypeEnum::string),
        new ApiParam('qha_id', ApiParamTypeEnum::int, default: 0),
        new ApiParam('text_to_access', ApiParamTypeEnum::string),
        new ApiParam('qhi_id', ApiParamTypeEnum::int, default: 0),
    ])]
    public function qrpgDecode(): ?Response
    {
        if (!CookieHelper::getCookie('ingame_application_id')) {
            $LOCALE = $this->LOCALE;

            return $this->asArray(
                [
                    'response' => 'error',
                    'response_text' => $LOCALE['messages']['need_to_reload_page'],
                ],
            );
        } else {
            $qrpgData = $this->param('data');
            $hackingSequence = $this->param('hacking_sequence');

            return $this->asArray(
                $this->service->qrpgDecode(
                    $qrpgData ? json_decode($qrpgData, true) : null,
                    $hackingSequence ? json_decode($hackingSequence, true) : null,
                    $this->param('qha_id'),
                    $this->param('text_to_access'),
                    $this->param('qhi_id'),
                ),
            );
        }
    }
}
