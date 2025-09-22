<?php

declare(strict_types=1);

namespace App\CMSVC\Registration;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Trait\{ProjectDataTrait, UserServiceTrait};
use App\CMSVC\Transaction\TransactionService;
use App\Helper\{DateHelper, RightsHelper};
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};

#[Controller(RegistrationController::class)]
class RegistrationService extends BaseService
{
    use ProjectDataTrait;
    use UserServiceTrait;

    #[DependencyInjection]
    public ApplicationService $applicationService;

    private ?bool $canSetFee = null;

    public function getCanSetFee(): bool
    {
        if (is_null($this->canSetFee)) {
            $this->canSetFee = RightsHelper::checkAllowProjectActions(PROJECT_RIGHTS, ['{gamemaster}', '{fee}']);
        }

        return $this->canSetFee;
    }

    /** Получение информации по игроку / заявке на регистрации */
    public function getRegistrationPlayer(string $objName): array
    {
        $LOCALE_REGISTRATION = LocaleHelper::getLocale(['registration', 'global']);

        $objName = mb_strtolower($objName);
        $responseData = '';

        $checkForRooms = false;

        if ($this->getActivatedProjectId() > 0) {
            $checkForRooms = DB->select(
                tableName: 'project_room',
                criteria: [
                    'project_id' => $this->getActivatedProjectId(),
                ],
                oneResult: true,
            );
            $checkForRooms = ($checkForRooms['id'] ?? 0) > 0;
        }

        $responseData .= '<table class="menutable registration"><thead><tr class="menu"><th id="th_header_application">' . $LOCALE_REGISTRATION['th_header_application'] . '</th><th id="th_header_money">' . $LOCALE_REGISTRATION['th_header_money'] . '</th><th id="th_header_eco_money">' . $LOCALE_REGISTRATION['th_header_eco_money'] . '</th><th id="th_header_comments">' . $LOCALE_REGISTRATION['th_header_comments'] . '</th><th id="th_header_distributed_ids">' . $LOCALE_REGISTRATION['th_header_distributed_ids'] . '</th>' . ($checkForRooms ? '<th id="th_header_rooms_data">' . $LOCALE_REGISTRATION['th_header_rooms_data'] . '</th>' : '') . '<th id="th_header_register">' . $LOCALE_REGISTRATION['th_header_register'] . '</th></tr></thead><tbody>';

        if ($objName !== '') {
            $result = DB->query(
                "SELECT
				pa.id AS project_application_id,
				pa.sorter AS project_application_sorter,
				pa.money AS project_application_money,
				pa.money_paid AS project_application_money_paid,
				pa.money_provided AS project_application_money_provided,
				pa.player_registered AS project_application_player_registered,
				pa.eco_money_paid AS project_application_eco_money_paid,
				pa.registration_comments AS project_application_registration_comments,
				pa.distributed_item_ids AS project_application_distributed_item_ids,
				pr.id AS project_room_id,
				pr.name AS project_room_name,
				u.*
			FROM
				project_application AS pa LEFT JOIN
				user AS u ON u.id = pa.creator_id LEFT JOIN
				relation AS r ON r.obj_id_from = pa.id AND
				r.obj_type_from = '{application}' AND
				r.obj_type_to = '{room}' LEFT JOIN
				project_room AS pr ON pr.id = r.obj_id_to
			WHERE
				pa.project_id = :project_id AND
				(
                    LOWER(pa.sorter) LIKE :search_string_1 OR
                    LOWER(u.fio) LIKE :search_string_2 OR
                    LOWER(u.nick) LIKE :search_string_3" . (is_numeric($objName) ? ' OR u.sid = :sid' : '') . "
				) AND
				pa.status != 4 AND
				pa.deleted_by_player = '0' AND
				pa.deleted_by_gamemaster = '0'
			ORDER BY
				pa.sorter",
                [
                    ['project_id', $this->getActivatedProjectId()],
                    ['sid', $objName],
                    ['search_string_1', '%' . $objName . '%'],
                    ['search_string_2', '%' . $objName . '%'],
                    ['search_string_3', '%' . $objName . '%'],
                ],
            );
            $i = 0;

            foreach ($result as $applicationData) {
                $responseData .= '<tr class="string' . ($i % 2 === 0 ? '1' : '2') . '">';

                $responseData .= '<td><a href="/application/' . $applicationData['project_application_id'] . '/" target="_blank">' . $applicationData['project_application_sorter'] . '</a><br><span class="small">' . preg_replace(
                    '#<a#',
                    '<a target="_blank"',
                    $this->getUserService()->showNameWithId($this->getUserService()->arrayToModel($applicationData), true),
                ) . '</span></td>';
                $responseData .= '<td>' . ($applicationData['project_application_money_paid'] === '1' ? '<span class="sbi sbi-check"></span>' : ($this->getCanSetFee() ? '<a id="set_registration_player_money" obj_id="' . $applicationData['project_application_id'] . '"><span class="sbi sbi-times"></span></a>' : '<span class="sbi sbi-times"></span>')) . ' ' . (int) $applicationData['project_application_money_provided'] . ' / ' . (int) $applicationData['project_application_money'] . '</td>';
                $responseData .= '<td>' . ($applicationData['project_application_eco_money_paid'] === '1' ? '<span class="sbi sbi-check"></span>' : ($this->getCanSetFee() ? '<a id="set_registration_eco_money" obj_id="' . $applicationData['project_application_id'] . '"><span class="sbi sbi-times"></span></a>' : '<span class="sbi sbi-times"></span>')) . '</td>';
                $responseData .= '<td><textarea id="player_registration_comments" obj_id="' . $applicationData['project_application_id'] . '">' . DataHelper::escapeOutput(
                    $applicationData['project_application_registration_comments'],
                ) . '</textarea></td>';
                $responseData .= '<td>';

                if ($applicationData['project_application_distributed_item_ids'] !== '' && $applicationData['project_application_distributed_item_ids'] !== '-') {
                    $projectApplicationDistributedItemIds = DataHelper::multiselectToArray(
                        $applicationData['project_application_distributed_item_ids'],
                    );

                    if (count($projectApplicationDistributedItemIds) > 0) {
                        $disributedItemsData = DB->select(
                            tableName: 'resource',
                            criteria: [
                                'distributed_item' => '1',
                                'id' => $projectApplicationDistributedItemIds,
                            ],
                        );

                        foreach ($disributedItemsData as $distributedItemData) {
                            $responseData .= DataHelper::escapeOutput($distributedItemData['name']) . '<br>';
                        }
                    }
                }
                $responseData .= '</td>';

                if ($checkForRooms) {
                    $responseData .= '<td><a href="/rooms/' . $applicationData['project_room_id'] . '/" target="_blank">' . $applicationData['project_room_name'] . '</a></td>';
                }
                $responseData .= '<td>' . ($applicationData['project_application_player_registered'] === '1' ? '<span class="sbi sbi-check"></span>' : '<a id="set_registration_player" obj_id="' . $applicationData['project_application_id'] . '">' . $LOCALE_REGISTRATION['th_header_register'] . '</a>') . '</td>';

                $responseData .= '</tr>';

                ++$i;
            }
        }

        $responseData .= '</tbody></table>';

        return [
            'response' => 'success',
            'response_data' => $responseData,
        ];
    }

    /** Выставление признака зарегистрированности в заявке на регистрации */
    public function setRegistrationPlayer(int $objId): array
    {
        DB->update(
            tableName: 'project_application',
            data: [
                'player_registered' => '1',
            ],
            criteria: [
                'id' => $objId,
                'project_id' => $this->getActivatedProjectId(),
            ],
        );

        return [
            'response' => 'success',
        ];
    }

    /** Выставление взноса в заявке на регистрации */
    public function setRegistrationPlayerMoney(int $objId): array
    {
        $LOCALE_MYAPPLICATION = LocaleHelper::getLocale(['myapplication', 'global']);
        $LOCALE_REGISTRATION = LocaleHelper::getLocale(['registration', 'global']);

        $returnArr = [];

        if ($this->getCanSetFee()) {
            $GLOBALS['kind'] = 'application'; // чтобы в именах пользователей показывались все возможные данные

            $applicationData = $this->applicationService->get($objId);

            if ($applicationData && $applicationData->project_id->getAsInt() === $this->getActivatedProjectId()) {
                /* проверить, существует ли на данного мастера метод оплаты: "Регистрация: такой-то" (registration_type=1 с отвественным мастером). Если нет, создать $LOCALE_REGISTRATION['payment_type_name'] . usname */
                $checkPaymentType = DB->select(
                    tableName: 'project_payment_type',
                    criteria: [
                        'project_id' => $this->getActivatedProjectId(),
                        'registration_type' => '1',
                        'user_id' => CURRENT_USER->id(),
                    ],
                    oneResult: true,
                );

                if ($checkPaymentType) {
                    $selectedPaymentTypeId = $checkPaymentType['id'];
                } else {
                    $name = $LOCALE_REGISTRATION['payment_type_name'] . $this->getUserService()->showNameWithId($this->getUserService()->get(CURRENT_USER->id()));
                    DB->insert(
                        tableName: 'project_payment_type',
                        data: [
                            'creator_id' => CURRENT_USER->id(),
                            'project_id' => $this->getActivatedProjectId(),
                            'name' => $name,
                            'user_id' => CURRENT_USER->id(),
                            'registration_type' => '1',
                            'created_at' => DateHelper::getNow(),
                            'updated_at' => DateHelper::getNow(),
                        ],
                    );
                    $selectedPaymentTypeId = DB->lastInsertId();
                }

                /* создать верифицированную транзакцию и комментарий в заявке на оплату */
                /** @var TransactionService */
                $transactionService = CMSVCHelper::getService('transaction');

                $transactionService->createTransaction(
                    $this->getActivatedProjectId(),
                    $applicationData->id->getAsInt(),
                    $selectedPaymentTypeId,
                    $LOCALE_MYAPPLICATION['fee_payment'],
                    $applicationData->money->get() - $applicationData->money_provided->get(),
                    true,
                );

                $returnArr = [
                    'response' => 'success',
                ];
            }
        }

        return $returnArr;
    }

    /** Выставление эко-взноса в заявке на регистрации */
    public function setRegistrationEcoMoney(int $objId): array
    {
        $returnArr = [];

        if ($this->getCanSetFee()) {
            $applicationData = $this->applicationService->get($objId);

            if ($applicationData && $applicationData->project_id->getAsInt() === $this->getActivatedProjectId()) {
                DB->update(
                    tableName: 'project_application',
                    data: [
                        'eco_money_paid' => '1',
                    ],
                    criteria: [
                        'id' => $applicationData->id->getAsInt(),
                    ],
                );

                $returnArr = [
                    'response' => 'success',
                ];
            }
        }

        return $returnArr;
    }

    /** Написание комментария на регистрации к заявке */
    public function setRegistrationComments(int $objId, string $value): array
    {
        $returnArr = [];

        $applicationData = $this->applicationService->get($objId);

        if ($applicationData && (int) $applicationData->project_id->getAsInt() === $this->getActivatedProjectId()) {
            DB->update(
                tableName: 'project_application',
                data: [
                    'registration_comments' => $value,
                ],
                criteria: [
                    'id' => $objId,
                ],
            );

            $returnArr = [
                'response' => 'success',
            ];
        }

        return $returnArr;
    }
}
