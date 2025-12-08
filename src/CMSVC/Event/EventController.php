<?php

declare(strict_types=1);

namespace App\CMSVC\Event;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Enum\ActionEnum;
use Fraym\Helper\{DataHelper, ResponseHelper};
use Fraym\Interface\Response;
use Fraym\Response\ArrayResponse;

/** @extends BaseController<EventService> */
#[CMSVC(
    model: EventModel::class,
    service: EventService::class,
    view: EventView::class,
)]
class EventController extends BaseController
{
    public function Response(): ?Response
    {
        if (!CURRENT_USER->isLogged()) {
            if (OBJ_TYPE && OBJ_ID > 0) {
                ResponseHelper::redirect(
                    '/login/',
                    [
                        'redirectToKind' => DataHelper::clearBraces(OBJ_TYPE),
                        'redirectToId' => OBJ_ID,
                        'additional_redirectobj' => KIND,
                        'additional_redirectid' => DataHelper::getId(),
                    ],
                );
            }

            ResponseHelper::redirect('/login/', ['redirectToKind' => KIND, 'redirectToId' => DataHelper::getId()]);
        }

        if (in_array(ACTION, ActionEnum::getBaseValues())) {
            if (
                !(
                    (
                        DataHelper::getId()
                        && RightsHelper::checkRights('{admin}', '{event}', DataHelper::getId())
                    )
                    || (
                        ACTION === ActionEnum::create
                        && (
                            (
                                is_null($this->service->getObjType())
                                && is_null($this->service->getObjId())
                            )
                            || RightsHelper::checkAnyRights($this->service->getObjType(), $this->service->getObjId())
                        )
                    )
                )
            ) {
                $LOCALE = $this->LOCALE;
                ResponseHelper::responseOneBlock(
                    'error',
                    $LOCALE['have_no_rights'] . ' ' . ($this->service->getObjType() === 'project' ? $LOCALE['have_no_rights_project'] : $LOCALE['have_no_rights_community']) . '.',
                );
            }
        }

        return parent::Response();
    }

    public function getAccess(): ?Response
    {
        $result = RightsHelper::getAccess(KIND);

        return new ArrayResponse(is_array($result) ? $result : []);
    }

    public function removeAccess(): void
    {
        RightsHelper::removeAccess(KIND);
    }
}
