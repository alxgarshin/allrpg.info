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

namespace Fraym\Service;

use Fraym\Enum\OperandEnum;
use Fraym\Helper\{DataHelper, LocaleHelper};
use Fraym\Interface\Database;
use Generator;
use PDO;
use PDOException;
use PDOStatement;

final class SQLDatabaseService implements Database
{
    private PDO $DB;

    private array $lastQuery = [
        'stmt' => null,
        'query' => '',
        'data' => [],
    ];

    private array $preparedQueriesCache = [];

    /** Конструктор соединения */
    private function __construct()
    {
        try {
            $this->DB = new PDO(
                $_ENV['DATABASE_TYPE'] . ':' .
                    ($_ENV['DATABASE_NAME'] !== '' ? 'dbname=' . $_ENV['DATABASE_NAME'] . ';' : '') .
                    'host=' . $_ENV['DATABASE_HOST'] .
                    ';port=' . $_ENV['DATABASE_PORT'] .
                    ';charset=utf8mb4',
                $_ENV['DATABASE_USER'],
                $_ENV['DATABASE_PASSWORD'],
            );
        } catch (PDOException $e) {
            error_log("PDO initialize error: " . $e->getMessage());
            exit;
        }

        $this->DB->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** Создание или получение соединения в константу. По умолчанию: DB */
    public static function getInstance(string $constName = 'DB'): self
    {
        if (defined($constName)) {
            return constant($constName);
        } else {
            return self::forceCreate();
        }
    }

    /** Принудительное создание соединения. Рекомендуется использовать только в случае необходимости манипуляции двумя соединениями за раз. */
    public static function forceCreate(): self
    {
        return new self();
    }

    /** Подготовка запроса */
    public function prepare(string $query): PDOStatement|bool
    {
        $queryHash = DataHelper::hashData($query);

        if (isset($this->preparedQueriesCache[$queryHash])) {
            return $this->preparedQueriesCache[$queryHash];
        }

        try {
            $this->preparedQueriesCache[$queryHash] = $this->DB->prepare($query);

            return $this->preparedQueriesCache[$queryHash];
        } catch (PDOException $e) {
            ob_start();
            debug_print_backtrace();
            error_log(
                'PDO prepare error: ' . $query .
                    ' | Error: ' . $e->getMessage() .
                    ' | Backtrace: ' . ob_get_clean(),
            );
            exit;
        }
    }

    /** Исполнение PDO-запроса */
    public function execute(PDOStatement $statement, array $preparedData): void
    {
        try {
            $statement->execute($preparedData);
        } catch (PDOException $e) {
            $this->lastQuery = [];

            ob_start();
            debug_print_backtrace();
            error_log(
                'PDO execute error: ' . $statement->queryString .
                    ' | Error: ' . $e->getMessage() .
                    ' | Used data: ' . print_r($preparedData, true) .
                    ' | Backtrace: ' . ob_get_clean(),
            );
            exit;
        }
    }

    /** Исполнение запроса
     *
     * @param array<int, array{0: string, 1: mixed, 2?: ?array}> $data
     */
    public function query(
        ?string $query,
        array $data,
        bool $oneResult = false,
    ): false|array {
        if (!is_null($query)) {
            $preparedData = [];

            foreach ($data as $i => $dataBlock) {
                $key = $dataBlock[0];
                $value = $dataBlock[1] ?? null;
                $fieldParams = $dataBlock[2] ?? [];

                if (preg_match('#:' . $key . '\b#', $query)) {
                    if (is_array($value)) {
                        if (in_array(OperandEnum::JSON, $fieldParams)) {
                            $preparedData[$key] = DataHelper::filterInput($value, $fieldParams);
                        } else {
                            $in = "";
                            $i = 0;

                            foreach ($value as $item) {
                                $keyForIn = $key . $i++;
                                $in .= ($in ? ", " : "") . ':' . $keyForIn;
                                $preparedData[$keyForIn] = DataHelper::filterInput($item, $fieldParams);
                            }
                            $query = str_replace(':' . $key, $in, $query);
                        }
                    } else {
                        $preparedData[$key] = DataHelper::filterInput($value, $fieldParams);
                    }
                } else {
                    unset($data[$i]);
                }
            }

            $stmt = $this->prepare($query);

            $this->execute($stmt, $preparedData);

            $this->lastQuery = [
                'stmt' => $stmt,
                'query' => $query,
                'data' => $preparedData,
            ];

            return $oneResult ? $stmt->fetch() : $stmt->fetchAll();
        }

        return [];
    }

    /** Получение id последней добавленной записи. UUID генерятся на стороне бэкенда, а не в БД, поэтому эта команда вам не потребуется: у вас будет ваш новый uuid еще до insert'а */
    public function lastInsertId(?string $name = null): int|string|false
    {
        return $this->DB->lastInsertId($name);
    }

    /** Получение количества строк, задетых последней операцией в базе данных */
    public function rowCount(): int
    {
        return $this->lastQuery['stmt']->rowCount();
    }

    /** Построение where-части запроса
     * @return array{0: string, 1: array}
     */
    public function constructWhere(?array $criteria): array
    {
        $whereQuery = "";
        $whereParams = [];

        if ($criteria !== null) {
            $nameUsedInVariablesCount = [];

            foreach ($criteria as $key => $value) {
                if (!is_null($value) && (!is_int($key) || !is_null($value[1] ?? null))) {
                    $useNoValue = false;
                    $equalSign = "<=>";
                    $dbColumn = (is_array($value) && is_int($key)) ? $value[0] : $key;

                    if (is_array($value) && is_int($key) && !is_null($value[2] ?? null)) {
                        if (!is_array($value[2])) {
                            $value[2] = [$value[2]];
                        }

                        if (in_array(OperandEnum::LIKE, $value[2])) {
                            $equalSign = " LIKE ";
                        } elseif (in_array(OperandEnum::NOT_LIKE, $value[2])) {
                            $equalSign = " NOT LIKE ";
                        } elseif (in_array(OperandEnum::LESS, $value[2])) {
                            $equalSign = "<";
                        } elseif (in_array(OperandEnum::MORE, $value[2])) {
                            $equalSign = ">";
                        } elseif (in_array(OperandEnum::LESS_OR_EQUAL, $value[2])) {
                            $equalSign = "<=";
                        } elseif (in_array(OperandEnum::MORE_OR_EQUAL, $value[2])) {
                            $equalSign = ">=";
                        } elseif (in_array(OperandEnum::NOT_EQUAL, $value[2])) {
                            $equalSign = "!=";
                        } elseif (in_array(OperandEnum::IS_NULL, $value[2])) {
                            $equalSign = " IS NULL";
                            $useNoValue = true;
                        } elseif (in_array(OperandEnum::NOT_NULL, $value[2])) {
                            $equalSign = " IS NOT NULL";
                            $useNoValue = true;
                        }

                        if (in_array(OperandEnum::LOWER, $value[2])) {
                            $dbColumn = "LOWER(" . $dbColumn . ")";
                        } elseif (in_array(OperandEnum::UPPER, $value[2])) {
                            $dbColumn = "UPPER(" . $dbColumn . ")";
                        }
                    } elseif (is_array($value) && ((($value[1] ?? false) && is_array($value[1])) || !is_int($key))) {
                        $equalSign = " IN (";
                    }

                    if (is_array($value) && is_int($key)) {
                        if (($nameUsedInVariablesCount[$value[0]] ?? false) === false) {
                            $nameUsedInVariablesCount[$value[0]] = -1;
                        }
                        $nameUsedInVariablesCount[$value[0]]++;
                        $value[0] .= "_" . $nameUsedInVariablesCount[$value[0]];

                        $whereQuery .= ($whereQuery !== "" ? " AND " : "") . $dbColumn . $equalSign . ($useNoValue ? "" : ":" . $value[0]);
                        $whereParams[] = $value;
                    } else {
                        if (($nameUsedInVariablesCount[$key] ?? false) === false) {
                            $nameUsedInVariablesCount[$key] = -1;
                        }
                        $nameUsedInVariablesCount[$key]++;
                        $key .= "_" . $nameUsedInVariablesCount[$key];

                        $whereQuery .= ($whereQuery !== "" ? " AND " : "") . $dbColumn . $equalSign . ($useNoValue ? "" : ":" . $key);
                        $whereParams[] = [$key, $value];
                    }
                    $whereQuery .= ($equalSign === " IN (" ? ")" : "");
                }
            }

            if ($whereQuery !== "") {
                $whereQuery = " WHERE " . $whereQuery;
            }
        }

        return [$whereQuery, $whereParams];
    }

    /** Получение данных из таблицы */
    public function select(
        string $tableName,
        ?array $criteria = null,
        bool $oneResult = false,
        ?array $order = null,
        ?int $limit = null,
        ?int $offset = null,
        bool $onlyCount = false,
        ?array $fieldsSet = null,
    ): false|array {
        [$whereQuery, $params] = $this->constructWhere($criteria);

        $orderQuery = "";

        if (!is_null($order)) {
            $orderQuery = " ORDER BY " . implode(", ", array_filter($order));
        }
        $limitQuery = "";

        if (!is_null($limit)) {
            $limitQuery = " LIMIT " . $limit;
        }
        $offsetQuery = "";

        if (!is_null($offset)) {
            $offsetQuery = " OFFSET " . $offset;
        }

        $fieldsSetQuery = "*";

        if (!is_null($fieldsSet)) {
            $fieldsSetQuery = implode(', ', $fieldsSet);
        }

        return $this->query(
            query: "SELECT " . ($onlyCount ? "COUNT(*)" : $fieldsSetQuery) . " FROM " . $tableName . $whereQuery . $orderQuery . $limitQuery . $offsetQuery,
            data: $params,
            oneResult: $onlyCount ? true : $oneResult,
        );
    }

    /** Получение количества объектов таблицы из последнего PDOStatement'а */
    public function selectCount(): int
    {
        if ($this->lastQuery['query'] ?? false) {
            $query = $this->lastQuery['query'];

            if (preg_match("#SELECT#i", $query)) {
                $query = preg_replace("#SELECT (.*) FROM#i", "SELECT COUNT(*) FROM", $query);

                if (preg_match("# ORDER BY #i", $query)) {
                    $query = preg_replace("# ORDER BY (.*)$#i", "", $query);
                }

                if (preg_match("# LIMIT [\d+]#i", $query)) {
                    $query = preg_replace("# LIMIT [\d+](.*)$#i", "", $query);
                }

                if (preg_match("# OFFSET [\d+]#i", $query)) {
                    $query = preg_replace("# OFFSET [\d+](.*)$#i", "", $query);
                }

                $stmt = $this->prepare($query);

                $this->execute($stmt, $this->lastQuery['data']);

                return (int) $stmt->fetchColumn();
            }
        }

        return 0;
    }

    /** Получение количества объектов таблицы простым count в select'e */
    public function count(
        string $tableName,
        ?array $criteria = null,
    ): int {
        return $this->select(
            tableName: $tableName,
            criteria: $criteria,
            oneResult: false,
            order: null,
            limit: null,
            offset: null,
            onlyCount: true,
        )[0];
    }

    /** Вставка данных в таблицу */
    public function insert(
        string $tableName,
        array $data,
    ): false|array {
        if (count($data) > 0) {
            $params = [];

            $keys = [];

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $params[] = [$value[0], $value[1], $value[2] ?? null];
                    $keys[] = $value[0];
                } else {
                    $params[] = [$key, $value];
                    $keys[] = $key;
                }
            }

            $paramsListSql = implode(", ", $keys);
            $dataSql = ":" . implode(", :", $keys);
            $query = "INSERT INTO " . $tableName . " (" . $paramsListSql . ") VALUES (" . $dataSql . ")";

            return $this->query(
                query: $query,
                data: $params,
            );
        }

        return [];
    }

    /** Изменение данных в таблице */
    public function update(
        string $tableName,
        array $data,
        array $criteria,
    ): false|array {
        if (count($data) > 0) {
            $params = [];

            $keys = [];

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $params[] = [$value[0], $value[1], $value[2] ?? null];
                    $keys[] = $value[0] . "=:" . $value[0];
                } else {
                    $params[] = [$key, $value];
                    $keys[] = $key . "=:" . $key;
                }
            }

            $dataSql = implode(", ", $keys);

            [$whereQuery, $criteria] = $this->constructWhere($criteria);
            $params = array_merge($params, $criteria);

            $query = "UPDATE " . $tableName . " SET " . $dataSql . $whereQuery;

            return $this->query(
                query: $query,
                data: $params,
            );
        }

        return [];
    }

    /** Удаление из таблицы */
    public function delete(
        string $tableName,
        array $criteria,
    ): false|array {
        if (count($criteria) > 0) {
            [$whereQuery, $params] = $this->constructWhere($criteria);

            /** @noinspection SqlWithoutWhere */
            $query = "DELETE FROM " . $tableName . $whereQuery;

            return $this->query(
                query: $query,
                data: $params,
            );
        }

        return [];
    }

    /** Исполнение скрипта данных без дополнительной фильтрации: использовать крайне осторожно */
    public function exec(string $SQL): true
    {
        $this->DB->exec($SQL);

        return true;
    }

    /** Начало транзакции */
    public function beginTransaction(): bool
    {
        return $this->DB->beginTransaction();
    }

    /** Коммит транзакции */
    public function commit(): bool
    {
        return $this->DB->commit();
    }

    /** Откат транзакции */
    public function rollBack(): bool
    {
        return $this->DB->rollBack();
    }

    /** Получение объекта на основе его id */
    public function findObjectById(
        string|int $objId,
        string $objType,
        bool $refresh = false,
        bool $bySid = false,
    ): ?array {
        $objType = DataHelper::clearBraces($objType);

        if ($objId) {
            $useTableColumn = $bySid ? 'sid' : 'id';

            if (!$refresh) {
                $checkData = CACHE->getFromCache($objType, $objId, $useTableColumn);

                if (!is_null($checkData)) {
                    return $checkData;
                }
            }

            $objData = $this->select(
                tableName: $objType,
                criteria: [
                    $useTableColumn => $objId,
                ],
                oneResult: true,
            );

            if ($objData === false) {
                $objData = null;
            }

            if ($objData['id'] ?? false) {
                CACHE->setToCache($objType, $objData['id'], $objData);

                if ($bySid) {
                    CACHE->setToCache($objType, $objData['sid'], $objData, $useTableColumn);
                }
            }

            return $objData;
        }

        return null;
    }

    /** Получение объектов на основе их id */
    public function findObjectsByIds(
        array $objIds,
        string $objType,
        bool $refresh = false,
    ): ?Generator {
        $objType = DataHelper::clearBraces($objType);

        if (count($objIds) > 0) {
            if (!$refresh) {
                foreach ($objIds as $key => $objId) {
                    $checkData = CACHE->getFromCache($objType, $objId);

                    if (!is_null($checkData)) {
                        yield $checkData;
                        unset($objIds[$key]);
                    }
                }
            }

            if (count($objIds) > 0) {
                $result = $this->select(
                    tableName: $objType,
                    criteria: [
                        'id' => $objIds,
                    ],
                );

                foreach ($result as $objData) {
                    CACHE->setToCache($objType, $objData['id'], $objData);
                    yield $objData;
                }
            }
        }

        return null;
    }

    /** Превращение в массив генератора соотношений данных из БД (id к name, например) */
    public function getArrayOfItemsAsArray(
        string $query,
        string $id,
        string|array|null $fields = null,
        bool $nodata = true,
    ): array {
        return iterator_to_array($this->getArrayOfItems($query, $id, $fields, $nodata));
    }

    /** Создание массива соотношений данных из БД (id к name, например) */
    public function getArrayOfItems(
        string $query,
        string $id,
        string|array|null $fields = null,
        bool $nodata = true,
    ): Generator {
        $level = 0;

        $data = $this->query(
            query: "SELECT * FROM " . $query,
            data: [],
        );

        foreach ($data as $item) {
            $key = DataHelper::checkNumeric($item[$id]);

            if (is_null($fields)) {
                $value = [$key];
            } elseif (!is_array($fields)) {
                $value = [
                    $key,
                    DataHelper::checkNumeric($item[$fields]),
                    $level,
                    ($nodata ? null : $item),
                ];
            } else {
                $whicher = [];

                foreach ($fields as $field) {
                    $whicher[] = (isset($item[$field]) ? DataHelper::checkNumeric($item[$field]) : $field);
                }

                $value = [
                    $key,
                    implode(' ', $whicher),
                    $level,
                    ($nodata ? null : $item),
                ];
            }

            yield $key => $value;
        }
    }

    /** Создание дерева объектов из БД на основе родительского идентификатора */
    public function getTreeOfItems(
        bool $empty,
        string $table,
        string $where,
        string|int|null $whereequal,
        ?string $and,
        ?string $order,
        int $level,
        string $id,
        string $fieldName,
        int $maxlevel,
        bool $nodata = true,
    ): array {
        $LOCALE = LocaleHelper::getLocale(['fraym', 'basefunc']);

        $objectsTree = [];

        if ($empty) {
            $objectsTree[] = ['0', $LOCALE['top_level'], 0];
        }

        /** Защита от пустого IN без идентификаторов, прилетевшего в and */
        if (mb_stripos($and, 'IN ()') === false) {
            //если есть лимит, забираем его, чтобы потом отсеять результаты вручную
            $limitFrom = false;
            $limitCount = false;

            if (str_contains($order, 'LIMIT')) {
                preg_match('#LIMIT\s*(\d*)\s*OFFSET\s*(\d*)#', $order, $match);

                if (isset($match[1])) {
                    $limitFrom = (int) $match[2];
                } else {
                    preg_match('#LIMIT\s*(\d*)#', $order, $match);
                    $limitFrom = 0;
                }
                $limitCount = (int) $match[1];
                $order = preg_replace('# LIMIT\s*\d*\s*OFFSET\s*\d*#', '', $order);
                $order = preg_replace('# LIMIT\s*\d*#', '', $order);
            }

            $and = $and !== '' ? " WHERE " . (str_starts_with($and, ' AND ') ? preg_replace('# AND #', '', $and, 1) : $and) : "";
            $query = "SELECT " . (str_contains($table, " AS t1") ? "t1." : "") . "* FROM " . $table . $and . " ORDER BY " . $order;

            $fullDataArray = [];
            $data = $this->query(
                query: $query,
                data: [],
            );

            foreach ($data as $item) {
                if ($item[$id] === '') {
                    $item[$id] = $LOCALE['not_set'];
                }

                if ($item[$fieldName] === '') {
                    $item[$fieldName] = $LOCALE['not_set'];
                }
                $fullDataArray[] = $item;
            }

            /** Собираем данные верхнего уровня */
            $mainDataFound = 0;

            foreach ($fullDataArray as $key => $fullData) {
                if ($fullData[$where] === $whereequal) {
                    if (
                        ($limitFrom === false && $limitCount === false) ||
                        ($limitFrom !== false && $limitCount !== false && $mainDataFound >= $limitFrom && $mainDataFound < $limitFrom + $limitCount)
                    ) {
                        $objectsTree[] = [
                            DataHelper::checkNumeric($fullData[$id]),
                            DataHelper::checkNumeric($fullData[$fieldName]),
                            $level,
                            ($nodata ? null : $fullData),
                        ];
                    }
                    $mainDataFound++;
                    unset($fullDataArray[$key]);
                }
            }
            $level++;

            /** Выстраиваем нужное дерево */
            while ($level <= $maxlevel) {
                $noObjectsFoundOnLevel = true;

                $insertedRows = 0;

                foreach ($objectsTree as $parentKey => $parentData) {
                    if ($parentData[2] === $level - 1) {
                        $insertData = [];

                        foreach ($fullDataArray as $key => $fullData) {
                            if ($fullData[$where] === $parentData[0]) {
                                $insertData[$fullData[$id]] = [
                                    $fullData[$id],
                                    $fullData[$fieldName],
                                    $level,
                                    ($nodata ? null : $fullData),
                                ];
                                unset($fullDataArray[$key]);
                                $noObjectsFoundOnLevel = false;
                            }
                        }
                        $objectsTree = array_merge(
                            array_slice($objectsTree, 0, $parentKey + 1 + $insertedRows),
                            $insertData,
                            array_slice($objectsTree, $parentKey + 1 + $insertedRows),
                        );

                        $insertedRows += count($insertData);
                    }
                }

                if ($noObjectsFoundOnLevel) {
                    break;
                } else {
                    $level++;
                }
            }
        }

        return $objectsTree;
    }

    /** Удаление объектов из созданного дерева, чтобы остались только отобранные id и их parent'ы до верхнего уровня. При этом у каталоговых сущностей
     * оставляем их наследников также. */
    public function chopOffTreeOfItemsBranches(
        array $objectsTree,
        array $listOfIds,
        string $fieldWithParentId,
    ): array {
        $keepItemsIds = [];

        foreach ($listOfIds as $idToFind) {
            foreach ($objectsTree as $key => $objectsTreeItem) {
                /** Находим ветку, где лежат данные */
                if ($idToFind === $objectsTreeItem[0]) {
                    $keepItemsIds[] = $idToFind;

                    /** Находим всех наследующих */
                    $childKey = $key;
                    $lookingForChilds = true;

                    while ($lookingForChilds) {
                        $childKey++;

                        if (($objectsTree[$childKey] ?? false) && $objectsTree[$childKey][2] > $objectsTreeItem[2]) {
                            $keepItemsIds[] = $objectsTree[$childKey][0];
                        } else {
                            $lookingForChilds = false;
                        }
                    }

                    /** Находим всех родителей */
                    $parentId = (int) $objectsTreeItem[3][$fieldWithParentId];
                    $keepItemsIds[] = $parentId;
                    $parentKey = $key;

                    while ($parentId > 0) {
                        $parentKey--;

                        if ($objectsTree[$parentKey] ?? false) {
                            $previousItemData = $objectsTree[$parentKey];

                            if ($parentId === $previousItemData[0]) {
                                $parentId = (int) $previousItemData[3][$fieldWithParentId];
                                $keepItemsIds[] = $parentId;
                            }
                        } else {
                            break;
                        }
                    }

                    break;
                }
            }
        }
        $keepItemsIds = array_unique($keepItemsIds);

        /** Удаляем все объекты, для которых мы не нашли связь */
        foreach ($objectsTree as $key => $objectsTreeItem) {
            if (!in_array($objectsTreeItem[0], $keepItemsIds)) {
                unset($objectsTree[$key]);
            }
        }

        return $objectsTree;
    }
}
