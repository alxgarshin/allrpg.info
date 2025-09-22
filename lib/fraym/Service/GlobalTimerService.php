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

final class GlobalTimerService
{
    private ?float $startTime = null;

    public function __construct()
    {
        $this->startTimer();
    }

    /** Запуск таймера */
    public function startTimer(): void
    {
        $this->startTime = microtime(true);
    }

    /** Получить текущую разницу с таймером */
    public function getTimerDiff(): string
    {
        return number_format(microtime(true) - $this->startTime, 10);
    }

    /** Вывести текстом данные по разнице с таймером */
    public function getTimerDiffStr(string $text = '<!-- execution time: %ss-->'): string
    {
        return sprintf($text, $this->getTimerDiff());
    }
}
