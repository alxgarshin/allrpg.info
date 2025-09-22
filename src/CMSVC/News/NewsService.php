<?php

declare(strict_types=1);

namespace App\CMSVC\News;

use App\CMSVC\NewsEdit\{NewsEditModel, NewsEditService};
use App\Helper\{DateHelper, FileHelper};
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Enum\EscapeModeEnum;
use Fraym\Helper\{DataHelper, LocaleHelper};

#[Controller(NewsController::class)]
class NewsService extends BaseService
{
    #[DependencyInjection]
    public NewsEditService $newsEditService;

    public function getOneItem(int $id): ?NewsEditModel
    {
        return $this->newsEditService->get($id, ['active' => true]);
    }

    /** @return array{0: NewsEditModel[], 1: int} */
    public function getAllItems(): array
    {
        $type = $_REQUEST['type'] ?? false;

        $query = 'SELECT * FROM news WHERE active=\'1\'';

        if ($type === 1) {
            $query .= ' AND ((show_date<CURDATE() AND (from_date IS NULL OR from_date<CURDATE()) AND to_date IS NULL) OR to_date<CURDATE())';
        } elseif ($type === 2) {
            $query .= ' AND ((show_date>=CURDATE() AND show_date<DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND to_date IS NULL AND from_date IS NULL) OR (from_date<=CURDATE() AND (to_date>=CURDATE() OR to_date IS NULL)) OR (to_date>=CURDATE() AND (from_date<=CURDATE() OR from_date IS NULL)))';
        } elseif ($type === 3) {
            $query .= ' AND ((show_date>=DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND from_date IS NULL) OR from_date>=DATE_ADD(CURDATE(), INTERVAL 1 DAY))';
        }

        $query .= ' ORDER BY show_date DESC, updated_at DESC LIMIT ' . (PAGE * 25) . ', 25';

        $newsData = DB->query(
            $query,
            [],
        );

        /** @var ?NewsEditModel[] */
        $newsItems = $this->newsEditService->arraysToModels($newsData);

        $totalCount = DB->selectCount();

        return [$newsItems, $totalCount];
    }

    /** Вывод новости */
    public function showNews(array $newsData, bool $short = false): string
    {
        $LOCALE = LocaleHelper::getLocale(['news', 'global']);

        $result = '';
        $newsDate = DateHelper::dateFromTo($newsData);
        $nameText = DataHelper::escapeOutput($newsData['name']);
        $annotationText = strip_tags(DataHelper::escapeOutput($newsData['annotation'], EscapeModeEnum::plainHTML));
        $contentText = DataHelper::escapeOutput($newsData['content'], EscapeModeEnum::plainHTML);
        $contentText = $contentText ? strip_tags($contentText) : null;

        if ($short) {
            $result .= '<div class="news_short">' . ($newsData['type'] === 1 ? '' : (FileHelper::getImagePath($newsData['attachments'], FileHelper::getUploadNumByType('news')) !== '' ?
                '<div class="news_short_img"><img src="' .
                FileHelper::getImagePath($newsData['attachments'], FileHelper::getUploadNumByType('news')) . '"></div>' : '')) .
                '<div class="news_short_name"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $newsData['id'] . '/">' .
                $nameText .
                '</a></div><div class="news_short_date">' . $newsDate['date'] . '</div>' .
                $annotationText .
                '<div class="clear"></div></div>';
        } else {
            $result .= '<div class="news_full">';
            $result .= '<h2>';

            if (mb_strlen($annotationText) > 255 || $contentText !== '') {
                $result .= '<a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $newsData['id'] . '/">';
            }
            $result .= $nameText;

            if (mb_strlen($annotationText) > 255 || $contentText !== '') {
                $result .= '</a>';
            }
            $result .= '</h2><div class="news_date">' . ($newsDate['range'] ? $LOCALE['range'] : $LOCALE['published']) . ': ' . $newsDate['date'] . '</div>';
            $more = false;
            $result .= '<div class="publication_content">';

            if ($annotationText !== '') {
                $result .= (mb_strlen($annotationText) > 255 ? mb_substr($annotationText, 0, 255, 'utf8') . '&#8230;' : $annotationText);
                $more = true;
            } elseif ($contentText !== '') {
                $pointCheck = mb_substr($contentText, mb_strlen($contentText) - 1, 1, 'utf8');
                $result .= ($pointCheck === '.' ? mb_substr($contentText, 0, mb_strlen($contentText) - 1, 'utf8') : $contentText) . '&#8230;';
                $more = true;
            }

            if ($more) {
                $result .= '</div><div class="news_additional"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/' . $newsData['id'] . '/">' . $LOCALE['read_more'] . '</a>';
            }
            $result .= '</div>';
            $result .= '</div>';
        }

        return $result;
    }
}
