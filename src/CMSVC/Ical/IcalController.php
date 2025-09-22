<?php

declare(strict_types=1);

namespace App\CMSVC\Ical;

use App\Helper\RightsHelper;
use Fraym\BaseObject\{BaseController, CMSVC};
use Fraym\Helper\{DataHelper, ResponseHelper};
use Fraym\Interface\Response;
use Ical\SimpleICS;

#[CMSVC(
    controller: IcalController::class,
)]
class IcalController extends BaseController
{
    public function Response(): ?Response
    {
        $userId = (string) $_REQUEST['id'];

        // расшифровываем и проверяем корректность id, с которым к нам обратились
        if ($userId !== '') {
            $hash = mb_substr($userId, 0, 32);
            $userId = (int) mb_substr($userId, 32);

            if ($userId > 0) {
                $hash_check = md5($userId . '.ical.' . ABSOLUTE_PATH);
                $check_user = DB->findObjectById($userId, 'user');

                if ($hash === $hash_check && $check_user) {
                    $cal = new SimpleICS();
                    $cal->productString = '-//' . ABSOLUTE_PATH . '/allrpg API//';

                    $my_communities = RightsHelper::findByRights(null, '{community}', $userId);
                    $my_projects = RightsHelper::findByRights(null, '{project}', $userId);

                    $result = DB->query(
                        "SELECT DISTINCT te.*, r2.obj_type_to as type, r.obj_type_to as parent_type, r.obj_id_to as parent_id FROM task_and_event te LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND (r2.obj_type_to='{task}' OR r2.obj_type_to='{event}') AND r2.obj_type_from='{user}' AND r2.obj_id_from=" . $userId . " LEFT JOIN relation r ON r.obj_id_from=r2.obj_id_to AND r.obj_type_from=r2.obj_type_to AND ((r.obj_type_to='{project}' AND r.obj_id_to" . ($my_projects ? ' IN (' . implode(',', $my_projects) . ')' : '=0') . ") OR (r.obj_type_to='{community}' AND r.obj_id_to" . ($my_communities ? ' IN (' . implode(',', $my_communities) . ')' : '=0') . ")) WHERE r2.type IN ('{admin}','{responsible}','{member}') AND (r.type='{child}' OR r.type IS NULL) AND ((r2.obj_type_to='{task}' AND te.status NOT IN ('{closed}','{rejected}','{delayed}')) OR (r2.obj_type_to='{event}' AND te.date_to >= '" . date(
                            'Y-m-d',
                            strtotime('-1 month'),
                        ) . "')) ORDER BY te.name",
                        [],
                    );

                    foreach ($result as $event) {
                        $cal->addEvent(static function ($e) use ($event): void {
                            if (($event['date_from'] ?? false) && ($event['date_to'] ?? false)) {
                                $e->startDate = date('Y-m-d', strtotime($event['date_from'])) . 'T' . date('H:i:sP', strtotime($event['date_from']));
                                $e->endDate = date('Y-m-d', strtotime($event['date_to'])) . 'T' . date('H:i:sP', strtotime($event['date_to']));
                                $e->uri = ABSOLUTE_PATH . '/' . DataHelper::clearBraces($event['type']) . '/' . $event['id'] . '/';
                                $e->location = DataHelper::escapeOutput($event['place'] ?? '');
                                $e->description = ((DataHelper::clearBraces($event['type']) === 'task' && DataHelper::escapeOutput($event['result']) !== '') ? str_replace("\r\n", '\\n', DataHelper::escapeOutput($event['result'])) : str_replace("\r\n", '\\n', DataHelper::escapeOutput($event['description'])));
                                $e->summary = DataHelper::escapeOutput($event['name']) ?? '';
                                $e->uniqueId = $event['id'] . '@ical.' . ABSOLUTE_PATH;
                            }
                        });
                    }

                    header('Content-Type: ' . SimpleICS::MIME_TYPE);
                    header('Content-Disposition: attachment; filename=' . $hash . $userId . '.ics');
                    echo $cal->serialize();
                    exit;
                } else {
                    ResponseHelper::redirect('/login/', ['redirectToKind' => KIND]);
                }
            } else {
                ResponseHelper::redirect('/login/', ['redirectToKind' => KIND]);
            }
        } else {
            ResponseHelper::redirect('/login/', ['redirectToKind' => KIND]);
        }

        return null;
    }
}
