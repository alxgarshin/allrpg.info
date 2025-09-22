<?php

declare(strict_types=1);

namespace App\CMSVC\Csvimport;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<CsvimportService> */
#[Controller(CsvimportController::class)]
class CsvimportView extends BaseView
{
    public function Response(): ?Response
    {
        $csvImportService = $this->getService();

        $LOCALE = $this->getLOCALE();

        $PAGETITLE = $LOCALE['title'];
        $RESPONSE_DATA = '';

        $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<h1 class="page_header">' . $LOCALE['title'] . '</h1>
<div class="page_blocks margin_top">
<div class="page_block">';

        $RESPONSE_DATA .= '<h2>' . $LOCALE['import_characters_btn'] . '</h2>';
        $RESPONSE_DATA .= '<div class="publication_content">' . $LOCALE['import_characters_text'] . '</div>';
        $RESPONSE_DATA .= '<form action="/' . KIND . '/" method="POST" enctype="multipart/form-data" id="form_import_characters">
<input type="hidden" name="action" value="import_characters">
<div class="field" id="field_attachments"><div class="fieldvalue" id="div_attachments"><input type="file" id="attachments" name="attachments" class="inputfile obligatory" data-url="' . $_ENV['UPLOADS_PATH'] . '?type=14"></div></div>
<button class="main">' . $LOCALE['import_characters_btn'] . '</button>
</form>';
        $RESPONSE_DATA .= ($csvImportService->importCharactersDebugText !== '' ? '<div class="csv_data">' . $csvImportService->importCharactersDebugText . '</div>' : '');

        $RESPONSE_DATA .= '<h2>' . $LOCALE['import_applications_btn'] . '</h2>';
        $RESPONSE_DATA .= '<div class="publication_content">' . $LOCALE['import_applications_text'] . '</div>';
        $RESPONSE_DATA .= '<form action="/' . KIND . '/" method="POST" enctype="multipart/form-data" id="form_import_applications">
<input type="hidden" name="action" value="import_applications">
<div class="field" id="field_attachments"><div class="fieldvalue" id="div_attachments"><input type="file" id="attachments" name="attachments" class="inputfile obligatory" data-url="' . $_ENV['UPLOADS_PATH'] . '?type=14"></div></div>
<button class="main">' . $LOCALE['import_applications_btn'] . '</button>
</form>';
        $RESPONSE_DATA .= ($csvImportService->importApplicationsDebugText !== '' ? '<div class="csv_data">' . $csvImportService->importApplicationsDebugText . '</div>' : '');

        $RESPONSE_DATA .= '
</div>
</div>
</div>';

        return new HtmlResponse(
            $RESPONSE_DATA,
            $PAGETITLE,
        );
    }
}
