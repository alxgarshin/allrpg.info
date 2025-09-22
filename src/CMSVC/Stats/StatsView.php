<?php

declare(strict_types=1);

namespace App\CMSVC\Stats;

use App\Helper\DesignHelper;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Element\{Item as Item};
use Fraym\Entity\{EntitySortingItem, Filters, Rights, TableEntity};
use Fraym\Enum\ActEnum;
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Response;
use Fraym\Response\HtmlResponse;

/** @extends BaseView<StatsService> */
#[TableEntity(
    'stats',
    '',
    [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
    ],
    null,
    100,
    defaultItemActType: ActEnum::view,
    useCustomView: true,
    useCustomList: true,
)]
#[Rights(
    viewRight: true,
    addRight: false,
    changeRight: false,
    deleteRight: false,
    viewRestrict: '',
    changeRestrict: '',
    deleteRestrict: '',
)]
#[Controller(StatsController::class)]
class StatsView extends BaseView
{
    public function exportToExcel(array $additionalIds): void
    {
        $statsService = $this->getService();

        $statsModel = $statsService->getStatsModel();
        $obj = $statsService->getModelForStatsModel($statsModel);
        $view = $statsService->getViewForStatsModel($statsModel);

        set_time_limit(600);
        ini_set("memory_limit", "500M");

        $elems = $obj->getElements();
        $ids = array_merge($additionalIds, [DataHelper::getId()]);

        //формируем заголовок таблицы
        $RESPONSE_DATA = '<html><head>
<style>
	table,tr,td {
		border: .5pt black solid;
		border-spacing: 0;
		border-collapse: collapse;
	}
	td {
		padding: 5px;
		vertical-align: top;
		width: auto;
	}
	br {mso-data-placement:same-cell;}
</style>
</head><body>
<table><tr>';

        foreach ($elems as $key => $element) {
            if ($element instanceof Item\Password || $element instanceof Item\Tab) {
                //пароли и вкладки не выводим даже
            } elseif ($element instanceof Item\H1) {
                if (isset($elems[$key + 1]) && $elems[$key + 1]->getGroup()) {
                    $RESPONSE_DATA .= '<td><b>' . $element->getShownName() . '</b></td>';
                }
            } elseif ($element->getGroup()) {
                //
            } else {
                $RESPONSE_DATA .= '<td id="col_' . $element->getName() . '"><b>' . $element->getShownName() . '</b></td>';
            }
        }
        $RESPONSE_DATA .= '</tr>';

        $biggestPhotoWidth = [];

        foreach ($ids as $id) {
            if ($id !== '') {
                $RESPONSE_DATA .= '<tr>';

                unset($elemsCount);
                unset($rowData);
                unset($groupCount);

                $data = DB->select(
                    tableName: $view->getEntity()->getTable(),
                    criteria: [
                        'id' => $id,
                    ],
                    oneResult: true,
                );

                $groupCount = [];

                foreach ($elems as $element) {
                    if ($element->getGroup()) {
                        $data[$element->getName()] = preg_replace('/(u[01-9a-fA-F]{4})/', '\\\$1', $data[$element->getName()]);
                        //замена табуляции
                        $data[$element->getName()] = preg_replace('/[\t]/', '\\t', $data[$element->getName()]);
                        $jsonData = json_decode($data[$element->getName()], true);
                        $rowData[$element->getGroup()][$element->getName()] = $jsonData;
                        $elemsCount = count($jsonData);

                        if ($elemsCount > $groupCount[$element->getGroup()]) {
                            $groupCount[$element->getGroup()] = $elemsCount;
                        }

                        if ($groupCount[$element->getGroup()] === 0) {
                            $groupCount[$element->getGroup()] = 1;
                        }
                    }
                }

                $checkGroupCount = [];

                foreach ($elems as $key => $element) {
                    if ($element instanceof Item\Password || $element instanceof Item\Tab) {
                        //пароли и вкладки не выводим даже
                    } elseif ($element instanceof Item\H1) {
                        if (isset($elems[$key + 1]) && $elems[$key + 1]->getGroup()) {
                            $RESPONSE_DATA .= '<td>';
                        }
                    } elseif ($element->getGroup()) {
                        $element->set($data[$element->getName()] ?? null);

                        if ($element->get()) {
                            if (isset($checkGroupCount[$element->getGroup()]) && $checkGroupCount[$element->getGroup()] === $element->getName()) {
                                $RESPONSE_DATA .= '<br>';
                            }
                            $RESPONSE_DATA .= '<i>' . $element->getShownName() . '</i>:<br>';

                            if (isset($rowData[$element->getGroup()][$element->getName()])) {
                                foreach ($rowData[$element->getGroup()][$element->getName()] as $value) {
                                    $RESPONSE_DATA .= $statsService->prepareForExcel($value) . '<br>';
                                }
                            }
                            $RESPONSE_DATA .= '<br>';
                        }

                        if (!isset($checkGroupCount[$element->getGroup()])) {
                            $checkGroupCount[$element->getGroup()] = $element->getName();
                        }
                    } else {
                        if (isset($elems[$key - 1]) && $elems[$key - 1]->getGroup()) {
                            $RESPONSE_DATA .= '</td>';
                        }

                        $element->set($data[$element->getName()] ?? null);

                        if ($element instanceof Item\Timestamp) {
                            $RESPONSE_DATA .= '<td>' . $element->getAsUsualDateTime() . '</td>';
                        } elseif ($element instanceof Item\File) {
                            unset($sizes);

                            preg_match_all('#{([^:]+):([^}]+)}#', $element->get(), $matches);
                            $upload = $_ENV['UPLOADS'][$element->getAttribute()->getUploadNum()];

                            foreach ($matches[0] as $matchKey => $value) {
                                if (file_exists($_ENV['UPLOADS_PATH'] . $upload['path'] . $matches[2][$matchKey]) && ($upload['isimage'] ?? false)) {
                                    $sizes = getimagesize($_ENV['UPLOADS_PATH'] . $upload['path'] . $matches[2][$matchKey]);

                                    if ($sizes[0] > $biggestPhotoWidth[$element->getName()]) {
                                        $biggestPhotoWidth[$element->getName()] = $sizes[0];
                                    }
                                }
                            }

                            if (isset($sizes)) {
                                if ($sizes[1] < 100) {
                                    $sizes[1] = 100;
                                }
                                $RESPONSE_DATA .= '<td height="' . $sizes[1] . '">' . $statsService->prepareForExcel($element->asHTML(false)) . '</td>';
                            } else {
                                $RESPONSE_DATA .= '<td>' . $statsService->prepareForExcel($element->asHTML(false)) . '</td>';
                            }
                        } else {
                            $RESPONSE_DATA .= '<td>' . $statsService->prepareForExcel($element->asHTML(false)) . '</td>';
                        }
                    }
                }

                $RESPONSE_DATA .= '</tr>';
            }
        }

        foreach ($biggestPhotoWidth as $key => $value) {
            $RESPONSE_DATA = preg_replace('#td id="col_' . $key . '"#', 'td id="col_' . $key . '" width="' . $value . '"', $RESPONSE_DATA);
        }

        $RESPONSE_DATA .= '</table></body></html>';

        //выгружаем в виде таблицы
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=data " . date("d.m.Y H-i") . ".xls");
        echo $RESPONSE_DATA;
        exit;
    }

    public function Response(): ?Response
    {
        $types = $this->getService()->getTypesList();
        $LOCALE = $this->getLocale();
        $statsService = $this->getService();

        $PAGETITLE = DesignHelper::changePageHeaderTextToLink($LOCALE['title']);

        if ($statsService->getStatsModel()) {
            $statsModel = $statsService->getStatsModel();

            $model = $statsService->getModelForStatsModel($statsModel);
            $view = $statsService->getViewForStatsModel($statsModel);

            $this->getEntity()->setTable($view->getEntity()->getTable());
            $this->getCMSVC()->setModel($model);

            /** @var HtmlResponse $RESPONSE */
            $RESPONSE = $this->getEntity()->view();
            $RESPONSE_DATA = $RESPONSE->getHtml();

            if (DataHelper::getId()) {
                $RESPONSE_DATA = preg_replace('#</h1>#', '</h1><form id="form_stats">', $RESPONSE_DATA) . '</form>';

                if ($statsModel === 'Profile') {
                    $LOCALE_PEOPLE = LocaleHelper::getLocale(['people', 'global']);

                    $RESPONSE_DATA = preg_replace('#<div class="maincontent_data autocreated kind_' . KIND . ' table_entity view">#', '<div class="maincontent_data autocreated kind_' . KIND . ' table_entity view"><a class="edit_button" href="' . ABSOLUTE_PATH . '/profile/adm_user=' . DataHelper::getId() . '">' . $LOCALE_PEOPLE['edit_profile'] . '</a>', $RESPONSE_DATA);
                }
            } else {
                $LIST_OF_FOUND_IDS = $this->getEntity()->getListOfFoundIds();

                $RESPONSE_DATA = preg_replace('#<div class="indexer_toggle#', '<a href="/' . KIND . '/model=reset" class="ctrlink"><span class="sbi sbi-plus"></span>' . $LOCALE['go_back_to_object_choice'] . '</a><div class="indexer_toggle', $RESPONSE_DATA);

                if (Filters::hasFiltersCookie(mb_lcfirst($statsModel)) && count($LIST_OF_FOUND_IDS) > 0) {
                    $href = '/' . KIND . '/' . $statsModel . '/' . $LIST_OF_FOUND_IDS[0] . '/act=edit&additional_ids=';
                    unset($LIST_OF_FOUND_IDS[0]);
                    $href .= '-' . implode('-', $LIST_OF_FOUND_IDS) . '-';
                    $RESPONSE_DATA = preg_replace('#</table>#', '</table><br><center><a href="' . $href . '&action=exportToExcel" target="_blank">' . $LOCALE['export_to_excel'] . '</a></center>', $RESPONSE_DATA, 1);
                }
            }

            $RESPONSE->setHtml($RESPONSE_DATA);

            $RESPONSE->setPagetitle($PAGETITLE);

            return $RESPONSE;
        } elseif (count($types) > 0) {
            $RESPONSE_DATA = '<div class="maincontent_data autocreated kind_' . KIND . '">
<h1 class="form_header"><a href="/' . KIND . '/">' . $LOCALE['stats'] . '</a></h1>
<div class="page_blocks">
<div class="page_block">
<h2>' . $LOCALE['select_the_object_needed'] . '</h2><ul>';

            foreach ($types as $modelId => $modelName) {
                $RESPONSE_DATA .= '<li><a href="/' . KIND . '/model=' . $modelId . '">' . $modelName . '</a></li>';
            }
            $RESPONSE_DATA .= '</ul></div></div></div>';

            return new HtmlResponse($RESPONSE_DATA, $PAGETITLE);
        }

        return null;
    }
}
