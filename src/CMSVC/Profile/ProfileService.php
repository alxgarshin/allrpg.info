<?php

declare(strict_types=1);

namespace App\CMSVC\Profile;

use App\CMSVC\Trait\UserServiceTrait;
use App\CMSVC\User\UserModel;
use Fraym\BaseObject\{BaseModel, BaseService, Controller};
use Fraym\Entity\{PostChange, PostDelete, PreChange};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{CookieHelper, EmailHelper, LocaleHelper, ResponseHelper};

#[PreChange]
#[PostChange]
#[PostDelete]
#[Controller(ProfileController::class)]
class ProfileService extends BaseService
{
    use UserServiceTrait;

    public function preChange(): void
    {
        $_REQUEST['twitter'][0] = $this->getUserService()->social($_REQUEST['twitter'][0]);
        $_REQUEST['livejournal'][0] = $this->getUserService()->social($_REQUEST['livejournal'][0]);
        $_REQUEST['facebook_visible'][0] = $this->getUserService()->social($_REQUEST['facebook_visible'][0]);
        $_REQUEST['vkontakte_visible'][0] = $this->getUserService()->social($_REQUEST['vkontakte_visible'][0]);
        $_REQUEST['telegram'][0] = $this->getUserService()->social($_REQUEST['telegram'][0]);

        $LOCALE = $this->getMessages();
        $LOCALE_PROFILE = LocaleHelper::getLocale(['profile', 'global']);
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $userData = $this->getUserService()->get(CURRENT_USER->id());

        if ($_REQUEST['em'][0] !== $userData->em->get()) {
            $checkEmailExists = DB->select(
                'user',
                [
                    'em' => $_REQUEST['em'][0],
                    ['id', CURRENT_USER->id(), [OperandEnum::NOT_EQUAL]],
                ],
                true,
            );

            if (!$checkEmailExists) {
                DB->update('user', ['em_verified' => 0], ['id' => CURRENT_USER->id()]);

                $idToReverify = md5($userData->id->getAsInt() . $userData->created_at->getAsTimeStamp() . $_REQUEST['em'][0] . $_ENV['PROJECT_HASH_WORD']);
                $text = sprintf(
                    $LOCALE_PROFILE['verify_em']['base_text'],
                    $LOCALE_GLOBAL['sitename'],
                    ABSOLUTE_PATH . '/profile/action=verify_em&verify_id=' . $idToReverify,
                    ABSOLUTE_PATH . '/profile/action=verify_em&verify_id=' . $idToReverify,
                );
                EmailHelper::sendMail(
                    $LOCALE_GLOBAL['sitename'],
                    $LOCALE_GLOBAL['admin_mail'],
                    $_REQUEST['em'][0],
                    sprintf($LOCALE_PROFILE['verify_em']['name'], $LOCALE_GLOBAL['sitename']),
                    $text,
                    true,
                );
            } else {
                ResponseHelper::response([['error', $LOCALE['already_registered_email']]], '', ['em[0]']);
            }
        } elseif ($_REQUEST['em'][0] === '' && $userData['googleplus'] === '' && $userData['vkontakte'] === '' && $userData['facebook'] === '') {
            ResponseHelper::response([['error', $LOCALE['email_cannot_be_empty']]], '', ['em[0]']);
        }
    }

    public function postChange(array $successfulResultsIds): void
    {
        DB->query('UPDATE user SET login=em WHERE id=:id', [['id', CURRENT_USER->id()]]);

        $this->getUserService()->checkForAchievements(CURRENT_USER->id(), [14]);

        if (CookieHelper::getCookie('admUser')) {
            $this->getEntity()->setFraymActionRedirectPath(ABSOLUTE_PATH . '/profile/adm_user=' . CURRENT_USER->getAdminData()['id']);
        }
    }

    public function postDelete(array $successfulResultsIds): void
    {
        if (CookieHelper::getCookie('admUser')) {
            ResponseHelper::response([], ABSOLUTE_PATH . '/profile/adm_user=' . CURRENT_USER->getAdminData()['id']);
        } else {
            ResponseHelper::response([], ABSOLUTE_PATH . '/action=logout');
        }
    }

    public function getSubsObjectsList(): array
    {
        return $this->getUserService()->getSubsObjectsList();
    }

    public function getRightsContext(): array
    {
        return $this->getUserService()->getRightsContext();
    }

    public function getSidDefault(): ?int
    {
        return $this->getUserService()->getSidDefault();
    }

    public function checkRights(): bool
    {
        return CURRENT_USER->isLogged();
    }

    public function checkRightsRestrict(): string
    {
        return 'id=' . CURRENT_USER->id();
    }

    public function postModelInit(BaseModel $model): BaseModel
    {
        $userData = $this->getUserService()->get(CURRENT_USER->id());

        $agreementContext = $userData->agreement->get() ? [] : [UserModel::CONTEXT, UserModel::REGISTER_CONTEXT];

        $model->getElement('agreement')?->getAttribute()->setContext($agreementContext);

        return $model;
    }
}
