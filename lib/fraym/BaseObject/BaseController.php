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

use Fraym\BaseObject\Trait\InitDependencyInjectionsTrait;
use Fraym\Entity\BaseEntity;
use Fraym\Enum\{ActEnum, ActionEnum};
use Fraym\Helper\{AuthHelper, DataHelper, LocaleHelper, ObjectsHelper, ResponseHelper};
use Fraym\Interface\Response;
use Fraym\Response\{ArrayResponse, HtmlResponse};
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;

/** @template T of BaseService */
abstract class BaseController
{
    use InitDependencyInjectionsTrait;

    protected ?array $LOCALE = null;

    protected CMSVC $CMSVC;

    public function construct(?CMSVC $CMSVC = null, bool $CMSVCinit = true): static
    {
        if (is_null($CMSVC)) {
            $reflection = new ReflectionObject($this);

            if ($reflection->getAttributes(CMSVC::class)[0] ?? false) {
                $this->CMSVC = $reflection->getAttributes(CMSVC::class)[0]->newInstance();
                $this->CMSVC->setController($this);

                if ($CMSVCinit) {
                    $this->CMSVC->init();
                }
            }
        } else {
            $this->CMSVC = $CMSVC;
            $this->CMSVC->setController($this);
        }

        $this->LOCALE = $this->setLOCALE([ObjectsHelper::getClassShortNameFromCMSVCObject($this), 'global']);

        $this->initDependencyInjections();

        return $this;
    }

    public function init(): static
    {
        return $this;
    }

    public function Response(): ?Response
    {
        if ($this->getEntity()) {
            if ($this->getService() && $this->getService() instanceof BaseService) {
                $this->getService()->preLoadModel();
            }

            if (in_array(ACTION, ActionEnum::getBaseValues())) {
                return $this->getEntity()->fraymAction();
            } elseif (ACTION === ActionEnum::setFilters) {
                $filtersData = $this->getEntity()->getFilters()->prepareSearchSqlAndFiltersLink();

                if ($filtersData[0] !== '') {
                    if (PRE_REQUEST_CHECK) {
                        return ResponseHelper::response([], ResponseHelper::redirectConstruct());
                    }
                } else {
                    $this->getEntity()->getFilters()->clearEntityFiltersData();

                    if (PRE_REQUEST_CHECK) {
                        $LOC = $this->getEntity()->getFilters()->getLocale();
                        ResponseHelper::responseOneBlock('error', $LOC['filters_not_set']);
                    } else {
                        ResponseHelper::redirect(ABSOLUTE_PATH . '/');
                    }
                }
            } elseif (ACTION === ActionEnum::clearFilters) {
                $this->getEntity()->getFilters()->clearEntityFiltersData();
                $this->getService()->postClearFilters();
                ResponseHelper::redirect(ResponseHelper::redirectConstruct());
            } elseif (!is_null(ACTION) && method_exists($this, ACTION)) {
                $action = ACTION;

                return $this->{$action}();
            }
        }

        return $this->Default();
    }

    public function getLOCALE(): ?array
    {
        return $this->LOCALE;
    }

    public function setLOCALE(array $entityPathInLocale): ?array
    {
        $this->LOCALE = LocaleHelper::getLocale($entityPathInLocale);

        return $this->LOCALE;
    }

    public function getMessages(): array
    {
        return $this->LOCALE['messages'];
    }

    public function getCMSVC(): CMSVC
    {
        return $this->CMSVC;
    }

    public function getEntity(): ?BaseEntity
    {
        return $this->CMSVC->getView()?->getEntity();
    }

    /** @return T|null */
    public function getService(): ?BaseService
    {
        return $this->CMSVC->getService();
    }

    public function asHtml(?string $html, ?string $pagetitle): ?HtmlResponse
    {
        return !is_null($html) ? new HtmlResponse($html, $pagetitle) : null;
    }

    public function asArray(?array $data): ?ArrayResponse
    {
        return !is_null($data) ? new ArrayResponse($data) : null;
    }

    public function checkIfIsAccessible(?string $methodName = null): bool
    {
        $canProceed = true;

        $reflectionClass = new ReflectionClass($this::class);

        if (!is_null($methodName)) {
            $method = $reflectionClass->getMethod($methodName);
            $attributes = $method->getAttributes(IsAccessible::class);
        } else {
            $attributes = $reflectionClass->getAttributes(IsAccessible::class);
        }

        if (count($attributes) > 0) {
            /** @var IsAccessible */
            $isAccessibleAttribute = $attributes[0]->newInstance();

            if (!CURRENT_USER->isLogged() && !is_null(AuthHelper::getRefreshTokenCookie())) {
                ResponseHelper::response401();
            }

            if (!CURRENT_USER->isLogged()) {
                if (!is_null($isAccessibleAttribute->getRedirectPath())) {
                    ResponseHelper::redirect($isAccessibleAttribute->getRedirectPath(), $isAccessibleAttribute->getRedirectData());
                } else {
                    $canProceed = false;
                }
            }

            if ($canProceed && !is_null($isAccessibleAttribute->getAdditionalCheckAccessHelper()) && !is_null($isAccessibleAttribute->getAdditionalCheckAccessMethod())) {
                $helper = $isAccessibleAttribute->getAdditionalCheckAccessHelper();
                $method = $isAccessibleAttribute->getAdditionalCheckAccessMethod();
                $refClass = new ReflectionClass($helper);

                if ($refClass->hasMethod($method)) {
                    $refMethod = new ReflectionMethod($helper, $method);
                    $canProceed = $refMethod->invoke(null);
                }
                unset($helper);
                unset($method);
                unset($refClass);
                unset($refMethod);
            }
        }

        return $canProceed;
    }

    public function checkIfHasToBeAndIsAdmin(?string $methodName = null): bool
    {
        $canProceed = true;

        $reflectionClass = new ReflectionClass($this::class);

        if (!is_null($methodName)) {
            $method = $reflectionClass->getMethod($methodName);
            $attributes = $method->getAttributes(IsAdmin::class);
        } else {
            $attributes = $reflectionClass->getAttributes(IsAdmin::class);
        }

        if (count($attributes) > 0) {
            if (!CURRENT_USER->isLogged() && !is_null(AuthHelper::getRefreshTokenCookie())) {
                ResponseHelper::response401();
            } elseif (!CURRENT_USER->isAdmin()) {
                /** @var IsAdmin $isAdminAttribute */
                $isAdminAttribute = $attributes[0]->newInstance();

                if (!is_null($isAdminAttribute->getRedirectPath())) {
                    ResponseHelper::redirect($isAdminAttribute->getRedirectPath(), $isAdminAttribute->getRedirectData());
                } else {
                    $canProceed = false;
                }
            }
        }

        return $canProceed;
    }

    protected function Default(): ?Response
    {
        $entity = $this->getEntity();

        if (!is_null($entity)) {
            $entity->getView()->preViewHandler();
        }

        if (
            !is_null($this->CMSVC->getView()) &&
            (
                is_null($entity) ||
                (DataHelper::getActDefault($entity) === ActEnum::view && $entity->getUseCustomView()) ||
                (DataHelper::getActDefault($entity) === ActEnum::list && $entity->getUseCustomList())
            )
        ) {
            return $this->CMSVC->getView()->Response();
        }

        $responseData = $entity->view();

        if ($responseData instanceof HtmlResponse) {
            $entity->getView()->postViewHandler($responseData);
        }

        return $responseData;
    }
}
