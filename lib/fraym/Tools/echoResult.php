<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

function echoResult(string $message, string $color = 'green'): void
{
    $colors = [
        'green' => '42',
        'red' => '41',
    ];
    $color = $colors[$color] ?? $colors['green'];

    echo "\033[" . $color . "m" . $message . "\033[0m" . PHP_EOL;
}
