<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Fraym\Helper;

use DateTime;
use DateTimeImmutable;
use Exception;
use Fiber;
use Fraym\Element\{Attribute as Attribute, Item as Item};
use Fraym\Entity\BaseEntity;
use Fraym\Enum\{ActEnum, EscapeModeEnum};
use Fraym\Interface\{ElementAttribute, ElementItem, HasDefaultValue, Helper};
use Fraym\Vendor\StripTags\StripTags;
use Generator;
use JsonException;

abstract class DataHelper implements Helper
{
    /** Определение ActEnum по умолчанию в случае, если он не предоставлен */
    public static function getActDefault(?BaseEntity $entity = null): ActEnum
    {
        $act = ACT;

        if (is_null($act)) {
            if (DataHelper::getId() > 0) {
                $act = !is_null($entity) ? $entity->getDefaultItemActType() : ActEnum::edit;
            } else {
                $act = !is_null($entity) ? $entity->getDefaultListActType() : ActEnum::list;
            }
        }

        return $act;
    }

    /** Проверка наличия всех переменных в массиве */
    public static function inArrayAll(array $needles, array $haystack): bool
    {
        return empty(array_diff($needles, $haystack));
    }

    /** Проверка наличия любой из переменных в массиве */
    public static function inArrayAny(array $needles, array $haystack): bool
    {
        return !empty(array_intersect($needles, $haystack));
    }

    /** Перенос элемента массива с одного индекса на другой */
    public static function changeValueIndexInArray(array $array, int|string $oldIndex, int|string $newIndex): array
    {
        $out = array_splice($array, $oldIndex, 1);
        array_splice($array, $newIndex, 0, $out);

        return $array;
    }

    /** Получение id вне зависимости от того запрос это на сохранение или просто вывод страницы */
    public static function getId(): null|int|string
    {
        return ID[key(ID) ?? 0] ?? null;
    }

    /** Поиск в $_REQUEST ключа массива данных, в которых присутствует указанный id */
    public static function findDataKeyInRequestById(int|string $id): int
    {
        foreach (ID as $key => $value) {
            if ((string) $value === (string) $id) {
                return $key;
            }
        }

        return 0;
    }

    /** Вывод сообщений исключительно для админа */
    public static function adminEcho(string $str): void
    {
        if (CURRENT_USER->isAdmin(true) && CookieHelper::getCookie('testMode') === '1') {
            ResponseHelper::error($str);
        }
    }

    /** Получение json-файла и декодирование его */
    public static function getJsonFile(string $filePath): ?array
    {
        $data = null;

        if (file_exists($filePath)) {
            $fileData = file_get_contents($filePath);
            $data = DataHelper::jsonFixedDecode($fileData, true);
            unset($fileData);
        }

        return $data;
    }

    /** Защищенный парсинг JSON'а с выдачей ошибки в случае проблемы */
    public static function jsonFixedDecode(?string $string, bool $strict = false): mixed
    {
        try {
            if ($string === null) {
                return [];
            }

            $data = $strict ? json_decode($string, true, 512, JSON_THROW_ON_ERROR) : json_decode($string, true);

            if (!$strict && !is_array($data)) {
                $data = [
                    DataHelper::jsonLastErrorText(),
                ];
            }
        } catch (JsonException $jsonException) {
            throw new Exception('JSON parsing error: ' . $jsonException->getMessage() . '. Details: ' . DataHelper::jsonLastErrorText());
        }

        return $data;
    }

    /** JSON-энкодирование без превращения кириллицы в символы класса \u */
    public static function jsonFixedEncode(array $array): string
    {
        /* на случай, если в массиве данные почему-то не UTF-8 */
        $array = self::fixUTFEncoding($array);

        return json_encode($array, JSON_UNESCAPED_UNICODE);
    }

    /** Исправление данных в массиве на UTF-8 данные */
    public static function fixUTFEncoding(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $array[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            } elseif (is_array($value)) {
                $array[$key] = self::fixUTFEncoding($value);
            }
        }

        return $array;
    }

    /** Вывод данных последней ошибки обработки JSON */
    public static function jsonLastErrorText(): string
    {
        $constants = get_defined_constants(true);
        $json_errors = [];

        foreach ($constants['json'] as $name => $value) {
            if (!strncmp($name, 'JSON_ERROR_', 11)) {
                $json_errors[$value] = $name;
            }
        }

        return $json_errors[json_last_error()];
    }

    /** Добавление сигнатуры (подписи) в массив API-запроса */
    public static function apiSignature(array $request, ?string $hash = null): array
    {
        if (!is_null($hash)) {
            $request['hash'] = $hash;
        }
        $requestSignature = self::hashData($request);
        unset($request['hash']);
        $request['signature'] = $requestSignature;

        return $request;
    }

    /** Логирование действий пользователей */
    public static function activityLog(bool $fullLog = false): void
    {
        $fiber = new Fiber(static function ($fullLog): void {
            if ($fullLog) {
                if ('get_new_events' !== ACTION && 'load_tasks' !== ACTION && CURRENT_USER->id() > 0) {
                    DB->insert('activity_log', [
                        ['user_id', CURRENT_USER->isLogged() ? CURRENT_USER->id() : null],
                        ['real_ip', self::getRealIp()],
                        ['url', self::selfURL()],
                        ['kind', KIND],
                        ['id', self::getId()],
                        ['action', ACTION],
                        ['obj_type', OBJ_TYPE],
                        ['obj_id', OBJ_ID],
                        ['created_at', DateHelper::getNow()],
                        ['updated_at', DateHelper::getNow()],
                    ]);
                }
            }

            if (CURRENT_USER->id() > 0) {
                DB->update('user', [['updated_at', DateHelper::getNow()]], ['id' => CURRENT_USER->id()]);

                if ('profile' === KIND) {
                    $_REQUEST['updated_at'][0] = time() + 20;
                }
            }
        });
        $fiber->start($fullLog);
    }

    /** Исправление http-адреса на корректный */
    public static function fixURL(string $url): string
    {
        if (!str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /** Определение адреса страницы */
    public static function selfURL(): string
    {
        $s = empty($_SERVER['HTTPS']) ? '' : ($_SERVER['HTTPS'] === 'on' ? 's' : '');
        $protocol = mb_substr(mb_strtolower($_SERVER['SERVER_PROTOCOL']), 0, mb_strpos(mb_strtolower($_SERVER['SERVER_PROTOCOL']), '/')) . $s;
        $port = ($_SERVER['SERVER_PORT'] === '80') ? '' : (':' . $_SERVER['SERVER_PORT']);

        return $protocol . '://' . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
    }

    /** Получение "реального" IP пользователя */
    public static function getRealIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /** preg_quote заменяемого preg_replace контента */
    public static function pregQuoteReplaced(?string $text): string
    {
        return preg_replace('#(?<!\\\\)(\\$|\\\\)#', '\\\\$1', $text);
    }

    /** Получение названия объекта в фигурных кавычках */
    public static function addBraces(string $name): string
    {
        $clearedName = self::clearBraces($name);

        return $clearedName !== '' ? '{' . $clearedName . '}' : '';
    }

    /** Получение названия объекта без фигурных кавычек */
    public static function clearBraces(?string $name): string
    {
        return str_replace(['{', '}'], '', (string) $name);
    }

    /** Очистка HTML-данных с помощью StripTags */
    public static function escapeHTMLData(?string $string): string
    {
        $_StripTags = CACHE->getFromCache('stripTags', 0);

        if (!isset($_StripTags) || !($_StripTags instanceof StripTags)) {
            $_StripTags = new StripTags();
            CACHE->setToCache('stripTags', 0, $_StripTags);
        }

        return $_StripTags->filter((string) $string);
    }

    /** Хеширование строки / массива */
    public static function hashData(string|array $data): string
    {
        if (is_array($data)) {
            $data = serialize($data);
        }

        return hash('xxh3', $data);
    }

    /** Получение случайной байтовой строки */
    public static function getRandomStringBin2hex(int $length = 200): string
    {
        $bytes = random_bytes($length / 2);

        return bin2hex($bytes);
    }

    /** Base64Url шифрование строки */
    public static function base64UrlEncode(string $str): string
    {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }

    /** Поиск по ключу в массиве вида [name, value] */
    public static function getFlatArrayElement(int|string|array $needle, array $haystack): ?array
    {
        if (is_array($needle)) {
            $needle = $needle[0];
        }

        foreach ($haystack as $value) {
            if ($needle === $value[0] || self::addBraces($needle) === $value[0] || (is_numeric($needle) && (int) $needle === $value[0])) {
                return $value;
            }
        }

        return null;
    }

    /** Перевод значения multiselect в array */
    public static function multiselectToArray(?string $string): array
    {
        if (!is_null($string)) {
            if (str_starts_with($string, '-') && str_ends_with($string, '-')) {
                $string = mb_substr($string, 1, mb_strlen($string) - 2);
            }
            $return_array = explode('-', $string);

            foreach ($return_array as $key => $value) {
                if (trim($value) === '') {
                    unset($return_array[$key]);
                }
            }
        } else {
            $return_array = [];
        }

        return $return_array;
    }

    /** Перевод array в значения multiselect */
    public static function arrayToMultiselect(array $array): string
    {
        $value_array = [];

        foreach ($array as $key => $value) {
            if ($value === 'on') {
                $value_array[] = $key;
            } else {
                $value_array[] = $value;
            }
        }

        return count($array) > 0 ? '-' . implode('-', $value_array) . '-' : '';
    }

    /** Проверка число или строка и возврата в соответствующем типе */
    public static function checkNumeric(mixed $data): mixed
    {
        return is_numeric($data) ? (int) $data : $data;
    }

    /** Разбор формата виртуальных данных */
    public static function unmakeVirtual(string $data): array
    {
        $result = [];

        if ($data !== '') {
            preg_match_all('#\[([^]]+)]\[(.*?)]\r\n#msu', $data, $matches);

            foreach ($matches[0] as $key => $value) {
                if (str_starts_with($matches[1][$key], 'virtual')) {
                    $result[$matches[1][$key]] = DataHelper::escapeOutput($matches[2][$key]);
                }
            }
        }

        return $result;
    }

    /** Создание структуры объекта на основе виртуально-структурированных данных
     * @return Generator<int|string, ElementItem>
     */
    public static function virtualStructure(
        string $query,
        array $params = [],
        string $prefix = '',
        array $additionalDataFields = [],
    ): Generator {
        $uploads = $_ENV['UPLOADS'];

        $fieldsData = DB->query($query, $params);

        foreach ($fieldsData as $fieldData) {
            $values = [];

            $obligatory = ($fieldData[$prefix . 'mustbe'] ?? false) === '1';

            $read = match ($fieldData[$prefix . 'rights'] ?? false) {
                1 => 100,
                2, 3 => 10,
                4 => 1,
                default => 100000,
            };

            $write = match ($fieldData[$prefix . 'rights'] ?? false) {
                1, 2 => 100,
                3, 4 => 10,
                default => 100000,
            };

            if ($fieldData[$prefix . 'values'] ?? false) {
                $field_values = DataHelper::escapeOutput($fieldData[$prefix . 'values'] ?? null);
                preg_match_all('#\[(\d+)]\[([^]]+)]#', $field_values, $matches);

                foreach ($matches[1] as $key => $value) {
                    $values[] = [$value, $matches[2][$key]];
                }
            }

            if (($fieldData[$prefix . 'group'] ?? false) === 0) {
                $fieldData[$prefix . 'group'] = null;
            }

            $itemClassName = match ($fieldData[$prefix . 'type'] ?? false) {
                'calendar' => Item\Calendar::class,
                'checkbox' => Item\Checkbox::class,
                'file' => Item\File::class,
                'h1' => Item\H1::class,
                'multiselect' => Item\Multiselect::class,
                'number' => Item\Number::class,
                'select' => Item\Select::class,
                'text' => Item\Text::class,
                'textarea' => Item\Textarea::class,
                'wysiwyg' => Item\Wysiwyg::class,
                default => throw new Exception('Unknown virtual field type: ' . ($fieldData[$prefix . 'type'] ?? false)),
            };

            $attributeClassName = match ($fieldData[$prefix . 'type'] ?? false) {
                'calendar' => Attribute\Calendar::class,
                'checkbox' => Attribute\Checkbox::class,
                'file' => Attribute\File::class,
                'h1' => Attribute\H1::class,
                'multiselect' => Attribute\Multiselect::class,
                'number' => Attribute\Number::class,
                'select' => Attribute\Select::class,
                'text' => Attribute\Text::class,
                'textarea' => Attribute\Textarea::class,
                'wysiwyg' => Attribute\Wysiwyg::class,
                default => throw  new Exception('Unknown virtual field type: ' . ($fieldData[$prefix . 'type'] ?? false)),
            };

            $field = new $itemClassName();

            if ($field instanceof ElementItem) {
                $fieldAttribute = new $attributeClassName();

                if ($fieldAttribute instanceof ElementAttribute) {
                    $field
                        ->setAttribute($fieldAttribute)
                        ->setName('virtual' . ($fieldData['id'] ?? ''))
                        ->setShownName($fieldData[$prefix . 'name'] ?? null)
                        ->setHelpText($fieldData[$prefix . 'help'] ?? null);

                    $fieldAttribute
                        ->setVirtual(true)
                        ->setContext([$read, $write])
                        ->setObligatory($obligatory)
                        ->setGroup($fieldData[$prefix . 'group'] ?? null)
                        ->setUseInFilters((bool) ($fieldData['show_in_filters'] ?? false));

                    if ($fieldAttribute instanceof HasDefaultValue) {
                        $fieldAttribute->setDefaultValue(DataHelper::escapeOutput($fieldData[$prefix . 'default'] ?? null));
                    }

                    if (method_exists($fieldAttribute, 'setValues')) {
                        /** @var Attribute\Multiselect|Attribute\Select $fieldAttribute */
                        $fieldAttribute->setValues($values);
                    }

                    $additionalData = [];

                    foreach ($additionalDataFields as $additionalDataField) {
                        $additionalData[$additionalDataField] = $fieldData[$additionalDataField] ?? null;
                    }
                    $fieldAttribute->setAdditionalData($additionalData);

                    if ('file' === ($fieldData[$prefix . 'type'] ?? false)) {
                        /** @var Attribute\File $fieldAttribute */
                        if (isset($uploads[0]['virtual'])) {
                            $new_upload = [
                                'path' => $uploads[0]['virtual']['path'],
                                'columnname' => 'virtual' . $fieldData['id'],
                                'extensions' => $uploads[0]['virtual']['extensions'],
                            ];
                            $_ENV['UPLOADS'][] = $new_upload;
                            end($_ENV['UPLOADS']);
                            $fieldAttribute->setUploadNum(key($_ENV['UPLOADS']));
                        } else {
                            ResponseHelper::error('Virtual upload data not set.');
                            $field = null;
                        }
                    }

                    if (!is_null($field)) {
                        yield $fieldData['id'] => $field;
                    }
                }
            }
        }
        unset($fieldsData);
    }

    /** Эскапирование данных перед выводом во фронтенд */
    public static function escapeOutput(mixed $value, EscapeModeEnum $escapeModeEnum = EscapeModeEnum::forHTML): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::escapeOutput($item, $escapeModeEnum);
            }
        } else {
            $value = (string) $value;

            if ($escapeModeEnum === EscapeModeEnum::forHTML) {
                $value = htmlspecialchars($value);
            } elseif ($escapeModeEnum === EscapeModeEnum::forHTMLforceNewLines) {
                $value = str_replace(["\r\n", "\n"], '<br>', htmlspecialchars($value));
            } elseif ($escapeModeEnum === EscapeModeEnum::forAttributes) {
                $value = htmlspecialchars($value, ENT_COMPAT);
            } elseif ($escapeModeEnum === EscapeModeEnum::plainHTML) {
            } elseif ($escapeModeEnum === EscapeModeEnum::plainHTMLforceNewLines) {
                $value = str_replace(["\r\n", "\n"], '<br>', $value);
            }
        }

        return $value;
    }

    /** Фильтрация данных перед размещением в БД */
    public static function filterInput(bool|int|string|null|DateTime|DateTimeImmutable|array|float $value, array $fieldParams, bool $topLevel = true): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = DataHelper::filterInput($item, [], false);
            }

            if ($topLevel) {
                $value = DataHelper::jsonFixedEncode($value);
            }
        } elseif (in_array('url', $fieldParams)) {
            $value = filter_var($value, FILTER_SANITIZE_URL);
        } elseif (in_array('email', $fieldParams)) {
            $value = filter_var($value, FILTER_SANITIZE_EMAIL);
        } elseif (in_array('html', $fieldParams)) {
            $value = DataHelper::escapeHTMLData($value);
        } elseif ($value instanceof DateTime || $value instanceof DateTimeImmutable) {
            $value = $value->format('Y-m-d H:i:s');
        }

        return $value;
    }
}
