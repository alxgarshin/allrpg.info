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
    public ?BaseEntity $entity = null;

    public ?BaseService $service {
        get => $this->entity->view->CMSVC->service;
    }

    public function __construct(
        /** Право видеть данные: bool или название функции сервиса для проверки */
        public bool|string $viewRight {
            get {
                $defaultValue = $this->viewRight;
                $service = $this->service;

                if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
                    $defaultValue = $service->{$defaultValue}();
                }

                if (!is_bool($defaultValue) || $defaultValue === false) {
                    if (DataHelper::getActDefault($this->entity) === ActEnum::add) {
                        $defaultValue = $this->addRight;
                    } elseif (!is_null(DataHelper::getId())) {
                        $defaultValue = $this->changeRight || $this->deleteRight;
                    }
                }

                return $defaultValue;
            }
            set => $this->viewRight = $value;
        },

        /** Право добавлять данные: bool или название функции сервиса для проверки */
        public bool|string $addRight {
            get {
                $defaultValue = $this->addRight;
                $service = $this->service;

                if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
                    $defaultValue = $service->{$defaultValue}();
                }

                return $defaultValue;
            }
            set => $this->addRight = $value;
        },

        /** Право менять данные: bool или название функции сервиса для проверки */
        public bool|string $changeRight {
            get {
                $defaultValue = $this->changeRight;
                $service = $this->service;

                if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
                    $defaultValue = $service->{$defaultValue}();
                }

                return $defaultValue;
            }
            set => $this->changeRight = $value;
        },

        /** Право удалять данные: bool или название функции сервиса для проверки */
        public bool|string $deleteRight {
            get {
                $defaultValue = $this->deleteRight;
                $service = $this->service;

                if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
                    $defaultValue = $service->{$defaultValue}();
                }

                return $defaultValue;
            }
            set => $this->deleteRight = $value;
        },

        /** SQL-ограничение на просмотр данных */
        public ?string $viewRestrict = null {
            get {
                $defaultValue = $this->viewRestrict;
                $service = $this->service;

                if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
                    $defaultValue = $service->{$defaultValue}();
                }

                if ($defaultValue === '') {
                    $defaultValue = null;
                }

                return $defaultValue;
            }
            set => $this->viewRestrict = $value;
        },

        /** SQL-ограничение на изменение данных */
        public ?string $changeRestrict = null {
            get {
                $defaultValue = $this->changeRestrict;
                $service = $this->service;

                if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
                    $defaultValue = $service->{$defaultValue}();
                }

                if ($defaultValue === '') {
                    $defaultValue = null;
                }

                return $defaultValue;
            }
            set => $this->changeRestrict = $value;
        },

        /** SQL-ограничение на удаление данных */
        public ?string $deleteRestrict = null {
            get {
                $defaultValue = $this->deleteRestrict;
                $service = $this->service;

                if (is_string($defaultValue) && method_exists($service, $defaultValue)) {
                    $defaultValue = $service->{$defaultValue}();
                }

                if ($defaultValue === '') {
                    $defaultValue = null;
                }

                return $defaultValue;
            }
            set => $this->deleteRestrict = $value;
        },
    ) {}
}
