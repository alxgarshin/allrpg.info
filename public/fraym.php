<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

foreach ([__DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

use Fraym\Kernel;

Kernel::init();
