<?php

declare(strict_types=1);

namespace App\CMSVC\Trait;

use Fraym\Helper\{LocaleHelper, ResponseHelper};
use Fraym\Response\ArrayResponse;

/** Провека заполненности строки search текстом */
trait RequestCheckSearchTrait
{
    public function requestCheckSearch(): ?ArrayResponse
    {
        if (PRE_REQUEST_CHECK) {
            if (!($_REQUEST['search'] ?? false) || mb_strlen($_REQUEST['search']) < 3) {
                $LOCALE = LocaleHelper::getLocale(['search', 'global', 'messages']);
                ResponseHelper::responseOneBlock('error', $LOCALE['need_more_symbols']);
            } else {
                return ResponseHelper::response([], 'submit');
            }
        }

        return null;
    }
}
