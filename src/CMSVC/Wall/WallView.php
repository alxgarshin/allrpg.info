<?php

declare(strict_types=1);

namespace App\CMSVC\Wall;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Interface\Response;

#[Controller(WallController::class)]
class WallView extends BaseView
{
    public function Response(): ?Response
    {
        $LOCALE = $this->getLOCALE();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<h1 class="page_header"><a href="/' . KIND . '/">' . $LOCALE['title'] . '</a></h1>
<div class="page_blocks">
<div class="page_block">

<div class="fraymtabs">
	<ul>
		<li><a id="all">' . $LOCALE['all_messages'] . '</a></li>
		<li><a id="new">' . $LOCALE['new_messages'] . '</a></li>
	</ul>
	<div id="fraymtabs-all">
		<a class="load_wall" obj_type="{main_wall}" sub_obj_type="{all_messages}">' . $LOCALE['show_previous'] . '</a>
	</div>
	<div id="fraymtabs-new">
		<a class="load_wall" obj_type="{main_wall}" sub_obj_type="{new_messages}">' . $LOCALE['show_previous'] . '</a>
	</div>
</div>
</div>

</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
