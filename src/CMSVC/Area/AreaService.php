<?php

declare(strict_types=1);

namespace App\CMSVC\Area;

use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Enum\{ActEnum, ActionEnum};
use Fraym\Helper\{DataHelper, LocaleHelper};
use Generator;

/** @extends BaseService<AreaModel> */
#[Controller(AreaController::class)]
class AreaService extends BaseService
{
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

    public function getSortTipe(): array
    {
        $LOCALE = $this->entity->LOCALE;

        return $LOCALE['elements']['tipe']['values'];
    }

    public function getToAreaDefault(): string
    {
        if ($this->act === ActEnum::edit && DataHelper::getId() > 0) {
            $LOCALE = LocaleHelper::getLocale(['fraym']);

            return '<a href="' . ABSOLUTE_PATH . '/area/' . DataHelper::getId() . '/" target="_blank">' . $LOCALE['functions']['open_in_a_new_window'] . '</a>';
        }

        return '';
    }

    public function getHaveGoodValues(): Generator
    {
        return DB->getArrayOfItems('areahave WHERE gr=1 ORDER BY name', 'id', 'name');
    }

    public function getHaveBadValues(): Generator
    {
        return DB->getArrayOfItems('areahave WHERE gr=2 ORDER BY name', 'id', 'name');
    }

    public function getCoordinatesContext(): array
    {
        if ($this->act === ActEnum::edit && DataHelper::getId() > 0) {
            return ['area:view', 'area:create', 'area:update'];
        }

        return [];
    }

    public function getKogdaigraIdContext(): array
    {
        if (CURRENT_USER->isAdmin() || CURRENT_USER->checkAllRights('info')) {
            return ['area:view', 'area:create', 'area:update'];
        }

        return [];
    }
}
