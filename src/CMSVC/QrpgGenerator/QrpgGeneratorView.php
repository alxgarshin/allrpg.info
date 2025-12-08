<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgGenerator;

use App\Helper\{FileHelper, RightsHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;
use PhpQrCode\{QRCode, QRConfig};

#[Controller(QrpgGeneratorController::class)]
class QrpgGeneratorView extends BaseView
{
    public function Response(): ?Response
    {
        $LOCALE = $this->LOCALE;

        $projectId = (int) ($_REQUEST['project_id'] ?? false);
        $qrpgCodeId = (int) ($_REQUEST['qrpg_code_id'] ?? false);

        $projectData = DB->findObjectById($projectId, 'project');
        $qrpgCodeData = DB->findObjectById($qrpgCodeId, 'qrpg_code');

        if ($projectId > 0 && $qrpgCodeId > 0 && $qrpgCodeData['project_id'] === $projectId) {
            /* есть два варианта, при которых пользователь имеет право смотреть код: если он автор / мастер проекта и если код вставлен в другой код, который пользователь открыл */
            if (OBJ_ID > 0 && (OBJ_TYPE === 'key' || OBJ_TYPE === 'code')) {
                $objData = DB->findObjectById(OBJ_ID, OBJ_TYPE === 'key' ? 'qrpg_key' : 'qrpg_code');

                if (
                    (
                        OBJ_TYPE === 'key'
                        && preg_match('#\[\#' . $qrpgCodeData['sid'] . ']#', DataHelper::escapeOutput($objData['property_description']))
                    ) || (
                        OBJ_TYPE === 'code'
                        && preg_match('#\[\#' . $qrpgCodeData['sid'] . ']#', DataHelper::escapeOutput($objData['description']))
                    )
                ) {
                    // всё в порядке, указанный код есть в описании
                } else {
                    $svg = '<svg width="465" height="50" viewBox="0 0 465 50" fill="black" xmlns="http://www.w3.org/2000/svg"><title>' . $LOCALE['access_error'] . '</title><text x="0" y="30" fill="red">' . $LOCALE['access_error'] . '</text></svg>';
                    header('Content-type: image/svg+xml');
                    echo $svg;
                    exit;
                }
            } else {
                RightsHelper::checkProjectKindAccessAndRedirect();
            }

            $colored = ($_REQUEST['color'] ?? false) === '1';

            $jsonText = DataHelper::jsonFixedEncode([
                'p' => $projectId,
                'c' => $qrpgCodeId,
                'h' => mb_substr(md5('qrpg' . $projectId . 'qrpg' . $qrpgCodeId . 'qrpg'), 0, 8),
            ]);

            $outerFrame = 4;
            $pixelPerPoint = 15;
            $pixelPerPointHalf = $pixelPerPoint / 2;
            $pixelPerPointDouble = $pixelPerPoint * 2;

            $frame = QRCode::raw($jsonText, QRConfig::QR_ECLEVEL_H);

            $h = count($frame);
            $w = $frame[0] ? mb_strlen($frame[0]) : 0;

            $imgW = $w + $outerFrame * 2;
            $imgH = $h + $outerFrame * 2;

            $imgWp = $imgW * $pixelPerPoint;
            $imgHp = $imgH * $pixelPerPoint;

            $logoInCenterSize = $w - floor($w / 3) * 2;
            $logoInCenterSizePX = $logoInCenterSize * $pixelPerPoint;
            $finderSize = 7;

            /* лого в центре */
            $hasProjectLogo = false;
            $logoImage = imagecreate((int) $logoInCenterSizePX, (int) $logoInCenterSizePX);
            $projectLogo = FileHelper::getImagePath($projectData['attachments'], FileHelper::getUploadNumByType('projects_and_communities_avatars'));

            $iconImage = null;

            if ($projectLogo) {
                $condition = getimagesize($projectLogo);

                if ($condition[2] === 3) {
                    $hasProjectLogo = true;
                    $iconImage = imagecreatefrompng("$projectLogo");

                    if (!$colored) {
                        imagefilter($iconImage, IMG_FILTER_GRAYSCALE);
                        imagefilter($iconImage, IMG_FILTER_CONTRAST, -1000);
                    }

                    if (($_REQUEST['transparent'] ?? false) === 'off') {
                        $col[2] = imagecolorallocate($iconImage, 255, 255, 255);
                        imagefill($iconImage, 0, 0, $col[2]);
                    }
                    $logoWidth = imagesx($iconImage);
                    $logoHeight = imagesy($iconImage);
                    $logoIsVertical = $logoHeight > $logoWidth;
                    imagecopyresampled(
                        $logoImage,
                        $iconImage,
                        (int) $pixelPerPointHalf,
                        (int) $pixelPerPointHalf,
                        $logoIsVertical ? 0 : (int) (($logoWidth - $logoHeight) / 2),
                        $logoIsVertical ? (int) (($logoHeight - $logoWidth) / 2) : 0,
                        (int) $logoInCenterSizePX - $pixelPerPoint,
                        (int) $logoInCenterSizePX - $pixelPerPoint,
                        $logoIsVertical ? $logoWidth : $logoHeight,
                        $logoIsVertical ? $logoWidth : $logoHeight,
                    );
                }
            } else {
                $allrpgLogo = INNER_PATH . $_ENV['DESIGN_PATH'] . 'qrcode_logo.png';
                $iconImage = imagecreatefrompng("$allrpgLogo");

                if (!$colored) {
                    imagefilter($iconImage, IMG_FILTER_GRAYSCALE);
                    imagefilter($iconImage, IMG_FILTER_CONTRAST, -1000);
                }

                if (($_REQUEST['transparent'] ?? false) === 'off') {
                    $col[2] = imagecolorallocate($iconImage, 255, 255, 255);
                    imagefill($iconImage, 0, 0, $col[2]);
                }
                $logoWidth = imagesx($iconImage);
                $logoHeight = imagesy($iconImage);
                imagecopyresampled(
                    $logoImage,
                    $iconImage,
                    (int) $pixelPerPointHalf,
                    (int) $pixelPerPointHalf,
                    ($logoWidth - $logoHeight) / 2,
                    0,
                    (int) $logoInCenterSizePX - $pixelPerPoint,
                    (int) $logoInCenterSizePX - $pixelPerPoint,
                    $logoWidth,
                    $logoHeight,
                );
            }

            if ($iconImage !== null) {
                imagedestroy($iconImage);
            }

            /* рассчитываем увеличение высоты свг-картинки в зависимости от количества данных под QR-кодом */
            $additionalElementsHeight = 0;
            $bottomHtml = '<g id="qrcode_bottom" fill="black" color="black" stroke="black" width="' . ($w * $pixelPerPoint) . '" transform="translate(' . ($outerFrame * $pixelPerPoint) . ', ' . (($outerFrame * 2 + $h) * $pixelPerPoint) . ')">';

            $fontSize = ($pixelPerPoint * 3);
            $blockCount = 0;

            /* рисуем id кода и имя проекта / надпись "allrpg.info" */
            $projectName = $hasProjectLogo ? 'allrpg.info' : mb_substr(DataHelper::escapeOutput($projectData['name']), 0, 25);
            $bottomHtml .= '<text x="0" y="' . ($blockCount * $fontSize) . '"><tspan font-size="' . ($fontSize - 2) . 'px" text-anchor="start" x="0">' . $qrpgCodeData['sid'] . '</tspan><tspan font-size="' . (mb_strlen(
                $projectName,
            ) <= 15 ? round($fontSize / 1.3) : round(
                $fontSize / 2,
            )) . 'px" text-anchor="end" x="' . ($w * $pixelPerPoint) . '">' . $projectName . '</tspan></text>';
            ++$blockCount;

            /* рисуем табличку используемых вариантов ключей */
            $icons = [];
            $setsAlreadyMet = [];
            $i = 0;
            $qrpgKeys = json_decode(DataHelper::escapeOutput($qrpgCodeData['qrpg_keys']), true);

            if ($qrpgKeys) {
                foreach ($qrpgKeys as $qrpgKeysSet) {
                    $foundIcon = false;

                    foreach ($qrpgKeysSet as $qrpgKey => $uselessValue) {
                        $qrpgKeyData = DB->findObjectById($qrpgKey, 'qrpg_key');

                        if ($qrpgKeyData['img'] !== '' && $qrpgKeyData['img'] > 0) {
                            $icons[$i][] = $qrpgKeyData['img'];
                            $foundIcon = true;
                        }
                    }

                    if ($foundIcon && !in_array($icons[$i], $setsAlreadyMet)) {
                        $setsAlreadyMet[] = $icons[$i];
                        ++$i;
                    } else {
                        unset($icons[$i]);
                    }
                }
            }

            $additionalElementsHeight += $fontSize * $blockCount;

            $blockCount = 0;
            $fontSize = ($pixelPerPoint * 4);
            $margin = $pixelPerPoint;

            foreach ($icons as $iconsSet) {
                $row = 0;
                $newIconSet = true;
                $bottomHtml .= '<g transform="translate(0, ' . ($additionalElementsHeight + $blockCount * $fontSize) . ')">';

                foreach ($iconsSet as $icon) {
                    $icon = ABSOLUTE_PATH . '/design/qrpg/' . $icon . '.svg';

                    if ($newIconSet) {
                        $newIconSet = false;
                        $bottomHtml .= '<line x1="0" y1="0" x2="' . ($w * $pixelPerPoint) . '" y2="0" />';
                    }

                    $svgData = file_get_contents($icon);
                    unset($match);
                    preg_match('#<g>(.*)</g>#msu', $svgData, $match);
                    $svgData = trim($match[1]);
                    $embedSvgSize = ($fontSize - $margin) / 56;
                    $bottomHtml .= '<g transform="scale(' . $embedSvgSize . ', ' . $embedSvgSize . ') translate(' . ($pixelPerPoint * 5 * $row) . ', ' . round(
                        $margin / 2,
                    ) . ')">' . $svgData . '</g>';
                    ++$row;
                }
                $bottomHtml .= '</g>';
                ++$blockCount;
            }
            $additionalElementsHeight += $fontSize * $blockCount;
            $additionalElementsHeight += $outerFrame * $pixelPerPoint;

            $bottomHtml .= '</g>';

            $svg = '<svg width="' . $imgWp . '" height="' . ($imgHp + $additionalElementsHeight) . '" viewBox="0 0 ' . $imgWp . ' ' . ($imgHp + $additionalElementsHeight) . '" fill="black" xmlns="http://www.w3.org/2000/svg"><title>' . DataHelper::escapeOutput(
                $qrpgCodeData['location'],
            ) . '</title>
<g id="qrcode_grid" transform="translate(' . ($outerFrame * $pixelPerPoint) . ', ' . ($outerFrame * $pixelPerPoint) . ')">
<defs>
<style>
.c {
    fill: white;
}
text {
    dominant-baseline: middle;
    font-family: "Roboto", Arial, Helvetica, sans-serif;
}
line {
    stroke: black;
}
</style>
<linearGradient gradientTransform="rotate(90)" id="qrcode_grad"><stop offset="0%" stop-color="black"/><stop offset="85%" stop-color="' . ($colored ? 'rgb(85,115,156)' : 'black') . '"/></linearGradient>
<mask id="qrcode_mask">';

            for ($y = 0; $y < $h; ++$y) {
                for ($x = 0; $x < $w; ++$x) {
                    if (ord($frame[$y][$x]) & 1) {
                        if (($x > $finderSize && $x < $w - $finderSize) || ($y > $finderSize && $y < $h - $finderSize) || ($y >= $h - $finderSize && $x >= $w - $finderSize)) { // вычищаем углы под квадратики finder'ов
                            if ($x < $w / 2 - $logoInCenterSize / 2 || $x >= $w / 2 + $logoInCenterSize / 2 || $y < $h / 2 - $logoInCenterSize / 2 || $y >= $h / 2 + $logoInCenterSize / 2) { // вычищаем место в центре под логотип
                                $svg .= '<circle cx="' . ($x * $pixelPerPoint + $pixelPerPoint / 2) . '" cy="' . ($y * $pixelPerPoint + $pixelPerPoint / 2) . '" r="' . $pixelPerPointHalf . '" class="c"/>';
                            }
                        }
                    }
                }
            }

            // рисуем три finder'а вручную
            $finderOuterSize = $pixelPerPoint * ($finderSize - 1);
            $finderInnerSize = $pixelPerPoint * (($finderSize - 1) / 2);

            $svg .= '<rect x="' . $pixelPerPointHalf . '" y="' . $pixelPerPointHalf . '" width="' . $finderOuterSize . '" height="' . $finderOuterSize . '" rx="' . $pixelPerPointDouble . '" ry="' . $pixelPerPointDouble . '" style="stroke-width:' . $pixelPerPoint . ';stroke:white;" />';
            $svg .= '<rect x="' . $pixelPerPointDouble . '" y="' . $pixelPerPointDouble . '" width="' . $finderInnerSize . '" height="' . $finderInnerSize . '" rx="' . $pixelPerPoint . '" ry="' . $pixelPerPoint . '" class="c" />';

            $svg .= '<rect x="' . (($w - $finderSize + 1) * $pixelPerPoint - $pixelPerPointHalf) . '" y="' . $pixelPerPointHalf . '" width="' . $finderOuterSize . '" height="' . $finderOuterSize . '" rx="' . $pixelPerPointDouble . '" ry="' . $pixelPerPointDouble . '" style="stroke-width:' . $pixelPerPoint . ';stroke:white;" />';
            $svg .= '<rect x="' . (($w - ($finderSize - 1) / 2) * $pixelPerPoint - $pixelPerPointDouble) . '" y="' . $pixelPerPointDouble . '" width="' . $finderInnerSize . '" height="' . $finderInnerSize . '" rx="' . $pixelPerPoint . '" ry="' . $pixelPerPoint . '" class="c" />';

            $svg .= '<rect x="' . $pixelPerPointHalf . '" y="' . (($h - $finderSize + 1) * $pixelPerPoint - $pixelPerPointHalf) . '" width="' . $finderOuterSize . '" height="' . $finderOuterSize . '" rx="' . $pixelPerPointDouble . '" ry="' . $pixelPerPointDouble . '" style="stroke-width:' . $pixelPerPoint . ';stroke:white;" />';
            $svg .= '<rect x="' . $pixelPerPointDouble . '" y="' . (($h - ($finderSize - 1) / 2) * $pixelPerPoint - $pixelPerPointDouble) . '" width="' . $finderInnerSize . '" height="' . $finderInnerSize . '" rx="' . $pixelPerPoint . '" ry="' . $pixelPerPoint . '" class="c" />';

            $svg .= '</mask>
</defs>
<rect x="0" y="0" width="' . $w * $pixelPerPoint . '" height="' . $h * $pixelPerPoint . '" fill="url(#qrcode_grad)" mask="url(#qrcode_mask)"/>';

            /* рисуем лого в центре */
            ob_start();
            imagepng($logoImage);
            $buffer = ob_get_clean();
            ob_end_clean();

            $svg .= '<image id="qrcode_logo" x="' . floor($w / 3) * $pixelPerPoint . '" y="' . floor(
                $w / 3,
            ) * $pixelPerPoint . '" width="' . $logoInCenterSizePX . '" height="' . $logoInCenterSizePX . '" href="data:image/png;base64,' . base64_encode(
                $buffer,
            ) . '"/>';
            imagedestroy($logoImage);

            $svg .= '
</g>';

            $svg .= $bottomHtml;

            $svg .= '<g id="qrcode_separators">';

            /* рисуем линии отреза в каждом из углов получившегося сертификата */
            $lineSize = ceil($imgWp / 20);
            $svg .= '<line x1="0" y1="0" x2="' . $lineSize . '" y2="0" />';
            $svg .= '<line x1="0" y1="0" x2="0" y2="' . $lineSize . '" />';
            $svg .= '<line x1="' . $imgWp . '" y1="0" x2="' . ($imgWp - $lineSize) . '" y2="0" />';
            $svg .= '<line x1="' . $imgWp . '" y1="0" x2="' . $imgWp . '" y2="' . $lineSize . '" />';
            $svg .= '<line x1="0" y1="' . ($imgHp + $additionalElementsHeight) . '" x2="' . $lineSize . '" y2="' . ($imgHp + $additionalElementsHeight) . '" />';
            $svg .= '<line x1="0" y1="' . ($imgHp + $additionalElementsHeight) . '" x2="0" y2="' . (($imgHp + $additionalElementsHeight) - $lineSize) . '" />';
            $svg .= '<line x1="' . $imgWp . '" y1="' . ($imgHp + $additionalElementsHeight) . '" x2="' . ($imgWp - $lineSize) . '" y2="' . ($imgHp + $additionalElementsHeight) . '" />';
            $svg .= '<line x1="' . $imgWp . '" y1="' . ($imgHp + $additionalElementsHeight) . '" x2="' . $imgWp . '" y2="' . (($imgHp + $additionalElementsHeight) - $lineSize) . '" />';

            $svg .= '</g>';

            $svg .= '
</svg>';

            header('Content-type: image/svg+xml');
            echo $svg;
        }

        exit;
    }
}
