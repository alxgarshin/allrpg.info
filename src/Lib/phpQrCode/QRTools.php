<?php

declare(strict_types=1);

namespace PhpQrCode;

class QRTools
{
    public static function binarize($frame)
    {
        $len = count($frame);

        foreach ($frame as &$frameLine) {
            for ($i = 0; $i < $len; ++$i) {
                $frameLine[$i] = (ord($frameLine[$i]) & 1) ? '1' : '0';
            }
        }

        return $frame;
    }

    public static function log($outfile, $err): void
    {
        if (QRConfig::QR_LOG_DIR !== false) {
            if ($err !== '') {
                if ($outfile !== false) {
                    file_put_contents(
                        QRConfig::QR_LOG_DIR . basename($outfile) . '-errors.txt',
                        date('Y-m-d H:i:s') . ': ' . $err,
                        FILE_APPEND,
                    );
                } else {
                    file_put_contents(QRConfig::QR_LOG_DIR . 'errors.txt', date('Y-m-d H:i:s') . ': ' . $err, FILE_APPEND);
                }
            }
        }
    }

    public static function markTime($markerId): void
    {
        list($usec, $sec) = explode(' ', microtime());
        $time = ((float) $usec + (float) $sec);

        if (!isset($GLOBALS['QRConfig::QR_time_bench'])) {
            $GLOBALS['QRConfig::QR_time_bench'] = [];
        }

        $GLOBALS['QRConfig::QR_time_bench'][$markerId] = $time;
    }
}
