<?php

declare(strict_types=1);

namespace App\CMSVC\Budget;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Element\Item\Multiselect;
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<BudgetService> */
#[MultiObjectsEntity(
    name: 'budget',
    table: 'resource',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'code',
            showFieldDataInEntityTable: false,
            showFieldShownNameInCatalogItemString: false,
        ),
    ],
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: true,
    deleteRight: true,
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsChangeRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(BudgetController::class)]
class BudgetView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $LOCALE = $this->LOCALE;
        $budgetService = $this->service;

        $budgetService->updateDistributedItems();

        $RESPONSE_DATA = $response->getHtml();

        /** Различные полезные расчеты и формулы: расчет взноса, фиксация потрачено и осталось, подсчет планируемого количества игроков */
        $budgetData = $budgetService->getBudgetData();

        $budgetDataHtml = '<div class="budget_info">';
        $budgetDataHtml .= '<div class="budget_info_left_part">';
        $budgetDataHtml .= '<div class="budget_field" id="budget_total"><span class="budget_field_title">' . $LOCALE['total'] . '</span><span class="budget_field_data">' . (int) $budgetData['total'] . '</span></div>';
        $budgetDataHtml .= '<div class="budget_field" id="budget_total10"><span class="budget_field_title">' . $LOCALE['total10'] . '</span><span class="budget_field_data">' . ((int) $budgetData['total'] + ((int) $budgetData['total'] / 10)) . '</span></div>';
        $budgetDataHtml .= '<div class="budget_field" id="budget_player_count"><span class="budget_field_title"><a href="/project/' . $budgetService->getActivatedProjectId() . '/act=edit">' . $LOCALE['player_count'] . '</a></span><span class="budget_field_data">' . (int) $budgetData['player_count'] . '</span></div>';
        $budgetDataHtml .= '<div class="budget_field" id="budget_recommended"><span class="budget_field_title">' . $LOCALE['recommended'] . '</span><span class="budget_field_data">' . (int) $budgetData['recommended'] . '</span></div>';
        $budgetDataHtml .= '<div class="budget_field" id="budget_set"><span class="budget_field_title"><a href="/fee/">' . $LOCALE['set'] . '</a></span><span class="budget_field_data">' . (int) $budgetData['set'] . '</span></div>';
        $budgetDataHtml .= '<div class="budget_field" id="budget_overdraft"><span class="budget_field_title">' . $LOCALE['overdraft'] . '</span><span class="budget_field_data">' . (int) $budgetData['overdraft'] . '</span></div>';

        $budgetDataHtml .= '</div>';
        $budgetDataHtml .= '<div class="budget_info_right_part">';

        $budgetDataHtml .= '<div class="budget_field" id="budget_spent"><span class="budget_field_title">' . $LOCALE['spent'] . '</span><span class="budget_field_data">' . (int) $budgetData['spent'] . '</span></div>';
        $budgetDataHtml .= '<div class="budget_field" id="budget_remaining"><span class="budget_field_title">' . $LOCALE['remaining'] . '</span><span class="budget_field_data">' . (int) $budgetData['remaining'] . '</span></div>';
        $budgetDataHtml .= '<div class="budget_field" id="budget_paid"><span class="budget_field_title">' . $LOCALE['paid'] . '</span><span class="budget_field_data">' . (int) $budgetData['paid'] . ((int) $budgetData['paid'] !== (int) $budgetData['paid_application'] ? ' <span title="' . $LOCALE['paid_application'] . '" class="tooltipBottomRight">(' . (int) $budgetData['paid_application'] . ')</span>' : '') . '</span></div>';
        $budgetDataHtml .= '<div class="budget_field" id="budget_comission"><span class="budget_field_title">' . $LOCALE['comission'] . '</span><span class="budget_field_data">' . round((float) $budgetData['comission'], 2) . '</span></div>';
        $budgetDataHtml .= '<div class="budget_field" id="budget_needed"><span class="budget_field_title">' . $LOCALE['needed'] . '</span><span class="budget_field_data">' . ((int) $budgetData['total'] - ((int) $budgetData['paid'] - round((float) $budgetData['comission'], 2))) . '</span></div>';
        $budgetDataHtml .= '<div class="budget_field" id="budget_not_paid"><span class="budget_field_title">' . $LOCALE['not_paid'] . '</span><span class="budget_field_data">' . (int) $budgetData['not_paid'] . '</span></div>';
        $budgetDataHtml .= '</div>';

        $budgetDataHtml .= '</div>';

        $RESPONSE_DATA = str_replace('<div class="multi_objects_table excel"', $budgetDataHtml . '<div class="multi_objects_table excel"', $RESPONSE_DATA);

        /** @var Multiselect */
        $responsiblesIds = $this->model->getElement('responsible_id');
        $boughtBy = $this->model->getElement('bought_by');

        $additionalOptions = '<div class="bought_by_data">
<h2>' . $boughtBy->shownName . '</h2>';

        foreach ($responsiblesIds->getAttribute()->values as $gamemaster) {
            $additionalOptions .= '<div class="bought_by_gamemaster" obj_id="' . $gamemaster[0] . '"><span class="bought_by_gamemaster_name">' . $gamemaster[1] . '</span><span class="bought_by_gamemaster_amount"></span></div>';
        }
        $additionalOptions .= '</div>';

        $additionalOptions .= '
<h2>' . $LOCALE['options'] . '</h2>
<div class="filter"><a class="fixed_select" id="nullify_fees">' . $LOCALE['nullify_fees'] . '</a></div>';

        $RESPONSE_DATA = str_replace('</form', $additionalOptions . '</form', $RESPONSE_DATA);

        return $response->setHtml($RESPONSE_DATA);
    }
}
