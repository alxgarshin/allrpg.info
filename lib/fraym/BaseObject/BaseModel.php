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

namespace Fraym\BaseObject;

use AllowDynamicProperties;
use App\Helper\TextHelper;
use Exception;
use Fraym\BaseObject\Trait\InitDependencyInjectionsTrait;
use Fraym\Element\{Attribute as Attribute, Item as Item};
use Fraym\Entity\BaseEntity;
use Fraym\Helper\{DataHelper, ObjectsHelper};
use Fraym\Interface\{ElementAttribute, ElementItem, HasDefaultValue};
use ReflectionAttribute;
use ReflectionObject;
use RuntimeException;

#[AllowDynamicProperties]
abstract class BaseModel
{
    use InitDependencyInjectionsTrait;

    public ?CMSVC $CMSVC = null;

    public array $modelData = [] {
        get => $this->modelData;
        set(?array $value) {
            $this->modelData = $value ?? [];
        }
    }

    /** @var array<int, ElementItem> */
    public array $elementsList = [];

    public ?BaseEntity $entity {
        get => $this->CMSVC->view?->entity;
    }

    public function __clone()
    {
        foreach ($this->elementsList as $element) {
            $this->{$element->name} = clone $element;
            $this->{$element->name}->model = $this;
        }
    }

    public function construct(?CMSVC $CMSVC = null, ?BaseEntity $alternativeEntity = null): static
    {
        $reflection = new ReflectionObject($this);

        if (is_null($CMSVC)) {
            $controllerRef = $reflection->getAttributes(Controller::class);

            if ($controllerRef[0] ?? false) {
                /** @var Controller $controller */
                $controller = $controllerRef[0]->newInstance();
                $this->CMSVC = $controller->CMSVC;
            } else {
                $CMSVC = $reflection->getAttributes(CMSVC::class);

                if ($CMSVC[0] ?? false) {
                    $this->CMSVC = $CMSVC[0]->newInstance();
                    $this->CMSVC->model = $this::class;
                    $this->CMSVC->init();
                }
            }
        } else {
            $this->CMSVC = $CMSVC;
        }

        if (is_null($alternativeEntity)) {
            $this->CMSVC->model = $this::class;
        }

        $properties = $reflection->getProperties();

        foreach ($properties as $propertyData) {
            $item = $propertyData->getAttributes(Attribute\BaseElement::class, ReflectionAttribute::IS_INSTANCEOF);

            if ($item[0] ?? false) {
                $className = str_replace('\Attribute\\', '\Item\\', $item[0]->name);
                $attribute = $propertyData->getAttributes(Attribute\BaseElement::class, ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();

                $create = null;
                $createInstance = $propertyData->getAttributes(Attribute\OnCreate::class, ReflectionAttribute::IS_INSTANCEOF);

                if ($createInstance[0] ?? false) {
                    $create = $createInstance[0]->newInstance();
                }

                $change = null;
                $changeInstance = $propertyData->getAttributes(Attribute\OnChange::class, ReflectionAttribute::IS_INSTANCEOF);

                if ($changeInstance[0] ?? false) {
                    $change = $changeInstance[0]->newInstance();
                }

                $this->initElement(
                    $propertyData->name,
                    $className,
                    $attribute,
                    $create,
                    $change,
                    $alternativeEntity,
                );
            } elseif (!in_array($propertyData->name, ['entity', 'elementsList', 'modelData', 'CMSVC'])) {
                throw new RuntimeException('Property ' . $propertyData->name . ' in model ' . $this::class . ' does not have a BaseElement attribute set.');
            }
        }

        $this->initDependencyInjections();

        return $this;
    }

    public function init(): static
    {
        return $this;
    }

    public function getModelDataFieldValue(string $elementName): mixed
    {
        return $this->modelData[$elementName] ?? null;
    }

    public function initElement(
        ElementItem|string $elementOrElementName,
        ?string $className = null,
        ?ElementAttribute $attribute = null,
        ?Attribute\OnCreate $create = null,
        ?Attribute\OnChange $change = null,
        ?BaseEntity $alternativeEntity = null,
    ): ?ElementItem {
        $service = $this->CMSVC->service;

        if ($elementOrElementName instanceof ElementItem) {
            $elementName = $elementOrElementName->name;
            $attribute = $elementOrElementName->getAttribute();
        } else {
            if (is_null($className)) {
                throw new Exception('className must be set in initElement.');
            }

            if (is_null($attribute)) {
                throw new Exception('attribute must be set in initElement.');
            }

            $elementName = $elementOrElementName;
            $elementOrElementName = new $className();
        }
        $property = $this->{$elementName} = $elementOrElementName;

        if ($property instanceof ElementItem) {
            $property->model = $this;

            $this->elementsList[] = $property;

            $property->name = $elementName;

            $property->setAttribute($attribute);

            $entity = $this->entity;

            if (!is_null($alternativeEntity)) {
                $entity = $alternativeEntity;
            }

            $property->entity = $entity;

            $elementsLocale = $entity->getElementsLocale();

            $modelElementsLocale = null;
            $entityNameFromModel = TextHelper::camelCaseToSnakeCase(ObjectsHelper::getClassShortNameFromCMSVCObject($entity->model));

            if ($entity->getNameUsedInLocale() !== $entityNameFromModel) {
                $entityNameUsedInLocale = $entity->getNameUsedInLocale();
                $entity->name = $entityNameFromModel;
                $modelElementsLocale = $entity->getElementsLocale();
                $entity->name = $entityNameUsedInLocale;
                unset($entityNameUsedInLocale);
            }

            $elementLocale = $elementsLocale[$property->name] ?? [];
            $modelElementLocale = $modelElementsLocale[$property->name] ?? [];

            if ($modelElementLocale || $elementLocale) {
                $property->shownName = array_key_exists('shownName', $elementLocale) ?
                    $elementLocale['shownName'] :
                    $modelElementLocale['shownName'] ?? $property->shownName;

                $property->helpText = array_key_exists('helpText', $elementLocale) ?
                    $elementLocale['helpText'] :
                    $modelElementLocale['helpText'] ?? $property->helpText;

                $attr = $property->getAttribute();

                if ($attr instanceof HasDefaultValue && is_null($attr->defaultValue)) {
                    $attr->defaultValue = array_key_exists('defaultValue', $elementLocale) ? $elementLocale['defaultValue'] : $modelElementLocale['defaultValue'] ?? null;
                }
            }

            if (method_exists($property, 'getValues')) {
                /** @var Item\Multiselect|Item\Select $property */
                $values = null;

                if ($modelElementLocale || $elementLocale) {
                    $values = array_key_exists('values', $elementLocale) ? $elementLocale['values'] : $modelElementLocale['values'] ?? null;
                }

                if (is_null($values)) {
                    $values = $property->getValues();

                    if (is_string($values) && method_exists($service, $values)) {
                        $values = $service->{$values}();
                    }
                }

                $property->getAttribute()->values = $values;

                $locked = $property->getAttribute()->locked;

                if (is_string($locked) && method_exists($service, $locked)) {
                    $locked =  $service->{$locked}();
                }

                $property->getAttribute()->locked = $locked;
            }

            if (method_exists($property, 'getCreator')) {
                /** @var Item\Multiselect $property */
                $multiselectCreatorAdditionalData = $property->getCreator()?->getAdditional();

                if (!is_null($multiselectCreatorAdditionalData)) {
                    foreach ($multiselectCreatorAdditionalData as $multiselectCreatorAdditionalName => $multiselectCreatorAdditionalItem) {
                        if (is_string($multiselectCreatorAdditionalItem) && method_exists($service, $multiselectCreatorAdditionalItem)) {
                            $multiselectCreatorAdditionalData[$multiselectCreatorAdditionalName] = $service->{$multiselectCreatorAdditionalItem}();
                        }
                    }
                    $property->getCreator()->setAdditional($multiselectCreatorAdditionalData);
                }
            }

            if ($property instanceof Item\Multiselect) {
                $images = $property->getAttribute()->images;

                if (is_string($images) && method_exists($service, $images)) {
                    $property->getAttribute()->images = $service->{$images}();
                }
            }

            $context = $property->getAttribute()->context;

            if (is_string($context) && method_exists($service, $context)) {
                $context = $service->{$context}();
            } elseif (!is_array($context) || count($context) === 0) {
                $objectName = ObjectsHelper::getClassShortNameFromCMSVCObject($this);
                $propertiesWithListContext = $this->CMSVC->view->propertiesWithListContext;

                $context = [];

                if (in_array($property->name, $propertiesWithListContext) || count($propertiesWithListContext) === 0) {
                    $context[] = $objectName . ':list';
                }

                $context = array_merge($context, [
                    $objectName . ':view',
                    $objectName . ':create',
                    $objectName . ':update',
                    $objectName . ':embedded',
                ]);
            }
            $property->getAttribute()->context = $context;

            $property->create = $create;

            $property->change = $change;

            return $property;
        }

        return null;
    }

    public function getElement(string $elementName): ?ElementItem
    {
        if (property_exists($this, $elementName)) {
            $property = $this->{$elementName};

            if ($property instanceof ElementItem) {
                return $property;
            }
        }

        return null;
    }

    public function removeElement(string $elementName): static
    {
        if (property_exists($this, $elementName)) {
            if (isset($this->{$elementName})) {
                $property = $this->{$elementName};

                if ($property instanceof ElementItem) {
                    $elementsList = $this->elementsList;
                    $key = array_search($property, $elementsList);
                    unset($elementsList[$key]);
                    $this->elementsList = $elementsList;
                    unset($this->{$elementName});
                }
            }
        }

        return $this;
    }

    public function changeElementsOrder(string $elementName, ?string $setBeforeElementName = null): static
    {
        $modelElements = $this->elementsList;

        $setBeforeElementIndex = is_null($setBeforeElementName) ? 0 : null;
        $elementIndex = null;

        foreach ($modelElements as $key => $modelElement) {
            if (!is_null($setBeforeElementName) && $modelElement->name === $setBeforeElementName) {
                $setBeforeElementIndex = $key;
            } elseif ($modelElement->name === $elementName) {
                $elementIndex = $key;
            }

            if (!is_null($setBeforeElementIndex) && !is_null($elementIndex)) {
                $modelElements = DataHelper::changeValueIndexInArray($modelElements, $elementIndex, $setBeforeElementIndex);
                break;
            }
        }
        $this->elementsList = $modelElements;

        return $this;
    }
}
