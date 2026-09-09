<?php

declare(strict_types=1);

namespace App\CMSVC\Conversation;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ActEnum, ActionEnum, ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;

/** @extends BaseController<ConversationService> */
#[IsAccessible(
    '/login/',
    [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
    ],
)]
#[CMSVC(
    model: ConversationModel::class,
    service: ConversationService::class,
    view: ConversationView::class,
)]
class ConversationController extends BaseController
{
    public function Response(): ?Response
    {
        if (ACTION === ActionEnum::create) {
            return $this->entity->fraymAction();
        } elseif (DataHelper::getActDefault($this->entity) === ActEnum::add) {
            return $this->entity->view();
        }

        return $this->CMSVC->view->Response();
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('user_id', ApiParamTypeEnum::string),
        new ApiParam('limit', ApiParamTypeEnum::int, default: 0),
        new ApiParam('time', ApiParamTypeEnum::string),
    ])]
    public function getDialog(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->getDialog(
                OBJ_ID,
                $this->param('user_id'),
                $this->param('limit'),
                $this->param('time'),
            ),
        );
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('user_id', ApiParamTypeEnum::string, obligatory: true),
    ])]
    public function getDialogAvatar(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->getDialogAvatar(
                OBJ_ID,
                $this->param('user_id'),
            ),
        );
    }

    public function setDialogPosition(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->setDialogPosition(
                OBJ_ID,
                $_REQUEST['left'] ?? '0px',
                $_REQUEST['top'] ?? '0px',
                ($_REQUEST['visible'] ?? false) === 'true',
                ($_REQUEST['user_id'] ?? false) ? (int) $_REQUEST['user_id'] : null,
                $_REQUEST['sound'] ?? '',
            ),
        );
    }

    public function deleteDialogPosition(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->deleteDialogPosition(
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_limit', ApiParamTypeEnum::int, default: 0),
        new ApiParam('dynamic_load', ApiParamTypeEnum::bool, default: false),
        new ApiParam('search_string', ApiParamTypeEnum::string, default: ''),
        new ApiParam('show_limit', ApiParamTypeEnum::int, default: 0),
    ])]
    public function loadConversation(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->loadConversation(
                (int) OBJ_ID,
                $this->param('obj_limit'),
                $this->param('dynamic_load'),
                $this->param('search_string'),
                $this->param('show_limit'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('user_id', ApiParamTypeEnum::string),
        new ApiParam('value', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function dialogNewMessage(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->dialogNewMessage(
                OBJ_ID,
                $this->param('user_id'),
                $this->param('value'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('text', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function messageSave(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->messageSave(
                OBJ_ID,
                $this->param('text'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function wallMessageDelete(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->messageDelete(
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function conversationMessageDelete(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->conversationMessageDelete(
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('user', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function contact(): null
    {
        $conversationService = $this->service;

        return $conversationService->contact($this->param('user'));
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function grantAccess(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->resolveAction(
                ACTION,
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function denyAccess(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->resolveAction(
                ACTION,
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function acceptInvitation(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->resolveAction(
                ACTION,
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function declineInvitation(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->resolveAction(
                ACTION,
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function acceptFriend(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->resolveAction(
                ACTION,
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function declineFriend(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->resolveAction(
                ACTION,
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function leaveDialog(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->leaveDialog(
                OBJ_ID,
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('user_id', ApiParamTypeEnum::string, obligatory: true),
    ])]
    public function sendInvitation(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->sendInvitation(
                OBJ_TYPE,
                OBJ_ID,
                $this->param('user_id'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('user_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
    ])]
    public function addUserToDialog(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->addUserToDialog(
                OBJ_ID,
                $this->param('user_id'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('value', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function conversationRename(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->conversationRename(
                OBJ_ID,
                $this->param('value'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function switchUseNamesType(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->switchUseNamesType(
                OBJ_ID,
            ),
        );
    }
}
