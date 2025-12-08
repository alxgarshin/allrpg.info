<?php

declare(strict_types=1);

namespace App\CMSVC\Notion;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Response\ArrayResponse;

/** @extends BaseController<NotionService> */
#[IsAccessible]
#[CMSVC(
    service: NotionService::class,
)]
class NotionController extends BaseController
{
    public function notionMessageSave(): ArrayResponse
    {
        return $this->asArray(
            $this->service->notionMessageSave(
                OBJ_ID,
                $_REQUEST['text'] ?? '',
                (int) ($_REQUEST['rating'] ?? 0),
            ),
        );
    }

    public function notionMessageDelete(): ArrayResponse
    {
        return $this->asArray($this->service->notionMessageDelete(OBJ_ID));
    }

    public function showHideNotion(): ArrayResponse
    {
        return $this->asArray($this->service->showHideNotion(OBJ_ID));
    }
}
