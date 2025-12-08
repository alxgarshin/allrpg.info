<?php

declare(strict_types=1);

namespace App\CMSVC\Error404;

use Fraym\BaseObject\{BaseController, CMSVC};

#[CMSVC(
    controller: Error404Controller::class,
)]
class Error404Controller extends BaseController
{
    public function Default(): null
    {
        header('HTTP/1.0 404 Not Found');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        $LOCALE = $this->LOCALE;

        $content = '<html>
<title>' . $LOCALE['title'] . '</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="' . ABSOLUTE_PATH . '/css/global.css" type="text/css">
<body><table style="width: 100%; height: 100%; border: 0;"><tr><td style="text-align:center; vertical-align: middle;"><h1>404</h1>
' . $LOCALE['text'] . '<br>
<a href="' . ABSOLUTE_PATH . '/">' . $LOCALE['to_main_page'] . '</a></td></tr></table></body></html>';

        echo $content;
        exit;
    }
}
