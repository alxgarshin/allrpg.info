<?php

declare(strict_types=1);

use Fraym\Helper\{CookieHelper, DataHelper};

$qhaId = (int) $_GET['qha_id'];

if ($qhaId > 0) {
    $qhaData = DB->findObjectById($qhaId, 'qrpg_hacking');

    if ($qhaData['id'] > 0) {
        $applicationId = (int) CookieHelper::getCookie('ingame_application_id');

        if ($applicationId > 0 && $applicationId === $qhaData['project_application_id']) {
            $applicationData = DB->findObjectById($applicationId, 'project_application');

            if ($applicationData['creator_id'] === CURRENT_USER->id() && $applicationData['deleted_by_player'] !== '1') {
                $sequences = DataHelper::jsonFixedDecode($qhaData['sequences']);

                putenv('GDFONTPATH=' . realpath('.'));
                $newwidth = 220;
                $newheight = 106;

                $image = imagecreatetruecolor($newwidth, $newheight);
                $white = imagecolorallocate($image, 255, 255, 255);
                $black = imagecolorallocate($image, 0, 0, 0);
                $transparent = imagecolorallocate($image, 76, 90, 98);
                imagecolortransparent($image, $transparent);
                imagefill($image, 0, 0, $transparent);

                foreach ($sequences as $sequenceKey => $sequence) {
                    foreach ($sequence as $sequencePieceKey => $sequencePiece) {
                        imagettftext(
                            $image,
                            24,
                            0,
                            20 + ($sequencePieceKey * 50),
                            24 + ($sequenceKey * 40),
                            $white,
                            'College',
                            $sequencePiece,
                        );
                        imageline($image, 0, 32 + ($sequenceKey * 40), $newwidth, 32 + ($sequenceKey * 40), $white);
                    }
                }

                // output and free memory
                header('Content-type: image/png');
                imagepng($image);
                imagedestroy($image);
            }
        }
    }
}
