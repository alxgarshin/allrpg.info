<?php

declare(strict_types=1);

namespace App\CMSVC\QrpgKey;

use App\CMSVC\Trait\ProjectIdTrait;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(QrpgKeyController::class)]
class QrpgKeyModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use ProjectIdTrait;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Multiselect(
        values: 'getImgValues',
        images: 'getImgImages',
        one: true,
    )]
    public Item\Multiselect $img;

    #[Attribute\Multiselect(
        values: 'getConsistsOfValues',
        useInFilters: true,
    )]
    public Item\Multiselect $consists_of;

    #[Attribute\Text(
        useInFilters: true,
    )]
    public Item\Text $property_name;

    #[Attribute\Textarea(
        useInFilters: true,
    )]
    public Item\Textarea $property_description;

    #[Attribute\Select(
        values: 'getUsedInCodesValues',
        context: ['qrpgKey:view', 'qrpgKey:list'],
        alternativeDataColumnName: 'id',
        noData: true,
    )]
    public Item\Select $used_in_codes;

    #[Attribute\Select(
        values: 'getUsedInApplicationsValues',
        context: ['qrpgKey:view', 'qrpgKey:list'],
        linkAtBegin: '<a href="/application/application/action=setFilters&search_qrpg_key[{value}]=on&search_qrpg_keyselect=1" target="_blank">',
        linkAtEnd: '</a>',
        alternativeDataColumnName: 'id',
        noData: true,
    )]
    public Item\Select $used_in_applications;
}
