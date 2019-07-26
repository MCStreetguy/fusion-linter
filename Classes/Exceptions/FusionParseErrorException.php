<?php
namespace MCStreetguy\FusionDebugger\Exceptions;

use Neos\Flow\Exception;

/**
 * A fusion parse exception.
 */
class FusionParseErrorException extends Exception
{
    public static function forFile(string $path, \Throwable $previous = null)
    {
        return new self("Failed to parse fusion file at $path!", 1564130709438, $previous);
    }
}
