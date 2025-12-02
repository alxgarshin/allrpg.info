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

namespace Fraym\Element\Item;

use Fraym\BaseObject\BaseModel;
use Fraym\Element\Attribute as Attribute;
use Fraym\Entity\BaseEntity;
use Fraym\Enum\ActEnum;
use Fraym\Helper\{DataHelper, ObjectsHelper};
use Fraym\Interface\{ElementAttribute, ElementItem, Validator};
use RuntimeException;

abstract class BaseElement implements ElementItem
{
    /** Данные для замены значения элемента при create */
    protected ?Attribute\OnCreate $create = null;

    /** Данные для замены значения элемента при change */
    protected ?Attribute\OnChange $change = null;

    /** Все нижеидущие переменные присваиваются в процессе обработки объекта */

    /** Родительская сущность */
    protected ?BaseEntity $entity = null;

    /** Родительская модель */
    protected ?BaseModel $model = null;

    /** Название поля: совпадает с названием в БД */
    protected ?string $name = null;

    /** Видимое пользователям название поля */
    protected ?string $shownName = null;

    /** Текст подсказки к полю */
    protected ?string $helpText = null;

    /** Проверенные и отфильтрованные контексты для различных объектов */
    /** @var array<string, array> */
    private array $filteredContexts = [];

    public function asArrayBase(): array
    {
        return [
            'class' => ObjectsHelper::getClassShortName($this::class),
            'name' => $this->name,
            'shownName' => $this->shownName,
            'obligatory' => $this->getObligatory(),
            'helpText' => $this->helpText,
            'helpClass' => $this->getHelpClass(),
            'group' => $this->getGroup(),
            'groupNumber' => $this->getGroupNumber(),
            'noData' => $this->getNoData(),
            'virtual' => $this->getVirtual(),
            'linkAtBegin' => $this->getLinkAt()->getLinkAtBegin(),
            'linkAtEnd' => $this->getLinkAt()->getLinkAtEnd(),
            'lineNumber' => $this->getLineNumber(),
        ];
    }

    public function checkAttribute(ElementAttribute $attribute, string $elementClassName): void
    {
        if (!is_a($attribute, $elementClassName)) {
            throw new RuntimeException('Attribute should be of class: ' . $elementClassName . '. Got ' . $attribute::class . ' instead.');
        }
    }

    public function getOnCreate(): ?Attribute\OnCreate
    {
        return $this->create;
    }

    public function setOnCreate(?Attribute\OnCreate $create): static
    {
        $this->create = $create;

        return $this;
    }

    public function getOnChange(): ?Attribute\OnChange
    {
        return $this->change;
    }

    public function setOnChange(?Attribute\OnChange $change): static
    {
        $this->change = $change;

        return $this;
    }

    public function getEntity(): ?BaseEntity
    {
        return $this->entity;
    }

    public function setEntity(?BaseEntity $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getModel(): ?BaseModel
    {
        return $this->model;
    }

    public function setModel(?BaseModel $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function checkDefaultValueInServiceFunctions(mixed $defaultValue): mixed
    {
        if (!is_null($this->entity) && is_string($defaultValue)) {
            $service = $this->entity->getView()->getCMSVC()->getService();

            if (method_exists($service, $defaultValue)) {
                return $service->{$defaultValue}();
            }
        }

        return $defaultValue;
    }

    public function getLineNumber(): ?int
    {
        return $this->getAttribute()->getLineNumber();
    }

    public function getLineNumberWrapped(): string
    {
        return $this->getAttribute()->getLineNumberWrapped();
    }

    public function getObligatory(): bool
    {
        return $this->getAttribute()->getObligatory() ?? false;
    }

    public function getObligatoryStr(): string
    {
        return $this->getAttribute()->getObligatoryStr();
    }

    public function getGroup(): ?int
    {
        return $this->getAttribute()->getGroup();
    }

    public function getGroupNumber(): ?int
    {
        return $this->getAttribute()->getGroupNumber();
    }

    public function getHelpClass(): ?string
    {
        return $this->getAttribute()->getHelpClass();
    }

    public function getHelpText(): ?string
    {
        return $this->helpText === '' ? null : $this->helpText;
    }

    public function setHelpText(?string $helpText): static
    {
        $this->helpText = $helpText;

        return $this;
    }

    public function getLinkAt(): LinkAt
    {
        return $this->getAttribute()->getLinkAt();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getNoData(): ?bool
    {
        return $this->getAttribute()->getNoData();
    }

    public function getShownName(): ?string
    {
        return $this->shownName === '' ? null : $this->shownName;
    }

    public function setShownName(?string $shownName): static
    {
        $this->shownName = $shownName;

        return $this;
    }

    public function getVirtual(): ?bool
    {
        return $this->getAttribute()->getVirtual();
    }

    public function checkContext(array $context): bool
    {
        foreach ($context as $contextItem) {
            if ($this->getAttribute()->checkContext($contextItem)) {
                return true;
            }
        }

        return false;
    }

    public function checkVisibility(): bool
    {
        return
            !(
                !$this->getObligatory() &&
                (
                    ($this instanceof Select && !$this->getValues()) ||
                    ($this instanceof Multiselect && !$this->getValues())
                ) &&
                (!$this instanceof Select || $this->getHelper() === null)
            ) &&
            !($this instanceof Tab);
    }

    public function checkDOMVisibility(): bool
    {
        return !($this instanceof Hidden) &&
            !($this instanceof Timestamp && !$this->getShowInObjects()) &&
            !($this instanceof H1) &&
            !($this instanceof Tab);
    }

    public function checkWritable(?ActEnum $act = null, ?string $objectName = null): ?bool
    {
        if (is_null($act)) {
            $act = DataHelper::getActDefault($this->entity);
        }

        $context = $this->getContext($objectName);

        $elementIsWritable = null;

        if (
            ($act === ActEnum::add && $this->checkContext($context['CREATE'] ?? [])) ||
            ($act === ActEnum::edit && ($this->checkContext($context['UPDATE'] ?? [])))
        ) {
            if ($act === ActEnum::add && ACTION === null && !$this->checkContext($context['VIEW'] ?? []) && !$this->checkContext($context['VIEWONACTADD'] ?? []) && !$this->getNoData()) {
                return $elementIsWritable;
            }

            $elementIsWritable = true;
        } elseif (
            $this->checkContext($context['VIEW'] ?? []) ||
            (
                $this->checkContext($context['VIEWIFNOTNULL'] ?? []) &&
                !is_null($this->get())
            )
        ) {
            $elementIsWritable = false;
        }

        return $elementIsWritable;
    }

    public function asHTML(bool $elementIsWritable, bool $removeHtmlFromValue = false): string
    {
        $FIELD_RESPONSE_DATA = '';

        $customAsHTMLRenderer = $this->getAttribute()->getCustomAsHTMLRenderer();

        if (!is_null($customAsHTMLRenderer)) {
            $service = $this->getModel()?->getCMSVC()?->getService();

            if ($service && method_exists($service, $customAsHTMLRenderer)) {
                $FIELD_RESPONSE_DATA = $service->{$customAsHTMLRenderer}($this, $elementIsWritable, $removeHtmlFromValue);
            }
        } else {
            $FIELD_RESPONSE_DATA = $this->usualAsHTMLRenderer($elementIsWritable, $removeHtmlFromValue);
        }

        return $FIELD_RESPONSE_DATA;
    }

    public function asHTMLWrapped(?int $lineNumber, bool $elementIsWritable, int $elementTabindexNum): string
    {
        $this->getAttribute()->setLineNumber($lineNumber);

        $FIELD_RESPONSE_DATA = $this->asHTML($elementIsWritable);

        if ($this->checkDOMVisibility()) {
            $RESPONSE_DATA = '';

            if ($FIELD_RESPONSE_DATA !== '' && !is_null($this->getShownName())) {
                $thisName = $this->name . $this->getLineNumberWrapped();

                $RESPONSE_DATA .= '<div class="field ' . ObjectsHelper::getClassShortName($this::class) .
                    ($this instanceof Select && $this->getHelper() ? ' full_width' : '') .
                    ($this instanceof Multiselect && $this->getOne() ? ' multiselect_one' : '') .
                    '" id="field_' . $thisName . '"><div class="fieldname" id="name_' . $thisName .
                    '" tabindex="' . $elementTabindexNum . '">' . $this->getShownName() . '</div>';

                if (!is_null($this->helpText)) {
                    $RESPONSE_DATA .= '<div class="' . ($this->getHelpClass() ?: 'help') . '" id="help_' . $thisName . '">' .
                        $this->helpText . '</div>';
                }

                $RESPONSE_DATA .= '<div class="fieldvalue';

                if (!$elementIsWritable) {
                    $RESPONSE_DATA .= ' read';
                }
                $RESPONSE_DATA .= '" id="div_' . $thisName . '">';
                $RESPONSE_DATA .= $FIELD_RESPONSE_DATA;
                $RESPONSE_DATA .= '</div></div>';
            }

            return $RESPONSE_DATA;
        } else {
            return $FIELD_RESPONSE_DATA;
        }
    }

    public function validate(mixed $value, array $options): array
    {
        $failedValidations = [];

        foreach ($this->getAttribute()->getAdditionalValidators() as $validator) {
            $validatorObject = new $validator();

            if ($validatorObject instanceof Validator) {
                if (!$validatorObject->validate($this, $value, $options)) {
                    $failedValidations[] = $validatorObject->getName();
                }
            }
        }

        return $failedValidations;
    }

    private function getContext(?string $objectName = null): array
    {
        $view = $this->entity?->getView();

        $context = $view?->getCMSVC()?->getContext();

        if (is_null($objectName) && $view !== null) {
            $objectName = $view->getCMSVC()?->getObjectName() ?? ObjectsHelper::getClassShortNameFromCMSVCObject($view);
        } elseif ($context === null) {
            throw new RuntimeException(sprintf('Was not able to find any suitable context for an element %s in object %s', $this->getName(), $objectName));
        }

        if ($this->filteredContexts[$objectName] ?? false) {
            return $this->filteredContexts[$objectName];
        }

        if ($context === null) {
            $context = [
                'LIST' => [
                    ':list',
                    $objectName . ':list',
                ],
                'VIEW' => [
                    ':view',
                    $objectName . ':view',
                ],
                'VIEWIFNOTNULL' => [
                    ':viewIfNotNull',
                    $objectName . ':viewIfNotNull',
                ],
                'VIEWONACTADD' => [
                    ':viewOnActAdd',
                    $objectName . ':viewOnActAdd',
                ],
                'CREATE' => [
                    ':create',
                    $objectName . ':create',
                ],
                'UPDATE' => [
                    ':update',
                    $objectName . ':update',
                ],
                'EMBEDDED' => [
                    ':embedded',
                    $objectName . ':embedded',
                ],
            ];
        }

        /** Убираем все контексты, которые не общие и не принадлежат к запрашивающему объекту */
        foreach ($context as &$contextArray) {
            foreach ($contextArray as $contextKey => $contextItem) {
                if (!str_starts_with($contextItem, ':') && !str_starts_with($contextItem, $objectName . ':')) {
                    unset($contextArray[$contextKey]);
                }
            }
        }

        $this->filteredContexts[$objectName] = $context;

        return $context;
    }
}
