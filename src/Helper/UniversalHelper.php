<?php

declare(strict_types=1);

namespace App\Helper;

use Fraym\Enum\OperandEnum;
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Helper;

abstract class UniversalHelper implements Helper
{
    /** Проверка количества обновлений в объекте */
    public static function checkForUpdates(string $objType, int $objId): int
    {
        $result = 0;
        $objType = DataHelper::clearBraces($objType);

        // Количество новых тем / комментариев в обсуждениях + новых комментариев на стене + новых запросов и предложений.
        if ($objType === 'project' && $objId > 0) {
            $result = DB->query(
                'SELECT cm.id FROM conversation_message cm INNER JOIN conversation c ON cm.conversation_id=c.id AND c.obj_id=:obj_id AND c.obj_type IN ("{project_wall}", "{project_conversation}") WHERE cm.use_group_name="1"',
                [
                    ['obj_id', $objId],
                ],
            );
            $conversationMessageCount = count($result);

            $result = DB->query(
                'SELECT cms.id FROM conversation_message_status cms INNER JOIN conversation_message cm ON cms.message_id=cm.id INNER JOIN conversation c ON cm.conversation_id=c.id AND c.obj_id=:obj_id AND c.obj_type IN ("{project_wall}", "{project_conversation}") WHERE cm.use_group_name="1" AND cms.user_id=:user_id AND cms.message_read="1"',
                [
                    ['obj_id', $objId],
                    ['user_id', CURRENT_USER->id()],
                ],
            );
            $result = $conversationMessageCount - count($result);
        } elseif ($objType === 'community' && $objId > 0) {
            $result = DB->query(
                'SELECT cm.id FROM conversation_message cm INNER JOIN conversation c ON cm.conversation_id=c.id AND c.obj_id=:obj_id AND c.obj_type IN ("{community_wall}", "{community_conversation}") WHERE cm.use_group_name="1"',
                [
                    ['obj_id', $objId],
                ],
            );
            $conversationMessageCount = count($result);
            $result = DB->query(
                'SELECT cms.id FROM conversation_message_status cms INNER JOIN conversation_message cm ON cms.message_id=cm.id INNER JOIN conversation c ON cm.conversation_id=c.id AND c.obj_id=:obj_id AND c.obj_type IN ("{community_wall}", "{community_conversation}") WHERE cm.use_group_name="1" AND cms.user_id=:user_id AND cms.message_read="1"',
                [
                    ['obj_id', $objId],
                    ['user_id', CURRENT_USER->id()],
                ],
            );
            $result = $conversationMessageCount - count($result);
        }

        return $result;
    }

    /** Отрисовка кнопки "Сообщения" */
    public static function drawMessagesButton(string $objType, int|string $objId, ?int $messagesCount = null): string
    {
        if (is_null($messagesCount)) {
            $result = DB->query(
                'SELECT COUNT(cm.id) as cm_count FROM conversation_message cm INNER JOIN conversation c ON cm.conversation_id=c.id AND c.obj_type=:obj_type AND c.obj_id=:obj_id',
                [
                    ['obj_type', $objType],
                    ['obj_id', $objId],
                ],
                true,
            );
            $messagesCount = $result['cm_count'];
        }

        $buttonHtml = '<div class="messages_button' . ((int) $messagesCount > 0 ? ' marked' : '') . '"><span class="messages_button_icon"></span><span class="messages_button_counter">';
        $buttonHtml .= $messagesCount . '</span></div>';

        return $buttonHtml;
    }

    /** Отрисовка кнопки "Нравится" */
    public static function drawImportant(string $objType, int|string $objId, ?bool $marked = null, ?int $checkedImportantCounter = null): array|string
    {
        $LOCALE = LocaleHelper::getLocale(['mark', 'global', 'important_button']);
        $objType = DataHelper::clearBraces($objType);
        $objTypeBraced = DataHelper::addBraces($objType);

        if (is_null($checkedImportantCounter)) {
            $result = RightsHelper::findByRights('{important}', $objTypeBraced, $objId, '{user}', false);
            $checkedImportantCounter = ($result ? count($result) : 0);
        }

        if (is_null($marked)) {
            $marked = RightsHelper::checkRights('{important}', $objTypeBraced, $objId);
        }

        if (REQUEST_TYPE->isApiRequest()) {
            return [
                'marked_count' => $checkedImportantCounter,
                'marked_by_user' => ($marked ? 'true' : 'false'),
            ];
        } else {
            $buttonHtml = '<div class="important_button' . ($marked ? ' marked' : '') . '" obj_type="' . $objType . '" obj_id="' . $objId . '"><span class="important_button_icon sbi"></span><span class="important_button_counter">';
            $buttonHtml .= ($checkedImportantCounter > 0 ? $checkedImportantCounter : '') . '</span><span class="important_button_text">' . ($marked ? $LOCALE['marked_message'] : $LOCALE['unmarked_message']) . '</span></div>';

            return $buttonHtml;
        }
    }

    /** Создание hash для капчи */
    public static function getCaptcha(): array
    {
        $clear = time() - (60 * 60);
        DB->delete(
            tableName: 'regstamp',
            criteria: [
                ['updated_at', $clear, [OperandEnum::LESS]],
            ],
        );

        $pass = '';
        $salt = 'abcdefghjkmnpqrstuvwxyz23456789';
        srand((int) ((float) microtime() * 1000000));
        $i = 0;

        while ($i <= 5) {
            $num = rand() % 31;
            $tmp = mb_substr($salt, $num, 1);
            $pass .= $tmp;
            ++$i;
        }
        $code = $pass;
        $hash = md5($code . $_ENV['PROJECT_HASH_WORD']);

        DB->insert(
            tableName: 'regstamp',
            data: [
                'code' => $code,
                'hash' => $hash,
                'created_at' => DateHelper::getNow(),
            ],
        );

        return ['response' => 'success', 'hash' => $hash];
    }
}
