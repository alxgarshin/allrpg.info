<?php

declare(strict_types=1);

namespace Identicon;

class IdenticonMersenneTwister
{
    public $bits32;
    public $N;
    public $M;
    public $MATRIX_A;
    public $UPPER_MASK;
    public $LOWER_MASK;
    public $MASK10;
    public $MASK11;
    public $MASK12;
    public $MASK14;
    public $MASK20;
    public $MASK21;
    public $MASK22;
    public $MASK26;
    public $MASK27;
    public $MASK31;
    public $TWO_TO_THE_16;
    public $TWO_TO_THE_31;
    public $TWO_TO_THE_32;
    public $MASK32;
    public $mt;
    public $mti;

    public function __construct($seed = 123456)
    {
        $this->bits32 = PHP_INT_MAX === 2147483647;
        $this->define_constants();
        $this->init_with_integer($seed);
    }

    public function define_constants(): void
    {
        $this->N = 624;
        $this->M = 397;
        $this->MATRIX_A = 0x9908B0DF;
        $this->UPPER_MASK = 0x80000000;
        $this->LOWER_MASK = 0x7FFFFFFF;

        $this->MASK10 = ~((~0) << 10);
        $this->MASK11 = ~((~0) << 11);
        $this->MASK12 = ~((~0) << 12);
        $this->MASK14 = ~((~0) << 14);
        $this->MASK20 = ~((~0) << 20);
        $this->MASK21 = ~((~0) << 21);
        $this->MASK22 = ~((~0) << 22);
        $this->MASK26 = ~((~0) << 26);
        $this->MASK27 = ~((~0) << 27);
        $this->MASK31 = ~((~0) << 31);

        $this->TWO_TO_THE_16 = pow(2, 16);
        $this->TWO_TO_THE_31 = pow(2, 31);
        $this->TWO_TO_THE_32 = pow(2, 32);

        $this->MASK32 = $this->MASK31 | ($this->MASK31 << 1);
    }

    public function init_with_integer($integer_seed): void
    {
        $integer_seed = $this->force_32_bit_int($integer_seed);

        $mt = &$this->mt;
        $mti = &$this->mti;

        $mt = array_fill(0, $this->N, 0);

        $mt[0] = $integer_seed;

        for ($mti = 1; $mti < $this->N; ++$mti) {
            $mt[$mti] = $this->add_2(
                $this->mul(
                    1812433253,
                    $mt[$mti - 1] ^ (($mt[$mti - 1] >> 30) & 3),
                ),
                $mti,
            );
        }
    }

    /* generates a random number on [0,1)-real-interval */
    public function real_halfopen()
    {
        return $this->signed2unsigned($this->int32()) * (1.0 / 4294967296.0);
    }

    public function int32()
    {
        $mag01 = [0, $this->MATRIX_A];

        $mt = &$this->mt;
        $mti = &$this->mti;

        if ($mti >= $this->N) { /* generate N words all at once */
            for ($kk = 0; $kk < $this->N - $this->M; ++$kk) {
                $y = ($mt[$kk] & $this->UPPER_MASK) | ($mt[$kk + 1] & $this->LOWER_MASK);
                $mt[$kk] = $mt[$kk + $this->M] ^ (($y >> 1) & $this->MASK31) ^ $mag01[$y & 1];
            }

            for (; $kk < $this->N - 1; ++$kk) {
                $y = ($mt[$kk] & $this->UPPER_MASK) | ($mt[$kk + 1] & $this->LOWER_MASK);
                $mt[$kk] =
                    $mt[$kk + ($this->M - $this->N)] ^ (($y >> 1) & $this->MASK31) ^ $mag01[$y & 1];
            }
            $y = ($mt[$this->N - 1] & $this->UPPER_MASK) | ($mt[0] & $this->LOWER_MASK);
            $mt[$this->N - 1] = $mt[$this->M - 1] ^ (($y >> 1) & $this->MASK31) ^ $mag01[$y & 1];

            $mti = 0;
        }

        $y = $mt[$mti++];

        /* Tempering */
        $y ^= ($y >> 11) & $this->MASK21;
        $y ^= ($y << 7) & ((0x9D2C << 16) | 0x5680);
        $y ^= ($y << 15) & (0xEFC6 << 16);
        $y ^= ($y >> 18) & $this->MASK14;

        return $y;
    }

    public function signed2unsigned($signed_integer)
    {
        // # assert(is_integer($signed_integer));
        // # assert(($signed_integer & ~$this->MASK32) === 0);

        return $signed_integer >= 0 ? $signed_integer :
            $this->TWO_TO_THE_32 + $signed_integer;
    }

    public function unsigned2signed($unsigned_integer)
    {
        // # assert($unsigned_integer >= 0);
        // # assert($unsigned_integer < pow(2, 32));
        // # assert(floor($unsigned_integer) === floatval($unsigned_integer));

        return intval(
            $unsigned_integer < $this->TWO_TO_THE_31 ? $unsigned_integer :
                $unsigned_integer - $this->TWO_TO_THE_32,
        );
    }

    public function force_32_bit_int($x)
    {
        /*
             it would be un-PHP-like to require is_integer($x),
             so we have to handle cases like this:

                $x === pow(2, 31)
                $x === strval(pow(2, 31))

             we are also opting to do something sensible (rather than dying)
             if the seed is outside the range of a 32-bit unsigned integer.
            */

        if (is_integer($x)) {
            /*
                we mask in case we are on a 64-bit machine and at least one
                bit is set between position 32 and position 63.
              */
            return $x & $this->MASK32;
        } else {
            $x = floatval($x);

            $x = $x < 0 ? ceil($x) : floor($x);

            $x = fmod($x, $this->TWO_TO_THE_32);

            if ($x < 0) {
                $x += $this->TWO_TO_THE_32;
            }

            return $this->unsigned2signed($x);
        }
    }

    /*
        takes 2 integers, treats them as unsigned 32-bit integers,
        and adds them.

        it works by splitting each integer into
        2 "half-integers", then adding the high and low half-integers
        separately.

        a slight complication is that the sum of the low half-integers
        may not fit into 16 bits; any "overspill" is added to the sum
        of the high half-integers.
     */
    public function add_2($n1, $n2)
    {
        $x = ($n1 & 0xFFFF) + ($n2 & 0xFFFF);

        return (((($n1 >> 16) & 0xFFFF) +
                (($n2 >> 16) & 0xFFFF) +
                ($x >> 16)) << 16) | ($x & 0xFFFF);
    }

    public function mul($a, $b)
    {
        /*
             a and b, considered as unsigned integers, can be expressed as follows:

                a = 2**16 * a1 + a2,

                b = 2**16 * b1 + b2,

                where

            0 <= a2 < 2**16,
            0 <= b2 < 2**16.

             given those 2 equations, what this function essentially does is to
             use the following identity:

                a * b = 2**32 * a1 * b1 + 2**16 * a1 * b2 + 2**16 * b1 * a2 + a2 * b2

             note that the first term, i.e. 2**32 * a1 * b1, is unnecessary here,
             so we don't compute it.

             we could make the following code clearer by using intermediate
             variables, but that would probably hurt performance.
            */

        return
            $this->unsigned2signed(
                fmod(
                    $this->TWO_TO_THE_16 *
                        /*
                             the next line of code calculates a1 * b2,
                             the line after that calculates b1 * a2,
                             and the line after that calculates a2 * b2.
                            */
                        ((($a >> 16) & 0xFFFF) * ($b & 0xFFFF) +
                            (($b >> 16) & 0xFFFF) * ($a & 0xFFFF)) +
                        ($a & 0xFFFF) * ($b & 0xFFFF),
                    $this->TWO_TO_THE_32,
                ),
            );
    }

    public function rand($low, $high)
    {
        $pick = floor($low + ($high - $low + 1) * $this->real_halfopen());

        return $pick;
    }

    public function array_rand($array)
    {
        return $this->rand(0, count($array) - 1);
    }
}
