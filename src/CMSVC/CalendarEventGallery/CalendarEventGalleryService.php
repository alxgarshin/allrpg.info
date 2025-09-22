<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEventGallery;

use App\CMSVC\CalendarEvent\CalendarEventService;
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Helper\{DateHelper, ResponseHelper};

#[Controller(CalendarEventGalleryController::class)]
class CalendarEventGalleryService extends BaseService
{
    #[DependencyInjection]
    public CalendarEventService $calendarEventService;

    /** Добавление объекта галереи события */
    public function addCalendarEventGallery(int $objId, string $link, string $name, string $thumb, string $author): array
    {
        $LOCALE = $this->getLOCALE();

        $returnArr = [];

        $calendarEventData = $this->calendarEventService->get($objId);

        if ($calendarEventData->id->getAsInt() > 0) {
            DB->insert(
                'calendar_event_gallery',
                [
                    'creator_id' => CURRENT_USER->id(),
                    'calendar_event_id' => $objId,
                    'link' => $link,
                    'name' => $name,
                    'thumb' => $thumb,
                    'author' => $author,
                    'created_at' => time(),
                    'updated_at' => time(),
                ],
            );

            ResponseHelper::success($LOCALE['messages']['photo_video_added']);
            $returnArr = [
                'response' => 'success',
            ];
        }

        return $returnArr;
    }

    /** Изменение объекта галереи события */
    public function changeCalendarEventGallery(int $objId, string $link, string $name, string $thumb, string $author): array
    {
        $LOCALE = $this->getLOCALE();

        $returnArr = [];

        $calendarEventGalleryData = DB->select('calendar_event_gallery', ['id' => $objId], true);

        if ($calendarEventGalleryData['id'] > 0) {
            $calendarEventData = $this->calendarEventService->get($calendarEventGalleryData['calendar_event_id']);

            if ($calendarEventData->id->getAsInt() > 0) {
                if (
                    CURRENT_USER->isAdmin() || CURRENT_USER->checkAllrights('info') || $calendarEventData->creator_id->getAsInt() === CURRENT_USER->id()
                    || $calendarEventGalleryData['user_id'] === CURRENT_USER->id()
                ) {
                    DB->update(
                        'calendar_event_gallery',
                        [
                            ['creator_id', CURRENT_USER->id()],
                            ['link', $link],
                            ['name', $name],
                            ['thumb', $thumb],
                            ['author', $author],
                            ['updated_at', DateHelper::getNow()],
                        ],
                        ['id' => $objId],
                    );

                    ResponseHelper::success($LOCALE['messages']['photo_video_changed']);
                    $returnArr = [
                        'response' => 'success',
                    ];
                }
            }
        }

        return $returnArr;
    }

    /** Удаление объекта галереи события */
    public function deleteCalendarEventGallery(int $objId): array
    {
        $LOCALE = $this->getLOCALE();

        $returnArr = [];

        $calendarEventGalleryData = DB->select('calendar_event_gallery', ['id' => $objId], true);

        if ($calendarEventGalleryData['id'] > 0) {
            $calendarEventData = $this->calendarEventService->get($calendarEventGalleryData['calendar_event_id']);

            if ($calendarEventData->id->getAsInt() > 0) {
                if (
                    CURRENT_USER->isAdmin() || CURRENT_USER->checkAllrights('info') || $calendarEventData->creator_id->getAsInt() === CURRENT_USER->id()
                    || $calendarEventGalleryData['user_id'] === CURRENT_USER->id()
                ) {
                    DB->delete('calendar_event_gallery', ['id' => $objId]);
                    ResponseHelper::success($LOCALE['messages']['photo_video_deleted']);
                    $returnArr = [
                        'response' => 'success',
                    ];
                }
            }
        }

        return $returnArr;
    }
}
