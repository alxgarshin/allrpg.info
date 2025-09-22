<?php

declare(strict_types=1);

$path = $_GET['path'];
$active = $_GET['active'] === 1;

$ext = pathinfo($path, PATHINFO_EXTENSION);

if (in_array($ext, ['jpg', 'jpeg'])) {
    $imageS = imagecreatefromjpeg($path);
} else {
    $imageS = imagecreatefrompng($path);
}

$width = imagesx($imageS);
$height = $width;

$newwidth = 100;
$newheight = 100;

$image = imagecreatetruecolor($newwidth, $newheight);
imagealphablending($image, true);
imagecopyresampled($image, $imageS, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

// create masking
$mask = imagecreatetruecolor($newwidth, $newheight);

$transparent = imagecolorallocate($mask, 255, 0, 255);
imagecolortransparent($mask, $transparent);

$red = imagecolorallocate($mask, 202, 18, 18);
$green = imagecolorallocate($mask, 43, 137, 191);

imageantialias($mask, true);

imagefilledellipse($mask, $newwidth / 2, $newheight / 2, $newwidth, $newheight, $active ? $green : $red);
imagefilledellipse($mask, $newwidth / 2, $newheight / 2, $newwidth - 10, $newheight - 10, $transparent);

$black = imagecolorallocate($mask, 0, 0, 0);
imagecopymerge($image, $mask, 0, 0, 0, 0, $newwidth, $newheight, 100);
imagecolortransparent($image, $black);
imagefill($image, 0, 0, $black);

// output and free memory
header('Content-type: image/png');
imagepng($image);
imagedestroy($image);
imagedestroy($mask);
