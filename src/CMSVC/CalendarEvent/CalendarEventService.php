<?php

declare(strict_types=1);

namespace App\CMSVC\CalendarEvent;

use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\{PostCreate, PreCreate, PreDelete};
use Fraym\Enum\{ActEnum, ActionEnum};
use Fraym\Helper\{CurlHelper, DataHelper, LocaleHelper, ResponseHelper};
use Generator;

/** @extends BaseService<CalendarEventModel> */
#[Controller(CalendarEventController::class)]
#[PreCreate]
#[PreDelete]
#[PostCreate]
class CalendarEventService extends BaseService
{
    private ?array $matchedProjectData = null;

    public function getMatchedProjectData(): array
    {
        if (is_null($this->matchedProjectData)) {
            $this->matchedProjectData = [];

            if ($this->act === ActEnum::add && ($_REQUEST['project_id'] ?? false)) {
                $this->matchedProjectData = DB->select('project', ['id' => $_REQUEST['project_id']], true);
            }
        }

        return $this->matchedProjectData;
    }

    public function preCreate(): void
    {
        $LOC = $this->LOCALE['messages'];

        $this->checkValidData();

        $calendarEventData = $this->get(
            null,
            [
                'name' => $_REQUEST['name'][0] ?? false,
                'date_from' => date('Y-m-d', strtotime($_REQUEST['date_from'][0] ?? '')),
            ],
        );

        if ($calendarEventData) {
            ResponseHelper::responseOneBlock('error', sprintf($LOC['calendar_event_present'], $calendarEventData->id->getAsInt()));
        }
    }

    public function preChange(): void
    {
        $this->checkValidData();
    }

    public function preDelete(): void
    {
        DB->update('calendar_event', ['wascancelled' => 1], ['id' => DataHelper::getId()]);
        ResponseHelper::response([['success', $this->entity->getObjectMessages($this->entity)[2]]], '/' . KIND . '/');
    }

    public function checkValidData(): void
    {
        if (($_REQUEST['date_to'][0] ?? false) < ($_REQUEST['date_from'][0] ?? false)) {
            $_REQUEST['date_to'][0] = $_REQUEST['date_from'][0];
            $_REQUEST['date_to'][0] = $_REQUEST['date_from'][0];
        }
    }

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $id) {
            if (CURRENT_USER->isLogged()) {
                DB->update('calendar_event', ['creator_id' => CURRENT_USER->id()], ['id' => $id]);
            } else {
                DB->update('calendar_event', ['addip' => DataHelper::getRealIp(), 'tomoderate' => 1], ['id' => $id]);
            }

            CurlHelper::curlPostAsync(
                'http://kogda-igra.ru/api/game/add.php',
                ['uri' => ABSOLUTE_PATH . '/calendar_event/' . $id . '/&automated=1'],
            );
        }
    }

    public function checkRightsViewRestrict(): ?string
    {
        if ($this->act === ActEnum::edit || ($_REQUEST['mine'] ?? false) === '1' || in_array(ACTION, ActionEnum::getBaseValues())) {
            if (CURRENT_USER->isLogged()) {
                return ($_REQUEST['mine'] ?? false) && $_REQUEST['mine'] === 1 ? 'creator_id=' . CURRENT_USER->id() : '';
            } else {
                return "addip='" . DataHelper::getRealIp() . "' and tomoderate='1'";
            }
        }

        return null;
    }

    public function checkRightsChangeRestrict(): ?string
    {
        if ($this->act === ActEnum::edit || ($_REQUEST['mine'] ?? false) === '1' || in_array(ACTION, ActionEnum::getBaseValues())) {
            if (CURRENT_USER->isAdmin()) {
                return null;
            } elseif (CURRENT_USER->isLogged()) {
                return 'creator_id=' . CURRENT_USER->id();
            } else {
                return "addip='" . DataHelper::getRealIp() . "' and tomoderate='1'";
            }
        }

        return null;
    }

    public function checkRightsChange(): bool
    {
        if (CURRENT_USER->isAdmin() || CURRENT_USER->checkAllRights('info')) {
            return true;
        } elseif ($this->act === ActEnum::edit || ($_REQUEST['mine'] ?? false) === '1' || in_array(ACTION, ActionEnum::getBaseValues())) {
            return true;
        }

        return false;
    }

    public function checkRightsDelete(): bool
    {
        return CURRENT_USER->isAdmin();
    }

    public function getToGameDefault(): string
    {
        if ($this->act === ActEnum::edit && DataHelper::getId() > 0) {
            $LOCALE = LocaleHelper::getLocale(['fraym']);

            return '<a href="' . ABSOLUTE_PATH . '/calendar_event/' . DataHelper::getId() . '/" target="_blank">' . $LOCALE['functions']['open_in_a_new_window'] . '</a>';
        }

        return '';
    }

    public function getAdminFieldsContext(): array
    {
        if (CURRENT_USER->isAdmin() || CURRENT_USER->checkAllRights('info')) {
            return ['calendarEvent:view', 'calendarEvent:create', 'calendarEvent:update'];
        }

        return [];
    }

    public function getNameDefault(): string
    {
        return $this->getMatchedProjectData()['name'] ?? '';
    }

    public function getAreaValues(): Generator
    {
        return DB->getArrayOfItems('area ORDER BY name', 'id', 'name');
    }

    public function getGametypeValues(): Generator
    {
        return DB->getArrayOfItems('gametype WHERE gametype=1 ORDER BY name', 'id', 'name');
    }

    public function getGametype2Values(): Generator
    {
        return DB->getArrayOfItems('gametype WHERE gametype=2 ORDER BY name', 'id', 'name');
    }

    public function getGametype3Values(): Generator
    {
        return DB->getArrayOfItems('gameworld ORDER BY name', 'id', 'name');
    }

    public function getGametype4Values(): Generator
    {
        return DB->getArrayOfItems('gametype WHERE gametype=3 ORDER BY name', 'id', 'name');
    }

    public function getOrderPageDefault(): string
    {
        return ($this->getMatchedProjectData()['id'] ?? false) ?
            ABSOLUTE_PATH . '/myapplication/act=add&project_id=' . $this->getMatchedProjectData()['id'] . '&application_type=0' :
            '';
    }

    public function getDateFromDefault(): string
    {
        return $this->getMatchedProjectData()['date_from'] ?? '';
    }

    public function getDateToDefault(): string
    {
        return $this->getMatchedProjectData()['date_to'] ?? '';
    }

    public function getPlayerNumDefault(): ?int
    {
        return $this->getMatchedProjectData()['player_count'] ?? null;
    }

    public function getContentDefault(): string
    {
        return $this->getMatchedProjectData()['annotation'] ?? '';
    }

    public function getAgroupValues(): Generator
    {
        return DB->getArrayOfItems('calendar_event_group ORDER BY name', 'id', 'name');
    }
}
