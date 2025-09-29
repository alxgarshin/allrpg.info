<?php

declare(strict_types=1);

namespace Identicon;

class Identicon
{
    public $identicon_options;
    public $blocks;
    public $shapes;
    public $rotatable;
    public $square;
    public $im;
    public $colors;
    public $size;
    public $blocksize;
    public $quarter;
    public $half;
    public $diagonal;
    public $halfdiag;
    public bool $transparent = false;
    public $centers;
    public $shapes_mat;
    public $symmetric_num;
    public $rot_mat;
    public $invert_mat;
    public $rotations;

    // constructor
    public function identicon($blocks = '', ?array $options = null): void
    {
        $this->identicon_options = $options ? $options : [];

        if ($blocks) {
            $this->blocks = $blocks;
        } else {
            $this->blocks = $this->identicon_options['squares'];
        }
        $this->blocksize = 80;
        $this->size = $this->blocks * $this->blocksize;
        $this->quarter = $this->blocksize / 4;
        $this->half = $this->blocksize / 2;
        $this->diagonal = sqrt($this->half * $this->half + $this->half * $this->half);
        $this->halfdiag = $this->diagonal / 2;
        $this->shapes = [
            [
                [
                    [90, $this->half],
                    [135, $this->diagonal],
                    [225, $this->diagonal],
                    [270, $this->half],
                ],
            ],
            // 0 rectangular half block
            [
                [
                    [45, $this->diagonal],
                    [135, $this->diagonal],
                    [225, $this->diagonal],
                    [315, $this->diagonal],
                ],
            ],
            // 1 full block
            [[[45, $this->diagonal], [135, $this->diagonal], [225, $this->diagonal]]],
            // 2 diagonal half block
            [[[90, $this->half], [225, $this->diagonal], [315, $this->diagonal]]],
            // 3 triangle
            [
                [
                    [0, $this->half],
                    [90, $this->half],
                    [180, $this->half],
                    [270, $this->half],
                ],
            ],
            // 4 diamond
            [
                [
                    [0, $this->half],
                    [135, $this->diagonal],
                    [270, $this->half],
                    [315, $this->diagonal],
                ],
            ],
            // 5 stretched diamond
            [
                [[0, $this->quarter], [90, $this->half], [180, $this->quarter]],
                [[0, $this->quarter], [315, $this->diagonal], [270, $this->half]],
                [[270, $this->half], [180, $this->quarter], [225, $this->diagonal]],
            ],
            // 6 triple triangle
            [[[0, $this->half], [135, $this->diagonal], [270, $this->half]]],
            // 7 pointer
            [
                [
                    [45, $this->halfdiag],
                    [135, $this->halfdiag],
                    [225, $this->halfdiag],
                    [315, $this->halfdiag],
                ],
            ],
            // 9 center square
            [
                [[180, $this->half], [225, $this->diagonal], [0, 0]],
                [[45, $this->diagonal], [90, $this->half], [0, 0]],
            ],
            // 9 double triangle diagonal
            [[[90, $this->half], [135, $this->diagonal], [180, $this->half], [0, 0]]],
            // 10 diagonal square
            [[[0, $this->half], [180, $this->half], [270, $this->half]]],
            // 11 quarter triangle out
            [[[315, $this->diagonal], [225, $this->diagonal], [0, 0]]],
            // 12quarter triangle in
            [[[90, $this->half], [180, $this->half], [0, 0]]],
            // 13 eighth triangle in
            [[[90, $this->half], [135, $this->diagonal], [180, $this->half]]],
            // 14 eighth triangle out
            [
                [[90, $this->half], [135, $this->diagonal], [180, $this->half], [0, 0]],
                [[0, $this->half], [315, $this->diagonal], [270, $this->half], [0, 0]],
            ],
            // 15 double corner square
            [
                [[315, $this->diagonal], [225, $this->diagonal], [0, 0]],
                [[45, $this->diagonal], [135, $this->diagonal], [0, 0]],
            ],
            // 16 double quarter triangle in
            [[[90, $this->half], [135, $this->diagonal], [225, $this->diagonal]]],
            // 17 tall quarter triangle
            [
                [[90, $this->half], [135, $this->diagonal], [225, $this->diagonal]],
                [[45, $this->diagonal], [90, $this->half], [270, $this->half]],
            ],
            // 18 double tall quarter triangle
            [
                [[90, $this->half], [135, $this->diagonal], [225, $this->diagonal]],
                [[45, $this->diagonal], [90, $this->half], [0, 0]],
            ],
            // 19 tall quarter + eighth triangles
            [[[135, $this->diagonal], [270, $this->half], [315, $this->diagonal]]],
            // 20 tipped over tall triangle
            [
                [[180, $this->half], [225, $this->diagonal], [0, 0]],
                [[45, $this->diagonal], [90, $this->half], [0, 0]],
                [[0, $this->half], [0, 0], [270, $this->half]],
            ],
            // 21 triple triangle diagonal
            [
                [[0, $this->quarter], [315, $this->diagonal], [270, $this->half]],
                [[270, $this->half], [180, $this->quarter], [225, $this->diagonal]],
            ],
            // 22 double triangle flat
            [
                [[0, $this->quarter], [45, $this->diagonal], [315, $this->diagonal]],
                [[180, $this->quarter], [135, $this->diagonal], [225, $this->diagonal]],
            ],
            // 23 opposite 8th triangles
            [
                [[0, $this->quarter], [45, $this->diagonal], [315, $this->diagonal]],
                [[180, $this->quarter], [135, $this->diagonal], [225, $this->diagonal]],
                [
                    [180, $this->quarter],
                    [90, $this->half],
                    [0, $this->quarter],
                    [270, $this->half],
                ],
            ],
            // 24 opposite 8th triangles + diamond
            [
                [
                    [0, $this->quarter],
                    [90, $this->quarter],
                    [180, $this->quarter],
                    [270, $this->quarter],
                ],
            ],
            // 25 small diamond
            [
                [[0, $this->quarter], [45, $this->diagonal], [315, $this->diagonal]],
                [[180, $this->quarter], [135, $this->diagonal], [225, $this->diagonal]],
                [[270, $this->quarter], [225, $this->diagonal], [315, $this->diagonal]],
                [[90, $this->quarter], [135, $this->diagonal], [45, $this->diagonal]],
            ],
            // 26 4 opposite 8th triangles
            [
                [[315, $this->diagonal], [225, $this->diagonal], [0, 0]],
                [[0, $this->half], [90, $this->half], [180, $this->half]],
            ],
            // 27 double quarter triangle parallel
            [
                [[135, $this->diagonal], [270, $this->half], [315, $this->diagonal]],
                [[225, $this->diagonal], [90, $this->half], [45, $this->diagonal]],
            ],
            // 28 double overlapping tipped over tall triangle
            [
                [[90, $this->half], [135, $this->diagonal], [225, $this->diagonal]],
                [[315, $this->diagonal], [45, $this->diagonal], [270, $this->half]],
            ],
            // 29 opposite double tall quarter triangle
            [
                [[0, $this->quarter], [45, $this->diagonal], [315, $this->diagonal]],
                [[180, $this->quarter], [135, $this->diagonal], [225, $this->diagonal]],
                [[270, $this->quarter], [225, $this->diagonal], [315, $this->diagonal]],
                [[90, $this->quarter], [135, $this->diagonal], [45, $this->diagonal]],
                [
                    [0, $this->quarter],
                    [90, $this->quarter],
                    [180, $this->quarter],
                    [270, $this->quarter],
                ],
            ],
            // 30 4 opposite 8th triangles+tiny diamond
            [
                [
                    [0, $this->half],
                    [90, $this->half],
                    [180, $this->half],
                    [270, $this->half],
                    [270, $this->quarter],
                    [180, $this->quarter],
                    [90, $this->quarter],
                    [0, $this->quarter],
                ],
            ],
            // 31 diamond C
            [
                [
                    [0, $this->quarter],
                    [90, $this->half],
                    [180, $this->quarter],
                    [270, $this->half],
                ],
            ],
            // 32 narrow diamond
            [
                [[180, $this->half], [225, $this->diagonal], [0, 0]],
                [[45, $this->diagonal], [90, $this->half], [0, 0]],
                [[0, $this->half], [0, 0], [270, $this->half]],
                [[90, $this->half], [135, $this->diagonal], [180, $this->half]],
            ],
            // 33 quadruple triangle diagonal
            [
                [
                    [0, $this->half],
                    [90, $this->half],
                    [180, $this->half],
                    [270, $this->half],
                    [0, $this->half],
                    [0, $this->quarter],
                    [270, $this->quarter],
                    [180, $this->quarter],
                    [90, $this->quarter],
                    [0, $this->quarter],
                ],
            ],
            // 34 diamond donut
            [
                [[90, $this->half], [45, $this->diagonal], [0, $this->quarter]],
                [[0, $this->half], [315, $this->diagonal], [270, $this->quarter]],
                [[270, $this->half], [225, $this->diagonal], [180, $this->quarter]],
            ],
            // 35 triple turning triangle
            [
                [[90, $this->half], [45, $this->diagonal], [0, $this->quarter]],
                [[0, $this->half], [315, $this->diagonal], [270, $this->quarter]],
            ],
            // 36 double turning triangle
            [
                [[90, $this->half], [45, $this->diagonal], [0, $this->quarter]],
                [[270, $this->half], [225, $this->diagonal], [180, $this->quarter]],
            ],
            // 37 diagonal opposite inward double triangle
            [[[90, $this->half], [225, $this->diagonal], [0, 0], [315, $this->diagonal]]],
            // 38 star fleet
            [
                [
                    [90, $this->half],
                    [225, $this->diagonal],
                    [0, 0],
                    [315, $this->halfdiag],
                    [225, $this->halfdiag],
                    [225, $this->diagonal],
                    [315, $this->diagonal],
                ],
            ],
            // 39 hollow half triangle
            [
                [[90, $this->half], [135, $this->diagonal], [180, $this->half]],
                [[270, $this->half], [315, $this->diagonal], [0, $this->half]],
            ],
            // 40 double eighth triangle out
            [
                [
                    [90, $this->half],
                    [135, $this->diagonal],
                    [180, $this->half],
                    [180, $this->quarter],
                ],
                [
                    [270, $this->half],
                    [315, $this->diagonal],
                    [0, $this->half],
                    [0, $this->quarter],
                ],
            ],
            // 42 double slanted square
            [
                [[0, $this->half], [45, $this->halfdiag], [0, 0], [315, $this->halfdiag]],
                [[180, $this->half], [135, $this->halfdiag], [0, 0], [225, $this->halfdiag]],
            ],
            // 43 double diamond
            [
                [[0, $this->half], [45, $this->diagonal], [0, 0], [315, $this->halfdiag]],
                [[180, $this->half], [135, $this->halfdiag], [0, 0], [225, $this->diagonal]],
            ],
            // 44 double pointer
        ];
        $this->rotatable = [1, 4, 8, 25, 26, 30, 34];
        $this->square = $this->shapes[1][0];
        $this->symmetric_num = ceil($this->blocks * $this->blocks / 4);

        for ($i = 0; $i < $this->blocks; ++$i) {
            for ($j = 0; $j < $this->blocks; ++$j) {
                $this->centers[$i][$j] = [
                    $this->half + $this->blocksize * $j,
                    $this->half + $this->blocksize * $i,
                ];
                $this->shapes_mat[$this->xy2symmetric($i, $j)] = 1;
                $this->rot_mat[$this->xy2symmetric($i, $j)] = 0;
                $this->invert_mat[$this->xy2symmetric($i, $j)] = 0;

                if (floor(($this->blocks - 1) / 2 - $i) >= 0 & floor(
                    ($this->blocks - 1) / 2 - $j,
                ) >= 0 & ($j >= $i | $this->blocks % 2 === 0)) {
                    $inversei = $this->blocks - 1 - $i;
                    $inversej = $this->blocks - 1 - $j;
                    $symmetrics = [
                        [$i, $j],
                        [$inversej, $i],
                        [$inversei, $inversej],
                        [$j, $inversei],
                    ];
                    $fill = [0, 270, 180, 90];

                    for ($k = 0; $k < count($symmetrics); ++$k) {
                        $this->rotations[$symmetrics[$k][0]][$symmetrics[$k][1]] = $fill[$k];
                    }
                }
            }
        }
    }

    public function xy2symmetric($x, $y)
    {
        $index = [floor(abs(($this->blocks - 1) / 2 - $x)), floor(abs(($this->blocks - 1) / 2 - $y))];
        sort($index);
        $index[1] *= ceil($this->blocks / 2);

        return array_sum($index);
    }

    // convert array(array(heading1,distance1),array(heading1,distance1)) to array(x1,y1,x2,y2)
    public function identicon_calc_x_y($array, $centers, $rotation = 0)
    {
        $output = [];
        $centerx = $centers[0];
        $centery = $centers[1];

        while ($thispoint = array_pop($array)) {
            $y = round($centery + sin(deg2rad($thispoint[0] + $rotation)) * $thispoint[1]);
            $x = round($centerx + cos(deg2rad($thispoint[0] + $rotation)) * $thispoint[1]);
            array_push($output, $x, $y);
        }

        return $output;
    }

    // draw filled polygon based on an array of (x1,y1,x2,y2,..)
    public function identicon_draw_shape($x, $y): void
    {
        $index = $this->xy2symmetric($x, $y);
        $shape = $this->shapes[$this->shapes_mat[$index]];
        $invert = $this->invert_mat[$index];
        $rotation = $this->rot_mat[$index];
        $centers = $this->centers[$x][$y];
        $invert2 = abs($invert - 1);
        $points = $this->identicon_calc_x_y($this->square, $centers);
        imagefilledpolygon($this->im, $points, $this->colors[$invert2]);

        foreach ($shape as $subshape) {
            $points = $this->identicon_calc_x_y($subshape, $centers, $rotation + $this->rotations[$x][$y]);
            $num = count($points) / 2;
            imagefilledpolygon($this->im, $points, $this->colors[$invert]);
        }
    }

    // use a seed value to determine shape, rotation, and color
    public function identicon_set_randomness($seed = '')
    {
        // set seed
        $twister = new IdenticonMersenneTwister(hexdec($seed));

        foreach ($this->rot_mat as $key => $value) {
            $this->rot_mat[$key] = $twister->rand(0, 3) * 90;
            $this->invert_mat[$key] = $twister->rand(0, 1);

            if ($key === 0) {
                $this->shapes_mat[$key] = $this->rotatable[(int) $twister->array_rand($this->rotatable)];
            } else {
                $this->shapes_mat[$key] = $twister->array_rand($this->shapes);
            }
        }
        $forecolors = [
            (int) $twister->rand($this->identicon_options['forer'][0], $this->identicon_options['forer'][1]),
            (int) $twister->rand($this->identicon_options['foreg'][0], $this->identicon_options['foreg'][1]),
            (int) $twister->rand($this->identicon_options['foreb'][0], $this->identicon_options['foreb'][1]),
        ];
        $this->colors[1] = imagecolorallocate($this->im, $forecolors[0], $forecolors[1], $forecolors[2]);
        $backcolors = [
            (int) $twister->rand($this->identicon_options['backr'][0], $this->identicon_options['backr'][1]),
            (int) $twister->rand($this->identicon_options['backg'][0], $this->identicon_options['backg'][1]),
            (int) $twister->rand($this->identicon_options['backb'][0], $this->identicon_options['backb'][1]),
        ];

        if (array_sum($this->identicon_options['backr']) + array_sum($this->identicon_options['backg']) + array_sum(
            $this->identicon_options['backb'],
        ) === 0) {
            $this->colors[0] = imagecolorallocatealpha($this->im, 0, 0, 0, 127);
            $this->transparent = true;
            imagealphablending($this->im, false);
            imagesavealpha($this->im, true);
        } else {
            $this->colors[0] = imagecolorallocate($this->im, $backcolors[0], $backcolors[1], $backcolors[2]);
        }

        if ($this->identicon_options['grey']) {
            $this->colors[1] = imagecolorallocate($this->im, $forecolors[0], $forecolors[0], $forecolors[0]);

            if (!$this->transparent) {
                $this->colors[0] = imagecolorallocate($this->im, $backcolors[0], $backcolors[0], $backcolors[0]);
            }
        }

        return true;
    }

    public function identicon_build($seed = '', $outsize = '', $random = true)
    {
        // make an identicon and return the filepath or if write=false return picture directly
        if (function_exists('gd_info')) {
            // init random seed
            if ($random) {
                $id = substr(sha1($seed), 0, 10);
            } else {
                $id = $seed;
            }

            if ($outsize === '') {
                $outsize = $this->identicon_options['size'];
            }
            $this->im = imagecreatetruecolor($this->size, $this->size);
            $this->colors = [imagecolorallocate($this->im, 255, 255, 255)];

            if ($random) {
                $this->identicon_set_randomness($id);
            } else {
                $this->colors = [
                    imagecolorallocate($this->im, 255, 255, 255),
                    imagecolorallocate($this->im, 0, 0, 0),
                ];
                $this->transparent = false;
            }
            imagefill($this->im, 0, 0, $this->colors[0]);

            for ($i = 0; $i < $this->blocks; ++$i) {
                for ($j = 0; $j < $this->blocks; ++$j) {
                    $this->identicon_draw_shape($i, $j);
                }
            }

            $out = @imagecreatetruecolor((int) $outsize, (int) $outsize);
            imagesavealpha($out, true);
            imagealphablending($out, false);
            imagecopyresampled($out, $this->im, 0, 0, 0, 0, (int) $outsize, (int) $outsize, $this->size, $this->size);
            imagedestroy($this->im);

            return $out;
        } else { // php GD image manipulation is required
            return false; // php GD image isn't installed but don't want to mess up blog layout
        }
    }

    public function identicon_display_parts()
    {
        $this->identicon(1);
        $output = '';

        for ($i = 0; $i < count($this->shapes); ++$i) {
            $this->shapes_mat = [$i];
            $this->invert_mat = [1];
            $output .= $this->identicon_build('example' . $i, 30, false);
            // $counter++;
        }
        $this->identicon();

        return $output;
    }
}
