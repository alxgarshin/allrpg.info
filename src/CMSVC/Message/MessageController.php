<?php

declare(strict_types=1);

namespace App\CMSVC\Message;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Interface\Response;

/** @extends BaseController<MessageService> */
#[CMSVC(
    service: MessageService::class,
)]
class MessageController extends BaseController
{
    #[ApiAction(params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('last_shown_conversation_id', ApiParamTypeEnum::int, default: 0),
        new ApiParam('obj_limit', ApiParamTypeEnum::int, default: 0),
        new ApiParam('show_limit', ApiParamTypeEnum::int, default: 0),
        new ApiParam('search_string', ApiParamTypeEnum::string, default: ''),
        new ApiParam('sub_obj_type', ApiParamTypeEnum::string, default: ''),
    ])]
    public function loadWall(): ?Response
    {
        return $this->asArray(
            $this->service->loadWall(
                OBJ_TYPE,
                OBJ_ID,
                $this->param('last_shown_conversation_id'),
                $this->param('obj_limit'),
                $this->param('show_limit'),
                $this->param('search_string'),
                $this->param('sub_obj_type'),
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_id', ApiParamTypeEnum::int, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('sub_obj_type', ApiParamTypeEnum::string, default: ''),
        new ApiParam('name', ApiParamTypeEnum::string, default: ''),
        new ApiParam('content', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('rating', ApiParamTypeEnum::int, default: 0),
        new ApiParam('conversation_id', ApiParamTypeEnum::int, default: 0),
        new ApiParam('use_group_name', ApiParamTypeEnum::bool, default: false),
        new ApiParam('parent', ApiParamTypeEnum::int, default: 0),
        new ApiParam('vote_name', ApiParamTypeEnum::string),
        new ApiParam('vote_answer', ApiParamTypeEnum::array),
        new ApiParam('attachments', ApiParamTypeEnum::array),
        new ApiParam('parent_obj_type', ApiParamTypeEnum::string),
        new ApiParam('parent_obj_id', ApiParamTypeEnum::int, default: 0),
        new ApiParam('status', ApiParamTypeEnum::string),
        new ApiParam('priority', ApiParamTypeEnum::int, default: 0),
        new ApiParam('date_to', ApiParamTypeEnum::string),
        new ApiParam('responsible', ApiParamTypeEnum::int, default: 0),
    ])]
    public function addComment(): ?Response
    {
        $conversationId = $this->param('conversation_id');
        $priority = $this->param('priority');
        $responsible = $this->param('responsible');

        return $this->asArray(
            $this->service->addComment(
                OBJ_TYPE,
                $this->param('sub_obj_type'),
                OBJ_ID,
                $this->param('name'),
                $this->param('content'),
                $this->param('rating'),
                $conversationId > 0 ? $conversationId : null,
                $this->param('use_group_name'),
                $this->param('parent'),
                $this->param('vote_name'),
                $this->param('vote_answer'),
                $this->param('attachments'),
                $this->param('parent_obj_type'),
                $this->param('parent_obj_id'),
                $this->param('status'),
                $priority > 0 ? $priority : null,
                $this->param('date_to'),
                $responsible > 0 ? $responsible : null,
            ),
        );
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('m_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('value', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('type', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function vote(): ?Response
    {
        $messageId = $this->param('m_id');
        $value = $this->param('value');

        return $this->asArray(
            $this->service->vote(
                $messageId > 0 ? $messageId : null,
                $value > 0 ? $value : null,
                $this->param('type'),
            ),
        );
    }
}
