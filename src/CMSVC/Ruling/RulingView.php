<?php

declare(strict_types=1);

namespace App\CMSVC\Ruling;

use App\CMSVC\RulingItemEdit\RulingItemEditService;
use App\CMSVC\RulingQuestionEdit\RulingQuestionEditService;
use App\CMSVC\User\UserService;
use App\Helper\{DesignHelper, MessageHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Enum\{EscapeModeEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

#[Controller(RulingController::class)]
class RulingView extends BaseView
{
    public function Response(bool $viewAll = false, int $rulingTagId = 0, string $searchString = '', bool $fillform = false): ?Response
    {
        /** @var RulingService $rulingService */
        $rulingService = CMSVCHelper::getService('ruling');

        /** @var RulingItemEditService $rulingItemEditService */
        $rulingItemEditService = CMSVCHelper::getService('rulingItemEdit');

        /** @var RulingQuestionEditService $rulingQuestionEditService */
        $rulingQuestionEditService = CMSVCHelper::getService('rulingQuestionEdit');

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->getLOCALE();
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_PUBLICATION = LocaleHelper::getLocale(['publication', 'global']);
        $LOCALE_PEOPLE = LocaleHelper::getLocale(['people', 'global']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '';

        if (DataHelper::getId() > 0) {
            $objData = $rulingItemEditService->get(DataHelper::getId());

            if ($objData) {
                $userData = $userService->get($objData->creator_id->getAsInt());

                $authors = $rulingService->getAuthors($objData);
                $tags = $rulingService->getTags($objData);

                $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
    <div class="page_block">
        <div class="object_info">
            <div class="object_info_1">
                <div class="object_avatar small" style="' . DesignHelper::getCssBackgroundImage($userService->photoUrl($userData)) . '"></div>
            </div>
            <div class="object_info_2">
                <h1>' . DataHelper::escapeOutput($objData->name->get()) . '</h1>
                <div class="object_info_2_additional">
                    <span class="gray">' . (count($authors) > 1 ? $LOCALE_PUBLICATION['authors'] : $LOCALE_PUBLICATION['author']) . ':</span>' . implode(', ', $authors) . '<br>
                    <span class="gray">' . $LOCALE_GLOBAL['published_by'] . LocaleHelper::declineVerb($userData) . ':</span>' . $userService->showName($userData, true) . '
                </div>
            </div>
            <div class="object_info_3 only_like">
                ' . UniversalHelper::drawImportant('{ruling_item}', $objData->id->getAsInt()) . '
                <div class="actions_list_switcher">';

                if (CURRENT_USER->id() === $userData->id->getAsInt()) {
                    $RESPONSE_DATA .= '
                    <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/ruling_item_edit/' . $objData->id->getAsInt() . '/"><span>' . TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</span></a></div>';
                } else {
                    if (CURRENT_USER->isAdmin() || $userService->isModerator()) {
                        $RESPONSE_DATA .= '
                    <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                    <div class="actions_list_items">';
                        $RESPONSE_DATA .= '
                        <a href="' . ABSOLUTE_PATH . '/ruling_item_edit/' . $objData->id->getAsInt() . '/">' . TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</a>
                        <a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $userData->id->getAsInt() . '">' . $LOCALE_PEOPLE['contact_user'] . '</a>';
                        $RESPONSE_DATA .= '
                    </div>';
                    } else {
                        $RESPONSE_DATA .= '
                    <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $userData->id->getAsInt() . '"><span>' . $LOCALE_PEOPLE['contact_user'] . '</span></a></div>';
                    }
                }
                $RESPONSE_DATA .= '
                    <div class="ruling_updated_at"><span>' . $LOCALE_PUBLICATION['publish_date'] . ':</span>' . $objData->updated_at->get()->format('d.m.Y ' . $LOCALE_FRAYM['datetime']['at'] . ' H:i') . '</div>
                </div>
            </div>
        </div>
    </div>
    <div class="page_block">
        <div class="publication_content">';

                $text = $rulingService->clearRulingItemText($objData->content->get());

                // Здесь будет показана модель
                $innerRules = $rulingService->getInnerRules($text);

                foreach ($innerRules as $innerRule) {
                    $result = '';
                    $data = $innerRule[1];

                    if (($data['if'] ?? false) && is_array($data['if']) && count($data['if']) > 0) {
                        // проверяем на наличие всех указанных признаков
                        $allValuesPresent = true;

                        foreach ($data['if'] as $questionData) {
                            if (!is_array($questionData)) {
                                $allValuesPresent = false;
                            }
                        }

                        $dataItem = $data['item'] ?? null;
                        $dataHtml = $data['html'] ?? null;

                        if ($allValuesPresent && (is_numeric($dataItem) || $dataHtml)) {
                            $result = '<div class="ruling_injection">';

                            if (is_numeric($data['item'] ?? false)) {
                                $objDataChild = $rulingItemEditService->get($data['item']);
                                $result .= sprintf($LOCALE['injection'], $objDataChild->id->getAsInt(), DataHelper::escapeOutput($objDataChild->name->get()));
                            } elseif ($data['html'] ?? false) {
                                $result .= sprintf($LOCALE['html_injection'], $data['html']);
                            }

                            $result .= '<ul>';

                            foreach ($data['if'] as $showIfQuestionId => $showIfQuestionAnswers) {
                                foreach ($showIfQuestionAnswers as $showIfQuestionAnswer) {
                                    $questionData = $rulingQuestionEditService->get($showIfQuestionId);
                                    $answerId = $showIfQuestionAnswer;
                                    preg_match('#\[' . $answerId . '\]\[([^\]]*)\]#', DataHelper::escapeOutput($questionData->field_values->get()), $answerMatch);
                                    $result .= '<li>' . sprintf($LOCALE['answer_to_question'], DataHelper::escapeOutput($questionData->field_name->get()), $answerMatch[1]) . '</li>';
                                }
                            }

                            $result .= '</ul></div>';
                        }
                    }

                    $text = preg_replace('#<p>' . preg_quote($innerRule[0]) . '</p>#', $result, $text);
                    $text = preg_replace('#' . preg_quote($innerRule[0]) . '<br>#', $result, $text);
                    $text = preg_replace('#' . preg_quote($innerRule[0]) . '#', $result, $text);
                }

                $RESPONSE_DATA .= $text . '</div><hr>';

                // Данная модель будет показана в генераторе, если
                $showIfs = DataHelper::jsonFixedDecode($objData->show_if->get()[0]);

                foreach ($showIfs as $showIf) {
                    $RESPONSE_DATA .= '<div class="ruling_injection_2">' . $LOCALE['will_be_shown'] . '<ul>';

                    foreach ($showIf as $showIfLinksData => $ignore) {
                        // $showIfLinksData - ссылка на вопрос и ответ в формате: 2:6
                        unset($matches);
                        preg_match('#(\d+):(\d+)#', $showIfLinksData, $matches);

                        if ($matches[1] > 0 && $matches[2] > 0) {
                            $questionData = $rulingQuestionEditService->get($matches[1]);
                            $answerId = $matches[2];
                            preg_match('#\[' . $answerId . '\]\[([^\]]*)\]#', DataHelper::escapeOutput($questionData->field_values->get()), $answerMatch);
                            $RESPONSE_DATA .= '<li>' . sprintf($LOCALE['answer_to_question'], DataHelper::escapeOutput($questionData->field_name->get()), $answerMatch[1]) . '</li>';
                        }
                    }
                    $RESPONSE_DATA .= '</ul></div>';
                }

                // Данная модель будет показана внутри
                $parentRulingItems = $rulingItemEditService->getAll([['content', '%"item":' . $objData->id->getAsInt() . '}%', [OperandEnum::LIKE]]]);

                $firstItem = true;

                foreach ($parentRulingItems as $parentRulingItem) {
                    if ($firstItem) {
                        $RESPONSE_DATA .= '<div class="ruling_injection_2">' . $LOCALE['will_be_shown_in'] . '<ul>';
                        $firstItem = false;
                    }

                    $RESPONSE_DATA .= '<li>' . sprintf($LOCALE['in_item'], $parentRulingItem->id->getAsInt(), DataHelper::escapeOutput($parentRulingItem->name->get())) . '<ul>';

                    $innerRules = $rulingService->getInnerRules(DataHelper::escapeOutput($parentRulingItem->content->get(), EscapeModeEnum::plainHTML), 'item');

                    foreach ($innerRules as $innerRule) {
                        $data = $innerRule[1];

                        if (($data['if'] ?? false) && is_array($data['if']) && count($data['if']) > 0 && is_numeric($data['item'])) {
                            // проверяем на наличие всех указанных признаков
                            $allValuesPresent = true;

                            foreach ($data['if'] as $questionData) {
                                if (!is_array($questionData)) {
                                    $allValuesPresent = false;
                                }
                            }

                            if ($allValuesPresent && $data['item'] === $objData->id->getAsInt()) {
                                foreach ($data['if'] as $showIfQuestionId => $showIfQuestionAnswers) {
                                    foreach ($showIfQuestionAnswers as $showIfQuestionAnswer) {
                                        $questionData = $rulingQuestionEditService->get($showIfQuestionId);
                                        $answerId = $showIfQuestionAnswer;
                                        preg_match('#\[' . $answerId . '\]\[([^\]]*)\]#', DataHelper::escapeOutput($questionData->field_values->get()), $answerMatch);
                                        $RESPONSE_DATA .= '<li>' . sprintf($LOCALE['answer_to_question'], DataHelper::escapeOutput($questionData->field_name->get()), $answerMatch[1]) . '</li>';
                                    }
                                }
                            }
                        }
                    }
                    $RESPONSE_DATA .= '</ul></li>';
                }

                if (!$firstItem) {
                    $RESPONSE_DATA .= '</ul></div>';
                }

                $RESPONSE_DATA .= ($tags !== '' ? '<div class="publication_tags">' . $tags . '</div>' : '');

                if (CURRENT_USER->isLogged()) {
                    $RESPONSE_DATA .= '</div>
        <div class="page_block">
		<h2>' . $LOCALE['conversation'] . '</h2>
		<div class="block" id="ruling_wall">
            <div class="block_header">' . MessageHelper::conversationForm(null, '{ruling_item_wall}', DataHelper::getId(), $LOCALE_PUBLICATION['input_message']) . '</div>
            <div class="block_data">
                <a class="load_wall" obj_type="{ruling_item_wall}" obj_id="' . $objData->id->getAsInt() . '">' . $LOCALE_GLOBAL['show_more'] . '</a>
            </div>
        </div>';
                }

                $RESPONSE_DATA .= '
	</div>
</div>
</div>';
            }
        } else {
            [$rulingQuestions, $showHideFieldsScript, $rulingItems] = $rulingService->preView($viewAll, $rulingTagId, $searchString, $fillform);

            $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<h1 class="page_header"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . ($viewAll ? 'view_all=1' : '') . '">' . $LOCALE['title'] . '</a></h1>
<div class="page_blocks margin_top">
';

            if (!$viewAll) {
                $RESPONSE_DATA .= '
<div class="page_block">
    <h2>' . $LOCALE['about'] . '</h2>
    <div class="publication_content">' . $LOCALE['about_text'] . '</div>
    <h2>' . $LOCALE['generate_rules'] . '</h2>
    <form action="/ruling/" method="POST" enctype="multipart/form-data" id="form_ruling_question" target="_blank">
    <input type="hidden" name="action" value="generate" />';

                $i = 1;

                foreach ($rulingQuestions as $rulingQuestion) {
                    $RESPONSE_DATA .= $rulingQuestion->asHTMLWrapped(0, true, $i);
                    ++$i;
                }

                $RESPONSE_DATA .= '
    <button class="main">' . $LOCALE['generate'] . '</button>
    </form>
    ' . $showHideFieldsScript . '
</div>
';
            } else {
                $RESPONSE_DATA .= '
<div class="page_block">';
                $RESPONSE_DATA .= '
    <div class="tags_cloud ruling">' . $rulingService->drawRulingTagsCloud($rulingTagId) . '</div>';

                $RESPONSE_DATA .= '
	<form action="' . ABSOLUTE_PATH . '/' . KIND . '/view_all=1" method="POST" id="form_inner_search">
		<a class="search_image sbi sbi-search"></a><input class="search_input" name="search" id="search" type="text" value="' . ($_REQUEST['search'] ?? '') . '" placehold="' . $LOCALE['search'] . '" autocomplete="off">
	</form>';

                $RESPONSE_DATA .= '
	<div class="publications ruling">
	';

                foreach ($rulingItems as $rulingItem) {
                    $RESPONSE_DATA .= $rulingService->showRulingItemShort($rulingItem, false);
                }
                $RESPONSE_DATA .= '
	</div>
	<div class="clear"></div>
</div>';
            }

            $RESPONSE_DATA .= '
</div>
</div>';
        }

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }

    public function Generate(bool $printMode): ?Response
    {
        /** @var RulingService $rulingService */
        $rulingService = CMSVCHelper::getService('ruling');

        $LOCALE = $this->getLOCALE();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '';

        [$filledFormLink, $linkToGeneration] = $rulingService->preGenerate();

        if (!$printMode) {
            $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
<div class="publication_content ruling">
<a href="' . $filledFormLink . '" id="filled_form_link" target="_blank">' . $LOCALE['filled_form_link'] . '</a><a href="' . $linkToGeneration . '" id="link_to_generation" target="_blank">' . $LOCALE['generation_link'] . '</a>';
        }

        $RESPONSE_DATA .= $rulingService->generateRulingText();

        if ($printMode) {
            echo '<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
	p.pagebreak {
		page-break-before: always;
	}
</style>
</head>

<body>' . $RESPONSE_DATA . '</body>
</html>';
            exit;
        } else {
            $RESPONSE_DATA .= '
</div>
</div>
</div>';
        }

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
