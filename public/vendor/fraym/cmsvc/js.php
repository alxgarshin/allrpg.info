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

require_once __DIR__ . '/../../../../lib/fraym/AppStart.php';

if (KIND) {
    $CMSCVName = TextHelper::snakeCaseToCamelCase(KIND);
    $isJavascriptComponent = false;

    if (($_REQUEST['component'] ?? null) === '1') {
        $isJavascriptComponent = true;

        $jsPath = INNER_PATH . 'src/JsComponent/' . KIND . '.min.js';

        if (!file_exists($jsPath)) {
            $jsPath = INNER_PATH . 'src/JsComponent/' . KIND . '.js';
        }
    } else {
        $jsPath = INNER_PATH . 'src/CMSVC/' . $CMSCVName . '/js.min.js';

        if (!file_exists($jsPath)) {
            $jsPath = INNER_PATH . 'src/CMSVC/' . $CMSCVName . '/js.js';
        }
    }

    if (file_exists($jsPath)) {
        $fileContents = file_get_contents($jsPath);

        $output = 'dataLoaded.' . ($isJavascriptComponent ? 'libraries' : 'js') . '["' . KIND . '"] = function (withDocumentEvents) {
' . $fileContents . '

showExecutionTime(\'' . ($isJavascriptComponent ? 'libraries' : 'js') . '/' . KIND . ' loaded\');
}';

        header('Content-Type: application/javascript');
        header('Content-Length: ' . strlen($output));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($jsPath)) . ' GMT');

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
