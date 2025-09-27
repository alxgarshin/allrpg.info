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
use Fraym\Interface\{DeletedAt, ElementItem, Response, Validator};
use Fraym\Response\{ArrayResponse, HtmlResponse};
use Fraym\Service\GlobalTimerService;
use PDOException;

abstract class BaseEntity
{
    use PageCounter;

    /** Языковая локаль сущности  */
    protected ?array $LOCALE;

    /** Фильтры */
    protected ?Filters $filters = null;

    /** Вьюшка, к которой привязан данный instance BaseEntity */
    protected BaseView $view;

    /** Массив найденных при последнем запросе id сущностей */
    protected array $listOfFoundIds = [];

    /** Перевернутые для удобства массивы сортировки */
    protected array $rotatedArrayIndexes = [];

    /** Все ошибки валидации в формате: [класс валидатора => [строка запроса => [номер группы (-1, если нет) => [непрошедший элемент]]]] */
    protected array $validationErrors = [];

    /** Отформатированные данные после валидации */
    protected array $dataAfterValidation = [];

    /** Подготовленные сообщения в результате стандартных действий: create, change и delete */
    protected array $fraymActionMessages = [];

    /** Путь, по которому нужно перенаправить пользователя по завершению стандартного действия */
    protected ?string $fraymActionRedirectPath = null;

    public function __construct(
        /** Имя сущности, чаще всего совпадающее с URL раздела на сайте */
        protected string $name,

        /** Таблица данных сущности */
        protected string $table,

        /** @var EntitySortingItem[] Информация по сортировке данных сущности */
        protected array $sortingData,

        /** Опциональный параметр, указывающий на колонку, в которой нужно хранить данные JSON-виртуальных полей, сделанных конструктором */
        protected ?string $virtualField = null,

        /** Количество выводимых на страницу строк в объекте */
        protected ?int $elementsPerPage = 50,

        /** Использовать для просмотра сушности view из CMSVC. В ином случае просмотр приравнен к редактированию объекта. */
        protected bool $useCustomView = false,

        /** Использовать для списка сущностей view из CMSVC. В ином случае будет применен автоматический view. */
        protected bool $useCustomList = false,

        /** В какой ACT (тип карточки сущности) осуществляется по умолчанию переход из общего списка сущностей? */
        protected ActEnum $defaultItemActType = ActEnum::edit,

        /** В какой ACT попадает по умолчанию пользователь при переходе на список сущностей? */
        protected ActEnum $defaultListActType = ActEnum::list,
    ) {
        foreach ($this->sortingData as $sortingData) {
            $sortingData->setEntity($this);
        }
    }

    abstract public function viewActList(array $DATA_FILTERED_BY_CONTEXT): string;

    abstract public function viewActItem(array $DATA_ITEM, ?ActEnum $act = null, ?string $contextName = null): string;

    public function getLOCALE(): array
    {
        return $this->LOCALE;
    }

    public function setLOCALE(array $entityPathInLocale): ?array
    {
        $this->LOCALE = LocaleHelper::getLocale($entityPathInLocale);

        return $this->LOCALE;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /** @return EntitySortingItem[] */
    public function getSortingData(): array
    {
        return $this->sortingData;
    }

    /** @param EntitySortingItem[] $sortingData */
    public function setSortingData(array $sortingData): self
    {
        $this->sortingData = $sortingData;

        return $this;
    }

    public function addEntitySortingData(EntitySortingItem $entitySortingItem): self
    {
        $entitySortingItem->setEntity($this);

        $this->sortingData[] = $entitySortingItem;

        return $this;
    }

    public function insertEntitySortingData(EntitySortingItem $entitySortingItem, int $offset): self
    {
        $entitySortingItem->setEntity($this);

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

    public function getVirtualField(): ?string
    {
        return $this->virtualField;
    }

    public function setVirtualField(?string $virtualField): static
    {
        $this->virtualField = $virtualField;

        return $this;
    }

    public function getElementsPerPage(): ?int
    {
        return $this->elementsPerPage;
    }

    public function setElementsPerPage(?int $elementsPerPage): static
    {
        $this->elementsPerPage = $elementsPerPage;

        return $this;
    }

    public function getUseCustomView(): bool
    {
        return $this->useCustomView;
    }

    public function setUseCustomView(bool $useCustomView): static
    {
        $this->useCustomView = $useCustomView;

        return $this;
    }

    public function getUseCustomList(): bool
    {
        return $this->useCustomList;
    }

    public function setUseCustomList(bool $useCustomList): static
    {
        $this->useCustomList = $useCustomList;

        return $this;
    }

    public function getDefaultItemActType(): ActEnum
    {
        return $this->defaultItemActType;
    }

    public function setDefaultItemActType(ActEnum $defaultItemActType): static
    {
        $this->defaultItemActType = $defaultItemActType;

        return $this;
    }

    public function getDefaultListActType(): ActEnum
    {
        return $this->defaultListActType;
    }

    public function setDefaultListActType(ActEnum $defaultListActType): static
    {
        $this->defaultListActType = $defaultListActType;

        return $this;
    }

    public function getFilters(): ?Filters
    {
        /** У наследующих сущностей каталога нет своих фильтров: они подчинены фильтрам родительской сущности */
        if (!($this instanceof CatalogItemEntity) && $this->filters === null) {
            $this->setFilters(new Filters($this));
        }

        return $this->filters;
    }

    public function setFilters(?Filters $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function getView(): BaseView
    {
        return $this->view;
    }

    public function setView(BaseView $view): static
    {
        $this->view = $view;

        return $this;
    }

    public function getModel(): ?BaseModel
    {
        return $this->getView()->getModel();
    }

    public function getController(): ?BaseController
    {
        return $this->getView()->getController();
    }

    public function getListOfFoundIds(): array
    {
        return $this->listOfFoundIds;
    }

    public function setListOfFoundIds(array $listOfFoundIds): static
    {
        $this->listOfFoundIds = $listOfFoundIds;

        return $this;
    }

    public function getRotatedArrayIndexes(): array
    {
        return $this->rotatedArrayIndexes;
    }

    public function setRotatedArrayIndexes(array $rotatedArrayIndexes): static
    {
        $this->rotatedArrayIndexes = $rotatedArrayIndexes;

        return $this;
    }

    public function getFraymActionMessages(): array
    {
        return $this->fraymActionMessages;
    }

    public function addFraymActionMessage(array $fraymActionMessage): static
    {
        $this->fraymActionMessages[] = $fraymActionMessage;

        return $this;
    }

    public function getFraymActionRedirectPath(): ?string
    {
        return $this->fraymActionRedirectPath;
    }

    public function setFraymActionRedirectPath(?string $fraymActionRedirectPath): static
    {
        $this->fraymActionRedirectPath = $fraymActionRedirectPath;

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

        $service = $this->view->getCMSVC()?->getService();

        $objectRights = $this->view->getViewRights();

        /** Проверка авторизации пользователя */
        if (
            match (ACTION) {
                ActionEnum::create => !$objectRights->getAddRight(),
                ActionEnum::change => !$objectRights->getChangeRight(),
                ActionEnum::delete => !$objectRights->getDeleteRight(),
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

        /** Валидация */
        $globalValidationSuccess = true;
        $troubledStrings = [];
        $troubledElements = [];
        $activeEntity = $this;

        $objectName = $this->getView()->getCMSVC()?->getObjectName() ?? $activeEntity->getName();

        if ($this instanceof CatalogEntity && TextHelper::camelCaseToSnakeCase($this->getCatalogItemEntity()->getName()) === CMSVC) {
            $activeEntity = $this->getCatalogItemEntity();
            $objectName = $activeEntity->getName();
        }

        if (
            (ACTION === ActionEnum::create && $objectRights->getAddRight()) ||
            (ACTION === ActionEnum::change && $objectRights->getChangeRight())
        ) {
            $groupsMaxValues = [];

            $act = ACTION === ActionEnum::create ? ActEnum::add : ActEnum::edit;

            foreach ($dataStringsIds as $dataStringId) {
                $checkReadOnly = $_REQUEST['readonly'][$dataStringId] ?? null;

                if (is_null($checkReadOnly)) {
                    foreach ($activeEntity->getModel()->getElements() as $element) {
                        if ($element->checkWritable($act, $objectName)) {
                            if (!$element->getNoData()) {
                                $elementValue = $_REQUEST[$element->getName()][$dataStringId] ?? ($element->getGroup() ? [] : null);

                                if ($element->getGroup()) {
                                    /** Определяем максимальные порядковые номера заполненных полей в каждой из групп полей */
                                    foreach ($this->getModel()->getElements() as $groupElement) {
                                        if (!is_null($groupElement->getGroup())) {
                                            /** Сначала выясняем количество непустых строк (максимальный id строки) в группе */
                                            if (!($groupsMaxValues[$dataStringId] ?? false)) {
                                                $groupsMaxValues[$dataStringId] = [];
                                            }

                                            if (!($groupsMaxValues[$dataStringId][$groupElement->getGroup()] ?? false)) {
                                                $groupsMaxValues[$dataStringId][$groupElement->getGroup()] = 0;

                                                foreach ($this->getModel()->getElements() as $groupCheckField) {
                                                    if ($groupCheckField->getGroup() === $groupElement->getGroup() && !$groupCheckField->getNoData()) {
                                                        $max = 0;
                                                        $stringsKeys = array_keys($_REQUEST[$groupCheckField->getName()][$dataStringId] ?? []);

                                                        if ($stringsKeys) {
                                                            $max = (int) max($stringsKeys);
                                                        }

                                                        /** Проверяем реверсивно все поступившие значения по ключам, чтобы понять, в какой самой большой строке у
                                                         * данного поля реально есть данные: таким образом, отсекаем лишние, полностью пустые группы
                                                         */
                                                        for ($i = $max; $i >= 0; $i--) {
                                                            if ($_REQUEST[$groupCheckField->getName()][$dataStringId][$i] ?? false) {
                                                                $max = $i;
                                                                break;
                                                            }
                                                        }

                                                        if (
                                                            $max > $groupsMaxValues[$dataStringId][$groupElement->getGroup()] &&
                                                            ($_REQUEST[$groupCheckField->getName()][$dataStringId][$max] ?? false)
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

                                            foreach ($failedValidatorsNames as $validationError) {
                                                $this->appendValidationErrors($validationError, $dataStringId, $groupId, $element);
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

                                        foreach ($failedValidatorsNames as $validationError) {
                                            $this->appendValidationErrors($validationError, $dataStringId, -1, $element);
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
                    /** @var Validator $validatorClass */
                    $this->addFraymActionMessage(['error', $validatorClass::getMessage($validationError)]);

                    foreach ($validationError as $stringId => $groupData) {
                        $troubledStrings[] = $stringId;

                        foreach ($groupData as $groupId => $elementsArray) {
                            foreach ($elementsArray as $element) {
                                $troubledElements[] = $element->getName() . '[' . $stringId . ']' . ($groupId > 0 ? '[' . $groupId . ']' : '');
                            }
                        }
                    }
                }
            }
        }

        if ($globalValidationSuccess) {
            /** Предействие из сервиса, если есть */
            if (!is_null($service)) {
                match (ACTION) {
                    ActionEnum::create => $service->getPreCreate() ? $service->{$service->getPreCreate()}() : null,
                    ActionEnum::change => $service->getPreChange() ? $service->{$service->getPreChange()}() : null,
                    ActionEnum::delete => $service->getPreDelete() ? $service->{$service->getPreDelete()}() : null,
                    default => null,
                };
            }

            /** Действие */
            $data = $this->getDataAfterValidation();

            if (ACTION !== ActionEnum::delete) {
                if ($this->virtualField) {
                    foreach ($dataStringsIds as $dataStringId) {
                        $stringVirtualDataString = '';
                        $stringVirtualDataArray = $data[$dataStringId][$this->virtualField];

                        foreach ($stringVirtualDataArray as $stringVirtualDataItem) {
                            $stringVirtualDataString .= '[' . $stringVirtualDataItem[0]->getName() . '][' . $stringVirtualDataItem[1] . ']' . chr(13) . chr(10);
                        }
                        $data[$dataStringId][$this->virtualField] = $stringVirtualDataString;
                    }
                }
            }

            $successfulResultsIds = [];

            if (ACTION === ActionEnum::create && $objectRights->getAddRight()) {
                $hasErrors = false;

                foreach ($dataStringsIds as $dataStringId) {
                    if (!in_array($dataStringId, $troubledStrings)) {
                        $stringData = $data[$dataStringId];
                        $checkData = $stringData;

                        foreach ($activeEntity->getModel()->getElements() as $element) {
                            if ($element instanceof Timestamp) {
                                unset($checkData[$element->getName()]);
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
            } elseif (ACTION === ActionEnum::change  && $objectRights->getChangeRight()) {
                $successfullySavedStringIds = [];

                foreach ($dataStringsIds as $dataStringId) {
                    if (!in_array($dataStringId, $troubledStrings)) {
                        $stringData = $data[$dataStringId] ?? [];
                        $id = $_REQUEST['id'][$dataStringId] ?? null;

                        if (!is_null($id)) {
                            if (!is_null($objectRights->getChangeRestrict())) {
                                $result = DB->query(
                                    'SELECT * FROM ' . $this->table . ' WHERE ' . $objectRights->getChangeRestrict() . ' AND id=:id',
                                    [['id', $id]],
                                    true,
                                );
                            } else {
                                $result = DB->select($this->table, ['id' => $id], true);
                            }

                            if ($result) {
                                try {
                                    foreach ($activeEntity->getModel()->getElements() as $element) {
                                        if ($element instanceof File) {
                                            unset($fileNames);
                                            preg_match_all('#{([^:]+):([^}]+)}#', ($result[$element->getName()] ?? ''), $fileNames);

                                            foreach ($fileNames[2] as $fileName) {
                                                if (!preg_match('#:' . $fileName . '}#', ($stringData[$element->getName()] ?? ''))) {
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
            } elseif (ACTION === ActionEnum::delete && $objectRights->getDeleteRight()) {
                $arrayOfIds = $useFixedId ? [0] : ID;

                foreach ($arrayOfIds as $key => $id) {
                    if (!is_null($id)) {
                        if (!is_null($objectRights->getDeleteRestrict())) {
                            $result = DB->query(
                                'SELECT * FROM ' . $this->table . ' WHERE ' . $objectRights->getDeleteRestrict() . ' AND id=:id',
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
                                    $catalogEntity = $this instanceof CatalogItemEntity ? $this->getCatalogEntity() : $this;
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
                    ActionEnum::create => $service->getPostCreate() ? $service->{$service->getPostCreate()}($successfulResultsIds) : null,
                    ActionEnum::change => $service->getPostChange() ? $service->{$service->getPostChange()}($successfulResultsIds) : null,
                    ActionEnum::delete => $service->getPostDelete() ? $service->{$service->getPostDelete()}($successfulResultsIds) : null,
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

            $errouneousFields = $this instanceof MultiObjectsEntity && $this->getSubType() === MultiObjectsEntitySubTypeEnum::Excel ?
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
            $OBJECT_LOCALE = $CATALOG_LOCALE = LocaleHelper::getLocale([$this->getCatalogEntity()->getNameUsedInLocale() . '/' . $this->getNameUsedInLocale()]);
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

        if ($this->view->getViewRights()->getViewRight()) {
            if ($act === ActEnum::list) {
                $filtersHtml = $this->getFilters()->getFiltersHtml();

                if ($_ENV['GLOBALTIMERDRAWREPORT']) {
                    $RESPONSE_DATA .= $_GLOBALTIMERDRAWREPORT->getTimerDiffStr('<!-- filters prepare time: %ss-->');
                }

                $maxItemsOnPage = $this->elementsPerPage;

                if (is_null($maxItemsOnPage)) {
                    $this->elementsPerPage = $maxItemsOnPage = 10000;
                }

                $mainTablePrefix = "t1.";

                $preparedViewRestrictSql = $this->view->getViewRights()->getViewRestrict();

                if (is_null($preparedViewRestrictSql)) {
                    $this->view->getViewRights()->setViewRestrict(null);
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

                $filtersQueryParams = $this->getFilters()->getPreparedSearchQueryParams();

                if (count($filtersQueryParams) > 0) {
                    $QUERY_PARAMS = $filtersQueryParams;
                }

                $QUERY = "SELECT t1.*" . $leftJoinedFieldsSql . " FROM " . $this->table . " AS t1" . $leftJoinedTablesSql . $preparedViewRestrictSql;

                if (!is_null($preparedViewRestrictSql) && $this->getFilters()->getPreparedSearchQuerySql() !== "") {
                    $QUERY .= " AND";
                }
                $QUERY .= $this->getFilters()->getPreparedSearchQuerySql() .
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
                    $catalogItemEntity = $this->getCatalogItemEntity();
                    $parentFieldName = $catalogItemEntity->getTableFieldWithParentId();

                    $additionalWhere = preg_replace('# WHERE #', '', $preparedViewRestrictSql ?? '');

                    $catalogEntityFullTree = DB->getTreeOfItems(
                        true,
                        $this->table . ' AS t1' . $leftJoinedTablesSql,
                        $parentFieldName,
                        null,
                        $additionalWhere,
                        $mainTablePrefix . $catalogItemEntity->getTableFieldToDetectType() . "='{menu}' DESC, " . $ORDER,
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
                        $catalogItemEntity->getTableFieldWithParentId(),
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
                                $catalogItemEntity->getTableFieldToDetectType() => '{menu}',
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
                        ($this->getFilters()->getFiltersState() ? ' with_indexer' : '') .
                        ' kind_' . KIND . ' ' . TextHelper::camelCaseToSnakeCase(ObjectsHelper::getClassShortName($this::class)) . ' ' . $act->value . '">';

                    if ($PAGETITLE !== '') {
                        $RESPONSE_DATA .= '<h1 class="form_header"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/">' . $PAGETITLE . '</a></h1>';
                    }

                    /** Добавляем переключатель фильтров */
                    if ($filtersHtml !== '') {
                        $RESPONSE_DATA .= '<div class="indexer_toggle' .
                            ($this->getFilters()->getFiltersState() ? ' indexer_shown' : '') .
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
                        if ($this->getFilters()->getPreparedCurrentFiltersLink() !== '' && $this->getFilters()->getFiltersState()) {
                            $RESPONSE_DATA .= '<div class="copy_filters_link"><a href="' . $this->getFilters()->getPreparedCurrentFiltersLink() .
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
                $modelRights = $this->view->getViewRights();

                if ($id > 0) {
                    $DATA = DB->select($this->table, ['id' => $id], true);

                    if (!$DATA) {
                        return null;
                    }

                    if (is_null($DATA['id'] ?? null)) {
                        $modelRights->setViewRight(false)
                            ->setChangeRight(false)
                            ->setDeleteRight(false);
                    } else {
                        if (in_array($act, [ActEnum::view, ActEnum::edit]) && !is_null($modelRights->getViewRestrict())) {
                            $viewCheckData = DB->query(
                                'SELECT * FROM ' . $this->table . ' WHERE id=:id AND ' . $modelRights->getViewRestrict(),
                                [['id', $id]],
                                true,
                            );

                            if (!$viewCheckData) {
                                $modelRights->setViewRight(false);
                            }
                        }

                        if (in_array($act, [ActEnum::edit]) && !is_null($modelRights->getChangeRestrict())) {
                            $changeCheckData = DB->query(
                                'SELECT * FROM ' . $this->table . ' WHERE id=:id AND ' . $modelRights->getChangeRestrict(),
                                [['id', $id]],
                                true,
                            );

                            if (!$changeCheckData) {
                                $modelRights->setChangeRight(false);
                            }
                        }

                        if (in_array($act, [ActEnum::edit]) && !is_null($modelRights->getDeleteRestrict())) {
                            $deleteCheckData = DB->query(
                                'SELECT * FROM ' . $this->table . ' WHERE id=:id AND ' . $modelRights->getDeleteRestrict(),
                                [['id', $id]],
                                true,
                            );

                            if (!$deleteCheckData) {
                                $modelRights->setDeleteRight(false);
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

                if (!REQUEST_TYPE->isApiRequest() && $modelRights->getViewRight()) {
                    /** Открываем div.maincontent_data */
                    $RESPONSE_DATA .= '<div class="maincontent_data autocreated kind_' . KIND .
                        ' ' . TextHelper::camelCaseToSnakeCase(ObjectsHelper::getClassShortName($this::class)) . ' ' . $act->value . '">';

                    if ($PAGETITLE !== '') {
                        $RESPONSE_DATA .= '<h1 class="form_header"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/">' . $PAGETITLE . '</a></h1>';
                    }

                    $activeEntity = $this;

                    if ($this instanceof CatalogEntity && TextHelper::camelCaseToSnakeCase($this->getCatalogItemEntity()->getName()) === CMSVC) {
                        $activeEntity = $this->getCatalogItemEntity();
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

        if (is_null($sortingItem) || $sortingItem->getShowFieldDataInEntityTable()) {
            if ($modelElement->checkVisibility() || $sortingItem->getSubstituteDataType() === SubstituteDataTypeEnum::TABLE || $sortingItem->getSubstituteDataType() === SubstituteDataTypeEnum::ARRAY) {
                $modelElement->set($DATA_ITEM[$modelElement->getName()]);

                if ($this instanceof CatalogEntity || $this instanceof CatalogItemEntity) {
                    if ($sortingItem->getShowFieldShownNameInCatalogItemString()) {
                        $RESPONSE_DATA .= $modelElement->getShownName() . ': ';
                    }
                    $RESPONSE_DATA .= '<b>';
                }

                $fieldValue = $modelElement->get();

                if (is_null($fieldValue) || (is_string($fieldValue) && trim($fieldValue) === '')) {
                    $GLOBAL_LOCALE = LocaleHelper::getLocale(['fraym', 'dynamiccreate']);
                    $useText = ($modelElement->getName() === 'name' && in_array($DATA_ITEM['code'] ?? '', ['default', '1'])) ?
                        'default' :
                        'not_set';
                    $RESPONSE_DATA .= '<i>' . $GLOBAL_LOCALE[$useText] . '</i>';
                } elseif ($sortingItem->getSubstituteDataType() === SubstituteDataTypeEnum::TABLE) {
                    if ($modelElement instanceof Multiselect) {
                        foreach ($fieldValue as $fieldValueItem) {
                            $RESPONSE_DATA .= (DataHelper::getFlatArrayElement($fieldValueItem, $modelElement->getValues())[1] ?? '') . '<br>';
                        }
                    } else {
                        $RESPONSE_DATA .= $DATA_ITEM[$sortingItem->getSubstituteDataTableName() .
                            TextHelper::mb_ucfirst($sortingItem->getSubstituteDataTableField())];
                    }
                } elseif ($sortingItem->getSubstituteDataType() === SubstituteDataTypeEnum::ARRAY) {
                    $rotatedArrayIndexes = $this->getRotatedArrayIndexes();

                    if (!isset($rotatedArrayIndexes[$sortingItem->getTableFieldName()])) {
                        $rotatedArrayIndexes[$sortingItem->getTableFieldName()] = [];

                        foreach ($sortingItem->getSubstituteDataArray() as $substituteDataArrayItem) {
                            $rotatedArrayIndexes[$sortingItem->getTableFieldName()][$substituteDataArrayItem[0]] = $substituteDataArrayItem[1];
                        }
                        $this->setRotatedArrayIndexes($rotatedArrayIndexes);
                    }

                    if ($modelElement instanceof Multiselect) {
                        foreach ($fieldValue as $fieldValueItem) {
                            if ($rotatedArrayIndexes[$sortingItem->getTableFieldName()][$fieldValueItem] ?? false) {
                                $RESPONSE_DATA .= $rotatedArrayIndexes[$sortingItem->getTableFieldName()][$fieldValueItem] . '<br>';
                            }
                        }
                    } else {
                        $RESPONSE_DATA .= $rotatedArrayIndexes[$sortingItem->getTableFieldName()][$fieldValue];
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

                    if (count($this->sortingData) > 1 && !$sortingItem->getRemoveDotAfterText()) {
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
        $model = $this->getModel();

        if ($model instanceof DeletedAt) {
            $deletedAtValue = $model->getDeletedAtTime();

            DB->update(
                tableName: $this->$this->getTable(),
                data: [
                    'deleted_at' => $deletedAtValue,
                ],
                criteria: [
                    'id' => $id,
                ],
            );
        } else {
            $item = DB->select(
                tableName: $this->getTable(),
                criteria: [
                    'id' => $id,
                ],
                oneResult: true,
            );

            if ($this instanceof CatalogInterface) {
                $elements = $this->detectEntityType($item)->getModel()->getElements();
            } else {
                $elements = $this->getModel()->getElements();
            }

            foreach ($elements as $element) {
                if ($element instanceof File) {
                    unset($fileNames);
                    preg_match_all('#{([^:]+):([^}]+)}#', ($item[$element->getName()] ?? ''), $fileNames);

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
        $activeEntityName = $activeEntity instanceof CatalogItemEntity ? $activeEntity->getCatalogEntity()->getNameUsedInLocale() . '/' . $activeEntity->getNameUsedInLocale() : $activeEntity->getNameUsedInLocale();

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
                    $data[$dataKey] = array_merge($dataValue, DataHelper::unmakeVirtual($dataValue[$this->virtualField]));
                }
            }
        }

        $alternativeDataColumnNames = [];

        $itemsToFilter = ['id', 'catalogLevel'];

        foreach ($this->getModel()->getElements() as $item) {
            if (DataHelper::inArrayAny($contexts, $item->getAttribute()->getContext())) {
                if ($item->getName() !== 'id') {
                    $itemsToFilter[] = $item->getName();

                    if ($item->getAttribute()->getAlternativeDataColumnName()) {
                        $alternativeDataColumnNames[$item->getAttribute()->getAlternativeDataColumnName()][] = $item->getName();
                    }
                }
            }
        }

        /** @var CatalogItemEntity|null $catalogItemEntity */
        $catalogItemEntity = null;
        $itemsToFilterCatalogItem = ['id', 'catalogLevel'];

        if ($this instanceof CatalogEntity) {
            $catalogItemEntity = $this->getCatalogItemEntity();
            $catalogItemContext = $contexts;

            foreach ($contexts as $context) {
                if (str_starts_with($context, ':')) {
                    $catalogItemContext[] = $catalogItemEntity->getName() . $context;
                }
            }

            foreach ($catalogItemEntity->getModel()->getElements() as $item) {
                if (DataHelper::inArrayAny($catalogItemContext, $item->getAttribute()->getContext())) {
                    if ($item->getName() !== 'id') {
                        $itemsToFilterCatalogItem[] = $item->getName();

                        if ($item->getAttribute()->getAlternativeDataColumnName()) {
                            $alternativeDataColumnNames[$item->getAttribute()->getAlternativeDataColumnName()][] = $item->getName();
                        }
                    }
                }
            }
        }

        foreach ($this->sortingData as $sortingItem) {
            if ($sortingItem->getSubstituteDataType() === SubstituteDataTypeEnum::TABLE) {
                $itemsToFilter[] = $sortingItem->getSubstituteDataTableName() . TextHelper::mb_ucfirst($sortingItem->getSubstituteDataTableField());
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

        if (!is_null(SORTING)) {
            $sortingFieldNum = (int) (round(SORTING / 2) - 1);
            $sortingOrder = (SORTING % 2 === 1 ? "" : " DESC");
        }

        foreach ($sortingData as $sortingItemNum => $sortingItem) {
            $sortingItemNum = (int) $sortingItemNum;

            /** Если у $sortingItem выставлен параметр $doNotUseInSorting, мы вообще не включаем его в запрос сортировки данных, никогда */
            if (!$sortingItem->getDoNotUseInSorting()) {
                if ($sortingItem->getSubstituteDataType() === SubstituteDataTypeEnum::TABLE) {
                    if ($this->getModel()->getElement($sortingItem->getTableFieldName()) instanceof Multiselect) {
                        /** Если вдруг указан мультиселект в качестве поля, нам нужно выдернуть первое значение из поля */
                        $leftJoinedTablesSql .= " LEFT JOIN " .
                            $sortingItem->getSubstituteDataTableName() . " t" . $tablesUsedCount . " ON " .
                            "SUBSTRING(t1." . $sortingItem->getTableFieldName() . ", 2, LOCATE('-', t1." . $sortingItem->getTableFieldName() . ", 2) - 2)=" .
                            "t" . $tablesUsedCount . "." . $sortingItem->getSubstituteDataTableId();
                    } else {
                        $leftJoinedFieldsSql .= ", t" . $tablesUsedCount . "." . $sortingItem->getSubstituteDataTableField() . " AS "
                            . $sortingItem->getSubstituteDataTableName() . TextHelper::mb_ucfirst($sortingItem->getSubstituteDataTableField());
                        $leftJoinedTablesSql .= " LEFT JOIN " .
                            $sortingItem->getSubstituteDataTableName() . " t" . $tablesUsedCount . " ON " .
                            "t1." . $sortingItem->getTableFieldName() . "=" .
                            "t" . $tablesUsedCount . "." . $sortingItem->getSubstituteDataTableId();
                    }

                    if (!$sortingItem->getDoNotUseIfNotSortedByThisField() || ($sortingItemNum === $sortingFieldNum && SORTING > 0)) {
                        if ($sortingItemNum === $sortingFieldNum && SORTING > 0) {
                            $ORDER = "t" . $tablesUsedCount . "." . $sortingItem->getSubstituteDataTableField() . $sortingOrder . ", " . $ORDER;
                        } else {
                            $ORDER .= "t" . $tablesUsedCount . "." . $sortingItem->getSubstituteDataTableField() .
                                $sortingItem->getTableFieldOrder()->asText() . ", ";
                        }
                    }
                    ++$tablesUsedCount;
                } elseif ($sortingItem->getSubstituteDataType() === SubstituteDataTypeEnum::ARRAY) {
                    /** Если выставлен параметр doNotUseIfNotSortedByThisField в настройке сортировки, то мы сортируем по данному полю ТОЛЬКО
                     * в случае, если прямо по нему задана сортировка. Это серьезно облегчает запросы */
                    if (!$sortingItem->getDoNotUseIfNotSortedByThisField() || ($sortingItemNum === $sortingFieldNum && SORTING > 0)) {
                        $substituteDataArray = $sortingItem->getSubstituteDataArray();

                        if (is_string($substituteDataArray) && method_exists($this->view, $substituteDataArray)) {
                            $sortingItem->setSubstituteDataArray($this->view->{$substituteDataArray}());
                            $substituteDataArray = $sortingItem->getSubstituteDataArray();
                        }

                        if (count($substituteDataArray) > 0) {
                            if ($_ENV['DATABASE_TYPE'] === "pgsql") {
                                $count_fields = 0;
                                $ordField = "CASE";

                                foreach ($substituteDataArray as $substituteDataItem) {
                                    $count_fields++;
                                    $ordField .= " WHEN " . $mainTablePrefix . $sortingItem->getTableFieldName() .
                                        "='" . $substituteDataItem[0] . "' THEN " . $count_fields;
                                }
                                $ordField .= " ELSE " . ($count_fields + 1) . " END";
                            } else {
                                $ordField = "FIELD(" . $mainTablePrefix . $sortingItem->getTableFieldName();

                                foreach ($substituteDataArray as $substituteDataItem) {
                                    $ordField .= ", " . (is_numeric($substituteDataItem[0]) ? $substituteDataItem[0] : "'" . $substituteDataItem[0] . "'");
                                }
                                $ordField .= ")";
                            }

                            if ($sortingItemNum === $sortingFieldNum && SORTING > 0) {
                                $ORDER = $ordField . $sortingOrder . ', ' . $ORDER;
                            } else {
                                $ORDER .= $ordField . $sortingItem->getTableFieldOrder()->asText() . ", ";
                            }
                        }
                    }
                } else {
                    $preparedSortName = $mainTablePrefix . $sortingItem->getTableFieldName();

                    if (preg_match('#length\(#i', $sortingItem->getTableFieldName())) {
                        $preparedSortName = preg_replace('#length\(#i', 'length(' . $mainTablePrefix, $sortingItem->getTableFieldName());
                    }

                    if (!$sortingItem->getDoNotUseIfNotSortedByThisField() || ($sortingItemNum === $sortingFieldNum && SORTING > 0)) {
                        if ($sortingItemNum === $sortingFieldNum && SORTING > 0) {
                            $ORDER = $preparedSortName . $sortingOrder . ", " . $ORDER;
                        } else {
                            $ORDER .= $preparedSortName . $sortingItem->getTableFieldOrder()->asText() . ", ";
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

    private function getDataAfterValidation(): array
    {
        return $this->dataAfterValidation;
    }

    /** Перевод значений полей в нужный формат для дальнейшего сохранения */
    private function appendDataAfterValidation(string|int $dataStringId, ElementItem $element, mixed $value, ActEnum $act, bool $groupedValue = false): void
    {
        if (!$element instanceof H1 && !$element->getNoData()) {
            if ($act === ActEnum::add && !is_null($element->getOnCreate())) {
                if (!is_null($element->getOnCreate()->getData())) {
                    $value = $element->getOnCreate()->getData();
                } else {
                    $service = $this->view->getCMSVC()->getService();

                    if (method_exists($service, $element->getOnCreate()->getCallback())) {
                        $value = $service->{$element->getOnCreate()->getCallback()}();
                    } else {
                        $model = $this->getModel();

                        if (method_exists($model, $element->getOnCreate()->getCallback())) {
                            $value = $model->{$element->getOnCreate()->getCallback()}();
                        }
                    }
                }
            } elseif ($act === ActEnum::edit && !is_null($element->getOnChange())) {
                if (!is_null($element->getOnChange()->getData())) {
                    $value = $element->getOnChange()->getData();
                } else {
                    $service = $this->view->getCMSVC()->getService();

                    if (method_exists($service, $element->getOnChange()->getCallback())) {
                        $value = $service->{$element->getOnChange()->getCallback()}();
                    } else {
                        $model = $this->getModel();

                        if (method_exists($model, $element->getOnChange()->getCallback())) {
                            $value = $model->{$element->getOnChange()->getCallback()}();
                        }
                    }
                }
            } elseif (!$groupedValue) {
                if ($element instanceof Multiselect) {
                    if (!$element->getOne()) {
                        $rehashedValues = [];

                        if (is_array($value)) {
                            foreach ($value as $key => $item) {
                                if ($item === 'on') {
                                    $rehashedValues[] = $key;
                                } elseif (is_array($item)) {
                                    $rehashedValues[$key] = $item;
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
                                            $createdItemsIds[] = $creator->createItem($value['name'][$key], $this->view->getCMSVC()->getService());
                                        }
                                    }
                                }

                                if (count($createdItemsIds) > 0) {
                                    $value = array_merge($value, $createdItemsIds);
                                }
                                unset($value['new']);
                                unset($value['name']);
                            }
                            $value = DataHelper::arrayToMultiselect(array_unique($value));
                        }
                    }
                } elseif ($element instanceof Password) {
                    $value = $value !== null ? md5($value . $_ENV['PROJECT_HASH_WORD']) : null;
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
                    $value = [$element->getName(), $value, ['email']];
                } elseif ($element instanceof Timestamp) {
                    $value = DateHelper::getNow();
                } elseif ($element instanceof File) {
                    if (is_array($value)) {
                        $formattedValue = implode('', $value);
                        $value = $formattedValue;
                    }
                }

                if ($element->getAttribute()->getSaveHtml()) {
                    $value = [$element->getName(), $value, ['html']];
                }
            }

            if (!$element instanceof Password || !is_null($value)) {
                if (!$element->getVirtual()) {
                    $this->dataAfterValidation[$dataStringId][$element->getAttribute()->getAlternativeDataColumnName() ?? $element->getName()] = $value;
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

        if ($element instanceof Password && $element->getAttribute()->getRepeatPasswordFieldName()) {
            $repeatPasswordFieldName = $element->getAttribute()->getRepeatPasswordFieldName();
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

    private function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
