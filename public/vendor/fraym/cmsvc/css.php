<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Fraym\Helper\TextHelper;

require_once __DIR__ . '/../../../fraym.php';

if ($_REQUEST['kind']) {
    $CMSCVName = TextHelper::snakeCaseToCamelCase(KIND);
    $cssPath = INNER_PATH . 'src/CMSVC/' . $CMSCVName . '/css.min.css';
    if (!file_exists($cssPath)) {
        $cssPath = INNER_PATH . 'src/CMSVC/' . $CMSCVName . '/css.css';
    }

    if (file_exists($cssPath)) {
        $fileContents = file_get_contents($cssPath);

        $output = $fileContents;

        header('Content-Type: text/css');
        header('Content-Length: ' . strlen($output));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($cssPath)) . ' GMT');

        echo $output;
        exit;
    }
}

header('HTTP/1.0 404 Not Found');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

exit;
