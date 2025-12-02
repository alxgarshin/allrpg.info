<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgCode;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights};
use Fraym\Entity\TableEntity;
use Fraym\Enum\{SubstituteDataTypeEnum};
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

#[TableEntity(
    name: 'qrpgCode',
    table: 'qrpg_code',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'category',
        ),
        new EntitySortingItem(
            tableFieldName: 'location',
        ),
        new EntitySortingItem(
            tableFieldName: 'settings',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortSettings',
        ),
        new EntitySortingItem(
            tableFieldName: 'sid',
        ),
    ],
    elementsPerPage: 5000,
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: true,
    deleteRight: true,
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(QrpgCodeController::class)]
class QrpgCodeView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $title = $LOCALE_GLOBAL['project_control_items'][KIND][0];
        $RESPONSE_DATA = $response->getHtml();

        $objData = [];

        if (DataHelper::getId() > 0) {
            $objData = DB->findObjectById(DataHelper::getId(), 'qrpg_code');

            if ($objData['location'] !== '') {
                $title = DataHelper::escapeOutput($objData['location']);
            }
        }

        $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, $title);
        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($title);

        return $response->setHtml($RESPONSE_DATA)->setPagetitle($PAGETITLE);
    }
}
