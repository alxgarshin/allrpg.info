<?php

declare(strict_types=1);

namespace App\CMSVC\Plot;

use App\CMSVC\Plot\PlotPlot\PlotPlotModel;
use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{CatalogEntity, CatalogItemEntity, EntitySortingItem, Rights};
use Fraym\Enum\{SubstituteDataTypeEnum, TableFieldOrderEnum};
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<PlotService> */
#[CatalogEntity(
    'plot',
    'project_plot',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
            showFieldShownNameInCatalogItemString: false,
            doNotUseInSorting: true,
        ),
        new EntitySortingItem(
            tableFieldName: 'code',
            tableFieldOrder: TableFieldOrderEnum::DESC,
        ),
        new EntitySortingItem(
            tableFieldName: 'id',
            showFieldShownNameInCatalogItemString: false,
            doNotUseIfNotSortedByThisField: true,
            removeDotAfterText: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortId',
        ),
    ],
    null,
    5000,
)]
#[CatalogItemEntity(
    'plotPlot',
    'project_plot',
    PlotPlotModel::class,
    'parent',
    'content',
    [
        new EntitySortingItem(
            tableFieldName: 'code',
            tableFieldOrder: TableFieldOrderEnum::DESC,
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
            doNotUseInSorting: true,
        ),
        new EntitySortingItem(
            tableFieldName: 'name',
            showFieldShownNameInCatalogItemString: false,
            doNotUseInSorting: true,
        ),
        new EntitySortingItem(
            tableFieldName: 'id',
            showFieldShownNameInCatalogItemString: false,
            doNotUseIfNotSortedByThisField: true,
            substituteDataType: SubstituteDataTypeEnum::ARRAY,
            substituteDataArray: 'getSortId',
        ),
        new EntitySortingItem(
            tableFieldName: 'code',
            tableFieldOrder: TableFieldOrderEnum::DESC,
        ),
    ],
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
#[Controller(PlotController::class)]
class PlotView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $LOCALE = $this->LOCALE;
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $title = $LOCALE_GLOBAL['project_control_items'][KIND][0];
        $RESPONSE_DATA = $response->getHtml();

        $objData = [];

        if (DataHelper::getId() > 0) {
            $objData = DB->findObjectById(DataHelper::getId(), 'project_plot');

            if ($objData['name'] !== '') {
                $title = DataHelper::escapeOutput($objData['name']);
            }
        }

        $RESPONSE_DATA = DesignHelper::insertHeader($RESPONSE_DATA, $title);
        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($title);

        if (DataHelper::getId()) {
            $RESPONSE_DATA = str_replace('<h1 class="data_h1" id="field_plots[0]">', '<h1 class="data_h1" id="field_plots[0]"><a class="add_something_svg" href="' . ABSOLUTE_PATH . '/plot/plot_plot/act=add&parent=' . DataHelper::getId() . '"></a>', $RESPONSE_DATA);
        }

        return $response->setHtml($RESPONSE_DATA)->setPagetitle($PAGETITLE);
    }
}
