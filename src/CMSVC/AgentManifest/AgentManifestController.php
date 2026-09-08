<?php

declare(strict_types=1);

namespace App\CMSVC\AgentManifest;

use Fraym\BaseObject\{BaseAgentManifestController, CMSVC};

/** Машиночитаемое описание API проекта для автономного агента.
 *  По умолчанию открыт всем: чтобы закрыть, добавьте #[IsAccessible] или #[IsAdmin]. */
#[CMSVC(
    controller: AgentManifestController::class,
)]
class AgentManifestController extends BaseAgentManifestController
{
}
