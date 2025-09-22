<?php

declare(strict_types=1);

$value = htmlspecialchars($_REQUEST['f']);

if ($value !== '') {
    $value = preg_replace('#^https?:/#', 'https://', $value);

    $filepath = urldecode(preg_replace('#escq#', '?', $value));

    if (getimagesize($filepath)) {
        $image = WideImage\WideImage::loadFromFile($filepath);
        $image->resize(800, 200, 'inside', 'down')->output('webp');
    } else {
        echo $filepath;
    }
}
