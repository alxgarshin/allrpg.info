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

namespace Fraym\Service;

use Fraym\Helper\DataHelper;
use InvalidArgumentException;
use RuntimeException;

final class EnvService
{
    /** Местоположение файла .env */
    protected string $path;

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(sprintf('%s does not exist', $path));
        }
        $this->path = $path;
    }

    public function load(): void
    {
        if (!is_readable($this->path)) {
            throw new RuntimeException(sprintf('%s file is not readable', $this->path));
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ((str_starts_with($value, '{') || str_starts_with($value, '[')) && json_decode($value)) {
                $value = DataHelper::jsonFixedDecode($value, true);
            }

            if (in_array($value, ['true', 'false'])) {
                $value = $value === 'true';
            }

            if (preg_match('#(.+)\[(\d+)]#', $name, $match)) {
                $_ENV[$match[1]][$match[2]] = $value;
            } else {
                $_ENV[$name] = $value;
            }
        }
    }
}
