<?php

declare(strict_types=1);

namespace PhpQrCode;

use Exception;

class QREncode
{
    public bool $casesensitive = true;
    public bool $eightbit = false;
    public int $version = 0;
    public int $size = 3;
    public int $margin = 4;
    public int $level = QRConfig::QR_ECLEVEL_L;
    public int $hint = QRConfig::QR_MODE_8;

    public static function factory($level = QRConfig::QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = new QREncode();
        $enc->size = $size;
        $enc->margin = $margin;
        $enc->level = match (mb_strtolower((string) $level)) {
            '0', '1', '2', '3' => $level,
            'l' => QRConfig::QR_ECLEVEL_L,
            'm' => QRConfig::QR_ECLEVEL_M,
            'q' => QRConfig::QR_ECLEVEL_Q,
            'h' => QRConfig::QR_ECLEVEL_H,
        };

        return $enc;
    }

    public function encodeRAW($intext)
    {
        $code = new QRCode();

        if ($this->eightbit) {
            $code->encodeString8bit($intext, $this->version, $this->level);
        } else {
            $code->encodeString($intext, $this->version, $this->level, $this->hint, $this->casesensitive);
        }

        return $code->data;
    }

    public function encode($intext, $outfile = false)
    {
        $code = new QRCode();

        if ($this->eightbit) {
            $code->encodeString8bit($intext, $this->version, $this->level);
        } else {
            $code->encodeString($intext, $this->version, $this->level, $this->hint, $this->casesensitive);
        }

        QRTools::markTime('after_encode');

        if ($outfile !== false) {
            file_put_contents((string) $outfile, join("\n", QRTools::binarize($code->data)));
        } else {
            return QRTools::binarize($code->data);
        }

        return;
    }

    public function encodePNG($intext, $outfile = false, $saveandprint = false): void
    {
        try {
            ob_start();
            $tab = $this->encode($intext);
            $err = ob_get_contents();
            ob_end_clean();

            if ($err !== '') {
                QRTools::log($outfile, $err);
            }

            $maxSize = (int) (QRConfig::QR_PNG_MAXIMUM_SIZE / (count($tab) + 2 * $this->margin));

            QRImage::png($tab, $outfile, min(max(1, $this->size), $maxSize), $this->margin, $saveandprint);
        } catch (Exception $e) {
            QRTools::log($outfile, $e->getMessage());
        }
    }
}
