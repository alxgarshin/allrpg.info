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

/** Переменные по умолчанию для phpstan
 *
 * В phpstan.neon рекомендуется указать:
 * parameters:
 *	dynamicConstantNames:
 *		- KIND
 *		- CMSVC
 *	bootstrapFiles:
 *		- src/bootstrap.php
 */

define('INNER_PATH', __DIR__ . '/../../');
define('KIND', 'start');
define('CMSVC', '');
