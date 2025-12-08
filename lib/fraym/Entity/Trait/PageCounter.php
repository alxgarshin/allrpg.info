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

namespace Fraym\Entity\Trait;

use Fraym\Helper\LocaleHelper;

/** Пагинатор */
trait PageCounter
{
    public function drawPageCounter(
        string $objectName,
        ?int $page = 0,
        int $totalObjectsCount = 0,
        int $objectsPerPage = 50,
        string $moreParams = '',
    ): string {
        $LOCALE = LocaleHelper::getLocale(['fraym']);

        $maxPage = ceil($totalObjectsCount / $objectsPerPage);
        $minPage = $page - 1;

        if ($minPage < 1) {
            $minPage = 1;
        }

        if ($maxPage > $minPage + 4) {
            $maxPage = $minPage + 4;
        } else {
            $minPage = $maxPage - 4;
        }

        if ($minPage < 1) {
            $minPage = 1;
        }

        $result = '<div class="pagecounter">';

        $result .= '<div class="pagecounter_buttons">';

        $result .= '<div class="pagecounter_prevs">';
        $result .= '<a class="first';

        if ($page !== 0 && $maxPage - 1 > 0) {
            $result .= '" href="/' . KIND . '/' . ($objectName !== '' ? $objectName . '/' : '') . 'sorting=' . SORTING . $moreParams . '" class="sm"';
        } else {
            $result .= ' disabled"';
        }
        $result .= '></a>';
        $result .= '<a class="prev';

        if ($maxPage - 1 > 0 && $page > 0) {
            $result .= '" href="/' . KIND . '/' . ($objectName !== '' ? $objectName . '/' : '') . 'page=' . ($page - 1) . '&sorting=' . SORTING . $moreParams . '"';
        } else {
            $result .= ' disabled"';
        }
        $result .= '></a>';
        $result .= '</div>';

        $result .= '<div class="pagecounter_nums">';

        if ($totalObjectsCount === 0) {
            $totalObjectsCount = 1;
        }

        for ($i = $minPage; $i <= $maxPage; $i++) {
            if ($i - 1 !== $page) {
                $result .= '<a href="/' . KIND . '/' . ($objectName !== '' ? $objectName . '/' : '') . 'page=' . ($i - 1) . '&sorting=' . SORTING . $moreParams . '">' . $i . '</a>';
            } else {
                $result .= '<a class="selected">' . $i . '</a>';
            }
        }
        $result .= '</div>';

        $result .= '<div class="pagecounter_nexts">';
        $result .= '<a class="next';

        if ($page < ($totalObjectsCount / $objectsPerPage) - 1) {
            $result .= '" href="/' . KIND . '/' . ($objectName !== '' ? $objectName . '/' : '') . 'page=' . ($page + 1) . '&sorting=' . SORTING . $moreParams . '"';
        } else {
            $result .= ' disabled"';
        }
        $result .= '></a>';
        $result .= '<a class="last';

        if ($page !== (int) ceil($totalObjectsCount / $objectsPerPage) - 1 && $totalObjectsCount > 0) {
            $result .= '" href="/' . KIND . '/' . ($objectName !== '' ? $objectName . '/' : '') .
                'page=' . (ceil($totalObjectsCount / $objectsPerPage) - 1) .
                '&sorting=' . SORTING . $moreParams . '"';
        } else {
            $result .= ' disabled"';
        }
        $result .= '></a>';
        $result .= '</div>';

        $result .= '</div>';

        $result .= '<div class="pagecounter_text">' . sprintf(
            $LOCALE['page_switcher']['counter'],
            (($page * $objectsPerPage) + 1) . '-' . (min(($page + 1) * $objectsPerPage, $totalObjectsCount)),
            $totalObjectsCount,
        ) . '</div>';

        $result .= '</div>';

        return $result;
    }
}
