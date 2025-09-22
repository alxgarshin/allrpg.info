<?php

declare(strict_types=1);

namespace App\CMSVC\Filterset;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Trait\{ProjectDataTrait};
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Entity\{PostCreate};
use Fraym\Helper\CookieHelper;

/** @extends BaseService<FiltersetModel> */
#[Controller(FiltersetController::class)]
#[PostCreate]
class FiltersetService extends BaseService
{
    use ProjectDataTrait;

    #[DependencyInjection]
    public ApplicationService $applicationService;

    public function postCreate(array $successfulResultsIds): void
    {
        foreach ($successfulResultsIds as $successfulResultsId) {
            $key = array_search($successfulResultsId, $_REQUEST['id']);

            $linkCleared = str_replace(
                ABSOLUTE_PATH . '/application/object=application&action=setFilters&',
                '',
                $_REQUEST['link'][$key],
            );

            DB->update(
                'project_filterset',
                [
                    'link' => $linkCleared,
                ],
                [
                    'id' => $successfulResultsId,
                ],
            );
        }
    }

    public function getNameDefault(): string
    {
        if (($_REQUEST['save'] ?? false) === '1') {
            return CookieHelper::getCookie('filters_name');
        }

        return '';
    }

    public function getLinkDefault(): string
    {
        if (($_REQUEST['save'] ?? false) === '1') {
            return str_replace(
                ABSOLUTE_PATH . '/application/object=application&action=setFilters&',
                '',
                $this->applicationService->getEntity()->getFilters()->getPreparedCurrentFiltersLink('application'),
            );
        }

        return '';
    }
}
