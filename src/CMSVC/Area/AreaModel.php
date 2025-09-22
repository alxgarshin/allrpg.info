<?php

declare(strict_types=1);

namespace App\CMSVC\Area;

use App\CMSVC\HelperGeographyCity\HelperGeographyCityController;
use Fraym\BaseObject\Trait\{CreatedUpdatedAtTrait, CreatorIdTrait, IdTrait};
use Fraym\BaseObject\{BaseModel, Controller};
use Fraym\Element\{Attribute, Item};

#[Controller(AreaController::class)]
class AreaModel extends BaseModel
{
    use IdTrait;
    use CreatedUpdatedAtTrait;
    use CreatorIdTrait;

    #[Attribute\Text(
        defaultValue: 'getToAreaDefault',
        context: ['area:view', 'area:embedded'],
        saveHtml: true,
    )]
    public Item\Text $toarea;

    #[Attribute\Text(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Text $name;

    #[Attribute\Select(
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Select $tipe;

    #[Attribute\Select(
        helper: new HelperGeographyCityController(),
        obligatory: true,
        useInFilters: true,
    )]
    public Item\Select $city;

    #[Attribute\Wysiwyg(
        obligatory: true,
    )]
    public Item\Wysiwyg $content;

    #[Attribute\Multiselect(
        values: 'getHaveGoodValues',
        useInFilters: true,
    )]
    public Item\Multiselect $havegood;

    #[Attribute\Multiselect(
        values: 'getHaveBadValues',
    )]
    public Item\Multiselect $havebad;

    #[Attribute\Text(
        maxChar: 1000,
    )]
    public Item\Text $external_map_link;

    #[Attribute\Wysiwyg]
    public Item\Wysiwyg $way;

    #[Attribute\Wysiwyg(
        context: 'getCoordinatesContext',
    )]
    public Item\Wysiwyg $coordinates;

    #[Attribute\Number(
        context: 'getKogdaigraIdContext',
    )]
    public Item\Number $kogdaigra_id;
}
