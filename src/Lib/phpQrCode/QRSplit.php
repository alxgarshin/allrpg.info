<?php

declare(strict_types=1);

namespace PhpQrCode;

use Exception;

class QRSplit
{
    public string $dataStr = '';
    public QRInput $input;
    public int $modeHint;

    public function __construct($dataStr, $input, $modeHint)
    {
        $this->dataStr = $dataStr;
        $this->input = $input;
        $this->modeHint = $modeHint;
    }

    public static function isdigitat($str, $pos)
    {
        if ($pos >= strlen($str)) {
            return false;
        }

        return (ord($str[$pos]) >= ord('0')) && (ord($str[$pos]) <= ord('9'));
    }

    public static function isalnumat($str, $pos)
    {
        if ($pos >= strlen($str)) {
            return false;
        }

        return QRInput::lookAnTable(ord($str[$pos])) >= 0;
    }

    public function identifyMode($pos)
    {
        if ($pos >= strlen($this->dataStr)) {
            return QRConfig::QR_MODE_NUL;
        }

        $c = $this->dataStr[$pos];

        if (self::isdigitat($this->dataStr, $pos)) {
            return QRConfig::QR_MODE_NUM;
        } elseif (self::isalnumat($this->dataStr, $pos)) {
            return QRConfig::QR_MODE_AN;
        } elseif ($this->modeHint === QRConfig::QR_MODE_KANJI) {
            if ($pos + 1 < strlen($this->dataStr)) {
                $d = $this->dataStr[$pos + 1];
                $word = (ord($c) << 8) | ord($d);

                if (($word >= 0x8140 && $word <= 0x9FFC) || ($word >= 0xE040 && $word <= 0xEBBF)) {
                    return QRConfig::QR_MODE_KANJI;
                }
            }
        }

        return QRConfig::QR_MODE_8;
    }

    public function eatNum()
    {
        $ln = QRSpec::lengthIndicator(QRConfig::QR_MODE_NUM, $this->input->getVersion());

        $p = 0;

        while (self::isdigitat($this->dataStr, $p)) {
            ++$p;
        }

        $run = $p;
        $mode = $this->identifyMode($p);

        if ($mode === QRConfig::QR_MODE_8) {
            $dif = QRInput::estimateBitsModeNum($run) + 4 + $ln
                + QRInput::estimateBitsMode8(1)         // + 4 + l8
                - QRInput::estimateBitsMode8($run + 1); // - 4 - l8

            if ($dif > 0) {
                return $this->eat8();
            }
        }

        if ($mode === QRConfig::QR_MODE_AN) {
            $dif = QRInput::estimateBitsModeNum($run) + 4 + $ln
                + QRInput::estimateBitsModeAn(1)        // + 4 + la
                - QRInput::estimateBitsModeAn($run + 1); // - 4 - la

            if ($dif > 0) {
                return $this->eatAn();
            }
        }

        $ret = $this->input->append(QRConfig::QR_MODE_NUM, $run, str_split($this->dataStr));

        if ($ret < 0) {
            return -1;
        }

        return $run;
    }

    public function eatAn()
    {
        $la = QRSpec::lengthIndicator(QRConfig::QR_MODE_AN, $this->input->getVersion());
        $ln = QRSpec::lengthIndicator(QRConfig::QR_MODE_NUM, $this->input->getVersion());

        $p = 0;

        while (self::isalnumat($this->dataStr, $p)) {
            if (self::isdigitat($this->dataStr, $p)) {
                $q = $p;

                while (self::isdigitat($this->dataStr, $q)) {
                    ++$q;
                }

                $dif = QRInput::estimateBitsModeAn($p) // + 4 + la
                    + QRInput::estimateBitsModeNum($q - $p) + 4 + $ln
                    - QRInput::estimateBitsModeAn($q); // - 4 - la

                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else {
                ++$p;
            }
        }

        $run = $p;

        if (!self::isalnumat($this->dataStr, $p)) {
            $dif = QRInput::estimateBitsModeAn($run) + 4 + $la
                + QRInput::estimateBitsMode8(1) // + 4 + l8
                - QRInput::estimateBitsMode8($run + 1); // - 4 - l8

            if ($dif > 0) {
                return $this->eat8();
            }
        }

        $ret = $this->input->append(QRConfig::QR_MODE_AN, $run, str_split($this->dataStr));

        if ($ret < 0) {
            return -1;
        }

        return $run;
    }

    public function eatKanji()
    {
        $p = 0;

        while ($this->identifyMode($p) === QRConfig::QR_MODE_KANJI) {
            $p += 2;
        }

        $ret = $this->input->append(QRConfig::QR_MODE_KANJI, $p, str_split($this->dataStr));

        if ($ret < 0) {
            return -1;
        }

        return $ret;
    }

    public function eat8()
    {
        $la = QRSpec::lengthIndicator(QRConfig::QR_MODE_AN, $this->input->getVersion());
        $ln = QRSpec::lengthIndicator(QRConfig::QR_MODE_NUM, $this->input->getVersion());

        $p = 1;
        $dataStrLen = strlen($this->dataStr);

        while ($p < $dataStrLen) {
            $mode = $this->identifyMode($p);

            if ($mode === QRConfig::QR_MODE_KANJI) {
                break;
            }

            if ($mode === QRConfig::QR_MODE_NUM) {
                $q = $p;

                while (self::isdigitat($this->dataStr, $q)) {
                    ++$q;
                }
                $dif = QRInput::estimateBitsMode8($p) // + 4 + l8
                    + QRInput::estimateBitsModeNum($q - $p) + 4 + $ln
                    - QRInput::estimateBitsMode8($q); // - 4 - l8

                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } elseif ($mode === QRConfig::QR_MODE_AN) {
                $q = $p;

                while (self::isalnumat($this->dataStr, $q)) {
                    ++$q;
                }
                $dif = QRInput::estimateBitsMode8($p)  // + 4 + l8
                    + QRInput::estimateBitsModeAn($q - $p) + 4 + $la
                    - QRInput::estimateBitsMode8($q); // - 4 - l8

                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else {
                ++$p;
            }
        }

        $run = $p;
        $ret = $this->input->append(QRConfig::QR_MODE_8, $run, str_split($this->dataStr));

        if ($ret < 0) {
            return -1;
        }

        return $run;
    }

    public function splitString()
    {
        while (strlen($this->dataStr) >= 0) {
            if ($this->dataStr === '') {
                return 0;
            }

            $mode = $this->identifyMode(0);

            $length = match ($mode) {
                QRConfig::QR_MODE_NUM => $this->eatNum(),
                QRConfig::QR_MODE_AN => $this->eatAn(),
                QRConfig::QR_MODE_KANJI => $this->eatKanji(),
                default => $this->eat8(),
            };

            if ($length === 0) {
                return 0;
            }

            if ($length < 0) {
                return -1;
            }

            $this->dataStr = substr($this->dataStr, $length);
        }

        return -1;
    }

    public function toUpper()
    {
        $stringLen = strlen($this->dataStr);
        $p = 0;

        while ($p < $stringLen) {
            $mode = self::identifyMode(substr($this->dataStr, $p));

            if ($mode === QRConfig::QR_MODE_KANJI) {
                $p += 2;
            } else {
                if (ord($this->dataStr[$p]) >= ord('a') && ord($this->dataStr[$p]) <= ord('z')) {
                    $this->dataStr[$p] = chr(ord($this->dataStr[$p]) - 32);
                }
                ++$p;
            }
        }

        return $this->dataStr;
    }

    public static function splitStringToQRInput($string, QRInput $input, $modeHint, $casesensitive = true)
    {
        if ($string === '\0' || $string === '') {
            throw new Exception('empty string!!!');
        }

        $split = new QRSplit($string, $input, $modeHint);

        if (!$casesensitive) {
            $split->toUpper();
        }

        return $split->splitString();
    }
}
