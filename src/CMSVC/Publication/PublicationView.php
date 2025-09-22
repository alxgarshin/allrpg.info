<?php

declare(strict_types=1);

namespace App\CMSVC\Publication;

use App\CMSVC\PublicationsEdit\PublicationsEditService;
use App\CMSVC\User\UserService;
use App\Helper\{DesignHelper, MessageHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Enum\EscapeModeEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

#[Controller(PublicationController::class)]
class PublicationView extends BaseView
{
    public function Response(): ?Response
    {
        /** @var PublicationService $publicationService */
        $publicationService = $this->getCMSVC()->getService();

        /** @var PublicationsEditService $publicationsEditService */
        $publicationsEditService = CMSVCHelper::getService('publications_edit');

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_PEOPLE = LocaleHelper::getLocale(['people', 'global']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '';

        if (DataHelper::getId() > 0) {
            $objData = $publicationsEditService->get(DataHelper::getId());

            if (!$objData) {
                return null;
            }
            $objType = 'publication';

            $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE['title']);

            if (
                $objData->active->get() || $objData->creator_id->getAsInt() === CURRENT_USER->id() || CURRENT_USER->isAdmin() || in_array(CURRENT_USER->sid(), $objData->author->get())
            ) {
                $authors = $publicationService->getAuthors($objData);
                $tags = $publicationService->getTags($objData);
                $creatorData = $publicationService->getCreator($objData);

                $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
<div class="page_block">
    <div class="object_info">
        <div class="object_info_1">
            <div class="object_avatar small" style="' . DesignHelper::getCssBackgroundImage($userService->photoUrl($creatorData)) . '"></div>
        </div>
        <div class="object_info_2">
            <h1>' . DataHelper::escapeOutput($objData->name->get()) . '</h1>
            
            <div class="object_info_2_additional">
                ' . ($authors !== '' ? '<span class="gray">' . $LOCALE['author'] . ':</span>' . $authors . '<br>' : '') . '
                <span class="gray">' . $LOCALE_GLOBAL['published_by'] . LocaleHelper::declineVerb($creatorData) . ':</span>' .
                    $userService->showName($creatorData, true) . '
            </div>
        </div>
        <div class="object_info_3 only_like">
            ' . UniversalHelper::drawImportant($objType, $objData->id->getAsInt()) . '
            <div class="actions_list_switcher">';

                if (CURRENT_USER->id() === $creatorData->id->getAsInt()) {
                    $RESPONSE_DATA .= '
                <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/publications_edit/publications_edit/' . DataHelper::getId() . '/act=edit"><span>' .
                        TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</span></a></div>';
                } elseif (CURRENT_USER->isAdmin() || $userService->isModerator()) {
                    $RESPONSE_DATA .= '
                <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                <div class="actions_list_items">';
                    $RESPONSE_DATA .= '
                    <a href="' . ABSOLUTE_PATH . '/publications_edit/publications_edit/' . DataHelper::getId() . '/act=edit">' .
                        TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</a>
                    <a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $creatorData->id->getAsInt() . '">' . $LOCALE_PEOPLE['contact_user'] . '</a>';
                    $RESPONSE_DATA .= '
                </div>';
                } else {
                    $RESPONSE_DATA .= '
                <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $creatorData->id->getAsInt() . '"><span>'
                        . $LOCALE_PEOPLE['contact_user'] . '</span></a></div>';
                }

                $RESPONSE_DATA .= '
                <div class="publication_updated_at"><span>' . $LOCALE['publish_date'] . ':</span>' .
                    $objData->updated_at->get()->format('d.m.Y ' . $LOCALE_FRAYM['datetime']['at'] . ' H:i') . '</div>
            </div>
        </div>
    </div>
</div>
<div class="page_block">
<div class="publication_content">' . DataHelper::escapeHTMLData(DataHelper::escapeOutput($objData->content->get(), EscapeModeEnum::plainHTML)) . '</div>
' . ($tags !== '' ? '<div class="publication_tags">' . $tags . '</div>' : '');

                /*$upload = $_ENV['UPLOADS'][10];

                preg_match_all('#{([^:]+):([^}]+)}#', $objData->attachments->get(), $matches);
                foreach ($matches[0] as $key => $value) {
                    if ($key == 0) {
                        $RESPONSE_DATA .= '<br>';
                    }
                    if (file_exists(INNER_PATH.$_ENV['UPLOADS_PATH'].$upload['path'].$matches[2][$key])) {
                        $catalog = ABSOLUTE_PATH.$_ENV['UPLOADS_PATH'];
                        if ($upload['isimage']) {
                            if ($upload['thumbmake']) {
                                $RESPONSE_DATA .= '<a href="'.$catalog.$upload['path'].$matches[2][$key].'" target="_blank"><img src="'
                                    .$catalog.$upload['path'].'thumbnail/'.$matches[2][$key].'"></a><br>';
                            } else {
                                $RESPONSE_DATA .= '<img src="'.$catalog.$upload['path'].$matches[2][$key].'"><br>';
                            }
                        } else {
                            $RESPONSE_DATA .= '<div class="uploaded_file"><a href="'.$catalog.$upload['path'].$matches[2][$key].'" target="_blank">'.$matches[1][$key].'</a></div>';
                        }
                    }
                }*/

                if (!$objData->nocomments->get()) {
                    $RESPONSE_DATA .= '</div>
        <div class="page_block">
		<h2>' . $LOCALE['conversation'] . '</h2>
		' . MessageHelper::conversationForm(null, '{publication_wall}', DataHelper::getId(), $LOCALE['input_message']);

                    $RESPONSE_DATA .= '<a class="load_wall" obj_type="{publication_wall}" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE['show_more'] . '</a>';
                }

                $RESPONSE_DATA .= '
	</div>
</div>
</div>';
            }
        } else {
            $publicationsData = $publicationService->getPublications();

            $canAddPublications = CURRENT_USER->isLogged();
            $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
';

            if ($canAddPublications) {
                $RESPONSE_DATA .= '<a class="outer_add_something_button" href="' . ABSOLUTE_PATH . '/publications_edit/act=add"><span class="sbi sbi-add-something"></span><span class="outer_add_something_button_text">' . $LOCALE['add_publication'] . '</span></a>';
            }
            $RESPONSE_DATA .= '<h1 class="page_header">' . $LOCALE['title'] . '</h1>
    
<div class="page_blocks margin_top">
<div class="page_block">
	<form action="' . ABSOLUTE_PATH . '/' . KIND . '/" method="POST" id="form_inner_search">
		<a class="search_image sbi sbi-search"></a><input class="search_input" name="search" id="search" type="text" value="' .
                ($_REQUEST['search'] ?? false) . '" placehold="' . $LOCALE['search'] . '" autocomplete="off">
	</form>
	
	<div class="tags_cloud">' . $publicationService->drawTagsCloud() . '</div>
	
	<div class="publications">
	';
            $publicationService->prepareImportantCounters();
            $publicationService->prepareMessagesCounters();

            foreach ($publicationsData as $publicationData) {
                $RESPONSE_DATA .= $publicationService->drawPublicationShort($publicationData);
            }

            $RESPONSE_DATA .= '
	</div>
</div>
</div>
</div>';
        }

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
