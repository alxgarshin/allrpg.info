<?php

declare(strict_types=1);

namespace App\CMSVC\Community;

use App\CMSVC\Trait\RequestCheckSearchTrait;
use App\Helper\RightsHelper;
use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ActEnum, ActionEnum, ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Helper\{CookieHelper, DataHelper, ResponseHelper};
use Fraym\Interface\Response;
use Fraym\Response\ArrayResponse;

/** @extends BaseController<CommunityService> */
#[CMSVC(
    model: CommunityModel::class,
    service: CommunityService::class,
    view: CommunityView::class,
)]
class CommunityController extends BaseController
{
    use RequestCheckSearchTrait;

    public function Response(): ?Response
    {
        $id = DataHelper::getId();

        if (is_null($id) && (ActionEnum::init() !== ActionEnum::create || ($_REQUEST['search'] ?? false)) && DataHelper::getActDefault($this->entity) === ActEnum::list) {
            $this->requestCheckSearch();
        }

        if (!CURRENT_USER->isLogged() && DataHelper::getActDefault($this->entity) === ActEnum::add) {
            ResponseHelper::redirect('/login/', ['redirectToKind' => KIND, 'redirectToId' => $id, 'redirectParams' => 'act=add']);
        }

        /** @var CommunityView */
        $communityView = $this->CMSVC->view;

        if (is_null($id) && (ActionEnum::init() !== ActionEnum::create || ($_REQUEST['search'] ?? false)) && DataHelper::getActDefault($this->entity) === ActEnum::list) {
            return $communityView->List();
        }

        if ($id > 0 && ($_REQUEST['show'] ?? false) === 'wall' && BID > 0 && $this->service->hasCommunityAccess($id)) {
            return $communityView->Wall();
        }

        if ($id > 0 && ($_REQUEST['show'] ?? false) === 'conversation' && BID > 0 && $this->service->hasCommunityAccess($id)) {
            return $communityView->Conversation();
        }

        return parent::Response();
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function getAccess(): ?Response
    {
        $result = RightsHelper::getAccess(KIND);

        $childObjId = (int) CookieHelper::getCookie('additional_redirectid');
        $childObjType = DataHelper::addBraces(CookieHelper::getCookie('additional_redirectobj'));

        if ($childObjType && $childObjId > 0) {
            if (RightsHelper::checkRights('{child}', DataHelper::addBraces(KIND), DataHelper::getId(), $childObjType, $childObjId)) {
                if (!RightsHelper::checkAnyRights($childObjType, $childObjId)) {
                    RightsHelper::getAccess($childObjType, $childObjId);
                    CookieHelper::batchDeleteCookie(['additional_redirectid', 'additional_redirectobj']);
                }
            }
        }

        return new ArrayResponse(is_array($result) ? $result : []);
    }

    #[IsAccessible]
    #[ApiAction(mutating: true, params: [
        new ApiParam('id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function removeAccess(): void
    {
        RightsHelper::removeAccess(KIND);
    }
}
