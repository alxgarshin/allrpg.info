<?php

declare(strict_types=1);

namespace App\CMSVC\Help;

use App\CMSVC\Message\MessageService;
use App\CMSVC\User\{UserModel, UserService};
use App\Helper\{TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseModel, BaseService, Controller};
use Fraym\Entity\PreCreate;
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper, EmailHelper, ResponseHelper};

/** @extends BaseService<HelpModel> */
#[PreCreate]
#[Controller(HelpController::class)]
class HelpService extends BaseService
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
                $subject = $LOCALE['support'] . ' ' . str_replace(['http://', 'https://', '/'], '', ABSOLUTE_PATH) . ': ' . $_REQUEST['maintext'][0];
                $message = DataHelper::escapeOutput($_REQUEST['details'][0]);

                if ($myname !== '' && $message !== '' && $_REQUEST['maintext'][0] !== '') {
                    if (TextHelper::checkForSpam($message) && ($_REQUEST['approvement'][0] ?? false) !== $_ENV['ANTIBOT_CODE']) {
                        ResponseHelper::responseOneBlock('error', $LOCALE['look_like_bot'], ['details[0]']);
                    } else {
                        if ($_REQUEST['link'][0] !== '') {
                            $message .= '

		' . $LOCALE['link_to_page'] . ': ' . $_REQUEST['link'][0];
                        }

                        if ($_REQUEST['technical'][0] !== '') {
                            $message .= '

		' . $LOCALE['technical_info'] . ': ' . DataHelper::escapeOutput($_REQUEST['technical'][0]);
                        }

                        /* создаем сообщение в службу поддержки */
                        $supportUsersIds = [];
                        $supportUsers = DB->select(
                            'user',
                            [['rights', '%-help-%', [OperandEnum::LIKE]]],
                        );

                        foreach ($supportUsers as $supportUserData) {
                            $supportUsersIds[$supportUserData['id']] = 'on';
                        }

                        if (CURRENT_USER->isLogged() && count($supportUsersIds) > 0) {
                            /** @var MessageService $messageService */
                            $messageService = CMSVCHelper::getService('message');

                            $messageService->newMessage(
                                null,
                                $_REQUEST['maintext'][0] . '
                            
        ' . $message,
                                '',
                                $supportUsersIds,
                            );

                            ResponseHelper::success($LOCALE['request_send_success']);
                            ResponseHelper::response([], ABSOLUTE_PATH . '/conversation/');
                        } elseif ($myemail === '') {
                            $fields[] = 'em[0]';
                            ResponseHelper::responseOneBlock('error', $LOCALE['please_fill_all_fields'], $fields);
                        } elseif (EmailHelper::sendMails($myname, $myemail, $contactemails, $subject, $message)) {
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

                    if ($message === '') {
                        $fields[] = 'details[0]';
                    }

                    if ($_REQUEST['maintext'][0] === '') {
                        $fields[] = 'maintext[0]';
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
        /** @var HelpModel $model */
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
