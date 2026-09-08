<?php

declare(strict_types=1);

namespace App\CMSVC\User;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\ApiParamTypeEnum;
use Fraym\Helper\LocaleHelper;
use Fraym\Interface\Response;

/** @extends BaseController<UserService> */
#[CMSVC(
    model: UserModel::class,
    service: UserService::class,
    view: UserView::class,
)]
class UserController extends BaseController
{
    public function Response(): ?Response
    {
        return null;
    }

    #[IsAccessible]
    public function changeStatus(): ?Response
    {
        return $this->asArray(
            $this->service->changeStatus(
                $_REQUEST['value'] ?? null,
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('deviceId', ApiParamTypeEnum::string, obligatory: true),
        new ApiParam('endpoint', ApiParamTypeEnum::string, obligatory: true),
        new ApiParam('p256dh', ApiParamTypeEnum::string, obligatory: true),
        new ApiParam('auth', ApiParamTypeEnum::string, obligatory: true),
        new ApiParam('contentEncoding', ApiParamTypeEnum::string, default: 'aesgcm'),
    ])]
    public function webpushSubscribe(): ?Response
    {
        return $this->asArray(
            $this->service->webpushSubscribe(
                $this->param('deviceId'),
                $this->param('endpoint'),
                $this->param('p256dh'),
                $this->param('auth'),
                $this->param('contentEncoding'),
            ),
        );
    }

    #[IsAccessible]
    public function webpushUnsubscribe(): ?Response
    {
        return $this->asArray(
            $this->service->webpushUnsubscribe(
                $_REQUEST['deviceId'] ?? null,
            ),
        );
    }

    #[IsAccessible]
    public function becomeFriends(): ?Response
    {
        return $this->asArray(
            $this->service->becomeFriends(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible]
    public function removeFriend(): ?Response
    {
        return $this->asArray(
            $this->service->removeFriend(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible]
    public function getNewEvents(): ?Response
    {
        $showList = ($_REQUEST['show_list'] ?? '') === 'true';
        $getOpenedDialogs = ($_REQUEST['get_opened_dialogs'] ?? '') === 'true';

        $contactsData = $this->service->getContactsOnlineExtended($showList, $getOpenedDialogs);
        $newEventsData = $this->service->getNewEvents(OBJ_ID, OBJ_TYPE, $getOpenedDialogs, $showList);

        return $this->asArray([
            'response' => 'success',
            'response_text' => $contactsData['response_text'],
            'response_data' => array_merge($contactsData['response_data'], $newEventsData['response_data']),
        ]);
    }

    public function loadUsersList(): ?Response
    {
        return $this->asArray(
            $this->service->loadUsersList(
                OBJ_ID,
                OBJ_TYPE,
                (int) ($_REQUEST['limit'] ?? 0),
                (int) ($_REQUEST['shown_limit'] ?? 0),
                $_REQUEST['sub_obj_type'] ?? '',
            ),
        );
    }

    public function getCaptcha(): ?Response
    {
        return $this->asArray(
            $this->service->getCaptcha(),
        );
    }

    public function addRights(): ?Response
    {
        return $this->asArray(
            $this->service->dynamicAddRights(
                OBJ_TYPE,
                OBJ_ID,
                $_REQUEST['user_id'] ?? null,
                $_REQUEST['rights_type'] ?? false,
            ),
        );
    }

    public function removeRights(): ?Response
    {
        return $this->asArray(
            $this->service->dynamicRemoveRights(
                OBJ_TYPE,
                OBJ_ID,
                $_REQUEST['user_id'] ?? null,
                $_REQUEST['rights_type'] ?? false,
            ),
        );
    }

    #[IsAccessible]
    public function subscribe(): ?Response
    {
        if (!empty(OBJ_TYPE) && OBJ_ID !== '') {
            if ($this->service->addSubscribe(OBJ_TYPE, OBJ_ID)) {
                $LOCALE = LocaleHelper::getLocale(['global', 'subscription']);

                return $this->asArray(
                    [
                        'response' => 'success',
                        'response_text' => $LOCALE['messages']['subscribe_success'],
                        'response_data' => $LOCALE['unsubscribe'],
                    ],
                );
            }
        }

        return null;
    }

    #[IsAccessible]
    public function unsubscribe(): ?Response
    {
        if (!empty(OBJ_TYPE) && OBJ_ID !== '') {
            if ($this->service->deleteSubscribe(OBJ_TYPE, OBJ_ID)) {
                $LOCALE = LocaleHelper::getLocale(['global', 'subscription']);

                return $this->asArray(
                    [
                        'response' => 'success',
                        'response_text' => $LOCALE['messages']['unsubscribe_success'],
                        'response_data' => $LOCALE['subscribe'],
                    ],
                );
            }
        }

        return null;
    }

    #[IsAccessible]
    public function reverifyEm(): ?Response
    {
        return $this->asArray(
            $this->service->reverifyEm(),
        );
    }
}
