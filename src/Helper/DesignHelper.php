<?php

declare(strict_types=1);

namespace App\Helper;

use App\CMSVC\User\UserService;
use Fraym\Helper\{CMSVCHelper, DataHelper};
use Fraym\Interface\Helper;

abstract class DesignHelper implements Helper
{
    /** Inline css-свойство background-image */
    public static function getCssBackgroundImage(string $path): string
    {
        return "background-image: url('" . $path . "')";
    }

    /** Добавление залоговка к формам ввода */
    public static function insertHeader(string $content, ?string $header): string
    {
        if (preg_match('#<div class="maincontent_data([^"]*)"><h1 class="form_header">.*?</h1>#', $content)) {
            return preg_replace(
                '#<div class="maincontent_data([^"]*)"><h1 class="form_header">.*?</h1>#',
                '<div class="maincontent_data$1"><h1 class="form_header">' . self::changePageHeaderTextToLink($header, '/' . KIND . '/') . '</h1>',
                $content,
            );
        }

        return preg_replace(
            '#<div class="maincontent_data([^"]*)">#',
            '<div class="maincontent_data$1"><h1 class="form_header">' . self::changePageHeaderTextToLink($header, '/' . KIND . '/') . '</h1>',
            $content,
        );
    }

    /** Добавление script'ов внутрь основного div'а ответа */
    public static function insertScripts(string $content, string $scripts): string
    {
        return mb_substr($content, 0, -6) . $scripts . '</div>';
    }

    /** Оборачивание залоговка страницы в ссылку */
    public static function changePageHeaderTextToLink(?string $text, ?string $href = null): string
    {
        return (!is_null($href) ? '<a href="' . $href . '">' : '') . (string) $text . (!is_null($href) ? '</a>' : '');
    }

    /** Замена {user_id} и {user_sid} блоков в пунктах меню */
    public static function replaceVarsInMenu(string $menuItemText)
    {
        $menuItemText = str_replace('{user_id}', CURRENT_USER->id() ? (string) CURRENT_USER->id() : '', $menuItemText);

        return str_replace('{user_sid}', CURRENT_USER->sid() ? (string) CURRENT_USER->sid() : '', $menuItemText);
    }

    /** Отрисовка навигационного блока сообщества или проекта */
    public static function drawPlate(string $objType, array $objData): string
    {
        $objType = DataHelper::clearBraces($objType);
        $uploadType = (in_array($objType, ['project', 'community']) ? 'projects_and_communities_avatars' : $objType);
        $imgPath = FileHelper::getImagePath($objData['attachments'], FileHelper::getUploadNumByType($uploadType));

        return '<div class="navitems_plate"><a href="' . ABSOLUTE_PATH . '/' . $objType . '/' . $objData['id'] . '/"><div class="navitems_plate_more"><div class="navitems_plate_name">' . $objData['name'] .
            '</div><div class="navitems_plate_events' . (($objData['new_count'] ?? false) > 0 ? ' filled' : '') . '"><span>' . (isset($objData['new_count']) ? '+'
                . $objData['new_count'] : $objData['members_count']) . '</span></div></div><div class="navitems_plate_avatar"><img src="' .
            ($imgPath ? $imgPath : ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_' . $objType . '.svg') . '"></div></a></div>';
    }

    /** Проверка видимости пункта того или иного меню */
    public static function checkMenuItemVisibility(array $menuItemData): bool
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        return !isset($menuItemData['visibility'])
            || ($menuItemData['visibility'] === 'ruling_admin' && $userService->isRulingAdmin())
            || ($menuItemData['visibility'] === 'admin' && CURRENT_USER->isAdmin())
            || ($menuItemData['visibility'] === 'logged' && CURRENT_USER->isLogged())
            || ($menuItemData['visibility'] === 'not_logged' && !CURRENT_USER->isLogged());
    }
}
