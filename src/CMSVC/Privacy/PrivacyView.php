<?php

declare(strict_types=1);

namespace App\CMSVC\Privacy;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Enum\EscapeModeEnum;
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;

#[Controller(PrivacyController::class)]
class PrivacyView extends BaseView
{
    public function Response(): ?Response
    {
        $LOCALE = $this->getLOCALE();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
<h1 class="page_header">' . $LOCALE['title'] . '</h1>
<div class="publication_content">
' . DataHelper::escapeOutput($LOCALE['text'], EscapeModeEnum::forHTMLforceNewLines) . '
</div>
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
