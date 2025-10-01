<?php

declare(strict_types=1);

$value = htmlspecialchars($_REQUEST['f']);

if ($value !== '') {
    $value = preg_replace('#^https?:/#', 'https://', $value);

    $filepath = urldecode(preg_replace('#escq#', '?', $value));

    $filepath = preg_replace('#&amp;amp;#', '&', $filepath);

    if (@getimagesize($filepath)) {
        $image = WideImage\WideImage::loadFromFile($filepath);
        $image->resize(800, 200, 'inside', 'down')->output('webp');
    } else {
        $filePath = INNER_PATH . 'public' . $_ENV['DESIGN_PATH'] . 'ajax-loader.gif';

        header("Content-Length: " . filesize($filePath));
        header("Content-Type: application/octet-stream;");
        readfile($filePath);
        exit;
    }
}
