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

use Attribute;
use Fraym\BaseObject\BaseService;
use Fraym\Enum\ActEnum;
use Fraym\Helper\DataHelper;

/** Права */
#[Attribute(Attribute::TARGET_CLASS)]
class Rights
{
    /** Родительская сущность */
    protected ?BaseEntity $entity = null;

    public function __construct(
        /** Право видеть данные: bool или название функции сервиса для проверки */
        private bool|string $viewRight,

        /** Право добавлять данные: bool или название функции сервиса для проверки */
        private bool|string $addRight,

        /** Право менять данные: bool или название функции сервиса для проверки */
        private bool|string $changeRight,

        /** Право удалять данные: bool или название функции сервиса для проверки */
        private bool|string $deleteRight,

        /** SQL-ограничение на просмотр данных */
        private ?string $viewRestrict = null,

        /** SQL-ограничение на изменение данных */
        private ?string $changeRestrict = null,

        /** SQL-ограничение на удаление данных */
        private ?string $deleteRestrict = null,
    ) {
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

    public function getService(): ?BaseService
    {
        return $this->entity->getView()->getCMSVC()->getService();
    }

    public function getViewRight(): bool
    {
        $defaultValue = $this->viewRight;
        $service = $this->getService();

        if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
            $defaultValue = $service->{$defaultValue}();
        }

        if (!is_bool($defaultValue) || $defaultValue === false) {
            if (DataHelper::getActDefault($this->getEntity()) === ActEnum::add) {
                $defaultValue = $this->getAddRight();
            } elseif (!is_null(DataHelper::getId())) {
                $defaultValue = $this->getChangeRight() || $this->getDeleteRight();
            }
        }

        return $defaultValue;
    }

    public function setViewRight(bool|string $viewRight): static
    {
        $this->viewRight = $viewRight;

        return $this;
    }

    public function getAddRight(): bool
    {
        $defaultValue = $this->addRight;
        $service = $this->getService();

        if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
            $defaultValue = $service->{$defaultValue}();
        }

        return $defaultValue;
    }

    public function setAddRight(bool|string $addRight): static
    {
        $this->addRight = $addRight;

        return $this;
    }

    public function getChangeRight(): bool
    {
        $defaultValue = $this->changeRight;
        $service = $this->getService();

        if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
            $defaultValue = $service->{$defaultValue}();
        }

        return $defaultValue;
    }

    public function setChangeRight(bool|string $changeRight): static
    {
        $this->changeRight = $changeRight;

        return $this;
    }

    public function getDeleteRight(): bool
    {
        $defaultValue = $this->deleteRight;
        $service = $this->getService();

        if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
            $defaultValue = $service->{$defaultValue}();
        }

        return $defaultValue;
    }

    public function setDeleteRight(bool|string $deleteRight): static
    {
        $this->deleteRight = $deleteRight;

        return $this;
    }

    public function getViewRestrict(): ?string
    {
        $defaultValue = $this->viewRestrict;
        $service = $this->getService();

        if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
            $defaultValue = $service->{$defaultValue}();
        }

        if ($defaultValue === '') {
            $defaultValue = null;
        }

        return $defaultValue;
    }

    public function setViewRestrict(?string $viewRestrict): static
    {
        $this->viewRestrict = $viewRestrict;

        return $this;
    }

    public function getChangeRestrict(): ?string
    {
        $defaultValue = $this->changeRestrict;
        $service = $this->getService();

        if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
            $defaultValue = $service->{$defaultValue}();
        }

        if ($defaultValue === '') {
            $defaultValue = null;
        }

        return $defaultValue;
    }

    public function setChangeRestrict(?string $changeRestrict): static
    {
        $this->changeRestrict = $changeRestrict;

        return $this;
    }

    public function getDeleteRestrict(): ?string
    {
        $defaultValue = $this->deleteRestrict;
        $service = $this->getService();

        if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
            $defaultValue = $service->{$defaultValue}();
        }

        if ($defaultValue === '') {
            $defaultValue = null;
        }

        return $defaultValue;
    }

    public function setDeleteRestrict(?string $deleteRestrict): static
    {
        $this->deleteRestrict = $deleteRestrict;

        return $this;
    }
}
