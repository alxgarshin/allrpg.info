<?php

declare(strict_types=1);

namespace App\CMSVC\News;

use App\CMSVC\NewsEdit\NewsEditModel;
use App\Helper\{DateHelper, DesignHelper, TextHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\Trait\PageCounter;
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;

#[Controller(NewsController::class)]
class NewsView extends BaseView
{
    use PageCounter;

    public function Response(): ?Response
    {
        /** @var NewsService $newsService */
        $newsService = $this->service;

        $LOCALE = $this->LOCALE;

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '';

        $newsData = DataHelper::getId() > 0 ? $newsService->getOneItem(DataHelper::getId()) : $newsService->getAllItems();

        if ($newsData) {
            $RESPONSE_DATA .= '<div class="maincontent_data autocreated kind_' . KIND . '">';
            $RESPONSE_DATA .= '<h1 class="form_header"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/">' . $LOCALE['header'] . '</a></h1>';

            if (CURRENT_USER->isAdmin()) {
                if (DataHelper::getId() > 0) {
                    $RESPONSE_DATA .= '<a class="edit_button" href="' . ABSOLUTE_PATH . '/news_edit/' . $newsData->id->getAsInt() . '/">' . $LOCALE['edit_current_news'] . '</a>';
                } else {
                    $RESPONSE_DATA .= '<a class="edit_button" href="' . ABSOLUTE_PATH . '/news_edit/">' . $LOCALE['edit_news'] . '</a>';
                }
            }

            $RESPONSE_DATA .= '
<div class="page_blocks margin_top">';

            if (DataHelper::getId() > 0) {
                /** @var NewsEditModel $newsData */
                $tags_array = $newsData->tags->get();
                $tags = '';

                foreach ($tags_array as $tag) {
                    if ($tag) {
                        $tag_data = DB->findObjectById($tag, 'tag');
                        // $tags.='<a href="'.ABSOLUTE_PATH.'/publication/tag='.$tag_data['id'].'#tabs-2">';
                        $tags .= $tag_data['name'];
                        // $tags.='</a>';
                        $tags .= ', ';
                    }
                }
                $tags = mb_substr($tags, 0, mb_strlen($tags) - 2);

                $newsDate = DateHelper::dateFromTo($newsData->modelData);

                $RESPONSE_DATA .= '<h2>' . DataHelper::escapeOutput($newsData->name->get()) . '</h2>
	' . ($tags !== '' ? '<div class="news_date">' . $LOCALE['tags'] . ': ' . $tags . '</div>' : '') . '
	<div class="news_date">' . ($newsDate['range'] ? $LOCALE['range'] . ': ' : $LOCALE['published'] . ': ') . $newsDate['date'] . '</div>
	<div class="publication_content">';

                if (strip_tags($newsData->content->get() ?? '') !== '') {
                    $text = $newsData->content->get();
                } else {
                    $text = $newsData->annotation->get();
                }
                $text = preg_replace('# alt=""#', '', $text);
                $text = preg_replace('# title="[^"]+"#', '', $text);
                $text = preg_replace('#font-size:\s*[0-9\.]+px[;]*#', '', $text);
                $RESPONSE_DATA .= $text;
                $RESPONSE_DATA .= '</div>';

                // заглушка для добавления видео-файлов
                if (preg_match('#video#', $RESPONSE_DATA)) {
                    $video = '';

                    preg_match('#video:([^:]+):(\d+):(\d+):([^:]+):#', $RESPONSE_DATA, $match);

                    if ($match[4]) {
                        preg_match('#\.(.+)$#', $match[1], $extension);
                        $extension = $extension[1];
                        $width = $match[2];
                        $height = $match[3];
                        $ratio = $match[4];
                        $path = '/' . $_ENV['UPLOADS'][7]['path'] . $match[1];
                    }
                    $RESPONSE_DATA = preg_replace('#video[^\s]+#', $video, $RESPONSE_DATA);
                }

                $RESPONSE_DATA .= '<div class="news_additional">';

                if ($newsData->data_came_from->get() !== null) {
                    $RESPONSE_DATA .= $LOCALE['source'] . ': ' . TextHelper::makeURLsActive(DataHelper::escapeOutput($newsData->data_came_from->get())) . '<br>';
                }
                $RESPONSE_DATA .= ($newsDate['range'] ? $LOCALE['published'] . ': ' . $newsData->show_date->getAsUsualDateTime() : '');
                $RESPONSE_DATA .= '</div>';
            } else {
                $newsDataCount = $newsData[1];
                $newsData = $newsData[0];

                /** @var NewsEditModel[] $newsData */
                foreach ($newsData as $newsDataItem) {
                    $RESPONSE_DATA .= $newsService->showNews($newsDataItem->modelData);
                }

                if ($newsDataCount > 25) {
                    $RESPONSE_DATA .= $this->drawPageCounter(
                        '',
                        PAGE,
                        $newsDataCount,
                        25,
                        '&type=' . ($_REQUEST['type'] ?? ''),
                    );
                }
            }
            $RESPONSE_DATA .= '</div></div>';
        }

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
