<?php

declare(strict_types=1);

namespace App\CMSVC\RulingEdit;

use App\Helper\{DesignHelper, TextHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

#[Controller(RulingEditController::class)]
class RulingEditView extends BaseView
{
    public function Response(): ?Response
    {
        /** @var RulingEditService $rulingEditService */
        $rulingEditService = CMSVCHelper::getService('rulingEdit');

        $LOCALE = $this->getLOCALE();
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '';

        [$nodes, $links, $selectedNodeType, $selectedNodeId] = $rulingEditService->prepareData();

        $RESPONSE_DATA = '
<div class="maincontent_data kind_' . KIND . '"><div class="page_blocks"><h1 class="page_header"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/">' . $LOCALE['title'] . '</a></h1>
<div class="container">
    <div id="ruling_chart" class="nlgraph_chart"></div>
</div>
<button class="main ruling_chart_btn" disabled target="_blank">' . TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</button><button class="main ruling_chart_btn switch_to" disabled>' . $LOCALE['switch_to'] . '</button><button class="nonimportant ruling_chart_btn" href="' . ABSOLUTE_PATH . '/ruling_question_edit/" target="_blank">' . $LOCALE['questions'] . '</button><button class="nonimportant ruling_chart_btn" href="' . ABSOLUTE_PATH . '/ruling_item_edit/" target="_blank">' . $LOCALE['items'] . '</button>

<script>
window.nlgraphNodes = ' . DataHelper::jsonFixedEncode($nodes) . ';
window.nlgraphLinks = ' . DataHelper::jsonFixedEncode($links) . ';
selectedNodeType = "' . $selectedNodeType . '";
selectedNodeId = ' . $selectedNodeId . ';
</script>

</div></div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
