<?php

declare(strict_types=1);

namespace App\CMSVC\Trait;

use App\Helper\RightsHelper;
use Fraym\Element\{Attribute, Item};

/** Id проекта объекта */
trait ProjectIdTrait
{
    #[Attribute\Hidden(
        context: [
            ':list',
            ':view',
            ':create',
        ],
    )]
    #[Attribute\OnCreate(callback: 'getProjectId')]
    public Item\Hidden $project_id;

    public function getProjectId(): ?int
    {
        return RightsHelper::getActivatedProjectId();
    }
}
