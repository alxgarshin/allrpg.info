<?php

declare(strict_types=1);

namespace PhpQrCode;

class QRRsBlock
{
    public int $dataLength;
    public array $data = [];
    public int $eccLength;
    public array $ecc = [];

    public function __construct($dl, $data, $el, &$ecc, QRRsItem $rs)
    {
        $rs->encode_rs_char($data, $ecc);

        $this->dataLength = $dl;
        $this->data = $data;
        $this->eccLength = $el;
        $this->ecc = $ecc;
    }
}
