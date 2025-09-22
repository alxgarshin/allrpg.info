<?php

declare(strict_types=1);

namespace App\Helper;

abstract class TextHelper extends \Fraym\Helper\TextHelper
{
    /** Превращение ссылок на mp3 в тексте в плеер */
    public static function mp3ToPlayer($content)
    {
        if (preg_match('#\[loop]#', $content)) {
            $content = preg_replace(
                '/((https?:\/\/)?(\w+?\.)+?(\w+?\/)+\w+?.(mp3|ogg))\[loop]/',
                '<div class="mp3_player"><audio src="$1" preload="auto" loop controls></audio></div>',
                $content,
            );
        } else {
            $content = preg_replace(
                '/((https?:\/\/)?(\w+?\.)+?(\w+?\/)+\w+?.(mp3|ogg))/',
                '<div class="mp3_player"><audio src="$1" preload="auto" controls></audio></div>',
                $content,
            );
        }

        return $content;
    }

    /** Превращение названия валюты в нужный значок */
    public static function currencyNameToSign(string $name): string
    {
        $result = '';

        if ($name === 'RUR') {
            $result = '&#8381;';
        } elseif ($name === 'USD') {
            $result = '$';
        } elseif ($name === 'EUR') {
            $result = '&euro;';
        }

        return $result;
    }
}
