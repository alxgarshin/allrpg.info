<?php

declare(strict_types=1);

namespace App\CMSVC\Csvimport;

use App\CMSVC\Trait\{ProjectDataTrait};
use App\Helper\{DateHelper, RightsHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Element\Item\{Checkbox, Multiselect, Number, Select, Text, Textarea};
use Fraym\Helper\{CookieHelper, DataHelper, ResponseHelper};

#[Controller(CsvimportController::class)]
class CsvimportService extends BaseService
{
    use ProjectDataTrait;

    public string $importCharactersDebugText = '';
    public string $importApplicationsDebugText = '';

    private array $localeDependentFields = [
        'character_main_field' => '',
        'character_groups' => '',
        'character_description' => '',
        'application_character_name' => '',
        'application_status' => '',
        'application_updated' => '',
        'application_created' => '',
        'application_left' => '',
        'application_paid' => '',
        'application_email' => '',
        'application_name' => '',
    ];

    public function init(): static
    {
        if (CookieHelper::getCookie('locale') === 'RU') {
            $this->localeDependentFields = [
                'character_main_field' => 'Персонаж',
                'character_groups' => 'Группы',
                'character_description' => 'Описание',
                'application_character_name' => 'Имя',
                'application_status' => 'Статус',
                'application_updated' => 'Обновлена',
                'application_created' => 'Создана',
                'application_left' => 'Осталось',
                'application_paid' => 'Уплачено',
                'application_email' => 'Игрок.Email',
                'application_name' => 'Имя персонажа',
            ];
        } elseif (CookieHelper::getCookie('locale') === 'EN') {
            $this->localeDependentFields = [
                'character_main_field' => 'Character',
                'character_groups' => 'Groups',
                'character_description' => 'Description',
                'application_character_name' => 'Name',
                'application_status' => 'Status',
                'application_updated' => 'Updated',
                'application_created' => 'Created',
                'application_left' => 'Left',
                'application_paid' => 'Paid',
                'application_email' => 'Email',
                'application_name' => 'Character name',
            ];
        }

        return parent::init();
    }

    /** Импорт персонажей и групп */
    public function importCharacters(): void
    {
        $LOCALE = $this->getLOCALE();

        set_time_limit(600);
        ini_set("memory_limit", "500M");
        $importCharactersDebugText = '';

        if ($_REQUEST['attachments'] !== '') {
            preg_match_all('#{([^:]+):([^}]+)}#', $_REQUEST['attachments'], $matches);
            $filename = $_ENV['UPLOADS_PATH'] . $_ENV['UPLOADS'][4]['path'] . basename($matches[2][0]);

            //конвертируем файл в utf8
            $fileData = file_get_contents($filename);
            $fileData = mb_convert_encoding($fileData, 'utf8', 'cp1251');
            $fileData = preg_replace("#(?<!\r)\n#", "<br>", $fileData);
            $lines = preg_split('/\r\n|\r|\n/', $fileData);
            unset($fileData);

            //разбираем файл на csv-данные
            $rows = array_map(static fn ($row) => str_getcsv($row, ';'), $lines);
            $header = array_shift($rows);
            //проверяем, не называется ли вторая колонка "Персонаж", как и первая
            $unsetDoubledHeader = false;

            if ($header[1] === 'Персонаж' && $header[0] === 'Персонаж') {
                unset($header[1]);
                $unsetDoubledHeader = true;
            }
            $csv = [];

            foreach ($rows as $row) {
                if ($unsetDoubledHeader) {
                    unset($row[1]);
                }
                $csv[] = array_combine($header, $row);
            }

            $localeDependentFields = $this->localeDependentFields;

            //обрабатываем данные
            foreach ($csv as $rowNum => $characterData) {
                if (isset($characterData[$localeDependentFields['character_main_field']]) && trim(
                    $characterData[$localeDependentFields['character_main_field']],
                ) !== '') {
                    $characterPresentCheck = DB->query(
                        "SELECT * FROM project_character WHERE name='" .
                            $characterData[$localeDependentFields['character_main_field']]
                            . "' AND project_id=" . $this->getActivatedProjectId(),
                        [],
                        true,
                    );

                    if ($characterPresentCheck['id'] > 0) {
                        $importCharactersDebugText .= '<div class="csv_data_error">' . sprintf(
                            $LOCALE['messages']['character_already_present'],
                            $rowNum + 2,
                            $characterPresentCheck['id'],
                            $characterData[$localeDependentFields['character_main_field']],
                        ) . '</div>';
                    } else {
                        $groupsNames = '';
                        $groupsIds = [];

                        if (isset($characterData[$localeDependentFields['character_groups']]) && trim(
                            $characterData[$localeDependentFields['character_groups']],
                        ) !== '') {
                            $groupsData = explode(
                                '|',
                                trim($characterData[$localeDependentFields['character_groups']]),
                            );

                            foreach ($groupsData as $groupKey => $groupValue) {
                                $groupName = trim($groupValue);
                                $groupPresentCheck = DB->query(
                                    "SELECT * FROM project_group WHERE name='" . $groupName . "' AND project_id=" . $this->getActivatedProjectId(),
                                    [],
                                    true,
                                );

                                if ($groupPresentCheck['id'] > 0) {
                                    $groupsIds[] = $groupPresentCheck['id'];
                                    $groupId = $groupPresentCheck['id'];
                                } else {
                                    $dataQuery = DB->query(
                                        "INSERT INTO project_group (parent, name, code, content, rights, project_id, responsible_gamemaster_id, last_update_user_id, created_at, updated_at) VALUES (0, '" . $groupName . "', 0, '{menu}', 0, " . $this->getActivatedProjectId() . ", " . CURRENT_USER->id() . ", " . CURRENT_USER->id() . ", " . time() . ", " . time() . ")",
                                        [],
                                    );
                                    $groupId = DB->lastInsertId();
                                    $groupsIds[] = $groupId;
                                }
                                $groupsNames .= '<a href="/group/' . $groupId . '/" target="_blank">' . DataHelper::escapeOutput(
                                    $groupName,
                                ) . '</a>, ';
                            }
                            $groupsNames = mb_substr($groupsNames, 0, mb_strlen($groupsNames) - 2);
                        }

                        $characterDescription =
                            trim(
                                preg_replace(
                                    '#<br>#',
                                    '
',
                                    $characterData[$localeDependentFields['character_description']],
                                ),
                            );

                        DB->insert(
                            tableName: 'project_character',
                            data: [
                                'project_group_ids' => DataHelper::arrayToMultiselect($groupsIds),
                                'setparentgroups' => '0',
                                'team_character' => '0',
                                'name' => trim($characterData[$localeDependentFields['character_main_field']]),
                                'applications_needed_count' => 1,
                                'auto_new_character_creation' => '1',
                                'content' => $characterDescription,
                                'project_id' => $this->getActivatedProjectId(),
                                'last_update_user_id' => CURRENT_USER->id(),
                                'created_at' => DateHelper::getNow(),
                                'updated_at' => DateHelper::getNow(),
                            ],
                        );

                        $characterId = DB->lastInsertId();

                        if ($characterId > 0) {
                            foreach ($groupsIds as $characterGroup) {
                                $code = 1;
                                $lastCharacterInGroup = DB->query(
                                    "SELECT * FROM relation WHERE obj_type_from='{character}' AND obj_type_to='{group}' AND type='{member}' AND obj_id_to=" . $characterGroup . " ORDER BY comment DESC LIMIT 1",
                                    [],
                                    true,
                                );

                                if ((int) $lastCharacterInGroup['comment'] > 0) {
                                    $code = (int) $lastCharacterInGroup['comment'] + 1;
                                }
                                RightsHelper::addRights(
                                    '{member}',
                                    '{group}',
                                    $characterGroup,
                                    '{character}',
                                    $characterId,
                                    (string) $code,
                                );
                            }
                        }

                        $importCharactersDebugText .= '<div class="csv_data_success"><a href="/character/' . $characterId . '/" target="_blank">' . $characterData[$localeDependentFields['character_main_field']] . '</a>' . ($groupsNames !== '' ? ' (' . $groupsNames . ')' : '') . '</div>';
                    }
                } elseif (is_array($characterData) && count($characterData) > 0) {
                    $importCharactersDebugText .= '<div class="csv_data_error">' . sprintf(
                        $LOCALE['messages']['character_no_name'],
                        $rowNum + 2,
                    ) . '</div>';
                }
            }

            unlink($filename);

            ResponseHelper::success($LOCALE['messages']['import_success']);
        } else {
            $importCharactersDebugText .= '<div class="csv_data_error">' . $LOCALE['messages']['upload_error'] . '</div>';
        }

        $this->importCharactersDebugText = $importCharactersDebugText;
    }

    /** Импорт заявок */
    public function importApplications(): void
    {
        $LOCALE = $this->getLOCALE();

        set_time_limit(600);
        ini_set("memory_limit", "500M");
        $importApplicationsDebugText = '';

        if ($_REQUEST['attachments'] !== '') {
            preg_match_all('#{([^:]+):([^}]+)}#', $_REQUEST['attachments'], $matches);
            $filename = $_ENV['UPLOADS_PATH'] . $_ENV['UPLOADS'][4]['path'] . basename($matches[2][0]);

            //конвертируем файл в utf8
            $fileData = file_get_contents($filename);
            $fileData = mb_convert_encoding($fileData, 'utf8', 'cp1251');
            $fileData = preg_replace("#(?<!\r)\n#", "<br>", $fileData);
            $lines = preg_split('/\r\n|\r|\n/', $fileData);
            unset($fileData);

            //разбираем файл на csv-данные
            $rows = array_map(static fn ($row) => str_getcsv($row, ';'), $lines);
            $header = array_shift($rows);
            $csv = [];

            foreach ($rows as $row) {
                $csv[] = array_combine($header, $row);
            }

            //собираем все поля заявок
            $applicationFields = DataHelper::virtualStructure(
                "SELECT * FROM project_application_field WHERE project_id=:project_id AND application_type='0' ORDER BY field_code",
                [
                    ['project_id', $this->getActivatedProjectId()],
                ],
                "field_",
            );

            $localeDependentFields = $this->localeDependentFields;

            //обрабатываем данные
            foreach ($csv as $rowNum => $applicationData) {
                if (isset($applicationData[$localeDependentFields['application_name']]) && trim(
                    $applicationData[$localeDependentFields['application_name']],
                ) !== '') {
                    $applicationPresentCheck = DB->query(
                        "SELECT * FROM project_application WHERE sorter='" .
                            $applicationData[$localeDependentFields['application_name']]
                            . "' AND project_id=" . $this->getActivatedProjectId(),
                        [],
                        true,
                    );

                    if ($applicationPresentCheck['id'] > 0) {
                        $importApplicationsDebugText .= '<div class="csv_data_error">' . sprintf(
                            $LOCALE['messages']['application_already_present'],
                            $rowNum + 2,
                            $applicationPresentCheck['id'],
                            $applicationData[$localeDependentFields['application_name']],
                        ) . '</div>';
                    } else {
                        $groupsNames = '';
                        $groupsIds = [];
                        $characterId = 0;

                        if (isset($applicationData[$localeDependentFields['application_character_name']]) && trim(
                            $applicationData[$localeDependentFields['application_character_name']],
                        ) !== '') {
                            $characterPresentCheck = DB->query(
                                "SELECT * FROM project_character WHERE name='" .
                                    trim(
                                        $applicationData[$localeDependentFields['application_character_name']],
                                    ) . "' AND project_id=" . $this->getActivatedProjectId(),
                                [],
                                true,
                            );

                            if ($characterPresentCheck['id'] > 0) {
                                $characterId = $characterPresentCheck['id'];
                                $groupsIds = DataHelper::multiselectToArray($characterPresentCheck['project_group_ids']);

                                foreach ($groupsIds as $groupIndex => $groupId) {
                                    $groupPresentCheck = DB->query(
                                        "SELECT * FROM project_group WHERE id=" . $groupId . " AND project_id=" . $this->getActivatedProjectId(),
                                        [],
                                        true,
                                    );

                                    if ($groupPresentCheck['id'] > 0) {
                                        $groupsNames .= '<a href="/group/' . $groupPresentCheck['id'] . '/" target="_blank">' . DataHelper::escapeOutput(
                                            $groupPresentCheck['name'],
                                        ) . '</a>, ';
                                    } else {
                                        unset($groupsIds[$groupIndex]);
                                    }
                                }
                                $groupsNames = mb_substr($groupsNames, 0, mb_strlen($groupsNames) - 2);
                            }
                        }

                        $allinfo = '';

                        foreach ($applicationFields as $applicationField) {
                            $sname = $applicationField->getShownName();
                            $csvValue = trim($applicationData[$sname]);

                            if ($csvValue !== '') {
                                $allinfoAddData = '';

                                if ($applicationField instanceof Text || $applicationField instanceof Textarea) {
                                    if ($applicationField instanceof Textarea) {
                                        $csvValue = preg_replace(
                                            '#<br>#',
                                            '
',
                                            $csvValue,
                                        );
                                    }
                                    $allinfoAddData = $csvValue;
                                } elseif ($applicationField instanceof Select) {
                                    $selectValue = '';

                                    foreach ($applicationField->getAttribute()->getValues() as $fieldValue) {
                                        if (mb_strtolower($fieldValue[1]) === mb_strtolower($csvValue)) {
                                            $selectValue = $fieldValue[0];
                                            break;
                                        }
                                    }
                                    $allinfoAddData = $selectValue;
                                } elseif ($applicationField instanceof Multiselect) {
                                    $multiselectIds = [];
                                    $csvValueArray = explode(',', $csvValue);

                                    foreach ($csvValueArray as $csvValueArrayKey => $csvValueArrayValue) {
                                        $csvValueArray[$csvValueArrayKey] = trim($csvValueArrayValue);
                                    }

                                    foreach ($applicationField->getAttribute()->getValues() as $fieldValue) {
                                        foreach ($csvValueArray as $csvValueArrayValue) {
                                            if (mb_strtolower($fieldValue[1]) === mb_strtolower($csvValueArrayValue)) {
                                                $multiselectIds[] = $fieldValue[0];
                                            }
                                        }
                                    }
                                    $allinfoAddData = (count($multiselectIds) > 0 ? DataHelper::arrayToMultiselect(
                                        $multiselectIds,
                                    ) : '');
                                } elseif ($applicationField instanceof Number) {
                                    $allinfoAddData = (int) $csvValue;
                                } elseif ($applicationField instanceof Checkbox) {
                                    $allinfoAddData = ($csvValue === '??' ? '1' : '0');
                                }

                                if ($allinfoAddData !== '') {
                                    $allinfo .= '[' . $applicationField->getName() . '][' . $allinfoAddData . ']' . chr(13) . chr(10);
                                }
                            }
                        }

                        $offerToUserId = 0;

                        if (trim($applicationData[$localeDependentFields['application_email']]) !== '') {
                            $checkUserOnAllrpg = DB->select(
                                tableName: 'user',
                                criteria: [
                                    'em' => trim($applicationData[$localeDependentFields['application_email']]),
                                ],
                                oneResult: true,
                            );

                            if ($checkUserOnAllrpg['id'] > 0 && $checkUserOnAllrpg['id'] !== CURRENT_USER->id()) {
                                $offerToUserId = $checkUserOnAllrpg['id'];
                            }
                        }

                        $money = 0;
                        $moneyProvided = 0;

                        if ((int) trim($applicationData[$localeDependentFields['application_paid']]) > 0 || (int) trim($applicationData[$localeDependentFields['application_left']]) > 0) {
                            $moneyProvided = (int) trim($applicationData[$localeDependentFields['application_paid']]);
                            $money = (int) trim($applicationData[$localeDependentFields['application_paid']]) + (int) trim(
                                $applicationData[$localeDependentFields['application_left']],
                            );
                        }

                        $projectFeeId = '';
                        /* если у нас всего одна опция взноса настроена, выставляем ее в заявку */
                        $feeOptionsData = DB->select(
                            tableName: 'project_fee',
                            criteria: [
                                'project_id' => $this->getActivatedProjectId(),
                                'content' => '{menu}',
                            ],
                        );

                        if (count($feeOptionsData) === 1) {
                            $feeOptionData = $feeOptionsData[0];
                            $feeOptionDateData = DB->query(
                                "SELECT * FROM project_fee WHERE parent=:parent AND date_from <= CURDATE() ORDER BY date_from DESC LIMIT 1",
                                [
                                    ['parent', $feeOptionData['id']],
                                ],
                                true,
                            );
                            $projectFeeId = $feeOptionDateData['id'];

                            /* если вдруг размер взноса из csv не равен взносу опции, то фиксируем опцию с измененным названием */
                            if ($feeOptionDateData['cost'] !== $money) {
                                $projectFeeId .= '.1';
                            }
                        }

                        $status = 1;
                        $statusName = mb_strtolower(
                            trim($applicationData[$localeDependentFields['application_status']]),
                        );

                        foreach ($LOCALE['models']['application']['elements']['status']['values'] as $statusArray) {
                            if ($statusName === $statusArray[1]) {
                                $status = $statusArray[0];
                                break;
                            }
                        }

                        DB->insert(
                            tableName: 'project_application',
                            data: [
                                'project_id' => $this->getActivatedProjectId(),
                                'creator_id' => CURRENT_USER->id(),
                                'offer_to_user_id' => $offerToUserId,
                                'offer_denied' => '0',
                                'team_application' => '0',
                                'project_character_id' => $characterId,
                                'money' => $money,
                                'money_provided' => $moneyProvided,
                                'money_paid' => ($money > 0 && $money <= $moneyProvided),
                                'project_fee_ids' => ($projectFeeId !== '' ? "'-" . $projectFeeId . "-'" : null),
                                'sorter' => trim($applicationData[$localeDependentFields['application_name']]),
                                'project_group_ids' => DataHelper::arrayToMultiselect($groupsIds),
                                'allinfo' => $allinfo,
                                'status' => $status,
                                'signtochange' => '1',
                                'signtocomments' => '1',
                                'responsible_gamemaster_id' => CURRENT_USER->id(),
                                'last_update_user_id' => CURRENT_USER->id(),
                                'created_at' => (trim($applicationData[$localeDependentFields['application_created']]) !== '' ? strtotime(trim($applicationData[$localeDependentFields['application_created']])) : DateHelper::getNow()),
                                'updated_at' => (trim($applicationData[$localeDependentFields['application_updated']]) !== '' ? strtotime(trim($applicationData[$localeDependentFields['application_updated']])) : DateHelper::getNow()),
                            ],
                        );
                        $applicationId = DB->lastInsertId();

                        if ($applicationId > 0) {
                            foreach ($groupsIds as $applicationGroup) {
                                RightsHelper::addRights(
                                    '{member}',
                                    '{group}',
                                    $applicationGroup,
                                    '{application}',
                                    $applicationId,
                                );
                            }
                        }

                        $importApplicationsDebugText .= '<div class="csv_data_success"><a href="/application/' . $applicationId . '/" target="_blank">' . $applicationData[$localeDependentFields['application_name']] . '</a>' . ($groupsNames !== '' ? ' (' . $groupsNames . ')' : '') . '</div>';
                    }
                } elseif (is_array($applicationData) && count($applicationData) > 0) {
                    $importApplicationsDebugText .= '<div class="csv_data_error">' . sprintf(
                        $LOCALE['messages']['application_no_name'],
                        $rowNum + 2,
                    ) . '</div>';
                }
            }

            unlink($filename);

            ResponseHelper::success($LOCALE['messages']['import_success']);
        } else {
            $importApplicationsDebugText .= '<div class="csv_data_error">' . $LOCALE['messages']['upload_error'] . '</div>';
        }

        $this->importApplicationsDebugText = $importApplicationsDebugText;
    }
}
