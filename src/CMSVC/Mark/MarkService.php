<?php

declare(strict_types=1);

namespace App\CMSVC\Mark;

use App\CMSVC\Application\ApplicationService;
use App\Helper\MessageHelper;
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Helper\{CookieHelper, DataHelper, LocaleHelper, RightsHelper};

#[Controller(MarkController::class)]
class MarkService extends BaseService
{
    #[DependencyInjection]
    public ApplicationService $applicationService;

    /** Отметка сообщения требующим решения */
    public function markNeedResponse(int $objId): array
    {
        $LOCALE = $this->LOCALE;

        $returnArr = [
            'response' => 'error',
            'response_text' => $LOCALE['messages']['message_marked_need_response_fail'],
        ];

        $message = DB->findObjectById($objId, 'conversation_message');

        if ($message['id'] ?? false) {
            $conversationData = DB->findObjectById($message['conversation_id'], 'conversation');
            $applicationData = $this->applicationService->get($conversationData['obj_id']);

            if ($applicationData->project_id->get() === CookieHelper::getCookie('project_id')) {
                if (!str_contains(($message['icon'] ?? ''), 'need_response')) {
                    DB->update(
                        'conversation_message',
                        [
                            ['icon', (($message['icon'] ?? false) ? $message['icon'] . '|' : '') . 'need_response'],
                        ],
                        ['id' => $objId],
                    );
                }

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['messages']['message_marked_need_response_success'],
                ];
            }
        }

        return $returnArr;
    }

    /** Отметка сообщения получившим решение */
    public function markHasResponse(int $objId): array
    {
        $LOCALE = $this->LOCALE;

        $returnArr = [
            'response' => 'error',
            'response_text' => $LOCALE['messages']['message_marked_has_response_fail'],
        ];

        $message = DB->findObjectById($objId, 'conversation_message');

        if ($message['id'] ?? false) {
            $conversationData = DB->findObjectById($message['conversation_id'], 'conversation');
            $applicationData = $this->applicationService->get($conversationData['obj_id']);

            if ($applicationData->project_id->get() === CookieHelper::getCookie('project_id')) {
                if (str_contains(($message['icon'] ?? ''), 'need_response')) {
                    DB->update(
                        'conversation_message',
                        [
                            ['icon', str_replace(['|need_response', 'need_response'], '', $message['icon'])],
                        ],
                        ['id' => $objId],
                    );
                }

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['messages']['message_marked_has_response_success'],
                ];
            }
        }

        return $returnArr;
    }

    /** Отметка сообщения прочитанным */
    public function markReadMessage(int $objId): array
    {
        $LOCALE = $this->LOCALE;

        $returnArr = [
            'response' => 'error',
            'response_text' => $LOCALE['messages']['message_marked_read_fail'],
        ];

        $message = DB->findObjectById($objId, 'conversation_message');

        if ($message['id'] ?? false) {
            $conversationData = DB->findObjectById($message['conversation_id'], 'conversation');
            $applicationData = $this->applicationService->get($conversationData['obj_id']);

            if ($applicationData->project_id->getAsInt() === CookieHelper::getCookie('project_id')) {
                if (!str_contains($message['icon'], 'mark_read')) {
                    DB->update(
                        'conversation_message',
                        [
                            ['icon', ($message['icon'] !== '' ? $message['icon'] . '|' : '') . 'mark_read'],
                        ],
                        ['id' => $objId],
                    );
                }
                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['messages']['message_marked_read_success'],
                ];
            }
        }

        return $returnArr;
    }

    /** Отметка сообщения прочитанным */
    public function markRead(int $cmId): array
    {
        if (CURRENT_USER->isLogged()) {
            MessageHelper::createRead([$cmId]);
        }

        return [
            'response' => 'success',
        ];
    }

    /** Функция отметки важным */
    public function markImportant(string $objType, int $objId): array
    {
        $LOCALE = LocaleHelper::getLocale(['global']);

        $returnArr = [];

        if ($objType !== '' && $objId > 0 && CURRENT_USER->isLogged()) {
            if (RightsHelper::checkRights('{important}', DataHelper::addBraces($objType), $objId)) {
                RightsHelper::deleteRights('{important}', DataHelper::addBraces($objType), $objId);
                $returnArr = [
                    'response' => 'success',
                    (REQUEST_TYPE->isApiRequest() ? 'response_data' : 'response_text') => 'decrease',
                ];
            } else {
                RightsHelper::addRights('{important}', DataHelper::addBraces($objType), $objId);
                $returnArr = [
                    'response' => 'success',
                    (REQUEST_TYPE->isApiRequest() ? 'response_data' : 'response_text') => 'increase',
                ];
            }
        } elseif (!CURRENT_USER->isLogged()) {
            $returnArr = [
                'response' => 'error',
                'error_code' => 'marking_error',
                'response_text' => $LOCALE['important_button']['marking_error'],
            ];
        }

        return $returnArr;
    }

    /**  Отметка сообщения спамом */
    /*public function reportSpam(string $objType, int $objId): array
    {
        $LOCALE = $this->LOCALE;

        $returnArr = [];
        $deleteType = '';

        if (DataHelper::clearBraces($objType) == 'conversation') {
            if (RightsHelper::checkRights('{member}', '{conversation}', $objId)) {
                DB->insert(
                    'user_report',
                    [
                        ['creator_id' => CURRENT_USER->id()],
                        ['obj_type' => 'conversation'],
                        ['obj_id' => $objId],
                        ['created_at' => DateHelper::getNow()],
                        ['updated_at' => DateHelper::getNow()],
                    ]
                );
                RightsHelper::deleteRights('{member}', '{conversation}', $objId);
                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['messages']['report_spam_success'],
                    'delete_type' => $deleteType,
                ];
            }
        } elseif (DataHelper::clearBraces($objType) == 'conversation_message') {
            $conversationMessageData = DB->findObjectById($objId, 'conversation_message');
            $conversationData = DB->findObjectById($conversationMessageData['conversation_id'], 'conversation');
            $conversationParentObjType = str_replace(['_wall', '_conversation'],
                '',
                DataHelper::clearBraces($conversationData['obj_type']));
            if (RightsHelper::checkAnyRights($conversationParentObjType, $conversationData['obj_id'])) {
                DB->insert(
                    'user_report',
                    [
                        ['creator_id' => CURRENT_USER->id()],
                        ['obj_type' => 'conversation_message'],
                        ['obj_id' => $objId],
                        ['content' => $conversationMessageData['content']],
                        ['created_at' => DateHelper::getNow()],
                        ['updated_at' => DateHelper::getNow()],
                    ]
                );

                if (RightsHelper::checkRights(['{admin}', '{moderator}'],
                    $conversationParentObjType,
                    $conversationData['obj_id'])) {
                    $deleteData = self::messageDelete($objId);
                    $deleteType = $deleteData['delete_type'];
                } else {
                    MessageHelper::createRead([$objId]);
                    $conversationService->deleteMessage($objId);
                }

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['messages']['report_spam_success'],
                    'delete_type' => $deleteType,
                ];
            }
        } elseif (DataHelper::clearBraces($objType) == 'user') {
            DB->insert(
                'user_report',
                [
                    ['creator_id' => CURRENT_USER->id()],
                    ['obj_type' => 'user'],
                    ['obj_id' => $objId],
                    ['created_at' => DateHelper::getNow()],
                    ['updated_at' => DateHelper::getNow()],
                ]
            );
            $returnArr = [
                'response' => 'success',
                'response_text' => $LOCALE['messages']['report_spam_success_2'],
                'delete_type' => 'refresh',
            ];
        }
        return $returnArr;
    }*/
}
