<?php

declare(strict_types=1);

namespace Captcha;

class Captcha
{
    /** Returns jpeg file stream with unscannable letters encoded in front of colorful disturbing background */
    public static function image($phrase, $width = 200, $height = 60, $inverse = 0)
    {
        putenv('GDFONTPATH=' . __DIR__);

        // -- initialize in-memory image with gd library
        srand((int) microtime() * 21017);
        $img = imagecreatetruecolor($width, $height);
        $R = $inverse ? 0xFF : 0x00;
        imagefilledrectangle($img, 0, 0, $width, $height, captcha::random_color($img, 222 ^ $R, 255 ^ $R));
        $c1 = rand(150 ^ $R, 185 ^ $R);
        $c2 = rand(195 ^ $R, 230 ^ $R);

        // -- configuration
        $fonts = ['College', 'VeraSe', 'Vera'];
        // $fonts += glob(EWIKI_FONT_DIR."/*.ttf");

        // -- encolour bg
        $wd = 20;
        $x = 0;
        $y = 0;

        while ($x < $width) {
            imagefilledrectangle($img, $x, 0, $x += $wd, $height, captcha::random_color($img, 222 ^ $R, 255 ^ $R));
            $wd += max(10, rand(0, 20) - 10);
        }

        // -- make interesting background I, lines
        $wd = 4;
        $w1 = 0;
        $w2 = 0;

        for ($x = 0; $x < $width; $x += $wd) {
            imageline($img, $x + $w1, 0, $x + $w2, $height - 1, captcha::random_color($img, $c1, $c2));

            if ($x < $height) {  // horizontally ("y")
                imageline($img, 0, $x - $w2, $width - 1, $x - $w1, captcha::random_color($img, $c1, $c2));
            }
            $wd += rand(0, 8) - 4;

            if ($wd < 1) {
                $wd = 2;
            }
            $w1 += rand(0, 8) - 4;
            $w2 += rand(0, 8) - 4;

            if (($x > $height) && ($y > $height)) {
                break;
            }
        }

        // -- more disturbing II, random letters
        $limit = rand(30, 90);

        for ($n = 0; $n < $limit; ++$n) {
            $letter = '';
            do {
                $letter .= chr(rand(31, 125)); // random symbol
            } while (rand(0, 1));
            $size = rand(5, $height / 2);
            $half = (int) ($size / 2);
            $x = rand(-$half, $width + $half);
            $y = rand(+$half, $height);
            $rotation = rand(60, 300);
            $c1 = captcha::random_color($img, 130 ^ $R, 240 ^ $R);
            $font = $fonts[rand(0, count($fonts) - 1)];
            imagettftext($img, $size, $rotation, $x, $y, $c1, $font, $letter);
        }

        // -- add the real text to it
        $len = strlen($phrase);
        $w1 = 10;
        $w2 = $width / ($len + 1);

        for ($p = 0; $p < $len; ++$p) {
            $letter = $phrase[$p];
            $size = rand(18, (int) ($height / 2.2));
            $rotation = rand(-33, 33);
            $y = rand($size + 3, $height - 3);
            $x = $w1 + $w2 * $p;
            $w1 += rand((int) (-$width / 90), (int) ($width / 40));  // @BUG: last char could be +30 pixel outside of image
            $font = $fonts[rand(0, count($fonts) - 1)];
            $r = rand(30, 99);
            $g = rand(30, 99);
            $b = rand(30, 99); // two colors for shadow
            $c1 = imagecolorallocate($img, $r ^ $R, $g ^ $R, $b ^ $R);
            $c2 = imagecolorallocate($img, $r * 2 ^ $R, $g * 2 ^ $R, $b * 2 ^ $R);
            imagettftext($img, $size, $rotation, (int) ($x + 1), (int) $y, $c2, $font, $letter);
            imagettftext($img, $size, $rotation, (int) $x, (int) ($y - 1), $c1, $font, $letter);
        }

        return $img;
    }

    /* helper code */
    public static function random_color($img, $a, $b)
    {
        return imagecolorallocate($img, rand($a, $b), rand($a, $b), rand($a, $b));
    }
}
