<?php

declare(strict_types=1);

namespace PhpQrCode;

use Exception;

class QRRawCode
{
    public int $version;
    public array $datacode = [];
    public array $ecccode = [];
    public int $blocks;
    /** @var QRRsBlock[] */
    public array $rsblocks = [];
    public int $count;
    public int $dataLength;
    public int $eccLength;
    public int $b1;

    public function __construct(QRInput $input)
    {
        $spec = [0, 0, 0, 0, 0];

        $this->datacode = $input->getByteStream();

        QRSpec::getEccSpec($input->getVersion(), $input->getErrorCorrectionLevel(), $spec);

        $this->version = $input->getVersion();
        $this->b1 = QRSpec::rsBlockNum1($spec);
        $this->dataLength = QRSpec::rsDataLength($spec);
        $this->eccLength = QRSpec::rsEccLength($spec);
        $this->ecccode = array_fill(0, $this->eccLength, 0);
        $this->blocks = QRSpec::rsBlockNum($spec);

        $ret = $this->init($spec);

        if ($ret < 0) {
            throw new Exception('block alloc error');
        }

        $this->count = 0;
    }

    public function init(array $spec)
    {
        $dl = QRSpec::rsDataCodes1($spec);
        $el = QRSpec::rsEccCodes1($spec);
        $rs = QRRs::init_rs(8, 0x11D, 0, 1, $el, 255 - $dl - $el);

        $blockNo = 0;
        $dataPos = 0;
        $eccPos = 0;

        for ($i = 0; $i < QRSpec::rsBlockNum1($spec); ++$i) {
            $ecc = array_slice($this->ecccode, $eccPos);
            $this->rsblocks[$blockNo] = new QRRsBlock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode, 0, $eccPos), $ecc);

            $dataPos += $dl;
            $eccPos += $el;
            ++$blockNo;
        }

        if (QRSpec::rsBlockNum2($spec) === 0) {
            return 0;
        }

        $dl = QRSpec::rsDataCodes2($spec);
        $el = QRSpec::rsEccCodes2($spec);
        $rs = QRRs::init_rs(8, 0x11D, 0, 1, $el, 255 - $dl - $el);

        if ($rs === null) {
            return -1;
        }

        for ($i = 0; $i < QRSpec::rsBlockNum2($spec); ++$i) {
            $ecc = array_slice($this->ecccode, $eccPos);
            $this->rsblocks[$blockNo] = new QRRsblock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode, 0, $eccPos), $ecc);

            $dataPos += $dl;
            $eccPos += $el;
            ++$blockNo;
        }

        return 0;
    }

    public function getCode()
    {
        if ($this->count < $this->dataLength) {
            $row = $this->count % $this->blocks;
            $col = $this->count / $this->blocks;

            if ($col >= $this->rsblocks[0]->dataLength) {
                $row += $this->b1;
            }
            $ret = $this->rsblocks[$row]->data[(int) $col];
        } elseif ($this->count < $this->dataLength + $this->eccLength) {
            $row = ($this->count - $this->dataLength) % $this->blocks;
            $col = ($this->count - $this->dataLength) / $this->blocks;
            $ret = $this->rsblocks[$row]->ecc[(int) $col];
        } else {
            return 0;
        }
        ++$this->count;

        return $ret;
    }
}
