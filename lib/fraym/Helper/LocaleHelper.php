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

use Exception;
use Fraym\BaseObject\BaseModel;
use Fraym\Entity\BaseEntity;
use Fraym\Enum\LocalableFieldsEnum;
use Fraym\Interface\{ElementItem, Helper};

abstract class LocaleHelper implements Helper
{
    /** Получение списка локалей */
    public static function getLocalesList(): ?array
    {
        $filePath = INNER_PATH . 'src/Template/localesList.json';

        $result = DataHelper::getJsonFile($filePath);

        if (is_null($result)) {
            throw new Exception('localesList.json not found in /src/Template/');
        }

        return $result;
    }

    /** Получение локали */
    public static function getLocale(?array $entityPathInLocale = null): ?array
    {
        $temp = CACHE->getFromCache('_LOCALE', 0);

        if (!is_null($entityPathInLocale)) {
            $entityPathInLocale[0] = TextHelper::camelCaseToSnakeCase($entityPathInLocale[0]);

            if (!isset($temp[$entityPathInLocale[0]])) {
                self::loadLocale($entityPathInLocale[0]);
                $temp = CACHE->getFromCache('_LOCALE', 0);
            }

            foreach ($entityPathInLocale as $key) {
                $key = TextHelper::camelCaseToSnakeCase($key);
                $temp = &$temp[$key];
            }
        }

        return $temp;
    }

    /** Получение названия поля объекта из локали */
    public static function getElementText(BaseEntity $entity, ElementItem $baseElement, LocalableFieldsEnum $propertyName): array|string|null
    {
        $entityName = TextHelper::camelCaseToSnakeCase($entity->name);
        $LOCALE = self::getLocale([$entityName, 'fraymModel', 'elements', TextHelper::camelCaseToSnakeCase($baseElement->name)]);

        return $LOCALE[$propertyName->value] ?? null;
    }

    /** Добавление окончания к глаголам в зависимости от пола пользователя */
    public static function declineVerb(BaseModel $userData): string
    {
        $LOCALE = self::getLocale(['fraym', 'decline']);

        if (property_exists($userData, 'gender') && $userData->gender->get() === 2) {
            return $LOCALE['verb']['female'];
        }

        return $LOCALE['verb']['male'];
    }

    /** Добавление окончаний к прилагательным среднего рода по количеству */
    public static function declineNeuterAdjective(int $count): string
    {
        $LOCALE = self::getLocale(['fraym', 'decline']);

        if ($count === 1) {
            $result = $LOCALE['neuter_adjective']['one'];
        } else {
            $result = $LOCALE['neuter_adjective']['other'];
        }

        return $result;
    }

    /** Добавление окончаний к прилагательным мужского рода по количеству */
    public static function declineMaleAdjective(int $count): string
    {
        $LOCALE = self::getLocale(['fraym', 'decline']);

        if ($count % 10 === 1) {
            $result = $LOCALE['male_adjective']['one'];
        } else {
            $result = $LOCALE['male_adjective']['other'];
        }

        return $result;
    }

    /** Добавление окончаний к словам мужского рода по количеству */
    public static function declineMale(int $count): string
    {
        $LOCALE = self::getLocale(['fraym', 'decline']);

        $result = '';

        if (($count < 20 && $count > 4) || $count === 0 || $count % 10 > 4) {
            $result = $LOCALE['male']['lots'];
        } elseif ($count % 10 === 1) {
            $result = $LOCALE['male']['one'];
        } elseif ($count % 10 > 1) {
            $result = $LOCALE['male']['little'];
        }

        return $result;
    }

    /** Добавление окончаний к словам женского рода по количеству */
    public static function declineFemale(int $count, int $case = 1): string
    {
        $LOCALE = self::getLocale(['fraym', 'decline']);

        $result = '';

        if (($count < 20 && $count > 4) || $count === 0 || $count % 10 > 4) {
            $result = $LOCALE['female']['lots'];
        } elseif ($count % 10 === 1) {
            $result = $LOCALE['female']['one'][$case];
        } elseif ($count % 10 > 1) {
            $result = $LOCALE['female']['little'];
        }

        return $result;
    }

    /** Добавление окончаний к словам среднего рода по количеству */
    public static function declineNeuter(int $count): string
    {
        $LOCALE = self::getLocale(['fraym', 'decline']);

        $result = '';

        if (($count <= 20 && $count > 4) || $count === 0 || $count % 10 > 4) {
            $result = $LOCALE['neuter']['lots'];
        } elseif ($count % 10 === 1) {
            $result = $LOCALE['neuter']['one'];
        } elseif ($count % 10 > 1) {
            $result = $LOCALE['neuter']['little'];
        }

        return $result;
    }

    /** Подгрузка файла локали */
    private static function loadLocale(string $entityName): void
    {
        if (!CookieHelper::getCookie('locale')) {
            CookieHelper::batchSetCookie(['locale' => 'RU']);
        }

        if ($entityName === 'fraym') {
            $filePath = INNER_PATH . 'lib/fraym/Locale/' . CookieHelper::getCookie('locale') . '.json';
        } elseif ($entityName === 'global') {
            $filePath = INNER_PATH . 'src/CMSVC/' . CookieHelper::getCookie('locale') . '.json';
        } else {
            $filePath = INNER_PATH . 'src/CMSVC/' . TextHelper::snakeCaseToCamelCase($entityName) . '/' . CookieHelper::getCookie('locale') . '.json';
        }
        $data = DataHelper::getJsonFile($filePath);

        if (!is_null($data)) {
            CACHE->setToCache(
                '_LOCALE',
                0,
                array_merge(
                    [$entityName => $data],
                    CACHE->getFromCache('_LOCALE', 0),
                ),
            );
        }
    }
}
