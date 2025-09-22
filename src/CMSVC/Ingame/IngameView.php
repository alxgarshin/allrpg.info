<?php

declare(strict_types=1);

namespace App\CMSVC\Ingame;

use App\Helper\FileHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Helper\{DataHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<IngameService> */
#[Controller(IngameController::class)]
class IngameView extends BaseView
{
    public function Response(): ?Response
    {
        return null;
    }

    public function applicationView(): ?Response
    {
        $LOCALE = $this->getLOCALE();
        $RESPONSE_DATA = '';
        $PAGETITLE = $LOCALE['title'];

        $ingameService = $this->getService();
        $applicationData = $ingameService->getApplicationData();
        $projectData = $ingameService->getProjectData();

        $RESPONSE_DATA .= '<div class="maincontent_data autocreated kind_' . KIND . '">';
        $RESPONSE_DATA .= '<link rel="stylesheet" type="text/css" href="' . DataHelper::escapeOutput($projectData->ingame_css->get()) . '">';
        $RESPONSE_DATA .= '<audio id="hacking_alert" src="' . $_ENV['DESIGN_PATH'] . 'siren.mp3" preload="auto"></audio>';
        $RESPONSE_DATA .= '<div class="geoposition_info"></div>';
        $RESPONSE_DATA .= '<h1 class="form_header"><a href="/' . KIND . '/">' . $LOCALE['title'] . ': ' . DataHelper::escapeOutput($applicationData->sorter->get()) . '</a></h1>';

        $RESPONSE_DATA .= '
<div class="page_blocks">
';

        $RESPONSE_DATA .= '
    <div class="fraymtabs">
        <ul>
            <li><a id="qrpg">' . $LOCALE['qrpg'] . '</a></li>
            <li><a id="documents">' . $LOCALE['documents'] . '</a></li>
            <li><a id="chat">' . $LOCALE['chat'] . '</a></li>
            <li><a id="bank">' . $LOCALE['bank'] . '</a></li>
            <a href="/myapplication/' . $ingameService->getApplicationId() . '/" class="simulate_tab">' . $LOCALE['to_application'] . '</a>
            <a href="/' . KIND . '/exit/" class="simulate_tab right">' . $LOCALE['exit'] . '</a>
        </ul>';

        $RESPONSE_DATA .= '
        <div id="fraymtabs-qrpg">';

        $RESPONSE_DATA .= '
            <div class="fraymtabs">
                <ul>
                    <li><a id="scan">' . $LOCALE['scan'] . '</a></li>
                    <li><a id="keys">' . $LOCALE['keys'] . '</a></li>
                </ul>';

        $RESPONSE_DATA .= '
                <div id="fraymtabs-scan">
                    <center>
                        <div id="qrcode_clicker_container"><div id="qrcode_clicker_sign">' . mb_strtoupper($LOCALE['scan_btn']) . '</div></div>
                        <input type="file" name="qrcode" accept="image/*" capture="environment" id="qrcode_scanner">
                        <canvas id="qr-canvas"></canvas>
                        <div class="qr-video-container"><video id="qr-video" autoplay playsinline></video><div class="flashlight"></div></div>
                        <a id="retry_qrcode_scanner">' . $LOCALE['retry_qrcode_scanner'] . '</a>
                        <div class="qrcode_result"><h1></h1><div class="qrpg_description"></div></div>
                        <div class="qrpg_properties_list not_loaded"></div>
                    </center>
                </div>';

        $RESPONSE_DATA .= '
                <div id="fraymtabs-keys">
                    <center>
                        <div class="qrpg_keys_list not_loaded"></div>
                    </center>
                </div>';

        $RESPONSE_DATA .= '
            </div>';

        $RESPONSE_DATA .= '
        </div>';

        $RESPONSE_DATA .= '
        <div id="fraymtabs-documents">';

        $RESPONSE_DATA .= '
            <div class="fraymtabs">
                <ul>
                    <li><a id="doc1">' . $LOCALE['doc1'] . '</a></li>
                    <li><a id="doc2">' . $LOCALE['doc2'] . '</a></li>
                    <li><a id="doc3">' . $LOCALE['plots'] . '</a></li>
                </ul>';

        $RESPONSE_DATA .= '
                <div id="fraymtabs-doc1">';

        $RESPONSE_DATA .= $ingameService->renderGameFieldsHtml();

        $RESPONSE_DATA .= '
                </div>';

        $RESPONSE_DATA .= '
                <div id="fraymtabs-doc2">';

        $RESPONSE_DATA .= $ingameService->renderOutOfGameFieldsHtml();

        $RESPONSE_DATA .= '
                </div>';

        $RESPONSE_DATA .= '
                <div id="fraymtabs-doc3"><div class="page_block"><h2>' . $LOCALE['plots'] . '</h2>
    <div class="publication_content">' . $ingameService->getPlotsDataDefault() . '</div></div>
                </div>';

        $RESPONSE_DATA .= '
            </div>';

        $RESPONSE_DATA .= '
        </div>';

        $RESPONSE_DATA .= '
        <div id="fraymtabs-chat">';

        $RESPONSE_DATA .= $ingameService->renderCommentContent();

        $RESPONSE_DATA .= '
        </div>';

        $RESPONSE_DATA .= '
        <div id="fraymtabs-bank">';

        $RESPONSE_DATA .= $ingameService->renderBankContent();

        $RESPONSE_DATA .= '
        </div>';

        $RESPONSE_DATA .= '
    </div>
</div>
</div>';

        return new HtmlResponse(
            html: $RESPONSE_DATA,
            pagetitle: $PAGETITLE,
        );
    }

    public function chooseProjectsList(): Response
    {
        $LOCALE = $this->getLOCALE();

        $RESPONSE_DATA = '';
        $PAGETITLE = $LOCALE['title'];

        $ingameService = $this->getService();

        $applicationsFullData = $ingameService->applicationsFullData;

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
    <h1 class="page_header"><a href="/' . KIND . '/">' . $LOCALE['title'] . '</a></h1>
	<div class="page_blocks">
	<div class="page_block margin_top">';

        if ($applicationsFullData) {
            $RESPONSE_DATA .= '
        <div class="myapplication_project_selection">';

            foreach ($applicationsFullData as $applicationData) {
                $RESPONSE_DATA .= '
        <div class="myapplication_project_selection_project_block">
            <div class="myapplication_project_selection_project_info">
                <div class="myapplication_project_selection_project_name"><a href="/' . KIND . '/' . $applicationData['id'] . '/">' . DataHelper::escapeOutput($applicationData['project_name']) . '</a></div>
                <div class="myapplication_project_selection_project_dates">' . DataHelper::escapeOutput($applicationData['sorter'] ?? '') . '</div>
            </div>
            <div class="myapplication_project_selection_project_links">
                <div class="main"><a href="/' . KIND . '/' . $applicationData['id'] . '/">' . $LOCALE['qrpg'] . ': ' . mb_strtolower($LOCALE['scan']) . '</a></div>
                <div class="main"><a href="/' . KIND . '/' . $applicationData['id'] . '/#keys">' . $LOCALE['qrpg'] . ': ' . mb_strtolower($LOCALE['keys']) . '</a></div>
                <a href="/' . KIND . '/' . $applicationData['id'] . '/#documents" class="additional">' . $LOCALE['documents'] . '</a>
                <a href="/' . KIND . '/' . $applicationData['id'] . '/#chat" class="additional">' . $LOCALE['chat_btn'] . '</a>
                <a href="/' . KIND . '/' . $applicationData['id'] . '/#bank" class="additional">' . $LOCALE['bank'] . '</a>
                <div class="project_links_avatar"><img src="' . (FileHelper::getImagePath($applicationData['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars')) ?? ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg') . '"></div>
            </div>
	    </div>';
            }

            $RESPONSE_DATA .= '</div>';
        } else {
            $RESPONSE_DATA .= '
        <h2>' . $LOCALE['messages']['no_applications_header'] . '</h2>
        ' . $LOCALE['messages']['no_applications'];
        }

        $RESPONSE_DATA .= '
    
	</div>
	</div>
	</div>';

        return new HtmlResponse(
            html: $RESPONSE_DATA,
            pagetitle: $PAGETITLE,
        );
    }
}
