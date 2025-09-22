<?php

declare(strict_types=1);

namespace App\CMSVC\HelperSearch;

use App\CMSVC\Event\EventService;
use App\CMSVC\Task\TaskService;
use App\CMSVC\User\{UserModel, UserService};
use Fraym\BaseObject\{BaseHelper, BaseModel};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper};
use Fraym\Interface\Response;

class HelperSearchController extends BaseHelper
{
    public function Response(): ?Response
    {
        $input = ($_REQUEST['input'] ?? null) ?? $_REQUEST['term'] ?? null;
        $input = str_replace([':', ',', '.', '-'], '', (string) $input);
        $isInputInt = is_numeric($input);

        $returnArr = [];
        $sort = [];

        if (CURRENT_USER->isLogged() && ($isInputInt || mb_strlen($input) >= 3)) {
            /** @var UserService $userService */
            $userService = CMSVCHelper::getService('user');

            /** @var TaskService $taskService */
            $taskService = CMSVCHelper::getService('task');

            /** @var EventService $eventService */
            $eventService = CMSVCHelper::getService('event');

            $entityData = DB->query(
                'SELECT * FROM user WHERE ' .
                    ($isInputInt ? 'sid=:input' : "(LOWER(fio) LIKE :input AND hidesome NOT LIKE '%-10-%') OR (LOWER(nick) LIKE :input2 AND hidesome NOT LIKE '%-0-%')"),
                [
                    ['input', $isInputInt ? $input : '%' . mb_strtolower($input) . '%'],
                    ['input2', $isInputInt ? $input : '%' . mb_strtolower($input) . '%'],
                ],
            );

            foreach ($entityData as $entityItem) {
                $value = $this->printItem($userService->arrayToModel($entityItem));
                $returnArr[] = [
                    'id' => $entityItem['id'],
                    'sid' => $entityItem['sid'],
                    'value' => $value,
                    'class' => 'user has-background',
                ];
                $sort[] = mb_strtolower($value);
            }

            array_multisort($sort, SORT_ASC, $returnArr);

            if (!$isInputInt && mb_strlen($input) >= 3) {
                $entityData = DB->query(
                    "SELECT s.* FROM task_and_event AS s LEFT JOIN relation AS r2 ON r2.obj_id_to=s.id AND r2.obj_type_from='{user}' AND r2
                    .obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from WHERE (r2.type IN ('{member}','{responsible}','{admin}')) AND (LOWER(s
                    .description) LIKE :input1 OR LOWER(s.name) LIKE :input2 OR LOWER(s.result) LIKE :input3) AND s.status IS NOT NULL ORDER BY s.name",
                    [
                        ['obj_id_from', CURRENT_USER->id()],
                        ['input1', '%' . mb_strtolower($input) . '%'],
                        ['input2', '%' . mb_strtolower($input) . '%'],
                        ['input3', '%' . mb_strtolower($input) . '%'],
                    ],
                );

                foreach ($entityData as $entityItem) {
                    $value = $this->printItem($taskService->arrayToModel($entityItem), 'task_and_event');
                    $returnArr[] = [
                        'id' => $entityItem['id'],
                        'value' => $value,
                        'class' => 'task has-background',
                    ];
                }

                $entityData = DB->query(
                    "SELECT s.* FROM task_and_event AS s LEFT JOIN relation AS r2 ON r2.obj_id_to=s.id AND r2.obj_type_from='{user}' AND r2
                    .obj_type_to='{event}' AND r2.obj_id_from=:obj_id_from WHERE (r2.type IN ('{member}','{responsible}','{admin}')) AND (LOWER(s
                    .description) LIKE :input1 OR LOWER(s.name) LIKE :input2 OR LOWER(s.result) LIKE :input3) AND s.status IS NOT NULL ORDER BY s.name",
                    [
                        ['obj_id_from', CURRENT_USER->id()],
                        ['input1', '%' . mb_strtolower($input) . '%'],
                        ['input2', '%' . mb_strtolower($input) . '%'],
                        ['input3', '%' . mb_strtolower($input) . '%'],
                    ],
                );

                foreach ($entityData as $entityItem) {
                    $value = $this->printItem($eventService->arrayToModel($entityItem), 'task_and_event');
                    $returnArr[] = [
                        'id' => $entityItem['id'],
                        'value' => $value,
                        'class' => 'event has-background',
                    ];
                }
            }
        }

        if (!$isInputInt && mb_strlen($input) >= 3) {
            $returnArr = array_merge($returnArr, $this->getByName('calendar_event', $input));

            $returnArr = array_merge($returnArr, $this->getByName('area', $input));

            $returnArr = array_merge($returnArr, $this->getByName('publication', $input));

            $returnArr = array_merge($returnArr, $this->getByName('project', $input));

            $returnArr = array_merge($returnArr, $this->getByName('community', $input));

            $returnArr = array_merge($returnArr, $this->getByName('tag', $input));
        }

        return $this->asArray($returnArr);
    }

    public function getByName(string $tableName, string $searchText): array
    {
        $returnArr = [];

        /** @var array<int, string[]> $entityData */
        $entityData = DB->select(
            $tableName,
            [
                ['name', '%' . mb_strtolower($searchText) . '%', [OperandEnum::LOWER, OperandEnum::LIKE]],
            ],
            false,
            ['name'],
        );

        foreach ($entityData as $entityItem) {
            $returnArr[] = [
                'id' => $entityItem['id'],
                'value' => $entityItem['name'],
                'class' => $tableName . ' has-background',
            ];
        }

        return $returnArr;
    }

    public function printOut(int|string|null $id, string $table = 'user'): string
    {
        $content = '';

        if (!is_null($id)) {
            if ($table === 'user') {
                /** @var UserService $userService */
                $userService = CMSVCHelper::getService('user');
                $entityItem = $userService->get($id);
                $content = $userService->showNameExtended($entityItem, false, false, '', false, true, true);
            } else {
                $entityItem = DB->select($table, ['id' => $id], true);
                $content = DataHelper::escapeOutput($entityItem['name']);
            }
        }

        return $content;
    }

    public function printItem(?BaseModel $entityItem, string $object = 'user'): string
    {
        $content = '';

        if (!is_null($entityItem)) {
            if ($object === 'user') {
                /** @var UserService $userService */
                $userService = CMSVCHelper::getService('user');
                /** @var UserModel|null $entityItem */
                $content = $userService->showNameExtended($entityItem, false, false, '', false, true, true);
            } elseif (property_exists($entityItem, 'name')) {
                $content = DataHelper::escapeOutput($entityItem->name->get());
            }
        }

        return $content;
    }
}
