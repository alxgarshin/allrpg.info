<?php

declare(strict_types=1);

namespace App\CMSVC\Login;

use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Helper\{AuthHelper, DataHelper, EmailHelper, ResponseHelper};

#[Controller(LoginController::class)]
class LoginService extends BaseService
{
    public function remindPassword(): void
    {
        $LOCALE = $this->getLOCALE();

        $em = $_REQUEST['em'];
        $userData = DB->select('user', ['em' => $em], true, ['id'], 1);

        if ($em !== '' && $userData) {
            $newPassword = '';
            $salt = 'abcdefghijklmnopqrstuvwxyz123456789';
            srand((int) ((float) microtime() * 1000000));
            $i = 0;

            while ($i <= 7) {
                $num = rand() % 35;
                $tmp = mb_substr($salt, $num, 1);
                $newPassword .= $tmp;
                ++$i;
            }
            DB->update('user', ['password_hashed' => AuthHelper::hashPassword($newPassword)], ['id' => $userData['id']]);

            $myname = str_replace(['http://', 'https://', 'www', '/'], '', ABSOLUTE_PATH);
            $contactemail = $em;

            $message = DataHelper::escapeOutput($userData['fio']) . sprintf($LOCALE['messages']['remind_message'], $myname, $newPassword);
            $subject = $LOCALE['messages']['remind_subject'] . ' ' . $myname;

            if (EmailHelper::sendMail($myname, '', $contactemail, $subject, $message)) {
                ResponseHelper::responseOneBlock('success', $LOCALE['messages']['new_pass_sent']);
            } else {
                ResponseHelper::responseOneBlock('error', $LOCALE['messages']['error_while_sending']);
            }
        } else {
            ResponseHelper::responseOneBlock('error', $LOCALE['messages']['no_email_found_in_db']);
        }
    }
}
