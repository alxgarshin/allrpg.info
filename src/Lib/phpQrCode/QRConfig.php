<?php

declare(strict_types=1);

namespace PhpQrCode;

class QRConfig
{
    public const QR_MODE_NUL = -1;
    public const QR_MODE_NUM = 0;
    public const QR_MODE_AN = 1;
    public const QR_MODE_8 = 2;
    public const QR_MODE_KANJI = 3;
    public const QR_MODE_STRUCTURE = 4;

    public const QR_ECLEVEL_L = 0;
    public const QR_ECLEVEL_M = 1;
    public const QR_ECLEVEL_Q = 2;
    public const QR_ECLEVEL_H = 3;

    public const QR_CACHEABLE = false;
    public const QR_CACHE_DIR = false;
    public const QR_LOG_DIR = false;
    public const QR_FIND_BEST_MASK = true;
    public const QR_FIND_FROM_RANDOM = 2;
    public const QR_DEFAULT_MASK = 2;
    public const QR_PNG_MAXIMUM_SIZE = 1024;

    public const QRSpec_VERSION_MAX = 40;
    public const QRSpec_WIDTH_MAX = 177;

    public const QRCAP_WIDTH = 0;
    public const QRCAP_WORDS = 1;
    public const QRCAP_REMINDER = 2;
    public const QRCAP_EC = 3;
    public const STRUCTURE_HEADER_BITS = 20;

    public const N1 = 3;
    public const N2 = 3;
    public const N3 = 40;
    public const N4 = 10;
}
