<?php

declare(strict_types=1);

namespace App\CMSVC\Setup;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Enum\{ActEnum, ActionEnum, SubstituteDataTypeEnum};
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

#[TableEntity(
    'setup',
    'project_application_field',
    [
        new EntitySortingItem(
            tableFieldName: 'field_code',
            showFieldDataInEntityTable: false,
        ),
        new EntitySortingItem(
            tableFieldName: 'field_name',
        ),
        new EntitySortingItem(
            tableFieldName: 'field_type',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortFieldType',
        ),
        new EntitySortingItem(
            tableFieldName: 'field_rights',
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortFieldRights',
        ),
        new EntitySortingItem(
            tableFieldName: 'field_mustbe',
            doNotUseIfNotSortedByThisField: true,
        ),
        new EntitySortingItem(
            tableFieldName: 'show_in_filters',
            doNotUseIfNotSortedByThisField: true,
        ),
    ],
    null,
    5000,
)]
#[Rights(
    viewRight: 'checkRightsView',
    addRight: 'checkRights',
    changeRight: 'checkRights',
    deleteRight: 'checkRights',
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(SetupController::class)]
class SetupView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE_GLOBAL['project_control_items'][KIND][0]);
        $RESPONSE_DATA = $response->getHtml();

        if ((!DataHelper::getId() || ACTION === ActionEnum::delete) && DataHelper::getActDefault($this->getEntity()) !== ActEnum::add) {
            $RESPONSE_DATA = preg_replace(
                '#<div class="maincontent_data([^"]*)">#',
                '<div class="maincontent_data$1"><div class="filter"><a href="/setup/application_type=0" class="fixed_select">' . $LOCALE['switch_to_individual'] . '</a><a href="/setup/application_type=1" class="fixed_select">' . $LOCALE['switch_to_group'] . '</a></div>',
                $RESPONSE_DATA,
            );
        }

        $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, $LOCALE_GLOBAL['project_control_items'][KIND][0]);

        return $response->setHtml($RESPONSE_DATA)->setPagetitle($PAGETITLE);
    }
}
