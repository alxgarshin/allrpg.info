<?php

declare(strict_types=1);

namespace App\CMSVC\Register;

use App\CMSVC\User\UserService;
use App\Helper\{DateHelper, UniversalHelper};
use Fraym\BaseObject\{BaseModel, BaseService, Controller, DependencyInjection};
use Fraym\Entity\{PostCreate, PreCreate};
use Fraym\Helper\{AuthHelper, DataHelper, ResponseHelper};

#[PreCreate]
#[PostCreate]
#[Controller(RegisterController::class)]
class RegisterService extends BaseService
{
    #[DependencyInjection]
    public UserService $userService;

    public function getHash(): string
    {
        return UniversalHelper::getCaptcha()['hash'];
    }

    public function preCreate(): void
    {
        $LOCALE = $this->getLOCALE()['messages'];

        $hash = $_REQUEST['hash'][0] ?? null;
        $regstamp = $_REQUEST['regstamp'][0] ?? null;

        if ($hash && $regstamp) {
            $checkHashData = DB->select(
                tableName: 'regstamp',
                criteria: [
                    'hash' => $hash,
                ],
                oneResult: true,
            );

            if ($checkHashData && mb_strtoupper($checkHashData["code"]) === mb_strtoupper($regstamp)) {
                $email = $_REQUEST['em'][0] ?? null;

                if ($email) {
                    $checkDoubleUserData = DB->select(
                        tableName: 'user',
                        criteria: [
                            'em' => $email,
                        ],
                        oneResult: true,
                    );

                    if (!$checkDoubleUserData) {
                        if ($_REQUEST['fio'][0] === '12' && ($_REQUEST['approvement'][0] ?? false) !== $_ENV['ANTIBOT_CODE']) {
                            ResponseHelper::responseOneBlock('error', $LOCALE['look_like_bot'], ['fio[0]']);
                        }
                    } else {
                        ResponseHelper::responseOneBlock('error', $LOCALE['email_already_registered'], ['em[0]']);
                    }
                }
            } else {
                ResponseHelper::responseOneBlock('error', $LOCALE['wrong_captcha'], ['regstamp[0]']);
            }
        } else {
            ResponseHelper::responseOneBlock('error', $LOCALE['wrong_captcha'], ['regstamp[0]']);
        }
    }

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            DB->query(
                query: "UPDATE user SET login=em WHERE id=:id",
                data: [
                    ['id', $successfulResultsId],
                ],
            );

            DB->update(
                tableName: 'user',
                data: [
                    'subs_type' => 1,
                    'subs_objects' => DataHelper::arrayToMultiselect($this->userService->getSubsObjectsList()),
                    'bazecount' => 50,
                    'hidesome' => '-2-',
                    'created_at' => DateHelper::getNow(),
                ],
                criteria: [
                    'id' => $successfulResultsId,
                ],
            );

            $this->userService->postRegister((int) $successfulResultsId);

            $userData = DB->select(
                tableName: 'user',
                criteria: [
                    'id' => $successfulResultsId,
                ],
                oneResult: true,
            );

            CURRENT_USER->authSetUserData($userData);
            AuthHelper::generateAndSaveRefreshToken();

            $redirectPath = ResponseHelper::createRedirect();
            ResponseHelper::response([], $redirectPath ?? '/start/');
        }
    }

    public function checkRightsRestrict(): string
    {
        return 'id=' . CURRENT_USER->id();
    }

    public function postModelInit(BaseModel $model): BaseModel
    {
        $model
            ->changeElementsOrder('em', 'photo')
            ->changeElementsOrder('password_hashed', 'photo')
            ->changeElementsOrder('pass2', 'photo')
            ->changeElementsOrder('fio', 'photo')
            ->changeElementsOrder('phone', 'photo')
            ->changeElementsOrder('birth', 'photo')
            ->changeElementsOrder('city', 'photo')
            ->changeElementsOrder('gender', 'photo');

        $model->getElement('password_hashed')->getAttribute()->setObligatory(true);
        $model->getElement('pass2')->getAttribute()->setObligatory(true);
        $model->getElement('birth')->getAttribute()->setObligatory(false);
        $model->getElement('city')->getAttribute()->setObligatory(false);

        return $model;
    }
}
