<?php

declare(strict_types=1);

namespace App\CMSVC\Exchange;

use App\CMSVC\ExchangeItemEdit\ExchangeItemEditService;
use App\CMSVC\User\UserService;
use App\Helper\{DesignHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\Trait\PageCounter;
use Fraym\Enum\EscapeModeEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Fraym\Interface\Response;

#[Controller(ExchangeController::class)]
class ExchangeView extends BaseView
{
    use PageCounter;

    public function Response(): ?Response
    {
        /** @var ExchangeService $exchangeService */
        $exchangeService = $this->CMSVC->service;

        /** @var ExchangeItemEditService $exchangeItemEditService */
        $exchangeItemEditService = CMSVCHelper::getService('exchange_item_edit');

        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE = $this->LOCALE;
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);
        $LOCALE_PEOPLE = LocaleHelper::getLocale(['people', 'global']);
        $LOCALE_CALENDAR = LocaleHelper::getLocale(['calendar', 'global']);

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);
        $RESPONSE_DATA = '';

        if (DataHelper::getId() > 0) {
            $objData = $exchangeItemEditService->get(DataHelper::getId());

            if (!$objData) {
                return null;
            }
            $objType = 'exchange_item';

            $PAGETITLE = DesignHelper::changePageHeaderTextToLink($objData->name->get() ?? $LOCALE['title']);

            if ($objData->active->get() || $objData->creator_id->getAsInt() === CURRENT_USER->id() || CURRENT_USER->isAdmin()) {
                $exchange_category = $exchangeService->getCategoriesString($objData->exchange_category_ids->get());
                $creatorData = $exchangeService->getCreator($objData);

                $cityName = '';

                if ($objData->region->get() > 0) {
                    $cityName = DB->findObjectById($objData->region->get(), 'geography');
                    $cityName = DataHelper::escapeOutput($cityName['name']);
                }

                $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
<div class="page_blocks">
    <div class="page_block">
        <div class="object_info">
            <div class="object_info_1">
                <div class="object_avatar small" style="' . DesignHelper::getCssBackgroundImage($userService->photoUrl($creatorData)) . '"></div>
            </div>
            <div class="object_info_2">
                <h1>' . DataHelper::escapeOutput($objData->name->get()) . '</h1>
                <div class="object_info_2_additional">
                    <span class="gray">' . $objData->region->shownName . ':</span>' . $cityName . '</span><br>
                    <span class="gray">' . $LOCALE_GLOBAL['published_by'] . LocaleHelper::declineVerb($creatorData) . ':</span>' . $userService->showName($creatorData, true);

                $additional = $objData->additional->get();

                if (count($additional) > 0) {
                    $RESPONSE_DATA .= '<br>
                    <span class="gray">' . $objData->additional->shownName . ':</span>' . str_replace('<br />', ', ', $objData->additional->asHTML(false)) . '</span>';
                }

                if ($objData->price_lease->get() > 0 || $objData->price_buy->get() > 0) {
                    $RESPONSE_DATA .= '<br>' .
                        ($objData->price_lease->get() > 0 ? '<span class="gray">' . $LOCALE['price_lease'] . ':</span>' . $objData->price_lease->get() . TextHelper::currencyNameToSign($objData->currency->get()) . '</span><br>' : '') .
                        ($objData->price_buy->get() > 0 ? '<span class="gray">' . $LOCALE['price_buy'] . ':</span>' . $objData->price_buy->get() . TextHelper::currencyNameToSign($objData->currency->get()) . '</span>' : '');
                }

                $RESPONSE_DATA .= '
                </div>
            </div>
            <div class="object_info_3 only_like">
                ' . UniversalHelper::drawImportant($objType, $objData->id->getAsInt()) . '
                <div class="actions_list_switcher">';

                if (CURRENT_USER->id() === $creatorData->id->getAsInt()) {
                    $RESPONSE_DATA .= '
                    <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/exchange_item_edit/' . $objData->id->getAsInt() . '/"><span>' . TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</span></a></div>';
                } else {
                    if (CURRENT_USER->isAdmin() || $userService->isModerator()) {
                        $RESPONSE_DATA .= '
                    <div class="actions_list_text sbi">' . $LOCALE_GLOBAL['actions_list_text'] . '</div>
                    <div class="actions_list_items">';
                        $RESPONSE_DATA .= '
                        <a href="' . ABSOLUTE_PATH . '/exchange_item_edit/' . $objData->id->getAsInt() . '/">' . TextHelper::mb_ucfirst($LOCALE_FRAYM['functions']['edit']) . '</a>
                        <a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $creatorData->id->getAsInt() . '">' . $LOCALE_PEOPLE['contact_user'] . '</a>';
                        $RESPONSE_DATA .= '
                    </div>';
                    } else {
                        $RESPONSE_DATA .= '
                    <div class="actions_list_button"><a href="' . ABSOLUTE_PATH . '/conversation/action=contact&user=' . $creatorData->id->getAsInt() . '"><span>' . $LOCALE_PEOPLE['contact_user'] . '</span></a></div>';
                    }
                }
                $RESPONSE_DATA .= '
                    <div class="exchange_updated_at"><span>' . $LOCALE['publish_date'] . ':</span>' . $objData->updated_at->get()->format('d.m.Y ' . $LOCALE_FRAYM['datetime']['at'] . ' H:i') . '</div>
                </div>
            </div>
        </div>
    </div>
    <div class="page_block">
        <div class="publication_content">' . DataHelper::escapeOutput($objData->description->get(), EscapeModeEnum::forHTMLforceNewLines) . '
            <div class="publication_images">';

                $images_data = json_decode($objData->images->get(), true);

                if (is_array($images_data) && count($images_data) > 0) {
                    foreach ($images_data as $image_data) {
                        $RESPONSE_DATA .= '<a href="' . $image_data . '" target="_blank"><img src="' . $image_data . '"></a>';
                    }
                }

                $RESPONSE_DATA .= '
            </div>
        </div>
' . ($exchange_category !== '' ? '<div class="publication_tags">' . $exchange_category . '</div>' : '') . '
	</div>
</div>
</div>';
            }
        } else {
            $canAddItems = false;

            if (CURRENT_USER->isLogged()) {
                $canAddItems = true;
            }
            $exchangeItemsData = $exchangeService->getExchangeItems();
            $userRegion = $exchangeService->getUserRegion();

            $RESPONSE_DATA .= '<div class="maincontent_data kind_' . KIND . '">
' . ($canAddItems ? '<a class="outer_add_something_button" href="' . ABSOLUTE_PATH . '/exchange_item_edit/act=add"><span class="sbi sbi-add-something"></span><span class="outer_add_something_button_text">' . $LOCALE['add_item'] . '</span></a>' : '') . '
<h1 class="page_header"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/">' . $LOCALE['title'] . '</a></h1>
<div class="page_blocks margin_top">
    <div class="page_block">
        <h2>' . $LOCALE['about'] . '</h2>
        <div class="publication_content">' . $LOCALE['about_text'] . '</div>
    </div>
    <div class="page_block margin_top">
        <div class="filter filter_region">
            <div class="name">
                ' . $LOCALE_CALENDAR['filter_region'] . '
            </div>
            <select id="filter_region">
                <option value="all">' . $LOCALE_CALENDAR['all'] . '</option>';

            foreach ($exchangeService->getRegionsList() as $region_data) {
                if ($region_data[0] === 2) {
                    $region_data[0] = '2,13';
                } elseif ($region_data[0] === 89) {
                    $region_data[0] = '89,119';
                }
                $RESPONSE_DATA .= '
                <option value="' . $region_data[0] . '"' . ((string) $userRegion === (string) $region_data[0] ? ' selected' : '') . '>' . DataHelper::escapeOutput($region_data[1]) . '</option>';
            }
            $RESPONSE_DATA .= '
            </select>
        </div>';

            $RESPONSE_DATA .= '
        <form action="' . ABSOLUTE_PATH . '/' . KIND . '/" method="POST" id="form_inner_search">
            <a class="search_image sbi sbi-search"></a><input class="search_input" name="search" id="search" type="text" value="' . ($_REQUEST['search'] ?? '') . '" placehold="' . $LOCALE['search'] . '" autocomplete="off">
        </form>
    
        <div class="tags_cloud">' . $exchangeService->drawCategoriesCloud() . '</div>
            <div class="publications">
            ';
            $exchangeItemsDataCount = 0;

            foreach ($exchangeItemsData as $exchangeItemData) {
                $RESPONSE_DATA .= $exchangeService->showExchangeItemShort($exchangeItemData);
                ++$exchangeItemsDataCount;
            }
            $RESPONSE_DATA .= '
            </div>
            <div class="clear"></div>
            ' . $this->drawPageCounter(
                '',
                PAGE,
                $exchangeItemsDataCount,
                CURRENT_USER->getBazeCount(),
            ) . '
	    </div>
    </div>
</div>';
        }

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
