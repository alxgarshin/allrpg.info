<?php

declare(strict_types=1);

namespace App\CMSVC\Mark;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Interface\Response;

/** @extends BaseController<MarkService> */
#[CMSVC(
    service: MarkService::class,
)]
class MarkController extends BaseController
{
    #[IsAccessible]
    public function markNeedResponse(): ?Response
    {
        $markService = $this->service;

        return $this->asArray(
            $markService->markNeedResponse(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible]
    public function markHasResponse(): ?Response
    {
        $markService = $this->service;

        return $this->asArray(
            $markService->markHasResponse(
                OBJ_ID,
            ),
        );
    }

    #[IsAccessible]
    public function markReadMessage(): ?Response
    {
        $markService = $this->service;

        return $this->asArray(
            $markService->markReadMessage(
                OBJ_ID,
            ),
        );
    }

    public function markRead(): ?Response
    {
        $markService = $this->service;

        return $this->asArray(
            $markService->markRead(
                OBJ_ID,
            ),
        );
    }

    public function markImportant(): ?Response
    {
        $markService = $this->service;

        return $this->asArray(
            $markService->markImportant(
                OBJ_TYPE,
                OBJ_ID,
            ),
        );
    }
}
