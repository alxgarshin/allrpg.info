<?php

declare(strict_types=1);

namespace App\CMSVC\Exchange;

use App\CMSVC\ExchangeCategoryEdit\ExchangeCategoryEditService;
use App\CMSVC\ExchangeItemEdit\{ExchangeItemEditModel, ExchangeItemEditService};
use App\CMSVC\Trait\UserServiceTrait;
use App\CMSVC\User\UserModel;
use App\Helper\{DesignHelper, TextHelper, UniversalHelper};
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{CookieHelper, DataHelper};
use Generator;

#[Controller(ExchangeController::class)]
class ExchangeService extends BaseService
{
    use UserServiceTrait;

    #[DependencyInjection]
    public ExchangeItemEditService $exchangeItemEditService;

    #[DependencyInjection]
    public ExchangeCategoryEditService $exchangeCategoryEditService;

    /** Автоопределение и выставление региона пользователя */
    public function getUserRegion(): string
    {
        $userRegion = 'all';

        if ($_REQUEST['region'] ?? false) {
            CookieHelper::batchSetCookie(['user_region' => $_REQUEST['region']]);

            $userRegion = $_REQUEST['region'];
        } elseif (CookieHelper::getCookie('user_region')) {
            $userRegion = CookieHelper::getCookie('user_region');
        }

        return $userRegion;
    }

    /** Список всех регионов с какими бы то ни было предметами */
    public function getRegionsList(): Generator
    {
        return DB->getArrayOfItems(
            'geography WHERE id IN (SELECT DISTINCT region FROM exchange_item) ORDER BY name',
            'id',
            'name',
        );
    }

    /** Получение creator'а предмета */
    public function getCreator(ExchangeItemEditModel $exchangeItemData): UserModel
    {
        if ($exchangeItemData->creator_id->getAsInt() === 0) {
            $exchangeItemData->creator_id->set('1');
        }

        return $this->getUserService()->get($exchangeItemData->creator_id->getAsInt());
    }

    /** Получение строки-списка категорий */
    public function getCategoriesString(?array $categories): string
    {
        $exchangeCategoryResult = [];

        if (!is_null($categories)) {
            foreach ($categories as $key => $value) {
                if ($value === '') {
                    unset($categories[$key]);
                }
            }

            if (count($categories) > 0) {
                $exchangeCategoriesData = $this->exchangeCategoryEditService->getAll(['id' => $categories], false, ['name']);

                foreach ($exchangeCategoriesData as $exchangeCategoryData) {
                    $exchangeCategoryResult[] = '<a href="' . ABSOLUTE_PATH . '/exchange/category=' . $exchangeCategoryData->id->getAsInt() . '">' . DataHelper::escapeOutput($exchangeCategoryData->name->get()) . '</a>';
                }
            }
        }

        return implode('', $exchangeCategoryResult);
    }

    /** Выборка публикаций на основе запроса.
     * @return Generator<int|string, ExchangeItemEditModel>
     */
    public function getExchangeItems(): Generator
    {
        $bazecount = CURRENT_USER->getBazeCount();
        $userRegion = $this->getUserRegion();

        if ($_REQUEST['category'] ?? false) {
            $exchangeItemsData = $this->exchangeItemEditService->getAll(
                [
                    'active' => 1,
                    'region' => ($userRegion === 'all' ? null : explode(',', $userRegion)),
                    ['exchange_category_ids', '%-' . $_REQUEST['category'] . '-%', [OperandEnum::LIKE]],
                ],
                false,
                ['created_at DESC'],
                $bazecount,
                PAGE * $bazecount,
            );
        } elseif ($_REQUEST['search'] ?? false) {
            $exchangeItemsData = $this->exchangeItemEditService->arraysToModels(
                DB->query(
                    'SELECT * FROM exchange_item WHERE active<=>:active' . ($userRegion === 'all' ? '' : ' AND region IN (:region)') . ' AND (name LIKE :input1 OR description LIKE :input2) ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
                    [
                        ['active', 1],
                        ['region', $userRegion === 'all' ? null : explode(',', $userRegion)],
                        ['input1', '%' . $_REQUEST['search'] . '%'],
                        ['input2', '%' . $_REQUEST['search'] . '%'],
                        ['limit', $bazecount],
                        ['offset', PAGE * $bazecount],
                    ],
                ),
            );
        } else {
            $exchangeItemsData = $this->exchangeItemEditService->getAll(
                [
                    'active' => 1,
                    'region' => ($userRegion === 'all' ? null : explode(',', $userRegion)),
                ],
                false,
                ['created_at DESC'],
                $bazecount,
                PAGE * $bazecount,
            );

            /** Если нет ни одного активного лота в регионе, переключаемся принудительно на Москву */
            if (iterator_count($exchangeItemsData) === 0) {
                CookieHelper::batchSetCookie(['user_region' => 'all']);
                $exchangeItemsData = $this->exchangeItemEditService->getAll(
                    [
                        'active' => 1,
                    ],
                    false,
                    ['created_at DESC'],
                    $bazecount,
                    PAGE * $bazecount,
                );
            } else {
                $exchangeItemsData = $this->exchangeItemEditService->getAll(
                    [
                        'active' => 1,
                        'region' => ($userRegion === 'all' ? null : explode(',', $userRegion)),
                    ],
                    false,
                    ['created_at DESC'],
                    $bazecount,
                    PAGE * $bazecount,
                );
            }
        }

        return $exchangeItemsData;
    }

    /** Создание облака категорий */
    public function drawCategoriesCloud(): string
    {
        $RESPONSE_DATA = '';

        $allExchangeItemsCount = DB->select(
            'exchange_item',
            [
                'active' => 1,
            ],
            false,
            null,
            null,
            null,
            true,
        )[0];
        $exchangeCategories = $this->exchangeCategoryEditService->getAll(null, false, ['name']);

        foreach ($exchangeCategories as $exchangeCategoryData) {
            $itemsWithCategoryCount = DB->select(
                'exchange_item',
                [
                    'active' => 1,
                    ['exchange_category_ids', '%-' . $exchangeCategoryData->id->getAsInt() . '-%', [OperandEnum::LIKE]],
                ],
                false,
                null,
                null,
                null,
                true,
            )[0];

            if ($itemsWithCategoryCount > 0) {
                $delta = ceil($itemsWithCategoryCount / $allExchangeItemsCount * 100);
            } else {
                $delta = 0;
            }
            $RESPONSE_DATA .= '<div class="tags_cloud_tag" style="font-size: ' . (90 + $delta) . '%;"><a href="' . ABSOLUTE_PATH . '/exchange/category=' . $exchangeCategoryData->id->getAsInt() . '">' . DataHelper::escapeOutput($exchangeCategoryData->name->get()) . '</a></div>';
        }

        return $RESPONSE_DATA;
    }

    /** Вывод краткого представления предмета в Складе */
    public function showExchangeItemShort(ExchangeItemEditModel $exchangeItem, bool $controlButtons = false): string
    {
        $LOCALE = $this->LOCALE;

        $exchangeCategoryArray = $exchangeItem->exchange_category_ids->get();

        foreach ($exchangeCategoryArray as $key => $value) {
            if ($value === '') {
                unset($exchangeCategoryArray[$key]);
            }
        }
        $exchangeCategoryResult = [];

        if (count($exchangeCategoryArray) > 0) {
            $exchangeCategoriesData = $this->exchangeCategoryEditService->getAll(['id' => $exchangeCategoryArray], false, ['name']);

            foreach ($exchangeCategoriesData as $exchangeCategoryData) {
                $exchangeCategoryResult[] = '<a href="' . ABSOLUTE_PATH . '/exchange/category=' . $exchangeCategoryData->id->getAsInt() . '">' .
                    DataHelper::escapeOutput($exchangeCategoryData->name->get()) . '</a>';
            }
        }
        $exchangeCategory = implode('', $exchangeCategoryResult);

        $imagesData = json_decode($exchangeItem->images->get(), true);

        $result = '<div class="publication">';

        if (is_array($imagesData) && count($imagesData) > 0) {
            $filepath = '';

            if (file_exists(INNER_PATH . 'public' . $_ENV['UPLOADS_PATH'] . $_ENV['UPLOADS'][16]['path'] . $exchangeItem->id->getAsInt() . '.jpg')) {
                $filepath = ABSOLUTE_PATH . $_ENV['UPLOADS_PATH'] . $_ENV['UPLOADS'][16]['path'] . $exchangeItem->id->getAsInt() . '.jpg';
            }

            $result .= '<a href="' . $filepath . '" target="_blank" class="publication_preview_image"><div style="' . DesignHelper::getCssBackgroundImage($filepath) . '"></div></a>';
        }

        if ($exchangeItem->price_lease->get() > 0) {
            $result .= '<div class="publication_price"><span>' . $LOCALE['price_lease'] . ':</span>' . $exchangeItem->price_lease->get() .
                TextHelper::currencyNameToSign($exchangeItem->currency->get()) . '</div>';
        }

        if ($exchangeItem->price_buy->get() > 0) {
            $result .= '<div class="publication_price"><span>' . $LOCALE['price_buy'] . ':</span>' . $exchangeItem->price_buy->get() .
                TextHelper::currencyNameToSign($exchangeItem->currency->get()) . '</div>';
        }

        if ($exchangeItem->region->get() > 0) {
            $cityName = DB->findObjectById($exchangeItem->region->get(), 'geography');
            $cityName = DataHelper::escapeOutput($cityName['name']);
            $result .= '<div class="publication_price inverted">' . $cityName . '</div>';
        }

        $result .= '<div class="publication_header"><a href="' . ABSOLUTE_PATH . '/exchange/' . $exchangeItem->id->getAsInt() . '/">' .
            DataHelper::escapeOutput($exchangeItem->name->get()) . '</a></div>';

        if ($controlButtons) {
            $result .= '<div class="publication_buttons"><div class="publication_buttons_edit"><a href="' . ABSOLUTE_PATH . '/exchange_item_edit/' . $exchangeItem->id->getAsInt() . '/">' . $LOCALE['edit'] . '</a></div></div>';
        }
        $result .= '<div class="publication_annotation">' . DataHelper::escapeOutput($exchangeItem->description->get()) . '</div>
	<div class="publication_tags">' . $exchangeCategory . '</div>
	' . UniversalHelper::drawImportant('{exchange_item}', $exchangeItem->id->getAsInt()) . '
	<div class="clear"></div>
	</div>';

        return $result;
    }
}
