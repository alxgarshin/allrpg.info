<?php

declare(strict_types=1);

namespace PhpQrCode;

use Exception;

class QRCode
{
    public int|string $version;
    public int $width;
    public array $data = [];

    public function encodeMask(QRInput $input, $mask)
    {
        if ($input->getVersion() < 0 || $input->getVersion() > QRConfig::QRSpec_VERSION_MAX) {
            throw new Exception('wrong version');
        }

        if ($input->getErrorCorrectionLevel() > QRConfig::QR_ECLEVEL_H) {
            throw new Exception('wrong level');
        }

        $raw = new QRRawCode($input);

        QRTools::markTime('after_raw');

        $version = $raw->version;
        $width = QRSpec::getWidth($version);
        $frame = QRSpec::newFrame($version);

        $filler = new QRFrameFiller($width, $frame);

        // inteleaved data and ecc codes
        for ($i = 0; $i < $raw->dataLength + $raw->eccLength; ++$i) {
            $code = $raw->getCode();
            $bit = 0x80;

            for ($j = 0; $j < 8; ++$j) {
                $addr = $filler->next();
                $filler->setFrameAt($addr, 0x02 | (($bit & $code) !== 0));
                $bit = $bit >> 1;
            }
        }

        QRTools::markTime('after_filler');

        unset($raw);

        // remainder bits
        $j = QRSpec::getRemainder($version);

        for ($i = 0; $i < $j; ++$i) {
            $addr = $filler->next();
            $filler->setFrameAt($addr, 0x02);
        }

        $frame = $filler->frame;
        unset($filler);

        // masking
        $maskObj = new QRMask();

        if ($mask < 0) {
            if (QRConfig::QR_FIND_BEST_MASK) {
                $masked = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());
            } else {
                $masked = $maskObj->makeMask(
                    $width,
                    $frame,
                    QRConfig::QR_DEFAULT_MASK % 8,
                    $input->getErrorCorrectionLevel(),
                );
            }
        } else {
            $masked = $maskObj->makeMask($width, $frame, $mask, $input->getErrorCorrectionLevel());
        }

        if ($masked === null) {
            return;
        }

        QRTools::markTime('after_mask');

        $this->version = $version;
        $this->width = $width;
        $this->data = $masked;

        return $this;
    }

    public function encodeInput(QRInput $input)
    {
        return $this->encodeMask($input, -1);
    }

    public function encodeString8bit($string, $version, $level)
    {
        if ($string === null) {
            throw new Exception('empty string!');
        }

        $input = new QRInput($version, $level);

        $ret = $input->append($input, QRConfig::QR_MODE_8, strlen($string));

        if ($ret < 0) {
            unset($input);

            return;
        }

        return $this->encodeInput($input);
    }

    public function encodeString($string, $version, $level, $hint, $casesensitive)
    {
        if ($hint !== QRConfig::QR_MODE_8 && $hint !== QRConfig::QR_MODE_KANJI) {
            throw new Exception('bad hint');
        }

        $input = new QRInput($version, $level);

        $ret = QRSplit::splitStringToQRInput($string, $input, $hint, $casesensitive);

        if ($ret < 0) {
            return;
        }

        return $this->encodeInput($input);
    }

    public static function png(
        $text,
        $outfile = false,
        $level = QRConfig::QR_ECLEVEL_L,
        $size = 3,
        $margin = 4,
    ): void {
        $enc = QREncode::factory($level, $size, $margin);
        $enc->encodePNG($text, $outfile);
    }
    public static function text($text, $outfile = false, $level = QRConfig::QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        QRTools::markTime('start');
        $enc = QREncode::factory($level, $size, $margin);

        return $enc->encode($text, $outfile);
    }

    public static function raw($text, $level = QRConfig::QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = QREncode::factory($level, $size, $margin);

        return $enc->encodeRAW($text);
    }
}
