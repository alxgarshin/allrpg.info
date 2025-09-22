<?php

declare(strict_types=1);

namespace App\CMSVC\Character;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};

/** @extends BaseController<CharacterService> */
#[CMSVC(
    model: CharacterModel::class,
    service: CharacterService::class,
    view: CharacterView::class,
)]
#[IsAccessible(
    redirectPath: '/login/',
    redirectData: [
        'redirectToKind' => KIND,
        'redirectToId' => ID,
        'redirectParams' => REQUEST_PROJECT_ID,
    ],
    additionalCheckAccessHelper: RightsHelper::class,
    additionalCheckAccessMethod: 'checkProjectKindAccessAndRedirect',
)]
class CharacterController extends BaseController
{
}
