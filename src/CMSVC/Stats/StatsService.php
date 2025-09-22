<?php

declare(strict_types=1);

namespace App\CMSVC\Stats;

use App\CMSVC\Trait\UserServiceTrait;
use App\Helper\DateHelper;
use Fraym\BaseObject\{BaseModel, BaseService, BaseView, Controller};
use Fraym\Element\{Item as Item};
use Fraym\Entity\{EntitySortingItem, Filters};
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper, LocaleHelper};

#[Controller(StatsController::class)]
class StatsService extends BaseService
{
    use UserServiceTrait;

    public function getTypesList(): array
    {
        $LOCALE = $this->getLOCALE();

        $types = [];

        if (CURRENT_USER->isAdmin() || $this->userService->isModerator()) {
            foreach ($LOCALE['types'] as $type) {
                $types[DataHelper::clearBraces($type[0])] = $type[1];
            }
        }

        return $types;
    }

    public function preLoadModel(): void
    {
        if ($this->getStatsModel()) {
            $statsModel = $this->getStatsModel();

            $model = $this->getModelForStatsModel($statsModel);
            $view = $this->getViewForStatsModel($statsModel);

            $this->getEntity()->setTable($view->getEntity()->getTable());
            $this->getCMSVC()->setModel($model);
        }
    }

    public function getStatsModel(): ?string
    {
        $model = $_GET['model'] ?? null;
        $cookieModel = CookieHelper::getCookie('stats_model');

        if (!$model && $cookieModel) {
            $model = $cookieModel;
        } elseif ($model === 'reset' || ($model !== null && !($this->getTypesList()[$model] ?? false))) {
            CookieHelper::batchDeleteCookie(['stats_model']);
            $model = null;
        } else {
            CookieHelper::batchSetCookie([
                'stats_model' => $model,
            ]);
        }

        return $model;
    }

    public function getViewForStatsModel(string $model): BaseView
    {
        $view = CMSVCHelper::getView($model);

        $this->getEntity()->setName(mb_lcfirst($model));

        return $view;
    }

    public function getModelForStatsModel(string $model): BaseModel
    {
        if ($model === 'User') {
            $this->getEntity()->setSortingData([
                new EntitySortingItem(
                    tableFieldName: 'fio',
                ),
            ]);
        }

        $obj = CMSVCHelper::getModel($model);

        $obj->removeElement('creator_id');
        $obj->removeElement('edit');
        $obj->removeElement('through_social_network');
        $obj->removeElement('or_by_inputting_data');

        foreach ($obj->getElements() as $element) {
            $element->getAttribute()->setContext([
                ':list',
                ':view',
            ]);

            if (
                !($element->getNoData() && $element instanceof Item\Calendar) &&
                !($element->getNoData() && !$element->getGroup()) &&
                !$element instanceof Item\File &&
                $element->getShownName()
            ) {
                $element->getAttribute()->setUseInFilters(true);
            }
        }

        return $obj;
    }

    public function prepareForExcel(string $line): string
    {
        $line = str_replace('<span class="sbi sbi-times"></span>', "-", $line);
        $line = str_replace('<span class="sbi sbi-check"></span>', "+", $line);
        $line = strip_tags($line, '<br><b><i>');

        return $line;
    }

    /** Добавление дополнительных прав */
    public function setAdditionalGroups(int $objId): array
    {
        $LOCALE = LocaleHelper::getLocale(['stats', 'global']);

        $usersData = DB->query(
            'SELECT t1.* FROM user t1' . $this->getEntity()->setName('profile')->getFilters()->getPreparedSearchQuerySql(),
            $this->getEntity()->setName('profile')->getFilters()->getPreparedSearchQueryParams(),
        );

        foreach ($usersData as $userData) {
            $userData = $this->getUserService()->arrayToModel($userData);
            $curGroups = $userData->rights->get();

            if (!in_array($objId, $curGroups)) {
                $curGroups[] = $objId;
            }
            DB->update(
                $this->getTable(),
                [
                    ['rights', DataHelper::arrayToMultiselect($curGroups)],
                    ['updated_at', 'time'],
                ],
                [],
            );
            DB->update(
                tableName: 'user',
                data: [
                    'program_additional_groups' => DataHelper::arrayToMultiselect($curGroups),
                    'updated_at' => DateHelper::getNow(),
                ],
                criteria: [
                    'id' => $userData->id->getAsInt(),
                ],
            );
        }

        return [
            'response' => 'success',
            'response_text' => $LOCALE['messages']['set_additional_groups'],
        ];
    }

    /** Выставление статуса пользователям */
    public function setStatus(int $objId): array
    {
        $LOCALE = LocaleHelper::getLocale(['stats', 'global', 'messages']);

        $returnArr = [];

        $listOfIds = [];

        $usersData = DB->query(
            'SELECT t1.* FROM user t1' . $this->getEntity()->setName('profile')->getFilters()->getPreparedSearchQuerySql(),
            $this->getEntity()->setName('profile')->getFilters()->getPreparedSearchQueryParams(),
        );

        foreach ($usersData as $userData) {
            $userData = $this->getUserService()->arrayToModel($userData);
            $listOfIds[] = $userData->id->getAsInt();
        }

        if (count($listOfIds) > 0) {
            if (
                DB->update(
                    tableName: 'user',
                    data: [
                        'program_status' => $objId,
                        'updated_at' => DateHelper::getNow(),
                    ],
                    criteria: [
                        'id' => $listOfIds,
                    ],
                )
            ) {
                // если это статус "верификация прошла успешно"
                if ($objId === 3) {
                    DB->update(
                        tableName: 'user',
                        data: [
                            'verified_date' => date('Y-m-d H:i'),
                        ],
                        criteria: [
                            'id' => $listOfIds,
                        ],
                    );
                }

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['set_status'],
                ];
            }
        } else {
            $returnArr = [
                'response' => 'success',
                'response_text' => $LOCALE['set_status'],
            ];
        }

        return $returnArr;
    }

    public function checkCanChangeProfiles(): bool
    {
        return CURRENT_USER->isAdmin() && OBJ_ID > 0 && Filters::hasFiltersCookie('user');
    }
}
