<?php

declare(strict_types=1);

namespace App\CMSVC\Message;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Interface\Response;

/** @extends BaseController<MessageService> */
#[CMSVC(
    service: MessageService::class,
)]
class MessageController extends BaseController
{
    public function loadWall(): ?Response
    {
        return $this->asArray(
            $this->getService()->loadWall(
                OBJ_TYPE,
                OBJ_ID,
                (int) ($_REQUEST['last_shown_conversation_id'] ?? 0),
                (int) ($_REQUEST['obj_limit'] ?? 0),
                (int) ($_REQUEST['show_limit'] ?? 0),
                $_REQUEST['search_string'] ?? '',
                $_REQUEST['sub_obj_type'] ?? '',
            ),
        );
    }

    #[IsAccessible]
    public function addComment(): ?Response
    {
        return $this->asArray(
            $this->getService()->addComment(
                OBJ_TYPE,
                $_REQUEST['sub_obj_type'] ?? '',
                OBJ_ID,
                $_REQUEST['name'] ?? '',
                $_REQUEST['content'] ?? '',
                (int) ($_REQUEST['rating'] ?? 0),
                ($_REQUEST['conversation_id'] ?? false) ? (int) $_REQUEST['conversation_id'] : null,
                'on' === ($_REQUEST['use_group_name'] ?? false),
                (int) ($_REQUEST['parent'] ?? 0),
                $_REQUEST['vote_name'] ?? null,
                $_REQUEST['vote_answer'] ?? null,
                $_REQUEST['attachments'] ?? null,
                $_REQUEST['parent_obj_type'] ?? null,
                (int) ($_REQUEST['parent_obj_id'] ?? 0),
                $_REQUEST['status'] ?? null,
                ($_REQUEST['priority'] ?? false) ? (int) $_REQUEST['priority'] : null,
                $_REQUEST['date_to'] ?? null,
                ($_REQUEST['responsible'] ?? false) ? (int) $_REQUEST['responsible'] : null,
            ),
        );
    }

    #[IsAccessible]
    public function vote(): ?Response
    {
        return $this->asArray(
            $this->getService()->vote(
                ($_REQUEST['m_id'] ?? false) ? (int) $_REQUEST['m_id'] : null,
                ($_REQUEST['value'] ?? false) ? (int) $_REQUEST['value'] : null,
                $_REQUEST['type'] ?? '',
            ),
        );
    }
}
