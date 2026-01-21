<?php

declare(strict_types=1);

namespace App\CMSVC\Article;

use App\CMSVC\ArticlesEdit\ArticlesEditModel;
use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;

#[CMSVC(
    model: ArticlesEditModel::class,
)]
class ArticleController extends BaseController
{
    public function Default(): ?Response
    {
        $RESPONSE_DATA = '';

        $LOCALE = $this->LOCALE;

        $kind = KIND;

        $section_data = DB->select(
            tableName: 'article',
            criteria: [
                'attachments' => $kind,
                'active' => '1',
            ],
            oneResult: true,
        );

        if (($section_data['parent'] ?? false) > 0) {
            $parent_data = $section_data;

            while (($parent_data['parent'] ?? false) > 0) {
                $parent_data = DB->select(
                    tableName: 'article',
                    criteria: [
                        'id' => $parent_data['parent'],
                    ],
                );
            }
            $kind = $parent_data['attachments'];
        }

        $article_section_id = $section_data['id'];

        if (DataHelper::getId() > 0) {
            $page_data = DB->select(
                tableName: 'article',
                criteria: [
                    'id' => DataHelper::getId(),
                ],
                oneResult: true,
            );
        } else {
            $page_data = DB->select(
                tableName: 'article',
                criteria: [
                    'parent' => $section_data['id'],
                    'active' => '1',
                ],
                oneResult: true,
                order: [
                    'code',
                ],
                limit: 1,
            );
        }

        if (is_null($page_data['content'] ?? null)) {
            while (isset($page_data['id']) && is_null($page_data['content'] ?? null)) {
                $article_section_id = $page_data['id'];
                $section_data = $page_data;
                $page_data = DB->select(
                    tableName: 'article',
                    criteria: [
                        'parent' => $page_data['id'],
                        'active' => '1',
                    ],
                    oneResult: true,
                    order: [
                        'code',
                    ],
                    limit: 1,
                );
            }
        } else {
            $section_data = DB->select(
                tableName: 'article',
                criteria: [
                    'id' => $page_data['parent'],
                ],
                oneResult: true,
                order: [
                    'code',
                ],
                limit: 1,
            );
            $article_section_id = $section_data['id'];
        }

        /** Составляем корректный путь до статьи */
        $article_path = '';

        if (($section_data['parent'] ?? false) > 0) {
            $parent_data = $section_data;

            while (($parent_data['parent'] ?? false) > 0) {
                $article_path = '&nbsp;&nbsp;>&nbsp;&nbsp;' . ($article_path === '' ? '' : '<a href="' . ABSOLUTE_PATH . '/' . $kind . '/' . $parent_data['id'] . '/">') .
                    $parent_data['name'] . ($article_path === '' ? '' : '</a>') . $article_path;
                $parent_data = DB->select(
                    tableName: 'article',
                    criteria: [
                        'id' => $parent_data['parent'],
                        'active' => '1',
                    ],
                );
            }
            $article_path = '<a href="' . ABSOLUTE_PATH . '/' . $kind . '/' . $parent_data['id'] . '/">' . $parent_data['name'] . '</a>' . $article_path;
            $article_path = '<div class="article_path">' . $article_path . '</div>';
        }

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($section_data['name']);

        if ($section_data['id'] === $page_data['parent'] && $section_data['id'] === $article_section_id && $page_data['active'] === '1') {
            $RESPONSE_DATA .= '<div class="maincontent_data kind_' . $kind . '">';

            if (CURRENT_USER->isAdmin()) {
                $RESPONSE_DATA .= '<a class="edit_button" href="' . ABSOLUTE_PATH . '/articles_edit/">' . $LOCALE['edit_pages'] . '</a>';
            }

            $RESPONSE_DATA .= $article_path . '
<div class="open_part_main">
<div class="open_part_main_left reduced noborder">
<ul class="global_menu">';

            $section_pages_data = DB->select(
                tableName: 'article',
                criteria: [
                    'parent' => $page_data['parent'],
                    'active' => '1',
                ],
                order: [
                    'code',
                ],
            );

            foreach ($section_pages_data as $section_page_data) {
                if (is_null($section_page_data['content']) && !empty($section_page_data['attachments'])) {
                    $RESPONSE_DATA .= '<li class="none"><a href="' . ABSOLUTE_PATH . '/' . $section_page_data['attachments'] . '/">' . $section_page_data['name'] . '</a></li>';
                } else {
                    $RESPONSE_DATA .= '<li class="none' . ($section_page_data['id'] === $page_data['id'] ? ' selected' : '') . '"><a href="' . ABSOLUTE_PATH . $kind . '/' . $section_page_data['id'] . '/">' . $section_page_data['name'] . '</a></li>';
                }
            }

            $RESPONSE_DATA .= '
</ul>
</div>
<div class="open_part_main_middle full">
<h1 class="article_top_header">' . $page_data['name'] . '</h1>';

            /*if(!empty($page_data["updated_at"])) {
                $RESPONSE_DATA.='<div class="article_date">'.$LOCALE['publish_date'].': '.date("d.m.Y",$page_data["updated_at"]).' '.$LOCALE['publish_time_separator'].' '.date("G:i",$page_data["updated_at"]).'</div>';
            }*/
            if (!empty($page_data['author'])) {
                $RESPONSE_DATA .= '<div class="article_author">' . $LOCALE['authors'] . ': ' . $page_data['author'] . '</div>';
            }

            if ($page_data['tags'] !== '' && $page_data['tags'] !== '-') {
                $RESPONSE_DATA .= '<div class="article_tags">' . $LOCALE['tags'] . ': ';
                $tags = DataHelper::multiselectToArray($page_data['tags']);

                foreach ($tags as $key => $value) {
                    if ($value === '') {
                        unset($tags[$key]);
                    }
                }
                $tag_result = [];
                $tags_data = DB->select(
                    tableName: 'tag',
                    criteria: [
                        'id' => $tags,
                    ],
                    order: [
                        'name',
                    ],
                );

                foreach ($tags_data as $tag_data) {
                    $tag_result[] = mb_strtolower($tag_data['name']);
                }
                $RESPONSE_DATA .= implode(', ', $tag_result);
                $RESPONSE_DATA .= '</div>';
            }
            $text = preg_replace('# alt=""#', '', DataHelper::escapeOutput($page_data['content']));
            $text = preg_replace('# title="[^"]+"#', '', $text);
            $text = preg_replace('#font-size:\s*15.6px;*#', '', $text);
            $RESPONSE_DATA .= '<div class="article_content">' . $text . '</div>';

            // заглушка для добавления видео-файлов
            if (str_contains($RESPONSE_DATA, 'video')) {
                $video = '';

                preg_match('#video:([^:]+):(\d+):(\d+):([^:]+):#', $RESPONSE_DATA, $match);

                if (!empty($match[4])) {
                    preg_match('#\.(.+)$#', $match[1], $extension);
                    $extension = $extension[1];
                    $width = $match[2];
                    $height = $match[3];
                    $ratio = $match[4];
                    $path = '/' . $_ENV['UPLOADS'][7]['path'] . $match[1];
                }
                $RESPONSE_DATA = preg_replace('#video\S+#', $video, $RESPONSE_DATA);
            }

            $RESPONSE_DATA .= '</div></div>';
        }

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
