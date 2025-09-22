<?php

declare(strict_types=1);

namespace App\CMSVC\HelperApplication;

use App\CMSVC\Application\ApplicationService;
use App\CMSVC\Character\{CharacterModel, CharacterService};
use App\CMSVC\Trait\UserServiceTrait;
use App\CMSVC\User\{UserModel, UserService};
use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseHelper, BaseModel};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper};
use Fraym\Interface\Response;

class HelperApplicationController extends BaseHelper
{
    use UserServiceTrait;

    public function Response(): ?Response
    {
        $input = $_REQUEST['input'] ?? $_REQUEST['term'] ?? '';
        $input = str_replace([':', ',', '.', '-'], '', (string) $input);
        $isInputInt = is_numeric($input);
        $noCharacter = '1' === ($_REQUEST['nochar'] ?? false);

        $returnArr = [];
        $sort = [];

        if ($isInputInt || mb_strlen($input) >= 3) {
            /** Запрос из личной заявки */
            $fromMyApplicationProjectId = 0;
            $removeDoubles = [];

            if (OBJ_ID > 0) {
                /** @var ApplicationService */
                $applicationService = CMSVCHelper::getService('application');

                $checkApplicationExists = $applicationService->get(
                    id: null,
                    criteria: [
                        'creator_id' => CURRENT_USER->id(),
                    ],
                );

                if ($checkApplicationExists && !$checkApplicationExists->deleted_by_gamemaster->get()) {
                    $fromMyApplicationProjectId = $checkApplicationExists->project_id->get();
                    $removeDoubles[] = CURRENT_USER->id();
                }
            }

            if (CURRENT_USER->isLogged()) {
                $projectRights = RightsHelper::checkProjectRights();

                if ((is_array($projectRights) && DataHelper::inArrayAny(['{admin}', '{gamemaster}'], $projectRights)) || $fromMyApplicationProjectId > 0) {
                    $entityData = DB->query(
                        'SELECT u.*, pa.id AS application_id, pa.sorter AS application_sorter FROM user u LEFT JOIN project_application AS pa ON u.id=pa.creator_id WHERE ' . ($isInputInt ? 'u.sid=:sid' : '(LOWER(u.fio) LIKE :input_1 OR LOWER(u.nick) LIKE :input_2 OR LOWER(pa.sorter) LIKE :input_3)') . ' AND pa.project_id=:project_id AND pa.deleted_by_gamemaster=\'0\' AND pa.deleted_by_player=\'0\'',
                        [
                            ['sid', $input],
                            ['input_1', '%' . mb_strtolower($input) . '%'],
                            ['input_2', '%' . mb_strtolower($input) . '%'],
                            ['input_3', '%' . mb_strtolower($input) . '%'],
                            ['project_id', $fromMyApplicationProjectId > 0 ? $fromMyApplicationProjectId : CookieHelper::getCookie('project_id')],
                        ],
                    );

                    $userService = $this->getUserService();

                    foreach ($entityData as $entityItem) {
                        $value = $this->printItem($userService->arrayToModel($entityItem));

                        if ($fromMyApplicationProjectId > 0) {
                            if (!in_array($entityItem['id'], $removeDoubles)) {
                                $returnArr[] = [
                                    'id' => $entityItem['id'],
                                    'sid' => $entityItem['sid'],
                                    'value' => $value,
                                    'class' => 'application has-background',
                                    'obj_type' => 'user',
                                    'obj_id' => $entityItem['id'],
                                ];
                                $removeDoubles[] = $entityItem['id'];
                                $sort[] = mb_strtolower($value);
                            }
                        } else {
                            $returnArr[] = [
                                'value' => DataHelper::escapeOutput($entityItem['application_sorter']) . ' – ' . $value,
                                'class' => 'application has-background',
                                'obj_type' => 'application',
                                'obj_id' => $entityItem['application_id'],
                            ];
                            $sort[] = mb_strtolower(DataHelper::escapeOutput($entityItem['application_sorter']) . ' – ' . $value);
                        }
                    }

                    /** @var CharacterService */
                    $characterService = CMSVCHelper::getService('character');

                    if (!$noCharacter && $fromMyApplicationProjectId === 0) {
                        $entityData = DB->select(
                            'project_character',
                            [
                                ['name', '%' . mb_strtolower($input) . '%', [OperandEnum::LOWER, OperandEnum::LIKE]],
                                ['project_id', CookieHelper::getCookie('project_id')],
                            ],
                            false,
                            ['name'],
                        );

                        foreach ($entityData as $entityItem) {
                            $value = $this->printItem($characterService->arrayToModel($entityItem), 'character');
                            $returnArr[] = [
                                'value' => $value,
                                'class' => 'character has-background',
                                'obj_type' => 'character',
                                'obj_id' => $entityItem['id'],
                            ];
                            $sort[] = mb_strtolower($value);
                        }
                    }
                }

                array_multisort($sort, SORT_ASC, $returnArr);
            }
        }

        return $this->asArray($returnArr);
    }

    public function printOut(int|string|null $id, string $table = 'user'): string
    {
        $content = '';

        if (!is_null($id)) {
            if ($table === 'user') {
                /** @var UserService $service */
                $service = CMSVCHelper::getService('user');
                $entityItem = $service->get($id);

                $content = $service->showNameExtended($entityItem, false, false, '', false, true, true);
            } else {
                $service = CMSVCHelper::getService($table);
                /** @var BaseModel $entityItem */
                $entityItem = $service->get($id);

                if (property_exists($entityItem, 'name')) {
                    $content = DataHelper::escapeOutput($entityItem->name->get());
                }
            }
        }

        return $content;
    }

    public function printItem(CharacterModel|UserModel|BaseModel|null $entityItem, string $object = 'user'): string
    {
        $content = '';

        if (!is_null($entityItem)) {
            if ($object === 'user') {
                /** @var UserService $service */
                $service = CMSVCHelper::getService('user');
                /** @var UserModel $entityItem */
                $content = $service->showNameExtended($entityItem, false, false, '', false, true, true);
            } elseif (property_exists($entityItem, 'name')) {
                $content = DataHelper::escapeOutput($entityItem->name->get());
            }
        }

        return $content;
    }
}
