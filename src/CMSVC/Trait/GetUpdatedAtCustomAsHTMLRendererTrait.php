<?php

declare(strict_types=1);

namespace App\CMSVC\Trait;

use Fraym\Element\Item;

/** Кастомная отрисовка даты и времени обновления вместе с автором обновления */
trait GetUpdatedAtCustomAsHTMLRendererTrait
{
    use UserServiceTrait;

    public function getUpdatedAtCustomAsHTMLRenderer(Item\Timestamp $item, bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $userService = $this->getUserService();

        $value = $item->get()->getTimestamp();
        $name = $item->name . $item->getLineNumberWrapped();
        $html = '';

        if ($item->getShowInObjects()) {
            $userModelId = $item->model->getElement('last_update_user_id')->get();

            $html .=
                $item->getAsUsualDateTime() .
                (
                    $userModelId !== null ?
                    '<br>' . $userService->showNameWithId($userService->get($userModelId), true) :
                    ''
                );
        }

        $html .= '<input type="hidden" name="' . $name . '" value="' . $value . '" class="timestamp" />';

        return $html;
    }
}
