<?php

declare(strict_types=1);

namespace App\CMSVC\Error404;

use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Interface\Response;

#[CMSVC(
    controller: Error404Controller::class,
)]
class Error404Controller extends BaseController
{
    public function Default(): ?Response
    {
        $LOCALE = $this->LOCALE;

        $errorHtml = '<div style="display: flex; justify-content:center; align-items: center; width: 100%; height: 100%;"><span style="text-align: center;"><h1>404</h1>
' . $LOCALE['text'] . '<br>
<a href="' . ABSOLUTE_PATH . '/">' . $LOCALE['to_main_page'] . '</a></span></div>';

        if (REQUEST_TYPE->isDynamicRequest()) {
            return $this->asHtml('<div class="maincontent_data kind_404">' . $errorHtml . '</div>', $LOCALE['title']);
        } else {
            header('HTTP/1.0 404 Not Found');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');

            $content = '<html>
<title>' . $LOCALE['title'] . '</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicons/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="' . ABSOLUTE_PATH . '/vendor/fraym/css/global.min.css" type="text/css">
<link rel="stylesheet" href="' . ABSOLUTE_PATH . '/css/global.min.css" type="text/css">
<body>' . $errorHtml . '</body></html>';

            echo $content;
            exit;
        }
    }
}
