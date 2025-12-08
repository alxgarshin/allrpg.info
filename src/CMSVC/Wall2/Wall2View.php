<?php

declare(strict_types=1);

namespace App\CMSVC\Wall2;

use App\Helper\{DesignHelper, FileHelper, MessageHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Response;

/** @extends BaseView<Wall2Service> */
#[Controller(Wall2Controller::class)]
class Wall2View extends BaseView
{
    public function Response(): ?Response
    {
        $LOCALE = $this->LOCALE;
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_PROJECT = LocaleHelper::getLocale(['project', 'global']);
        $LOCALE_COMMUNITY = LocaleHelper::getLocale(['community', 'global']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '';

        $myGroups = $this->service->getMyGroups();

        $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<h1 class="page_header"><a href="/' . KIND . '/">' . $LOCALE['conversations'] . '</a></h1>
<div class="page_blocks margin_top">
';
        $tabs_count = 0;

        $content_with_new_messages = '';
        $content_without_new_messages = '';

        foreach ($myGroups as $myGroup) {
            $tempContent = '';

            $tempContent .= '<div class="page_block margin_top">';

            $id = $myGroup['id'];
            $image = '';
            $OBJ_LOCALE = $LOCALE_PROJECT;

            if ($myGroup['group'] === 'project') {
                $image = FileHelper::getImagePath($myGroup['attachments'], 9) ??
                    ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_project.svg';
            } elseif ($myGroup['group'] === 'community') {
                $OBJ_LOCALE = $LOCALE_COMMUNITY;

                $image = FileHelper::getImagePath($myGroup['attachments'], 9) ??
                    ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_community.svg';
            }

            $tempContent .= '<a class="outer_add_something_button new_' . $myGroup['group'] . '_conversation"><span class="sbi sbi-add-something"></span><span class="outer_add_something_button_text">' . $OBJ_LOCALE['create_conversation'] . '</span></a>
<div class="wall_conversation_group"><a class="wall_conversation_group_image" href="' . ABSOLUTE_PATH . '/' . $myGroup['group'] . '/' . $myGroup['id'] . '/"><div style="' . DesignHelper::getCssBackgroundImage($image) . '"></div></a><div class="wall_conversation_group_name"><a href="' . ABSOLUTE_PATH . '/' . $myGroup['group'] . '/' . $myGroup['id'] . '/">' . DataHelper::escapeOutput($myGroup['name']) . '</a></div></div>';

            if ($myGroup['group'] === 'community') {
                $tempContent .= '<div id="community_conversation">
	' . MessageHelper::conversationForm(null, '{community_conversation}', $id, $LOCALE['conversation_text'], 0, false, true, '{admin}');

                $conversationsData = MessageHelper::prepareConversationTreePreviewData('{community_conversation}', $id);
                $conversationsCount = count($conversationsData);
                $i = 0;

                foreach ($conversationsData as $conversationData) {
                    ++$i;
                    $tempContent .= MessageHelper::conversationTreePreview($conversationData, 'community', $id, 'string' . ($i % 2 === 0 ? '1' : '2'), $myGroup);

                    if ($i === 4 && $conversationsCount > 4) {
                        $tempContent .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>
						<div class="hidden">';
                    }
                }

                if ($i > 4) {
                    $tempContent .= '</div>';
                }
                $tempContent .= '
</div>';
            } elseif ($myGroup['group'] === 'project') {
                $tempContent .= '<div id="project_conversation">
' . MessageHelper::conversationForm(null, '{project_conversation}', $id, $LOCALE['conversation_text'], 0, false, true, '{admin}');

                $conversationsData = MessageHelper::prepareConversationTreePreviewData('{project_conversation}', $id);
                $conversationsCount = count($conversationsData);
                $i = 0;

                foreach ($conversationsData as $conversationData) {
                    ++$i;
                    $tempContent .= MessageHelper::conversationTreePreview($conversationData, 'project', $id, 'string' . ($i % 2 === 0 ? '1' : '2'), $myGroup);

                    if ($i === 4 && $conversationsCount > 4) {
                        $tempContent .= '<a class="show_hidden">' . $LOCALE_GLOBAL['show_hidden'] . '</a>
							<div class="hidden">';
                    }
                }

                if ($i > 4) {
                    $tempContent .= '</div>';
                }
                $tempContent .= '
</div>
';
                $tabs_count += 4;
            }

            $tempContent .= '</div>';

            if (preg_match('#<span class="red">#', $tempContent)) {
                $content_with_new_messages .= $tempContent;
            } else {
                $content_without_new_messages .= $tempContent;
            }
        }

        if (count($myGroups) === 0) {
            $RESPONSE_DATA .= $LOCALE['no_projects_or_communities'] . '<br><br>';
        } else {
            $RESPONSE_DATA .= $content_with_new_messages . $content_without_new_messages;
        }

        $RESPONSE_DATA .= '
</div>
</div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
