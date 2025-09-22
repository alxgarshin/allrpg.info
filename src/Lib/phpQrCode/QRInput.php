<?php

declare(strict_types=1);

namespace PhpQrCode;

use Exception;

class QRInput
{
    /** @var QRInputItem[] */
    public array $items;

    public static array $anTable = [
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        36,
        -1,
        -1,
        -1,
        37,
        38,
        -1,
        -1,
        -1,
        -1,
        39,
        40,
        -1,
        41,
        42,
        43,
        0,
        1,
        2,
        3,
        4,
        5,
        6,
        7,
        8,
        9,
        44,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        10,
        11,
        12,
        13,
        14,
        15,
        16,
        17,
        18,
        19,
        20,
        21,
        22,
        23,
        24,
        25,
        26,
        27,
        28,
        29,
        30,
        31,
        32,
        33,
        34,
        35,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
        -1,
    ];

    private int $version;
    private int $level;

    public function __construct($version = 0, $level = QRConfig::QR_ECLEVEL_L)
    {
        if ($version < 0 || $version > QRConfig::QRSpec_VERSION_MAX || $level > QRConfig::QR_ECLEVEL_H) {
            throw new Exception('Invalid version no');
        }

        $this->version = $version;
        $this->level = $level;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function setVersion($version)
    {
        if ($version < 0 || $version > QRConfig::QRSpec_VERSION_MAX) {
            throw new Exception('Invalid version no');
        }

        $this->version = $version;

        return 0;
    }

    public function getErrorCorrectionLevel()
    {
        return $this->level;
    }

    public function append($mode, $size, $data)
    {
        try {
            $entry = new QRInputItem($mode, $size, $data);
            $this->items[] = $entry;

            return 0;
        } catch (Exception) {
            return -1;
        }
    }

    public static function checkModeNum($size, $data)
    {
        for ($i = 0; $i < $size; ++$i) {
            if ((ord($data[$i]) < ord('0')) || (ord($data[$i]) > ord('9'))) {
                return false;
            }
        }

        return true;
    }

    public static function estimateBitsModeNum($size)
    {
        $w = (int) $size / 3;
        $bits = $w * 10;

        switch ($size - $w * 3) {
            case 1:
                $bits += 4;
                break;
            case 2:
                $bits += 7;
                break;
            default:
                break;
        }

        return $bits;
    }

    public static function lookAnTable($c)
    {
        return ($c > 127) ? -1 : self::$anTable[$c];
    }

    public static function checkModeAn($size, $data)
    {
        for ($i = 0; $i < $size; ++$i) {
            if (self::lookAnTable(ord($data[$i])) === -1) {
                return false;
            }
        }

        return true;
    }

    public static function estimateBitsModeAn($size)
    {
        $w = (int) ($size / 2);
        $bits = $w * 11;

        if ($size & 1) {
            $bits += 6;
        }

        return $bits;
    }

    public static function estimateBitsMode8($size)
    {
        return $size * 8;
    }

    public static function estimateBitsModeKanji($size)
    {
        return (int) (($size / 2) * 13);
    }

    public static function checkModeKanji($size, $data)
    {
        if ($size & 1) {
            return false;
        }

        for ($i = 0; $i < $size; $i += 2) {
            $val = (ord($data[$i]) << 8) | ord($data[$i + 1]);

            if ($val < 0x8140
                || ($val > 0x9FFC && $val < 0xE040)
                || $val > 0xEBBF) {
                return false;
            }
        }

        return true;
    }

    public static function check($mode, $size, $data)
    {
        if ($size <= 0) {
            return false;
        }

        switch ($mode) {
            case QRConfig::QR_MODE_NUM:
                return self::checkModeNum($size, $data);
            case QRConfig::QR_MODE_AN:
                return self::checkModeAn($size, $data);
            case QRConfig::QR_MODE_KANJI:
                return self::checkModeKanji($size, $data);
            case QRConfig::QR_MODE_STRUCTURE:
            case QRConfig::QR_MODE_8:
                return true;
            default:
                break;
        }

        return false;
    }

    public function estimateBitStreamSize($version)
    {
        $bits = 0;

        foreach ($this->items as $item) {
            $bits += $item->estimateBitStreamSizeOfEntry($version);
        }

        return $bits;
    }

    public function estimateVersion()
    {
        $version = 0;
        do {
            $prev = $version;
            $bits = $this->estimateBitStreamSize($prev);
            $version = QRSpec::getMinimumVersion((int) (($bits + 7) / 8), $this->level);

            if ($version < 0) {
                return -1;
            }
        } while ($version > $prev);

        return $version;
    }

    public function createBitStream()
    {
        $total = 0;

        foreach ($this->items as $item) {
            $bits = $item->encodeBitStream($this->version);

            if ($bits < 0) {
                return -1;
            }

            $total += $bits;
        }

        return $total;
    }

    public function convertData()
    {
        $ver = $this->estimateVersion();

        if ($ver > $this->getVersion()) {
            $this->setVersion($ver);
        }

        while (true) {
            $bits = $this->createBitStream();

            if ($bits < 0) {
                return -1;
            }

            $ver = QRSpec::getMinimumVersion((int) (($bits + 7) / 8), $this->level);

            if ($ver < 0) {
                throw new Exception('WRONG VERSION');
            } elseif ($ver > $this->getVersion()) {
                $this->setVersion($ver);
            } else {
                break;
            }
        }

        return 0;
    }

    public function appendPaddingBit($bstream)
    {
        $bits = $bstream->size();
        $maxwords = QRSpec::getDataLength($this->version, $this->level);
        $maxbits = $maxwords * 8;

        if ($maxbits === $bits) {
            return 0;
        }

        if ($maxbits - $bits < 5) {
            return $bstream->appendNum($maxbits - $bits, 0);
        }

        $bits += 4;
        $words = (int) (($bits + 7) / 8);

        $padding = new QRBitStream();
        $ret = $padding->appendNum($words * 8 - $bits + 4, 0);

        if ($ret < 0) {
            return $ret;
        }

        $padlen = $maxwords - $words;

        if ($padlen > 0) {
            $padbuf = [];

            for ($i = 0; $i < $padlen; ++$i) {
                $padbuf[$i] = ($i & 1) ? 0x11 : 0xEC;
            }

            $ret = $padding->appendBytes($padlen, $padbuf);

            if ($ret < 0) {
                return $ret;
            }
        }

        return $bstream->append($padding);
    }

    public function mergeBitStream()
    {
        if ($this->convertData() < 0) {
            return;
        }

        $bstream = new QRBitStream();

        foreach ($this->items as $item) {
            $ret = $bstream->append($item->bstream);

            if ($ret < 0) {
                return;
            }
        }

        return $bstream;
    }

    public function getBitStream()
    {
        $bstream = $this->mergeBitStream();

        if ($bstream === null) {
            return;
        }

        $ret = $this->appendPaddingBit($bstream);

        if ($ret < 0) {
            return;
        }

        return $bstream;
    }

    public function getByteStream()
    {
        $bstream = $this->getBitStream();

        return $bstream?->toByte();
    }
}
