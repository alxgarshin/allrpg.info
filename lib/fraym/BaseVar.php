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

/** Определяем внутренний путь сервера */
$_ENV['INNER_PATH'] = __DIR__ . '/../../';
define('INNER_PATH', $_ENV['INNER_PATH']);

require_once INNER_PATH . 'vendor/autoload.php';
