<?php

declare(strict_types=1);

namespace App\CMSVC\User;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
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
    #[ApiAction(mutating: true, params: [
        new ApiParam('value', ApiParamTypeEnum::string, obligatory: true),
    ])]
    public function changeStatus(): ?Response
    {
        return $this->asArray(
            $this->service->changeStatus(
                $this->param('value'),
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
    #[ApiAction(mutating: true, params: [
        new ApiParam('deviceId', ApiParamTypeEnum::string, obligatory: true),
    ])]
    public function webpushUnsubscribe(): ?Response
    {
        return $this->asArray(
            $this->service->webpushUnsubscribe(
                $this->param('deviceId'),
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function becomeFriends(): ?Response
    {
        return $this->asArray(
            $this->service->becomeFriends(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function removeFriend(): ?Response
    {
        return $this->asArray(
            $this->service->removeFriend(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('show_list', ApiParamTypeEnum::bool, default: false),
        new ApiParam('get_opened_dialogs', ApiParamTypeEnum::bool, default: false),
    ])]
    public function getNewEvents(): ?Response
    {
        $showList = $this->param('show_list');
        $getOpenedDialogs = $this->param('get_opened_dialogs');

        $contactsData = $this->service->getContactsOnlineExtended($showList, $getOpenedDialogs);
        $newEventsData = $this->service->getNewEvents(OBJ_ID, OBJ_TYPE, $getOpenedDialogs, $showList);

        return $this->asArray([
            'response' => 'success',
            'response_text' => $contactsData['response_text'],
            'response_data' => array_merge($contactsData['response_data'], $newEventsData['response_data']),
        ]);
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('limit', ApiParamTypeEnum::int, default: 0),
        new ApiParam('shown_limit', ApiParamTypeEnum::int, default: 0),
        new ApiParam('sub_obj_type', ApiParamTypeEnum::string, default: ''),
    ])]
    public function loadUsersList(): ?Response
    {
        return $this->asArray(
            $this->service->loadUsersList(
                OBJ_ID,
                OBJ_TYPE,
                $this->param('limit'),
                $this->param('shown_limit'),
                $this->param('sub_obj_type'),
            ),
        );
    }

    #[ApiAction]
    public function getCaptcha(): ?Response
    {
        return $this->asArray(
            $this->service->getCaptcha(),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('user_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('rights_type', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function addRights(): ?Response
    {
        return $this->asArray(
            $this->service->dynamicAddRights(
                OBJ_TYPE,
                OBJ_ID,
                $this->param('user_id'),
                $this->param('rights_type'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('user_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('rights_type', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function removeRights(): ?Response
    {
        return $this->asArray(
            $this->service->dynamicRemoveRights(
                OBJ_TYPE,
                OBJ_ID,
                $this->param('user_id'),
                $this->param('rights_type'),
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
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
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
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
    #[ApiAction(mutating: true)]
    public function reverifyEm(): ?Response
    {
        return $this->asArray(
            $this->service->reverifyEm(),
        );
    }
}
