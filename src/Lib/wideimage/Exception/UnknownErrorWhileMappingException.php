<?php

declare(strict_types=1);

namespace WideImage\Exception;

/**
 * Thrown when an image can't be saved (returns false by the mapper).
 */
class UnknownErrorWhileMappingException extends Exception
{
}
