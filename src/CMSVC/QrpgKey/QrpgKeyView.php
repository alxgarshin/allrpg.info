<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgKey;

use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, MultiObjectsEntity, Rights, TableEntity};
use Fraym\Enum\MultiObjectsEntitySubTypeEnum;
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<QrpgKeyService> */
#[TableEntity(
    name: 'qrpgKey',
    table: 'qrpg_key',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
        new EntitySortingItem(
            tableFieldName: 'property_name',
        ),
    ],
    elementsPerPage: 5000,
)]
#[Rights(
    viewRight: true,
    addRight: true,
    changeRight: true,
    deleteRight: true,
    viewRestrict: 'checkRightsRestrict',
    changeRestrict: 'checkRightsRestrict',
    deleteRestrict: 'checkRightsRestrict',
)]
#[Controller(QrpgKeyController::class)]
class QrpgKeyView extends BaseView
{
    public function init(): static
    {
        $qrpgKeyService = $this->service;

        $viewtype = $qrpgKeyService->getViewType();

        if ($viewtype === 2) {
            $entity = $this->entity;
            $this->entity = new MultiObjectsEntity(
                name: $entity->name,
                table: $entity->table,
                sortingData: $entity->sortingData,
                elementsPerPage: $entity->elementsPerPage,
                subType: MultiObjectsEntitySubTypeEnum::Cards,
            );

            $this->propertiesWithListContext = [];
        }

        return $this;
    }

    public function Response(): ?Response
    {
        return null;
    }

    public function postViewHandler(HtmlResponse $response): HtmlResponse
    {
        $LOCALE = $this->LOCALE;

        $qrpgKeyService = $this->service;

        $viewtype = $qrpgKeyService->getViewType();

        $RESPONSE_DATA = $response->getHtml();

        $RESPONSE_DATA = preg_replace('#(<div class="clear"></div><hr>|<form action="\/' . KIND . '\/" enctype="multipart/form-data" id="form_' . KIND . '_add">)#', '<div class="filter"><a href="/' . KIND . '/viewtype=' . ($viewtype === 1 ? '2' : '1') . '" class="fixed_select">' . ($viewtype === 1 ? $LOCALE['switch_to_cards'] : $LOCALE['switch_to_table']) . '</a></div>$1', $RESPONSE_DATA);

        return $response->setHtml($RESPONSE_DATA);
    }
}
