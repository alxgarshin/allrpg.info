<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Fraym\Enum\OperandEnum;
use WideImage\WideImage;

require_once __DIR__ . '/../../lib/fraym/BaseVar.php';
require_once __DIR__ . '/../../lib/fraym/AppStart.php';

$path = basename($_GET['path']);
$name = $_GET['name'] ?? false;
$thumbnail = $_GET['thumbnail'] ?? false;

if ($name && !preg_match('#\.(php)$#i', $name)) {
    $filePath = $path . '/' . $name;

    if (file_exists($filePath)) {
        if ($thumbnail == 'true') {
            $fname = $path . '/thumbnail/' . $name . '.webp';

            if (!file_exists($fname)) {
                $width = 200;
                $height = 200;

                foreach ($_ENV['UPLOADS'] as $uploadData) {
                    if ($uploadData['path'] === $path . '/' && isset($uploadData['thumbwidth']) && isset($uploadData['thumbheight'])) {
                        $width = $uploadData['thumbwidth'];
                        $height = $uploadData['thumbheight'];
                        break;
                    }
                }

                WideImage::load($filePath)->resize($width, $height)->saveToFile(
                    INNER_PATH . 'public' . $_ENV['UPLOADS_PATH'] . $path . '/thumbnail/' . $name . '.webp'
                );
            }

            $fp = fopen($fname, 'rb');
            header("Content-Type: image/webp");
            header("Content-Length: " . filesize($fname));
            fpassthru($fp);
            exit;
        }

        $result = DB->query(
            "SELECT * FROM library WHERE path LIKE :pathValue AND path NOT LIKE '%{external:%'",
            [
                ['pathValue', '%' . $name . '%'],
            ],
            true
        );

        $field = null;

        if (!$result) {
            $result = DB->select(
                'conversation_message',
                [
                    ['attachments', '%' . $name . '%', [OperandEnum::LIKE]]
                ],
                true
            );

            if (!$result) {
                $table = null;

                foreach ($_ENV['UPLOADS'] as $uploadData) {
                    if ($uploadData['path'] === $path . '/') {
                        $field = $uploadData['columnname'];
                        $table = $uploadData['tablename'];
                        break;
                    }
                }

                if ($field && $table) {
                    if (is_array($table)) {
                        foreach ($table as $individualTable) {
                            $result = DB->select(
                                $individualTable,
                                [
                                    [$field, '%' . $name . '%', [OperandEnum::LIKE]]
                                ],
                                true
                            );

                            if ($result) {
                                break;
                            }
                        }
                    } else {
                        $result = DB->select(
                            $table,
                            [
                                [$field, '%' . $name . '%', [OperandEnum::LIKE]]
                            ],
                            true
                        );
                    }
                }
            }
        }

        $downloadedFileName = $name;

        if ($result) {
            preg_match_all('#{([^:]+):([^}]+)}#', $result[$field], $matches);

            foreach ($matches[1] as $key => $value) {
                if ($name === $matches[2][$key]) {
                    $downloadedFileName = $value;
                }
            }
        }

        header("Content-Disposition: attachment; filename=\"" . $downloadedFileName . "\"");
        header("Content-Length: " . filesize($filePath));
        header("Content-Type: application/octet-stream;");
        readfile($filePath);
        exit;
    }
}

header('Location: /Error404/');
