<?php

declare(strict_types=1);

namespace App\CMSVC\Geoposition;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Interface\Response;

#[Controller(GeopositionController::class)]
class GeopositionView extends BaseView
{
    public function Response(): ?Response
    {
        $LOCALE = $this->getLOCALE();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '"><h1 class="page_header">' . $LOCALE['title'] . '</h1><div class="page_blocks margin_top">';

        $RESPONSE_DATA .= '<input type="text" id="geoposition_search" placehold="' . $LOCALE['type_data_to_search'] . '" autocomplete="off">
<a class="geoposition_map_center">' . $LOCALE['center'] . '</a>
<div class="geoposition_map" id="geoposition_map"><div class="geoposition_map_overlay" id="geoposition_map_overlay"></div></div>
';

        $RESPONSE_DATA .= '</div></div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
