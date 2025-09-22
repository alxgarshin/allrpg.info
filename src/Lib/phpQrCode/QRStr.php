<?php

declare(strict_types=1);

namespace PhpQrCode;

class QRStr
{
    public static function set(&$srctab, $x, $y, $repl, $replLen = false): void
    {
        $srctab[$y] = substr_replace(
            $srctab[$y],
            ($replLen !== false) ? substr($repl, 0, (int) $replLen) : $repl,
            $x,
            ($replLen !== false) ? $replLen : strlen($repl),
        );
    }
}
