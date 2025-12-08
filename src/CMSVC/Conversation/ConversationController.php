<?php

declare(strict_types=1);

namespace App\CMSVC\Conversation;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ActEnum, ActionEnum};
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

    public function getDialog(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->getDialog(
                OBJ_ID,
                $_REQUEST['user_id'] ?? null,
                (int) ($_REQUEST['limit'] ?? 0),
                $_REQUEST['time'] ?? null,
            ),
        );
    }

    public function getDialogAvatar(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->getDialogAvatar(
                OBJ_ID,
                $_REQUEST['user_id'] ?? null,
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

    public function loadConversation(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->loadConversation(
                (int) OBJ_ID,
                (int) ($_REQUEST['obj_limit'] ?? 0),
                ($_REQUEST['dynamic_load'] ?? '') === 'true',
                $_REQUEST['search_string'] ?? '',
                (int) ($_REQUEST['show_limit'] ?? 0),
            ),
        );
    }

    public function dialogNewMessage(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->dialogNewMessage(
                OBJ_ID,
                $_REQUEST['user_id'] ?? null,
                $_REQUEST['value'] ?? '',
            ),
        );
    }

    public function messageSave(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->messageSave(
                OBJ_ID,
                $_REQUEST['text'] ?? '',
            ),
        );
    }

    public function wallMessageDelete(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->messageDelete(
                OBJ_ID,
            ),
        );
    }

    public function conversationMessageDelete(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->conversationMessageDelete(
                OBJ_ID,
            ),
        );
    }

    public function contact(): null
    {
        $conversationService = $this->service;

        return $conversationService->contact((int) ($_REQUEST['user'] ?? 0));
    }

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

    public function leaveDialog(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->leaveDialog(
                OBJ_ID,
            ),
        );
    }

    public function sendInvitation(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->sendInvitation(
                OBJ_TYPE,
                OBJ_ID,
                $_REQUEST['user_id'] ?? null,
            ),
        );
    }

    public function addUserToDialog(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->addUserToDialog(
                OBJ_ID,
                (int) ($_REQUEST['user_id'] ?? null),
            ),
        );
    }

    public function conversationRename(): ?Response
    {
        $conversationService = $this->service;

        return $this->asArray(
            $conversationService->conversationRename(
                OBJ_ID,
                $_REQUEST['value'] ?? '',
            ),
        );
    }

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
