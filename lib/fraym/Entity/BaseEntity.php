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

namespace Fraym\Entity;

use Fraym\BaseObject\{BaseController, BaseModel, BaseView};
use Fraym\Element\Item\{Calendar, Checkbox, Email, File, H1, Login, Multiselect, Number, Password, Timestamp};
use Fraym\Entity\Trait\PageCounter;
use Fraym\Enum\{ActEnum, ActionEnum, MultiObjectsEntitySubTypeEnum, SubstituteDataTypeEnum};
use Fraym\Helper\{AuthHelper, CookieHelper, DataHelper, DateHelper, LocaleHelper, ObjectsHelper, ResponseHelper, TextHelper};
use Fraym\Interface\{DeletedAt, ElementItem, Response};
use Fraym\Response\{ArrayResponse, HtmlResponse};
use Fraym\Service\GlobalTimerService;
use PDOException;

abstract class BaseEntity
{
    use PageCounter;

    /** Языковая локаль сущности */
    public ?array $LOCALE {
        get => $this->LOCALE;
        set => $this->LOCALE = LocaleHelper::getLocale($value);
    }

    /** Фильтры */
    public ?Filters $filters = null {
        get {
            /** У наследующих сущностей каталога нет своих фильтров: они подчинены фильтрам родительской сущности */
            if (!($this instanceof CatalogItemEntity) && $this->filters === null) {
                $this->filters = new Filters($this);
            }

            return $this->filters;
        }
        set => $this->filters = $value;
    }

    /** Вьюшка, к которой привязан данный instance BaseEntity */
    public BaseView $view;

    /** Массив найденных при последнем запросе id сущностей */
    public array $listOfFoundIds = [];

    /** Перевернутые для удобства массивы сортировки */
    public array $rotatedArrayIndexes = [];

    /** Все ошибки валидации в формате: [класс валидатора => [строка запроса => [номер группы (-1, если нет) => [непрошедший элемент]]]] */
    public array $validationErrors = [];

    /** Отформатированные данные после валидации */
    public array $dataAfterValidation = [];

    /** Подготовленные сообщения в результате стандартных действий: create, change и delete */
    public array $fraymActionMessages = [];

    /** Путь, по которому нужно перенаправить пользователя по завершению стандартного действия */
    public ?string $fraymActionRedirectPath = null;

    public ?BaseModel $model {
        get => $this->view->model;
    }

    public ?BaseController $controller {
        get => $this->view->controller;
    }

    /**
     * @param EntitySortingItem[] $sortingData
     */
    public function __construct(
        /** Имя сущности, чаще всего совпадающее с URL раздела на сайте */
        public string $name,

        /** Таблица данных сущности */
        public string $table,

        /** Информация по сортировке данных сущности */
        public array $sortingData,

        /** Опциональный параметр, указывающий на колонку, в которой нужно хранить данные JSON-виртуальных полей, сделанных конструктором */
        public ?string $virtualField = null,

        /** Количество выводимых на страницу строк в объекте */
        public ?int $elementsPerPage = 50,

        /** Использовать для просмотра сушности view из CMSVC. В ином случае просмотр приравнен к редактированию объекта. */
        public bool $useCustomView = false,

        /** Использовать для списка сущностей view из CMSVC. В ином случае будет применен автоматический view. */
        public bool $useCustomList = false,

        /** В какой ACT (тип карточки сущности) осуществляется по умолчанию переход из общего списка сущностей? */
        public ActEnum $defaultItemActType = ActEnum::edit,

        /** В какой ACT попадает по умолчанию пользователь при переходе на список сущностей? */
        public ActEnum $defaultListActType = ActEnum::list,
    ) {
        foreach ($this->sortingData as $sortingData) {
            $sortingData->entity = $this;
        }
    }

    abstract public function viewActList(array $DATA_FILTERED_BY_CONTEXT): string;

    abstract public function viewActItem(array $DATA_ITEM, ?ActEnum $act = null, ?string $contextName = null): string;

    public function addEntitySortingData(EntitySortingItem $entitySortingItem): self
    {
        $entitySortingItem->entity = $this;

        $this->sortingData[] = $entitySortingItem;

        return $this;
    }

    public function insertEntitySortingData(EntitySortingItem $entitySortingItem, int $offset): self
    {
        $entitySortingItem->entity = $this;

        $sortingData = $this->sortingData;
        array_splice(
            $sortingData,
            $offset,
            0,
            [$entitySortingItem],
        );
        $this->sortingData = $sortingData;

        return $this;
    }

    public function addFraymActionMessage(array $fraymActionMessage): static
    {
        $this->fraymActionMessages[] = $fraymActionMessage;

        return $this;
    }

    public function getObjectName(?BaseEntity $activeEntity = null): ?string
    {
        return $this->getFraymModelLocale($activeEntity)['object_name'] ?? null;
    }

    public function getObjectMessages(?BaseEntity $activeEntity = null): ?array
    {
        return $this->getFraymModelLocale($activeEntity)['object_messages'] ?? null;
    }

    public function getElementsLocale(?BaseEntity $activeEntity = null): ?array
    {
        return $this->getFraymModelLocale($activeEntity)['elements'] ?? null;
    }

    public function getNameUsedInLocale(): string
    {
        return TextHelper::camelCaseToSnakeCase($this->name);
    }

    public function asHtml(?string $html, ?string $pagetitle): ?HtmlResponse
    {
        return !is_null($html) ? new HtmlResponse($html, $pagetitle) : null;
    }

    public function asArray(?array $data): ?ArrayResponse
    {
        return !is_null($data) ? new ArrayResponse($data) : null;
    }

    public function fraymAction(bool $doNotUseActionResponse = false, bool $useFixedId = false): ?Response
    {
        $FRAYM_ACTIONS_LOCALE = LocaleHelper::getLocale(['fraym', 'fraymActions']);

        $service = $this->view->CMSVC?->service;

        $objectRights = $this->view->viewRights;

        /** Проверка авторизации пользователя */
        if (
            match (ACTION) {
                ActionEnum::create => !$objectRights->addRight,
                ActionEnum::change => !$objectRights->changeRight,
                ActionEnum::delete => !$objectRights->deleteRight,
                default => false,
            }
            ||
            (!CURRENT_USER->isLogged() && !is_null(AuthHelper::getRefreshTokenCookie()))
        ) {
            ResponseHelper::response401();
        }

        /** Определяем последовательные номера всех блоков пришедших значений. Если используется $useFixedId = true, то берем данные из $_REQUEST[0] */
        $dataStringsIds = $useFixedId ? [0] : array_keys(ID);
        $dataStringsIds = $dataStringsIds === [] ? [0] : $dataStringsIds;

        /** Предействие из сервиса, если есть */
        if (!is_null($service)) {
            match (ACTION) {
                ActionEnum::create => $service->preCreate ? $service->{$service->preCreate}() : null,
                ActionEnum::change => $service->preChange ? $service->{$service->preChange}() : null,
                ActionEnum::delete => $service->preDelete ? $service->{$service->preDelete}() : null,
                default => null,
            };
        }

        /** Валидация */
        $globalValidationSuccess = true;
        $troubledStrings = [];
        $troubledElements = [];
        $activeEntity = $this;

        $objectName = $this->view->CMSVC->objectName ?? $activeEntity->name;

        if ($this instanceof CatalogEntity && TextHelper::camelCaseToSnakeCase($this->catalogItemEntity->name) === CMSVC) {
            $activeEntity = $this->catalogItemEntity;
            $objectName = $activeEntity->name;
        }

        if (
            (ACTION === ActionEnum::create && $objectRights->addRight) ||
            (ACTION === ActionEnum::change && $objectRights->changeRight)
        ) {
            $groupsMaxValues = [];

            $act = ACTION === ActionEnum::create ? ActEnum::add : ActEnum::edit;

            foreach ($dataStringsIds as $dataStringId) {
                $checkReadOnly = $_REQUEST['readonly'][$dataStringId] ?? null;

                if (is_null($checkReadOnly)) {
                    foreach ($activeEntity->model->elementsList as $element) {
                        if ($element->checkWritable($act, $objectName)) {
                            if (!$element->getNoData()) {
                                $elementValue = $_REQUEST[$element->name][$dataStringId] ?? ($element->getGroup() ? [] : null);

                                if ($element->getGroup()) {
                                    /** Определяем максимальные порядковые номера заполненных полей в каждой из групп полей */
                                    foreach ($this->model->elementsList as $groupElement) {
                                        if (!is_null($groupElement->getGroup())) {
                                            /** Сначала выясняем количество непустых строк (максимальный id строки) в группе */
                                            if (!($groupsMaxValues[$dataStringId] ?? false)) {
                                                $groupsMaxValues[$dataStringId] = [];
                                            }

                                            if (!($groupsMaxValues[$dataStringId][$groupElement->getGroup()] ?? false)) {
                                                $groupsMaxValues[$dataStringId][$groupElement->getGroup()] = 0;

                                                foreach ($this->model->elementsList as $groupCheckField) {
                                                    if ($groupCheckField->getGroup() === $groupElement->getGroup() && !$groupCheckField->getNoData()) {
                                                        $max = 0;
                                                        $stringsKeys = array_keys($_REQUEST[$groupCheckField->name][$dataStringId] ?? []);

                                                        if ($stringsKeys) {
                                                            $max = (int) max($stringsKeys);
                                                        }

                                                        /** Проверяем реверсивно все поступившие значения по ключам, чтобы понять, в какой самой большой строке у
                                                         * данного поля реально есть данные: таким образом, отсекаем лишние, полностью пустые группы
                                                         */
                                                        for ($i = $max; $i >= 0; $i--) {
                                                            if ($_REQUEST[$groupCheckField->name][$dataStringId][$i] ?? false) {
                                                                $max = $i;
                                                                break;
                                                            }
                                                        }

                                                        if (
                                                            $max > $groupsMaxValues[$dataStringId][$groupElement->getGroup()] &&
                                                            ($_REQUEST[$groupCheckField->name][$dataStringId][$max] ?? false)
                                                        ) {
                                                            $groupsMaxValues[$dataStringId][$groupElement->getGroup()] = $max;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    $groupElementValues = [];

                                    for ($groupId = 0; $groupId <= $groupsMaxValues[$dataStringId][$element->getGroup()]; $groupId++) {
                                        $groupElementValue = $elementValue[$groupId] ?? null;
                                        $groupElementValue = $groupElementValue === '' ? null : $groupElementValue;
                                        $options = $this->prepareValidationOptions($element, $dataStringId, $groupId);
                                        $failedValidatorsNames = $element->validate($groupElementValue, $options);

                                        if (count($failedValidatorsNames) > 0) {
                                            $globalValidationSuccess = false;

                                            foreach ($failedValidatorsNames as $failedValidatorName) {
                                                $this->appendValidationErrors($failedValidatorName, $dataStringId, $groupId, $element);
                                            }
                                        } elseif (!is_null($groupElementValue)) {
                                            $groupElementValues[$groupId] = $groupElementValue;
                                        }
                                    }

                                    $this->appendDataAfterValidation(
                                        $dataStringId,
                                        $element,
                                        DataHelper::jsonFixedEncode($groupElementValues),
                                        $act,
                                        true,
                                    );
                                } else {
                                    $elementValue = $elementValue === '' ? null : $elementValue;
                                    $options = $this->prepareValidationOptions($element, $dataStringId);
                                    $failedValidatorsNames = $element->validate($elementValue, $options);

                                    if (count($failedValidatorsNames) > 0) {
                                        $globalValidationSuccess = false;

                                        foreach ($failedValidatorsNames as $failedValidatorName) {
                                            $this->appendValidationErrors($failedValidatorName, $dataStringId, -1, $element);
                                        }
                                    } else {
                                        $this->appendDataAfterValidation(
                                            $dataStringId,
                                            $element,
                                            $elementValue,
                                            $act,
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            /** Подготовка массива ошибок валидации */
            if (!$globalValidationSuccess) {
                $validationErrors = $this->validationErrors;

                foreach ($validationErrors as $validatorClass => $validationError) {
                    /** @var class-string $validatorClass */
                    $this->addFraymActionMessage(['error', $validatorClass::getMessage($validationError)]);

                    foreach ($validationError as $stringId => $groupData) {
                        $troubledStrings[] = $stringId;

                        foreach ($groupData as $groupId => $elementsArray) {
                            foreach ($elementsArray as $element) {
                                $troubledElements[] = $element->name . '[' . $stringId . ']' . ($groupId > 0 ? '[' . $groupId . ']' : '');
                            }
                        }
                    }
                }
            }
        }

        if ($globalValidationSuccess) {
            /** Действие */
            $data = $this->dataAfterValidation;

            if (ACTION !== ActionEnum::delete) {
                if ($this->virtualField) {
                    foreach ($dataStringsIds as $dataStringId) {
                        $stringVirtualDataString = '';
                        $stringVirtualDataArray = $data[$dataStringId][$this->virtualField];

                        foreach ($stringVirtualDataArray as $stringVirtualDataItem) {
                            $stringVirtualDataString .= '[' . $stringVirtualDataItem[0]->name . '][' . $stringVirtualDataItem[1] . ']' . chr(13) . chr(10);
                        }
                        $data[$dataStringId][$this->virtualField] = $stringVirtualDataString;
                    }
                }
            }

            $successfulResultsIds = [];

            if (ACTION === ActionEnum::create && $objectRights->addRight) {
                $hasErrors = false;

                foreach ($dataStringsIds as $dataStringId) {
                    if (!in_array($dataStringId, $troubledStrings)) {
                        $stringData = $data[$dataStringId];
                        $checkData = $stringData;

                        foreach ($activeEntity->model->elementsList as $element) {
                            if ($element instanceof Timestamp) {
                                unset($checkData[$element->name]);
                            }
                        }
                        $checkDoubledSaveItem = DB->select($this->table, $checkData, true);

                        if (!$checkDoubledSaveItem || (($checkDoubledSaveItem['created_at'] ?? false) && $checkDoubledSaveItem['created_at'] < (time() - 30))) {
                            DB->insert($this->table, $stringData);
                            $successfulResultsIds[] = DB->lastInsertId();

                            if (!$doNotUseActionResponse) {
                                $this->addFraymActionMessage(['success', $this->getObjectMessages($activeEntity)[0]]);
                            }
                        } else {
                            $hasErrors = true;
                            $this->addFraymActionMessage(['error', $FRAYM_ACTIONS_LOCALE['blocked_resave']]);
                        }
                    }
                }

                if (!$hasErrors) {
                    $this->fraymActionRedirectPath = ResponseHelper::redirectConstruct();
                }
            } elseif (ACTION === ActionEnum::change  && $objectRights->changeRight) {
                $successfullySavedStringIds = [];

                foreach ($dataStringsIds as $dataStringId) {
                    if (!in_array($dataStringId, $troubledStrings)) {
                        $stringData = $data[$dataStringId] ?? [];
                        $id = $_REQUEST['id'][$dataStringId] ?? null;

                        if (!is_null($id)) {
                            if (!is_null($objectRights->changeRestrict)) {
                                $result = DB->query(
                                    'SELECT * FROM ' . $this->table . ' WHERE ' . $objectRights->changeRestrict . ' AND id=:id',
                                    [['id', $id]],
                                    true,
                                );
                            } else {
                                $result = DB->select($this->table, ['id' => $id], true);
                            }

                            if ($result) {
                                try {
                                    foreach ($activeEntity->model->elementsList as $element) {
                                        if ($element instanceof File) {
                                            unset($fileNames);
                                            preg_match_all('#{([^:]+):([^}]+)}#', ($result[$element->name] ?? ''), $fileNames);

                                            foreach ($fileNames[2] as $fileName) {
                                                if (!preg_match('#:' . $fileName . '}#', ($stringData[$element->name] ?? ''))) {
                                                    $element->remove($fileName);
                                                }
                                            }
                                        }
                                    }

                                    DB->update($this->table, $stringData, ['id' => $id]);
                                    $successfulResultsIds[] = $id;
                                    $successfullySavedStringIds[] = $dataStringId + 1;
                                } catch (PDOException) {
                                    $this->addFraymActionMessage(['error', sprintf($FRAYM_ACTIONS_LOCALE['update_error'], $dataStringId + 1)]);
                                }
                            }
                        } else {
                            $this->addFraymActionMessage(['error', sprintf($FRAYM_ACTIONS_LOCALE['not_found_id_in_data'], $dataStringId + 1)]);
                        }
                    }
                }

                if (count($successfullySavedStringIds) > 0) {
                    $sequenceStarted = false;
                    $message = '';
                    $i = 0;

                    foreach ($successfullySavedStringIds as $successfullySavedStringId) {
                        $nextStringId = next($successfullySavedStringIds);

                        if ($i === 0) {
                            $message = $successfullySavedStringId - 1;

                            if ($nextStringId === $successfullySavedStringId + 1) {
                                $message .= '-';
                                $sequenceStarted = true;
                            } elseif (isset($nextStringId)) {
                                $message .= ', ';
                                $sequenceStarted = false;
                            }
                        } elseif ($i === count($successfullySavedStringIds) - 1) {
                            $message .= $successfullySavedStringId - 1;
                        } elseif ($nextStringId > $successfullySavedStringId + 1) {
                            $message .= ($successfullySavedStringId - 1) . ', ';
                            $sequenceStarted = false;
                        } elseif ($nextStringId === $successfullySavedStringId + 1) {
                            if (!$sequenceStarted) {
                                $message .= ($successfullySavedStringId - 1) . '-';
                                $sequenceStarted = true;
                            }
                        }
                        $i++;
                    }

                    if (!$doNotUseActionResponse) {
                        $this->addFraymActionMessage([
                            'success',
                            $this->getObjectMessages($activeEntity)[1] .
                                (count($successfullySavedStringIds) > 1 ? ' ' . $FRAYM_ACTIONS_LOCALE['in_strings'] . $message . '.' : ''),
                        ]);
                    }

                    $checkRedirectPath = ResponseHelper::redirectConstruct(true);

                    if (!is_null($checkRedirectPath)) {
                        $this->fraymActionRedirectPath = $checkRedirectPath;
                    }
                }
            } elseif (ACTION === ActionEnum::delete && $objectRights->deleteRight) {
                $arrayOfIds = $useFixedId ? [0] : ID;

                foreach ($arrayOfIds as $key => $id) {
                    if (!is_null($id)) {
                        if (!is_null($objectRights->deleteRestrict)) {
                            $result = DB->query(
                                'SELECT * FROM ' . $this->table . ' WHERE ' . $objectRights->deleteRestrict . ' AND id=:id',
                                [['id', $id]],
                                true,
                            );
                        } else {
                            $result = DB->select(
                                tableName: $this->table,
                                criteria: [
                                    'id' => $id,
                                ],
                                oneResult: true,
                            );
                        }

                        if ($result) {
                            try {
                                $isCatalog = $this instanceof CatalogInterface && $this->detectEntityType($result) instanceof CatalogEntity;

                                if ($isCatalog) {
                                    $catalogEntity = $this instanceof CatalogItemEntity ? $this->catalogEntity : $this;
                                    $catalogEntity->clearDataByParent($id);
                                    $this->addFraymActionMessage(['success', $this->getObjectMessages($catalogEntity)[3]]);
                                } else {
                                    $this->deleteItem($id);

                                    $successfulResultsIds[] = $id;

                                    if (!$doNotUseActionResponse) {
                                        $this->addFraymActionMessage(['success', $this->getObjectMessages($activeEntity)[2]]);
                                    }

                                    if ($this instanceof MultiObjectsEntity && !$doNotUseActionResponse) {
                                        $this->addFraymActionMessage(['success_delete', $id]);
                                    }
                                }
                            } catch (PDOException) {
                                $this->addFraymActionMessage(['error', sprintf($FRAYM_ACTIONS_LOCALE['delete_error'], $key + 1)]);
                            }
                        }
                    }
                }

                if (!$this instanceof MultiObjectsEntity) {
                    $this->fraymActionRedirectPath = ResponseHelper::redirectConstruct(false, true);
                }
            }

            /** Постдействие из сервиса, если есть */
            if (!is_null($service)) {
                match (ACTION) {
                    ActionEnum::create => $service->postCreate ? $service->{$service->postCreate}($successfulResultsIds) : null,
                    ActionEnum::change => $service->postChange ? $service->{$service->postChange}($successfulResultsIds) : null,
                    ActionEnum::delete => $service->postDelete ? $service->{$service->postDelete}($successfulResultsIds) : null,
                    default => null,
                };
            }
        }

        /** Вывод сообщений и указателей на проблемные строки-объекты (если есть), если вывод не заблокирован параметром $doNotUseActionResponse */
        if (!$doNotUseActionResponse) {
            $messages = $this->fraymActionMessages;
            $cookieMessages = CookieHelper::getCookie('messages', true);

            if ($cookieMessages) {
                $messages = array_merge($messages, $cookieMessages);
                CookieHelper::batchDeleteCookie(['messages']);
            }

            $errouneousFields = $this instanceof MultiObjectsEntity && $this->subType === MultiObjectsEntitySubTypeEnum::Excel ?
                $troubledStrings :
                $troubledElements;

            return ResponseHelper::response($messages, $this->fraymActionRedirectPath, $errouneousFields);
        }

        return null;
    }

    /** HTML или array вывод данных на выдачу */
    public function view(?ActEnum $act = null, int|string|null $id = null, ?string $contextName = null): ?Response
    {
        $OBJECT_LOCALE = LocaleHelper::getLocale([$this->getNameUsedInLocale()]);
        $FILTERS_LOCALE = LocaleHelper::getLocale(['fraym', 'filters']);

        if ($this instanceof CatalogItemEntity) {
            $OBJECT_LOCALE = $CATALOG_LOCALE = LocaleHelper::getLocale([$this->catalogEntity->getNameUsedInLocale() . '/' . $this->getNameUsedInLocale()]);
            $PAGETITLE = $CATALOG_LOCALE['global']['title'] ?? '';
        } else {
            $PAGETITLE = $OBJECT_LOCALE['global']['title'] ?? '';
        }

        $RESPONSE_DATA = '';
        $RESPONSE_ARRAY = [];

        $LIST_OF_FOUND_IDS = [];

        if ($_ENV['GLOBALTIMERDRAWREPORT']) {
            $_GLOBALTIMERDRAWREPORT = new GlobalTimerService();
        }

        if (is_null($act)) {
            $act = DataHelper::getActDefault($this);
        }

        if (is_null($id)) {
            $id = DataHelper::getId();
        }

        if ($this->view->viewRights->viewRight) {
            if ($act === ActEnum::list) {
                $filtersHtml = $this->filters->getFiltersHtml();

                if ($_ENV['GLOBALTIMERDRAWREPORT']) {
                    $RESPONSE_DATA .= $_GLOBALTIMERDRAWREPORT->getTimerDiffStr('<!-- filters prepare time: %ss-->');
                }

                $maxItemsOnPage = $this->elementsPerPage;

                if (is_null($maxItemsOnPage)) {
                    $this->elementsPerPage = $maxItemsOnPage = 10000;
                }

                $mainTablePrefix = "t1.";

                $preparedViewRestrictSql = $this->view->viewRights->viewRestrict;

                if (is_null($preparedViewRestrictSql)) {
                    $this->view->viewRights->viewRestrict = null;
                } else {
                    $preparedViewRestrictSql = preg_replace(
                        '# (and|or) (\(?)#i',
                        ' $1 $2' . $mainTablePrefix,
                        $preparedViewRestrictSql,
                    );
                    $preparedViewRestrictSql = preg_replace('#^(\(?)#', '$1' . $mainTablePrefix, $preparedViewRestrictSql);
                    $preparedViewRestrictSql = " WHERE " . $preparedViewRestrictSql;
                }

                [$ORDER, $leftJoinedTablesSql, $leftJoinedFieldsSql] = $this->getOrderString($this->sortingData, $mainTablePrefix);

                $QUERY_PARAMS = [];

                $filtersQueryParams = $this->filters->getPreparedSearchQueryParams();

                if (count($filtersQueryParams) > 0) {
                    $QUERY_PARAMS = $filtersQueryParams;
                }

                $QUERY = "SELECT t1.*" . $leftJoinedFieldsSql . " FROM " . $this->table . " AS t1" . $leftJoinedTablesSql . $preparedViewRestrictSql;

                if (!is_null($preparedViewRestrictSql) && $this->filters->getPreparedSearchQuerySql() !== "") {
                    $QUERY .= " AND";
                }
                $QUERY .= $this->filters->getPreparedSearchQuerySql() .
                    ($ORDER !== "" ? " ORDER BY " . $ORDER : "");

                /** В случае сущности-каталога необходимо провести полную пересборку списка полученных результатов: нужно получить полное дерево до
                 * соответствующих объектов, если были фильтры, или же просто полный список подобъектов, найденных по запросу.
                 */
                if ($this instanceof CatalogEntity) {
                    $DATA = DB->query($QUERY, $QUERY_PARAMS);

                    /** Записываем все id, которые были найдены нативным запросом: в дальнейшем это понадобится для понимания, какой из элементов каталога
                     * был найден в результате поиска, а какой был найден при создании структуры до найденных элементов.
                     */
                    $catalogEntityFoundIds = [];

                    foreach ($DATA as $ITEM) {
                        $catalogEntityFoundIds[] = $ITEM['id'];
                    }
                    $this->catalogEntityFoundIds = $catalogEntityFoundIds;

                    /** Формируем полное дерево объектов */

                    /** @var CatalogItemEntity $catalogItemEntity */
                    $catalogItemEntity = $this->catalogItemEntity;
                    $parentFieldName = $catalogItemEntity->tableFieldWithParentId;

                    $additionalWhere = preg_replace('# WHERE #', '', $preparedViewRestrictSql ?? '');

                    $catalogEntityFullTree = DB->getTreeOfItems(
                        true,
                        $this->table . ' AS t1' . $leftJoinedTablesSql,
                        $parentFieldName,
                        null,
                        $additionalWhere,
                        $mainTablePrefix . $catalogItemEntity->tableFieldToDetectType . "='{menu}' DESC, " . $ORDER,
                        1,
                        'id',
                        'name',
                        1000000,
                        false,
                    );

                    /** Убираем все элементы, которые отсутствуют в выборке по фильтрам */
                    $catalogEntityFullTree = DB->chopOffTreeOfItemsBranches(
                        $catalogEntityFullTree,
                        $catalogEntityFoundIds,
                        $catalogItemEntity->tableFieldWithParentId,
                    );

                    /** К оставшемуся дереву объектов применяем LIMIT и OFFSET к верхнему уровню каталога. И фиксируем финальный $ITEMS_TOTAL. */
                    $topLevelItemsNum = 0;
                    $topLevelItemsCount = 0;
                    $dataGrabStarted = false;
                    $catalogEntitySelectedTree = [];

                    foreach ($catalogEntityFullTree as $catalogEntityFullParentsTreeItem) {
                        if ($catalogEntityFullParentsTreeItem[0] === '0') {
                            $catalogEntitySelectedTree[] = $catalogEntityFullParentsTreeItem;
                        }

                        if ((int) $catalogEntityFullParentsTreeItem[2] === 1) {
                            if ((PAGE * $maxItemsOnPage) === $topLevelItemsNum) {
                                $dataGrabStarted = true;
                            }
                            $topLevelItemsNum++;

                            if (((PAGE + 1) * $maxItemsOnPage) < $topLevelItemsNum) {
                                break;
                            }
                        }

                        if ($dataGrabStarted) {
                            if ((int) $catalogEntityFullParentsTreeItem[2] === 1) {
                                $topLevelItemsCount++;
                            }
                            $catalogEntitySelectedTree[] = $catalogEntityFullParentsTreeItem;
                        }
                    }
                    unset($catalogEntityFullTree);
                    $ITEMS_TOTAL = $topLevelItemsCount;

                    /** Пересобираем дерево в виде стандартного набора данных из БД для дальнейшей обработки */
                    $DATA = [];

                    foreach ($catalogEntitySelectedTree as $catalogEntitySelectedTreeItem) {
                        if ($catalogEntitySelectedTreeItem[0] === '0') {
                            $DATA[] = [
                                'id' => $catalogEntitySelectedTreeItem[0],
                                'name' => $catalogEntitySelectedTreeItem[1],
                                $catalogItemEntity->tableFieldToDetectType => '{menu}',
                                'catalogLevel' => 0,
                            ];
                        } else {
                            $DATA[] = array_merge($catalogEntitySelectedTreeItem[3], ['catalogLevel' => (int) $catalogEntitySelectedTreeItem[2]]);
                        }
                    }
                    unset($catalogEntitySelectedTreeItem);
                } else {
                    $QUERY .=
                        " LIMIT " . $maxItemsOnPage .
                        " OFFSET " . (PAGE * $maxItemsOnPage);

                    $DATA = DB->query($QUERY, $QUERY_PARAMS);
                    $ITEMS_TOTAL = DB->selectCount();
                }

                if ($_ENV['GLOBALTIMERDRAWREPORT']) {
                    $RESPONSE_DATA .= $_GLOBALTIMERDRAWREPORT->getTimerDiffStr('<!-- sorting and order execution time: %ss-->');
                }

                $objectName = ObjectsHelper::getClassShortNameFromCMSVCObject($this->view);
                [$DATA_FILTERED_BY_CONTEXT, $LIST_OF_FOUND_IDS] = $this->filterDataByContext($DATA, [$objectName . ':list', ':list']);
                $RESPONSE_ARRAY = $DATA_FILTERED_BY_CONTEXT;

                if (!REQUEST_TYPE->isApiRequest()) {
                    /** Открываем div.maincontent_data */
                    $RESPONSE_DATA .= '<div class="maincontent_data autocreated' .
                        ($this->filters->getFiltersState() ? ' with_indexer' : '') .
                        ' kind_' . KIND . ' ' . TextHelper::camelCaseToSnakeCase(ObjectsHelper::getClassShortName($this::class)) . ' ' . $act->value . '">';

                    if ($PAGETITLE !== '') {
                        $RESPONSE_DATA .= '<h1 class="form_header"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/">' . $PAGETITLE . '</a></h1>';
                    }

                    /** Добавляем переключатель фильтров */
                    if ($filtersHtml !== '') {
                        $RESPONSE_DATA .= '<div class="indexer_toggle' .
                            ($this->filters->getFiltersState() ? ' indexer_shown' : '') .
                            '"><span class="indexer_toggle_text">' . $FILTERS_LOCALE['filter'] . '</span><span class="sbi sbi-search"></span></div>';
                    }

                    if ($_ENV['GLOBALTIMERDRAWREPORT']) {
                        $RESPONSE_DATA .= $_GLOBALTIMERDRAWREPORT->getTimerDiffStr('<!-- pre data draw execution time: %ss-->');
                    }

                    $viewActData = $this->viewActList($DATA_FILTERED_BY_CONTEXT);

                    if ($viewActData !== '') {
                        $RESPONSE_DATA .= $viewActData;

                        if ($_ENV['GLOBALTIMERDRAWREPORT']) {
                            $RESPONSE_DATA .= $_GLOBALTIMERDRAWREPORT->getTimerDiffStr('<!-- data draw execution time: %ss-->');
                        }

                        /** Ссылка на текущий набор фильтров */
                        if ($this->filters->getPreparedCurrentFiltersLink() !== '' && $this->filters->getFiltersState()) {
                            $RESPONSE_DATA .= '<div class="copy_filters_link"><a href="' . $this->filters->getPreparedCurrentFiltersLink() .
                                '" target="_blank">' . $FILTERS_LOCALE['copy_filters_link'] . '</a></div>';
                        }

                        /** Навигатор страниц с объектами */
                        if ($this->elementsPerPage) {
                            $RESPONSE_DATA .= $this->drawPageCounter($this->name, PAGE, $ITEMS_TOTAL, $maxItemsOnPage);

                            if ($_ENV['GLOBALTIMERDRAWREPORT']) {
                                $RESPONSE_DATA .= $_GLOBALTIMERDRAWREPORT->getTimerDiffStr('<!-- pages navigation execution time: %ss-->');
                            }
                        }

                        /** Закрываем div.maincontent_data */
                        $RESPONSE_DATA .= '</div>';

                        $RESPONSE_DATA .= $filtersHtml;
                    } else {
                        $RESPONSE_DATA = '';
                    }
                } else {
                    $RESPONSE_DATA = '';
                }
            } elseif (in_array($act, [ActEnum::add, ActEnum::view, ActEnum::edit])) {
                $DATA = [];
                $modelRights = $this->view->viewRights;

                if ($id > 0) {
                    $DATA = DB->select($this->table, ['id' => $id], true);

                    if (!$DATA) {
                        return null;
                    }

                    if (is_null($DATA['id'] ?? null)) {
                        $modelRights->viewRight = false;
                        $modelRights->changeRight = false;
                        $modelRights->deleteRight = false;
                    } else {
                        if (in_array($act, [ActEnum::view, ActEnum::edit]) && !is_null($modelRights->viewRestrict)) {
                            $viewCheckData = DB->query(
                                'SELECT * FROM ' . $this->table . ' WHERE id=:id AND ' . $modelRights->viewRestrict,
                                [['id', $id]],
                                true,
                            );

                            if (!$viewCheckData) {
                                $modelRights->viewRight = false;
                            }
                        }

                        if (in_array($act, [ActEnum::edit]) && !is_null($modelRights->changeRestrict)) {
                            $changeCheckData = DB->query(
                                'SELECT * FROM ' . $this->table . ' WHERE id=:id AND ' . $modelRights->changeRestrict,
                                [['id', $id]],
                                true,
                            );

                            if (!$changeCheckData) {
                                $modelRights->changeRight = false;
                            }
                        }

                        if (in_array($act, [ActEnum::edit]) && !is_null($modelRights->deleteRestrict)) {
                            $deleteCheckData = DB->query(
                                'SELECT * FROM ' . $this->table . ' WHERE id=:id AND ' . $modelRights->deleteRestrict,
                                [['id', $id]],
                                true,
                            );

                            if (!$deleteCheckData) {
                                $modelRights->deleteRight = false;
                            }
                        }
                    }

                    /** Фильтрация данных по контексту обрабатывает массив записей, поэтому одну запись оборачиваем в массив */
                    $DATA = [$DATA];
                }

                $objectName = ObjectsHelper::getClassShortNameFromCMSVCObject($this->view);
                $currentContext = match ($act) {
                    ActEnum::view => 'view',
                    ActEnum::add => 'create',
                    ActEnum::edit => 'update',
                };
                $contexts = $currentContext === 'view' ? [] : [$objectName . ':view', ':view', $objectName . ':viewIfNotNull', ':viewIfNotNull'];
                $contexts[] = $objectName . ':' . $currentContext;
                $contexts[] = ':' . $currentContext;
                [$DATA_FILTERED_BY_CONTEXT, $LIST_OF_FOUND_IDS] = $this->filterDataByContext($DATA, $contexts);
                $RESPONSE_ARRAY = $DATA_FILTERED_BY_CONTEXT;

                if (!REQUEST_TYPE->isApiRequest() && $modelRights->viewRight) {
                    /** Открываем div.maincontent_data */
                    $RESPONSE_DATA .= '<div class="maincontent_data autocreated kind_' . KIND .
                        ' ' . TextHelper::camelCaseToSnakeCase(ObjectsHelper::getClassShortName($this::class)) . ' ' . $act->value . '">';

                    if ($PAGETITLE !== '') {
                        $RESPONSE_DATA .= '<h1 class="form_header"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/">' . $PAGETITLE . '</a></h1>';
                    }

                    $activeEntity = $this;

                    if ($this instanceof CatalogEntity && TextHelper::camelCaseToSnakeCase($this->catalogItemEntity->name) === CMSVC) {
                        $activeEntity = $this->catalogItemEntity;
                    }
                    $viewActData = $activeEntity->viewActItem($DATA_FILTERED_BY_CONTEXT[0] ?? [], $act, $contextName);

                    if ($viewActData !== '') {
                        $RESPONSE_DATA .= $viewActData;

                        if ($_ENV['GLOBALTIMERDRAWREPORT']) {
                            $RESPONSE_DATA .= $_GLOBALTIMERDRAWREPORT->getTimerDiffStr('<!-- object draw execution time: %ss-->');
                        }

                        /** Закрываем div.maincontent_data */
                        $RESPONSE_DATA .= '</div>';
                    } else {
                        $RESPONSE_DATA = '';
                    }
                }
            }
        } else {
            ResponseHelper::response401();
        }

        $this->listOfFoundIds = $LIST_OF_FOUND_IDS;

        if (REQUEST_TYPE->isApiRequest()) {
            return $this->asArray($RESPONSE_ARRAY);
        }

        if ($RESPONSE_DATA !== '') {
            if ($_ENV['GLOBALTIMERDRAWREPORT']) {
                $RESPONSE_DATA .= $_GLOBALTIMERDRAWREPORT->getTimerDiffStr('<!-- draw execution time: %ss-->');
            }
        } else {
            return null;
        }

        return $this->asHtml($RESPONSE_DATA, $PAGETITLE);
    }

    /** HTML-отрисовка значения элемента в строковом списке объектов */
    public function drawElementValue(ElementItem $modelElement, array $DATA_ITEM, ?EntitySortingItem $sortingItem = null): string
    {
        $RESPONSE_DATA = '';

        if (is_null($sortingItem) || $sortingItem->showFieldDataInEntityTable) {
            if ($modelElement->checkVisibility() || $sortingItem->substituteDataType === SubstituteDataTypeEnum::TABLE || $sortingItem->substituteDataType === SubstituteDataTypeEnum::ARRAY) {
                if (($DATA_ITEM[$modelElement->name] ?? null) !== null) {
                    $modelElement->set($DATA_ITEM[$modelElement->name]);
                }

                if ($this instanceof CatalogEntity || $this instanceof CatalogItemEntity) {
                    if ($sortingItem->showFieldShownNameInCatalogItemString) {
                        $RESPONSE_DATA .= $modelElement->shownName . ': ';
                    }
                    $RESPONSE_DATA .= '<b>';
                }

                $fieldValue = $modelElement->get();

                if (is_null($fieldValue) || (is_string($fieldValue) && trim($fieldValue) === '')) {
                    $GLOBAL_LOCALE = LocaleHelper::getLocale(['fraym', 'dynamiccreate']);
                    $useText = ($modelElement->name === 'name' && in_array($DATA_ITEM['code'] ?? '', ['default', '1'])) ?
                        'default' :
                        'not_set';
                    $RESPONSE_DATA .= '<i>' . $GLOBAL_LOCALE[$useText] . '</i>';
                } elseif ($sortingItem->substituteDataType === SubstituteDataTypeEnum::TABLE) {
                    if ($modelElement instanceof Multiselect) {
                        foreach ($fieldValue as $fieldValueItem) {
                            $RESPONSE_DATA .= (DataHelper::getFlatArrayElement($fieldValueItem, $modelElement->getValues())[1] ?? '') . '<br>';
                        }
                    } else {
                        $RESPONSE_DATA .= $DATA_ITEM[$sortingItem->substituteDataTableName .
                            TextHelper::mb_ucfirst($sortingItem->substituteDataTableField)];
                    }
                } elseif ($sortingItem->substituteDataType === SubstituteDataTypeEnum::ARRAY) {
                    $rotatedArrayIndexes = $this->rotatedArrayIndexes;

                    if (!isset($rotatedArrayIndexes[$sortingItem->tableFieldName])) {
                        $rotatedArrayIndexes[$sortingItem->tableFieldName] = [];

                        foreach ($sortingItem->substituteDataArray as $substituteDataArrayItem) {
                            $rotatedArrayIndexes[$sortingItem->tableFieldName][$substituteDataArrayItem[0]] = $substituteDataArrayItem[1];
                        }
                        $this->rotatedArrayIndexes = $rotatedArrayIndexes;
                    }

                    if ($modelElement instanceof Multiselect) {
                        foreach ($fieldValue as $fieldValueItem) {
                            if ($rotatedArrayIndexes[$sortingItem->tableFieldName][$fieldValueItem] ?? false) {
                                $RESPONSE_DATA .= $rotatedArrayIndexes[$sortingItem->tableFieldName][$fieldValueItem] . '<br>';
                            }
                        }
                    } else {
                        $RESPONSE_DATA .= $rotatedArrayIndexes[$sortingItem->tableFieldName][$fieldValue] ?? '';
                    }
                } elseif ($modelElement instanceof Checkbox) {
                    if ($fieldValue) {
                        $RESPONSE_DATA .= '<span class="sbi sbi-check"></span>';
                    } else {
                        $RESPONSE_DATA .= '<span class="sbi sbi-times"></span>';
                    }
                } elseif ($modelElement instanceof Calendar) {
                    $RESPONSE_DATA .= $fieldValue->format('d.m.Y' . ($modelElement->getShowDatetime() ? ' H:i' : ''));
                } elseif ($modelElement instanceof Timestamp) {
                    $RESPONSE_DATA .= $modelElement->getAsUsualDateTime();
                } else {
                    $RESPONSE_DATA .= $fieldValue;
                }
                unset($fieldValue);

                if ($this instanceof CatalogEntity || $this instanceof CatalogItemEntity) {
                    $RESPONSE_DATA .= '</b>';

                    if (count($this->sortingData) > 1 && !$sortingItem->removeDotAfterText) {
                        $RESPONSE_DATA .= '. ';
                    }
                }
            }
        }

        return $RESPONSE_DATA;
    }

    /** Удаление / мягкое удаление объекта */
    public function deleteItem(string|int $id): void
    {
        $model = $this->model;

        if ($model instanceof DeletedAt) {
            $deletedAtValue = $model->getDeletedAtTime();

            DB->update(
                tableName: $this->table,
                data: [
                    'deleted_at' => $deletedAtValue,
                ],
                criteria: [
                    'id' => $id,
                ],
            );
        } else {
            $item = DB->select(
                tableName: $this->table,
                criteria: [
                    'id' => $id,
                ],
                oneResult: true,
            );

            if ($this instanceof CatalogInterface) {
                $elements = $this->detectEntityType($item)->model->elementsList;
            } else {
                $elements = $this->model->elementsList;
            }

            foreach ($elements as $element) {
                if ($element instanceof File) {
                    unset($fileNames);
                    preg_match_all('#{([^:]+):([^}]+)}#', ($item[$element->name] ?? ''), $fileNames);

                    foreach ($fileNames[2] as $fileName) {
                        $element->remove($fileName);
                    }
                }
            }

            DB->delete(
                tableName: $this->table,
                criteria: [
                    'id' => $id,
                ],
            );
        }
    }

    private function getFraymModelLocale(?BaseEntity $activeEntity = null): ?array
    {
        $activeEntity = $activeEntity ?? $this;
        $activeEntityName = $activeEntity instanceof CatalogItemEntity ? $activeEntity->catalogEntity->getNameUsedInLocale() . '/' . $activeEntity->getNameUsedInLocale() : $activeEntity->getNameUsedInLocale();

        $LOCALE = LocaleHelper::getLocale([$activeEntityName]);

        return $LOCALE['fraym_model'] ?? null;
    }

    /** Отфильтровка данных в зависимости от контекста элементов модели
     * @return array{0: array[], 1: int[]}
     */
    private function filterDataByContext(array $data, array $contexts): array
    {
        $filteredData = [];
        $LIST_OF_FOUND_IDS = [];

        /** Добавляем значения из виртуального поля сущности */
        if ($this->virtualField) {
            foreach ($data as $dataKey => $dataValue) {
                if ($dataValue[$this->virtualField] ?? false) {
                    $data[$dataKey] = array_merge(DataHelper::unmakeVirtual($dataValue[$this->virtualField]), $dataValue);
                }
            }
        }

        $alternativeDataColumnNames = [];

        $itemsToFilter = ['id', 'catalogLevel'];

        foreach ($this->model->elementsList as $item) {
            if (DataHelper::inArrayAny($contexts, $item->getAttribute()->context)) {
                if ($item->name !== 'id') {
                    $itemsToFilter[] = $item->name;

                    if ($item->getAttribute()->alternativeDataColumnName) {
                        $alternativeDataColumnNames[$item->getAttribute()->alternativeDataColumnName][] = $item->name;
                    }
                }
            }
        }

        /** @var CatalogItemEntity|null $catalogItemEntity */
        $catalogItemEntity = null;
        $itemsToFilterCatalogItem = ['id', 'catalogLevel'];

        if ($this instanceof CatalogEntity) {
            $catalogItemEntity = $this->catalogItemEntity;
            $catalogItemContext = $contexts;

            foreach ($contexts as $context) {
                if (str_starts_with($context, ':')) {
                    $catalogItemContext[] = $catalogItemEntity->name . $context;
                }
            }

            foreach ($catalogItemEntity->model->elementsList as $item) {
                if (DataHelper::inArrayAny($catalogItemContext, $item->getAttribute()->context)) {
                    if ($item->name !== 'id') {
                        $itemsToFilterCatalogItem[] = $item->name;

                        if ($item->getAttribute()->alternativeDataColumnName) {
                            $alternativeDataColumnNames[$item->getAttribute()->alternativeDataColumnName][] = $item->name;
                        }
                    }
                }
            }
        }

        foreach ($this->sortingData as $sortingItem) {
            if ($sortingItem->substituteDataType === SubstituteDataTypeEnum::TABLE) {
                $itemsToFilter[] = $sortingItem->substituteDataTableName . TextHelper::mb_ucfirst($sortingItem->substituteDataTableField);
            }
        }

        foreach ($data as $item) {
            $itemData = [];
            $catalogItem = !is_null($catalogItemEntity) && $this instanceof CatalogInterface && $this->detectEntityType($item) instanceof CatalogItemEntity;

            foreach ($item as $key => $field) {
                if (in_array($key, ($catalogItem ? $itemsToFilterCatalogItem : $itemsToFilter))) {
                    $itemData[$key] = $field;
                }

                if ($alternativeDataColumnNames[$key] ?? false) {
                    foreach ($alternativeDataColumnNames[$key] as $alternativeDataColumnName) {
                        $itemData[$alternativeDataColumnName] = $field;
                    }
                }
            }
            $filteredData[] = $itemData;

            if ($item['id'] ?? false) {
                $LIST_OF_FOUND_IDS[] = $item['id'];
            }
        }

        return [$filteredData, $LIST_OF_FOUND_IDS];
    }

    /** Формирование строки для ORDER BY в запросе
     * @param EntitySortingItem[] $sortingData
     */
    private function getOrderString(array $sortingData, string $mainTablePrefix): array
    {
        $tablesUsedCount = 2;
        $leftJoinedTablesSql = "";
        $leftJoinedFieldsSql = "";
        $ORDER = "";

        $sortingFieldNum = 0;
        $sortingOrder = "";

        if (SORTING > 0) {
            $sortingFieldNum = (int) (round(SORTING / 2) - 1);
            $sortingOrder = (SORTING % 2 === 1 ? "" : " DESC");
        }

        foreach ($sortingData as $sortingItemNum => $sortingItem) {
            $sortingItemNum = (int) $sortingItemNum;

            /** Если у $sortingItem выставлен параметр $doNotUseInSorting, мы вообще не включаем его в запрос сортировки данных, никогда */
            if (!$sortingItem->doNotUseInSorting) {
                if ($sortingItem->substituteDataType === SubstituteDataTypeEnum::TABLE) {
                    if ($this->model->getElement($sortingItem->tableFieldName) instanceof Multiselect) {
                        /** Если вдруг указан мультиселект в качестве поля, нам нужно выдернуть первое значение из поля */
                        $leftJoinedTablesSql .= " LEFT JOIN " .
                            $sortingItem->substituteDataTableName . " t" . $tablesUsedCount . " ON " .
                            "SUBSTRING(t1." . $sortingItem->tableFieldName . ", 2, LOCATE('-', t1." . $sortingItem->tableFieldName . ", 2) - 2)=" .
                            "t" . $tablesUsedCount . "." . $sortingItem->substituteDataTableId;
                    } else {
                        $leftJoinedFieldsSql .= ", t" . $tablesUsedCount . "." . $sortingItem->substituteDataTableField . " AS "
                            . $sortingItem->substituteDataTableName . TextHelper::mb_ucfirst($sortingItem->substituteDataTableField);
                        $leftJoinedTablesSql .= " LEFT JOIN " .
                            $sortingItem->substituteDataTableName . " t" . $tablesUsedCount . " ON " .
                            "t1." . $sortingItem->tableFieldName . "=" .
                            "t" . $tablesUsedCount . "." . $sortingItem->substituteDataTableId;
                    }

                    if (!$sortingItem->doNotUseIfNotSortedByThisField || ($sortingItemNum === $sortingFieldNum && SORTING > 0)) {
                        if ($sortingItemNum === $sortingFieldNum && SORTING > 0) {
                            $ORDER = "t" . $tablesUsedCount . "." . $sortingItem->substituteDataTableField . $sortingOrder . ", " . $ORDER;
                        } else {
                            $ORDER .= "t" . $tablesUsedCount . "." . $sortingItem->substituteDataTableField .
                                $sortingItem->tableFieldOrder->asText() . ", ";
                        }
                    }
                    ++$tablesUsedCount;
                } elseif ($sortingItem->substituteDataType === SubstituteDataTypeEnum::ARRAY) {
                    /** Если выставлен параметр doNotUseIfNotSortedByThisField в настройке сортировки, то мы сортируем по данному полю ТОЛЬКО
                     * в случае, если прямо по нему задана сортировка. Это серьезно облегчает запросы */
                    if (!$sortingItem->doNotUseIfNotSortedByThisField || ($sortingItemNum === $sortingFieldNum && SORTING > 0)) {
                        $substituteDataArray = $sortingItem->substituteDataArray;

                        if (is_string($substituteDataArray) && method_exists($this->view, $substituteDataArray)) {
                            $sortingItem->substituteDataArray = $this->view->{$substituteDataArray}();
                            $substituteDataArray = $sortingItem->substituteDataArray;
                        }

                        if (count($substituteDataArray) > 0) {
                            if ($_ENV['DATABASE_TYPE'] === "pgsql") {
                                $count_fields = 0;
                                $ordField = "CASE";

                                foreach ($substituteDataArray as $substituteDataItem) {
                                    $count_fields++;
                                    $ordField .= " WHEN " . $mainTablePrefix . $sortingItem->tableFieldName .
                                        "='" . $substituteDataItem[0] . "' THEN " . $count_fields;
                                }
                                $ordField .= " ELSE " . ($count_fields + 1) . " END";
                            } else {
                                $ordField = "FIELD(" . $mainTablePrefix . $sortingItem->tableFieldName;

                                foreach ($substituteDataArray as $substituteDataItem) {
                                    $ordField .= ", " . (is_numeric($substituteDataItem[0]) ? $substituteDataItem[0] : "'" . $substituteDataItem[0] . "'");
                                }
                                $ordField .= ")";
                            }

                            if ($sortingItemNum === $sortingFieldNum && SORTING > 0) {
                                $ORDER = $ordField . $sortingOrder . ', ' . $ORDER;
                            } else {
                                $ORDER .= $ordField . $sortingItem->tableFieldOrder->asText() . ", ";
                            }
                        }
                    }
                } else {
                    $preparedSortName = $mainTablePrefix . $sortingItem->tableFieldName;

                    if (preg_match('#length\(#i', $sortingItem->tableFieldName)) {
                        $preparedSortName = preg_replace('#length\(#i', 'length(' . $mainTablePrefix, $sortingItem->tableFieldName);
                    }

                    if (!$sortingItem->doNotUseIfNotSortedByThisField || ($sortingItemNum === $sortingFieldNum && SORTING > 0)) {
                        if ($sortingItemNum === $sortingFieldNum && SORTING > 0) {
                            $ORDER = $preparedSortName . $sortingOrder . ", " . $ORDER;
                        } else {
                            $ORDER .= $preparedSortName . $sortingItem->tableFieldOrder->asText() . ", ";
                        }
                    }
                }
            }
        }

        if (str_ends_with($ORDER, ", ")) {
            $ORDER = mb_substr($ORDER, 0, mb_strlen($ORDER) - 2);
        }

        return [
            $ORDER,
            $leftJoinedTablesSql,
            $leftJoinedFieldsSql,
        ];
    }

    /** Перевод значений полей в нужный формат для дальнейшего сохранения */
    private function appendDataAfterValidation(string|int $dataStringId, ElementItem $element, mixed $value, ActEnum $act, bool $groupedValue = false): void
    {
        if (!$element instanceof H1 && !$element->getNoData()) {
            if ($act === ActEnum::add && !is_null($element->create)) {
                if (!is_null($element->create->data)) {
                    $value = $element->create->data;
                } else {
                    $service = $this->view->CMSVC->service;

                    if (method_exists($service, $element->create->callback)) {
                        $value = $service->{$element->create->callback}();
                    } else {
                        $model = $this->model;

                        if (method_exists($model, $element->create->callback)) {
                            $value = $model->{$element->create->callback}();
                        }
                    }
                }
            } elseif ($act === ActEnum::edit && !is_null($element->change)) {
                if (!is_null($element->change->data)) {
                    $value = $element->change->data;
                } else {
                    $service = $this->view->CMSVC->service;

                    if (method_exists($service, $element->change->callback)) {
                        $value = $service->{$element->change->callback}();
                    } else {
                        $model = $this->model;

                        if (method_exists($model, $element->change->callback)) {
                            $value = $model->{$element->change->callback}();
                        }
                    }
                }
            } elseif (!$groupedValue) {
                if ($element instanceof Multiselect) {
                    if (!$element->getOne()) {
                        $rehashedValues = [];

                        if (is_array($value)) {
                            $hasArrayValues = false;

                            foreach ($value as $key => $item) {
                                if ($item === 'on') {
                                    $rehashedValues[] = $key;
                                } elseif (is_array($item)) {
                                    $rehashedValues[$key] = $item;
                                    $hasArrayValues = true;
                                }
                            }

                            $value = $rehashedValues;
                            unset($rehashedValues);

                            if (!is_null($element->getCreator())) {
                                $creator = $element->getCreator();
                                $createdItemsIds = [];

                                if (isset($value['new'])) {
                                    foreach ($value['new'] as $key => $item) {
                                        if ($item === 'on') {
                                            $createdItemsIds[] = $creator->createItem($value['name'][$key], $this->view->CMSVC->service);
                                        }
                                    }
                                }

                                if (count($createdItemsIds) > 0) {
                                    $value = array_merge($value, $createdItemsIds);
                                }

                                unset($value['new']);
                                unset($value['name']);
                            }

                            /** @phpstan-ignore-next-line */
                            $value = DataHelper::arrayToMultiselect($hasArrayValues ? $value : array_unique($value));
                        }
                    }
                } elseif ($element instanceof Password) {
                    $value = $value !== null ? AuthHelper::hashPassword($value) : null;
                } elseif ($element instanceof Calendar) {
                    $value = is_null($value) ? null : date('Y-m-d H:i:s', (is_numeric($value) ? $value : strtotime($value)));
                } elseif ($element instanceof Checkbox) {
                    $value = $value === 'on' ? 1 : 0;
                } elseif ($element instanceof Number) {
                    if (!is_numeric($value)) {
                        $value = 0;
                    } else {
                        $value = (int) $value;
                    }

                    if ($element->getRound()) {
                        $value = round($value);
                    }
                } elseif ($element instanceof Email) {
                    $value = [$element->name, $value, ['email']];
                } elseif ($element instanceof Timestamp) {
                    $value = DateHelper::getNow();
                } elseif ($element instanceof File) {
                    if (is_array($value)) {
                        $formattedValue = implode('', $value);
                        $value = $formattedValue;
                    }
                }

                if ($element->getAttribute()->saveHtml) {
                    $value = [$element->name, $value, ['html']];
                }
            }

            if (!$element instanceof Password || !is_null($value)) {
                if (!$element->getVirtual()) {
                    $this->dataAfterValidation[$dataStringId][$element->getAttribute()->alternativeDataColumnName ?? $element->name] = $value;
                } else {
                    $this->dataAfterValidation[$dataStringId][$this->virtualField][] = [$element, $value];
                }
            }
        }
    }

    /** Подготовка параметров валидации в зависимости от типа объекта */
    private function prepareValidationOptions(ElementItem $element, int $stringId, ?int $groupId = null): array
    {
        $options = [];

        $currentId = $_REQUEST['id'][$stringId] ?? null;

        if ($element instanceof Password && $element->getAttribute()->repeatPasswordFieldName) {
            $repeatPasswordFieldName = $element->getAttribute()->repeatPasswordFieldName;
            $compareValue = $_REQUEST[$repeatPasswordFieldName][$stringId] ?? null;

            if (!is_null($groupId)) {
                $compareValue = $compareValue[$groupId] ?? null;
            }

            if ($compareValue === '') {
                $compareValue = null;
            }

            $options = [
                'compareValue' => $compareValue,
            ];
        } elseif ($element instanceof Login || $element instanceof Timestamp) {
            $options = [
                'table' => $this->table,
                'id' => $currentId,
            ];
        }

        return $options;
    }

    /** Добавление ошибки валидации в массив ошибок */
    private function appendValidationErrors(string $validatorName, int $stringId, int $groupId, ElementItem $element): self
    {
        $validationErrors = $this->validationErrors;

        if (!($validationErrors[$validatorName] ?? false)) {
            $validationErrors[$validatorName] = [];
        }

        if (!($validationErrors[$validatorName][$stringId] ?? false)) {
            $validationErrors[$validatorName][$stringId] = [];
        }

        if (!($validationErrors[$validatorName][$stringId][$groupId] ?? false)) {
            $validationErrors[$validatorName][$stringId][$groupId] = [];
        }
        $validationErrors[$validatorName][$stringId][$groupId][] = $element;

        $this->validationErrors = $validationErrors;

        return $this;
    }
}
