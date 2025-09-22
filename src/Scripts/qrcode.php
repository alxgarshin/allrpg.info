<?php

declare(strict_types=1);

use PhpQrCode\QRCode;

$outerFrame = 1;
$pixelPerPoint = 5;

$frame = QRCode::text($_REQUEST['json_string']);

$h = count($frame);
$w = mb_strlen($frame[0]);

$imgW = $w + 2 * $outerFrame;
$imgH = $h + 2 * $outerFrame;

$baseImage = imagecreate($imgW, $imgH);

$col[0] = imagecolorallocate($baseImage, 255, 255, 255);
$col[1] = imagecolorallocate($baseImage, 0, 0, 0);

imagefill($baseImage, 0, 0, $col[0]);

for ($y = 0; $y < $h; ++$y) {
    for ($x = 0; $x < $w; ++$x) {
        if ($frame[$y][$x] === '1') {
            imagesetpixel($baseImage, $x + $outerFrame, $y + $outerFrame, $col[1]);
        }
    }
}

$targetImageWidth = 500;
$targetImageHeight = 500;
$targetImage = imagecreate($targetImageWidth, $targetImageHeight);
imagecopyresized(
    $targetImage,
    $baseImage,
    0,
    0,
    0,
    0,
    $targetImageWidth,
    $targetImageHeight,
    $imgW,
    $imgH,
);
imagedestroy($baseImage);

imagecolortransparent($targetImage, $col[0]);

header('Content-Type: image/png');
imagepng($targetImage);
imagedestroy($targetImage);
