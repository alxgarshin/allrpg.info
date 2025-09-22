<?php

declare(strict_types=1);

namespace App\CMSVC\ExchangeItemEdit;

use App\CMSVC\Trait\UserServiceTrait;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\{PostChange, PostCreate, PreChange, PreCreate};
use Fraym\Helper\{DataHelper, ResponseHelper};
use Generator;
use WideImage\WideImage;

/** @extends BaseService<ExchangeItemEditModel> */
#[Controller(ExchangeItemEditController::class)]
#[PreCreate('checkImages')]
#[PreChange('checkImages')]
#[PostCreate('createExchangeAvatar')]
#[PostChange('createExchangeAvatar')]
class ExchangeItemEditService extends BaseService
{
    use UserServiceTrait;

    /** Проверяем доступность всех указанных изображений предмета */
    public function checkImages(): void
    {
        $LOCALE = $this->getLOCALE();
        $LOCALE = $LOCALE['messages'];

        $images = $_REQUEST['images'][0] ?? [];

        foreach ($images as $imageKey => $imagePath) {
            if ($imagePath !== '' && !getimagesize($imagePath)) {
                ResponseHelper::responseOneBlock('error', $LOCALE['wrong_image_path'], ['images[' . $imageKey . ']']);
            }
        }
    }

    /** Создание аватара из первого изображения в наборе */
    public function createExchangeAvatar(): void
    {
        $exchangeItemData = $this->get(DataHelper::getId());

        if (!is_null($exchangeItemData)) {
            $imagesData = json_decode($exchangeItemData->images->get(), true);

            if ($imagesData[0] !== '') {
                $image = WideImage::loadFromFile($imagesData[0]);
                $image->resize(250, 250)->saveToFile(INNER_PATH . 'public' . $_ENV['UPLOADS_PATH'] . $_ENV['UPLOADS'][16]['path'] . DataHelper::getId() . '.jpg');
            }
        }
    }

    public function checkRights(): bool
    {
        return CURRENT_USER->isLogged();
    }

    public function checkRightsRestrict(): string
    {
        return CURRENT_USER->isAdmin() || $this->getUserService()->isModerator() ? '' : 'creator_id=' . CURRENT_USER->id();
    }

    public function getExchange_category_idsValues(): Generator
    {
        return DB->getArrayOfItems('exchange_category ORDER BY name', 'id', 'name');
    }

    public function getRegionDefault(): int
    {
        $userData = $this->getUserService()->get(CURRENT_USER->id());
        $userRegionData = DB->query('SELECT * FROM geography WHERE id IN (SELECT parent FROM geography WHERE id=:id)', [['id', $userData->city->get()]], true);

        if ((int) $userRegionData['parent'] === 0) {
            if (!is_null($userData->city->get())) {
                $userRegion = $userData->city->get();
            } else {
                $userRegion = 2; // Москва
            }
        } else {
            $userRegion = $userRegionData['id'];
        }

        return $userRegion;
    }
}
