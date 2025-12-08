<?php

declare(strict_types=1);

namespace App\CMSVC\Search;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Element\{Attribute, Item};
use Fraym\Helper\LocaleHelper;
use Fraym\Interface\Response;

#[Controller(SearchController::class)]
class SearchView extends BaseView
{
    public function Response(): ?Response
    {
        /** @var SearchService $searchService */
        $searchService = $this->service;

        $LOCALE = $this->LOCALE;

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);

        $elementsLocale = LocaleHelper::getLocale(['search', 'fraym_model', 'elements']);

        $searchInput = new Item\Text();
        $attribute = new Attribute\Text();
        $searchInput->setAttribute($attribute);
        $searchInput->name = 'qwerty';
        $searchInput->shownName = $elementsLocale['qwerty']['shownName'] ?? null;
        $searchInput->set($_REQUEST['qwerty'] ?? '');

        $regionsList = new Item\Multiselect();
        $attribute = new Attribute\Multiselect(
            values: $searchService->getRegionsList(),
        );
        $regionsList->setAttribute($attribute);
        $regionsList->name = 'region';
        $regionsList->shownName = $elementsLocale['region']['shownName'] ?? null;

        $RESPONSE_DATA = '<div class="maincontent_data kind_' . KIND . '">
<h1 class="page_header">' . $LOCALE['title'] . '</h1>
<div class="page_blocks margin_top">
<form action="' . ABSOLUTE_PATH . '/search/" method="POST" enctype="multipart/form-data" id="form_' . KIND . '">
' . $searchInput->asHTMLWrapped(null, true, 1);

        $RESPONSE_DATA .= '
' . $regionsList->asHTMLWrapped(null, true, 2) . '
<button class="main">' . $LOCALE['find'] . '</button>
</form>
';

        if ($searchService->checkIfSearch()) {
            $searchResults = $searchService->getSearchResults();

            $RESPONSE_DATA .= '<br><hr><br>';

            if (count($searchResults) > 0) {
                $RESPONSE_DATA .= '<ol class="searchresult">';

                for ($i = 0; $i < count($searchResults); ++$i) {
                    if (is_array($searchResults[$i])) {
                        $RESPONSE_DATA .= '<li>' . $searchResults[$i][0];

                        if (is_array($searchResults[$i][1] ?? false) && $searchResults[$i][1]) {
                            $RESPONSE_DATA .= '<ul>';

                            for ($j = 0; $j < count($searchResults[$i][1]); ++$j) {
                                $RESPONSE_DATA .= '<li>' . $searchResults[$i][1][$j];
                            }
                            $RESPONSE_DATA .= '</ul>';
                        }
                    } else {
                        $RESPONSE_DATA .= '<li>' . $searchResults[$i];
                    }
                }
                $RESPONSE_DATA .= '</ol>';
            } else {
                $RESPONSE_DATA .= '
<b>' . $LOCALE['no_matches'] . '</b>.
';
            }
        }

        $RESPONSE_DATA .= '</div></div>';

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }
}
