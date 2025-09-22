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

use Fraym\Helper\LocaleHelper;
use Fraym\Interface\Response;
use Fraym\Response\ArrayResponse;

abstract class BaseHelper
{
    protected ?array $LOCALE = null;

    public function __construct()
    {
        $this->LOCALE = $this->setLOCALE([KIND, 'global']);
    }

    abstract public function Response(): ?Response;

    abstract public function printOut(int|string|null $id): string;

    abstract public function printItem(?BaseModel $entityItem): string;

    public function getLOCALE(): ?array
    {
        return $this->LOCALE;
    }

    public function setLOCALE(array $entityPathInLocale): ?array
    {
        $this->LOCALE = LocaleHelper::getLocale($entityPathInLocale);

        return $this->LOCALE;
    }

    public function asArray(?array $data): ?ArrayResponse
    {
        return !is_null($data) ? new ArrayResponse($data) : null;
    }
}
