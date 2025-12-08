<?php

declare(strict_types=1);

namespace App\CMSVC\Document;

use App\CMSVC\Trait\ProjectSectionsPostViewHandlerTrait;
use Fraym\BaseObject\{BaseView, Controller};
use Fraym\Entity\{EntitySortingItem, Rights, TableEntity};
use Fraym\Helper\DataHelper;
use Fraym\Interface\Response;

/** @extends BaseView<DocumentService> */
#[TableEntity(
    name: 'document',
    table: 'document',
    sortingData: [
        new EntitySortingItem(
            tableFieldName: 'name',
        ),
    ],
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
#[Controller(DocumentController::class)]
class DocumentView extends BaseView
{
    use ProjectSectionsPostViewHandlerTrait;

    public function additionalPostViewHandler(string $RESPONSE_DATA): string
    {
        if (DataHelper::getId() > 0) {
            $LOCALE = $this->getLOCALE();

            $listOfRoles = $this->getService()->getListOfRolesElem();

            $content = '<br>
<form action="/document/" method="POST" enctype="multipart/form-data" id="form_generate_documents" no_dynamic_content target="_blank">
<input type="hidden" name="kind" value="document">
<input type="hidden" name="action" value="generate_documents">
<input type="hidden" name="template_id" value="' . DataHelper::getId() . '">
<div class="field" id="field_application_id[0]"><div class="fieldname" id="name_application_id[0]" tabindex="1">' . $LOCALE['list_of_roles_name'] . '</div><div class="fieldvalue" id="div_application_id[0]">' . $listOfRoles->asHTML(true) . '</div></div>
<button class="main">' . $LOCALE['generate_documents'] . '</button>
</form>';
            $RESPONSE_DATA = preg_replace('#</form>#', '</form>' . $content, $RESPONSE_DATA);
        }

        return $RESPONSE_DATA;
    }

    public function Response(): ?Response
    {
        return null;
    }

    public function generateDocuments(): void
    {
        $RESPONSE_DATA = '';

        /** ПРОБЛЕМЫ:
         * 1) почему-то при обновлении страницы мы не остаемся в результате перенесения шаблона, а попадаем на список шаблонов
         * 2) /document/template_id=151&action=generate_documents&application_id[0][52092]=on
         * 3) /document/template_id=151&action=generate_documents&application_id[0]=filter
         */

        /** Генерируем документы на основе шаблона */
        $templateData = $this->getService()->get(
            id: (int) $_REQUEST['template_id'],
            criteria: [
                'project_id' => $this->getService()->getActivatedProjectId(),
            ],
        );

        $RESPONSE_DATA = '<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
	p.pagebreak {
		page-break-before: always;
	}
	span#qrpg_key {
	display: block;
	}
	span#qrpg_key img:first-of-type:before {
	    content: " ";
	    display: block;
	}
	span#qrpg_key img {
	    max-width: 3em;
	    vertical-align: middle;
	}
</style>
';

        if ($templateData->outer_css->get()) {
            $RESPONSE_DATA .= '
<link rel="stylesheet" type="text/css" href="' . DataHelper::escapeOutput($templateData->outer_css->get()) . '">';
        }
        $RESPONSE_DATA .= '
</head>

<body>';

        if ($templateData->content->get()) {
            $fullApplicationsData = $this->getService()->fullApplicationsData;
            $fields = $this->getService()->fields;
            $fieldsShowIf = $this->getService()->fieldsShowIf;
            $plotService = $this->getService()->plotService;

            $templateContent = $templateData->content->get();

            foreach ($fullApplicationsData as $applicationRequestedId => $data) {
                $applicationRequestedId = $data['project_application_id'];
                $document = $templateContent;

                foreach ($fields as $field) {
                    if (preg_match('#\[' . $field->getShownName() . '\]#', $document)) {
                        if ($field->getName() === 'plots_data') {
                            $field->set($plotService->generateAllPlots($this->getService()->getActivatedProjectId(), '{application}', $applicationRequestedId, true));
                        } else {
                            $fieldData = $fullApplicationsData[$applicationRequestedId][$field->getName()] ?? null;

                            if ($fieldData) {
                                /** @phpstan-ignore-next-line */
                                $field->set($fieldData);
                            }
                        }

                        /** Проверка наличия условий по полям */
                        $changeToValue = true;

                        if (isset($fieldsShowIf[$field->getName()])) {
                            $changeToValue = false;

                            $showConditions = $fieldsShowIf[$field->getName()];

                            unset($matches);
                            preg_match_all('#-(\d+):(\d+)#', $showConditions, $matches);

                            foreach ($matches[1] as $key => $value) {
                                if ($fullApplicationsData[$applicationRequestedId]['virtual' . $value] === $matches[2][$key] || preg_match(
                                    '#-' . $matches[2][$key] . '-#',
                                    ($fullApplicationsData[$applicationRequestedId]['virtual' . $value] ?? ''),
                                )) {
                                    $changeToValue = true;
                                }
                            }

                            unset($matches);
                            preg_match_all('#-locat:(\d+)#', $showConditions, $matches);

                            foreach ($matches[1] as $key => $value) {
                                if (preg_match('#-' . $value . '-#', $fullApplicationsData[$applicationRequestedId]['project_group_ids'])) {
                                    $changeToValue = true;
                                }
                            }
                        }

                        $document = preg_replace(
                            '#\[' . $field->getShownName() . '\]#',
                            ($changeToValue ? '<span id="' . $field->getName() . '">' . $field->asHTML(false) . '</span>' : ''),
                            $document,
                        );
                    }
                }
                $RESPONSE_DATA .= $document;
            }
        }

        $RESPONSE_DATA .= '</body>
</html>';

        echo($RESPONSE_DATA);
        exit;
    }
}
