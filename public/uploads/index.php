<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Fraym\Vendor\UploadHandler\UploadHandler;

require_once __DIR__ . '/../../lib/fraym/AppStart.php';

$uploadType = $_REQUEST['type'] ?? false;
$uploads = $_ENV['UPLOADS'];

/** Если вдруг нет uploads[$uploadType], проверяем, не виртуальный ли это файл */
if (!isset($uploads[$uploadType]) && isset($uploads[0]['virtual'])) {
    $searchData = $_FILES;
    if (($_SERVER['REQUEST_METHOD'] ?? false) === 'DELETE' || ($_REQUEST['_method'] ?? false) === 'DELETE') {
        $searchData = $_REQUEST;
    }

    foreach ($searchData as $fileKey => $fileData) {
        if (str_contains($fileKey, 'virtual')) {
            $uploads[$uploadType] = [
                'path' => $uploads[0]['virtual']['path'],
                'columnname' => $fileKey,
                'extensions' => $uploads[0]['virtual']['extensions'],
            ];
        }
    }
}

if ($uploadType > 0) {
    $options = [
        'upload_dir' => __DIR__ . '/' . $uploads[$uploadType]['path'],
        'upload_url' => ABSOLUTE_PATH . $_ENV['UPLOADS_PATH'] . $uploads[$uploadType]['path'],
        'param_name' => $uploads[$uploadType]['columnname'],
        'accept_file_types' => $uploads[$uploadType]['extensions'],
    ];
    if ($uploads[$uploadType]['isimage'] ?? false) {
        $options['image_versions']['']['max_width'] = $uploads[$uploadType]['maxwidth'] ?? null;
        $options['image_versions']['']['max_height'] = $uploads[$uploadType]['maxheight'] ?? null;

        if ($uploads[$uploadType]['thumbmake'] ?? false) {
            $options['image_versions']['thumbnail'] = [
                // Uncomment the following to force the max
                // dimensions and e.g. create square thumbnails:
                //'crop' => true,
                'max_width' => $uploads[$uploadType]['thumbwidth'] ?? null,
                'max_height' => $uploads[$uploadType]['thumbheight'] ?? null
            ];
        }
    }

    ini_set('memory_limit', '256M');
    $upload_handler = new UploadHandler($options);
}
