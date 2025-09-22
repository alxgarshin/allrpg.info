<?php

declare(strict_types=1);

namespace App\CMSVC\Mailtest;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\{EmailHelper, LocaleHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

#[CMSVC]
class MailtestController extends BaseController
{
    public function Response(): ?Response
    {
        if (CURRENT_USER->isAdmin()) {
            $LOCALE = LocaleHelper::getLocale(['global']);

            $to = $_GET['to'] ?? 'alxgarshin@gmail.com';
            $html = ($_GET['html'] ?? false) !== 'no';

            $message = 'Добрый день!<br>
<br>
Это сообщение исключительно для того, чтобы проверить, правильно ли всё настроено в системе.<br>
<br>
Приятной Вам работы.';

            $PAGETITLE = 'Mailtest';
            $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<h1 class="page_header">Тест рассылки</h1>
<div class="page_block">';

            if (EmailHelper::sendMail($LOCALE['sitename'], $_ENV['NOTIFY_EMAIL'], $to, 'Сообщение для проверки работоспособности', $message, $html)) {
                $RESPONSE_DATA .= 'Тестовый email (' . ($html ? 'html' : 'plain text') . ') отправлен на ' . $to;
            } else {
                $RESPONSE_DATA .= 'Ошибка отправки тестового email (' . ($html ? 'html' : 'plain text') . ') на ' . $to;
            }

            $RESPONSE_DATA .= '</div>
</div>';

            return new HtmlResponse($RESPONSE_DATA, $PAGETITLE);
        }

        return null;
    }
}
