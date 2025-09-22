<?php

declare(strict_types=1);

namespace App\CMSVC\Photo;

use App\CMSVC\User\{UserModel, UserService};
use App\Helper\{TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseModel, BaseService, Controller};
use Fraym\Entity\PreCreate;
use Fraym\Helper\{CMSVCHelper, DataHelper, EmailHelper, ResponseHelper};

#[Controller(PhotoController::class)]
#[PreCreate]
class PhotoService extends BaseService
{
    private ?UserModel $user = null;
    private ?UserService $userService = null;

    public function preCreate(): void
    {
        $LOCALE = $this->getLOCALE()['messages'];

        if ($_REQUEST['hash'][0] !== '' && $_REQUEST['regstamp'][0] !== '') {
            $checkHashData = DB->select('regstamp', ['hash' => $_REQUEST['hash'][0]], true);

            if ($checkHashData['id'] !== '' && trim($checkHashData['code']) !== '' && strtoupper($checkHashData['code']) === strtoupper($_REQUEST['regstamp'][0])) {
                $myname = trim($_REQUEST['name'][0]);
                $myemail = trim($_REQUEST['em'][0]);
                $contactemails = $_ENV['HELP_EMAILS'];
                $subject = $LOCALE['photo'] . ' ' . str_replace(['http://', 'https://', '/'], '', ABSOLUTE_PATH);
                $message = DataHelper::escapeOutput($_REQUEST['details'][0]);
                $linkField = DataHelper::escapeOutput($_REQUEST['link'][0]);
                $agreed = $_REQUEST['agreed'][0] === 'on';

                if ($myname !== '' && $myemail !== '' && $message !== '' && $linkField !== '' && $agreed) {
                    if (TextHelper::checkForSpam($message) && ($_REQUEST['approvement'][0] ?? false) !== $_ENV['ANTIBOT_CODE']) {
                        ResponseHelper::responseOneBlock('error', $LOCALE['look_like_bot'], ['details[0]']);
                    } else {
                        $message = $linkField . '
            
            ' . $message;

                        if (EmailHelper::sendMails($myname, $myemail, $contactemails, $subject, $message)) {
                            ResponseHelper::success($LOCALE['request_send_success']);
                            ResponseHelper::response([], ABSOLUTE_PATH . '/start/');
                        }
                    }
                } else {
                    $fields = [];

                    if ($myname === '') {
                        $fields[] = 'name[0]';
                    }

                    if ($myemail === '') {
                        $fields[] = 'em[0]';
                    }

                    if ($linkField === '') {
                        $fields[] = 'link[0]';
                    }

                    if ($message === '') {
                        $fields[] = 'details[0]';
                    }

                    if (!$agreed) {
                        $fields[] = 'agreed[0]';
                    }
                    ResponseHelper::responseOneBlock('error', $LOCALE['please_fill_all_fields'], $fields);
                }
            } else {
                ResponseHelper::responseOneBlock('error', $LOCALE['wrong_captcha'], ['regstamp[0]']);
            }
        } else {
            ResponseHelper::responseOneBlock('error', $LOCALE['wrong_captcha'], ['regstamp[0]']);
        }
    }

    public function getNameDefault(): string
    {
        $this->getUserData();

        return CURRENT_USER->isLogged() ? $this->userService->showNameWithId($this->user) : '';
    }

    public function getEmailDefault(): string
    {
        $this->getUserData();

        return $this->getUserData()?->em->get() ?? '';
    }

    public function getHash(): string
    {
        return UniversalHelper::getCaptcha()['hash'];
    }

    public function postModelInit(BaseModel $model): BaseModel
    {
        /** @var PhotoModel $model */
        if (CURRENT_USER->isLogged()) {
            $model->getElement('em')->getAttribute()->setObligatory(true);
        }

        return $model;
    }

    private function getUserData(): ?UserModel
    {
        if (CURRENT_USER->isLogged() && $this->user === null) {
            /** @var UserService */
            $userService = CMSVCHelper::getService('user');
            $this->userService = $userService;

            $this->user = $userService->get(CURRENT_USER->getId());
        }

        return $this->user;
    }
}
