<?php

declare(strict_types=1);

namespace App\CMSVC\Notion;

use App\CMSVC\CalendarEvent\CalendarEventService;
use App\CMSVC\Trait\UserServiceTrait;
use App\Helper\{DateHelper, TextHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Enum\EscapeModeEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper};

#[Controller(NotionController::class)]
class NotionService extends BaseService
{
    use UserServiceTrait;

    /** Вывод отзыва */
    public function conversationNotion(array $commentData, bool $showRating = true): string
    {
        $userService = $this->getUserService();

        $LOCALE = $this->LOCALE;

        $commentCreator = $userService->get($commentData['creator_id']);

        $commentContent = '<div class="wall_message_container' . ($commentData['active'] !== '1' ? ' inactive' : '') . '"><a id="notion_' . $commentData['id'] . '"></a><div class="notion wall_message" message_id="' . $commentData['id'] . '">';
        $commentContent .= '<div class="wall_message_photo">' .
            $userService->photoNameLink($commentCreator, '', false) . '</div>
<div class="wall_message_data">' . ($showRating ? '
<div class="wall_message_rating">' . ($commentData['rating'] === 1 ? '+' . $commentData['rating'] : (int) $commentData['rating']) . '</div>' : '') . '
<div class="wall_message_creator">' . $userService->showNameExtended($commentCreator, true, true, '', false, false, true) . '</div>
<div class="wall_message_content">' . TextHelper::basePrepareText(DataHelper::escapeOutput($commentData['content'], EscapeModeEnum::forHTMLforceNewLines)) . '</div>
<div class="wall_message_time">' . DateHelper::showDateTime($commentData['updated_at']);

        if ($commentData['creator_id'] === CURRENT_USER->id()) {
            $commentContent .= '<a class="wall_message_edit" message_id="' . $commentData['id'] . '">' . $LOCALE['placeholders']['edit'] . '</a>';
        } elseif (
            CURRENT_USER->isAdmin() || $commentData['object_admin_id'] === CURRENT_USER->id()
            || ($commentData['calendar_event_id'] > 0 && CURRENT_USER->checkAllrights('info'))
        ) {
            if ($commentData['active'] === '1') {
                $commentContent .= '<a class="wall_message_hide" action_request="/notion/show_hide_notion" obj_id="' . $commentData['id'] . '">'
                    . $LOCALE['placeholders']['hide'] . '</a>';
            } else {
                $commentContent .= '<a class="wall_message_show" action_request="/notion/show_hide_notion" obj_id="' . $commentData['id'] . '">' . $LOCALE['placeholders']['show'] . '</a>';
            }
        }
        $commentContent .= '</div>
</div>
<div class="clear"></div>
</div></div>';

        return $commentContent;
    }

    /** Сохранение отзыва */
    public function notionMessageSave(int $objId, string $content, int $rating): array
    {
        $LOCALE = $this->LOCALE;

        $returnArr = [];

        $notion = DB->findObjectById($objId, 'notion');

        if ($notion['id'] > 0 && $notion['creator_id'] === CURRENT_USER->id()) {
            DB->update('notion', ['content' => $content, 'rating' => $rating], ['id' => $objId]);

            $returnArr = [
                'response' => 'success',
                'response_text' => $LOCALE['messages']['notion_save_success'],
            ];
        }

        return $returnArr;
    }

    /** Удаление отзыва */
    public function notionMessageDelete(int $objId): array
    {
        $LOCALE = $this->LOCALE;

        $returnArr = [];

        $notion = DB->findObjectById($objId, 'notion');

        if ($notion['id'] > 0 && $notion['creator_id'] === CURRENT_USER->id()) {
            DB->delete('notion', ['id' => $objId]);

            $returnArr = [
                'response' => 'success',
                'response_text' => $LOCALE['messages']['notion_delete_success'],
            ];
        }

        return $returnArr;
    }

    /** Показ / сокрытие отзыва */
    public function showHideNotion(int $objId): array
    {
        $returnArr = [];

        /** @var CalendarEventService $calendarEventService */
        $calendarEventService = CMSVCHelper::getService('calendar_event');

        $LOCALE = $this->LOCALE;

        $notion = DB->findObjectById($objId, 'notion');

        if ($notion['id'] ?? false) {
            $calendarEvent = false;

            if ($notion['calendar_event_id'] > 0) {
                $calendarEvent = $calendarEventService->get($notion['calendar_event_id']);
            }

            if ($calendarEvent || $notion['user_id'] > 0) {
                if (
                    CURRENT_USER->isAdmin()
                    || ($calendarEvent->id->getAsInt() > 0 && (CURRENT_USER->checkAllrights('info') || $calendarEvent->creator_id->getAsInt() === CURRENT_USER->id()))
                    || ($notion['user_id'] > 0 && $notion['user_id'] === CURRENT_USER->id())
                ) {
                    $responseData = [];

                    if ($notion['active'] === '1') {
                        DB->update('notion', ['active' => 0], ['id' => $objId]);
                        $responseData['text'] = $LOCALE['placeholders']['show'];
                        $responseData['active'] = 0;
                    } else {
                        DB->update('notion', ['active' => 1], ['id' => $objId]);
                        $responseData['text'] = $LOCALE['placeholders']['hide'];
                        $responseData['active'] = 1;
                    }

                    $returnArr = [
                        'response' => 'success',
                        'response_data' => $responseData,
                    ];
                }
            }
        }

        return $returnArr;
    }
}
