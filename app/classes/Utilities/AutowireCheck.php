<?php

namespace App\Utilities;

use ReflectionClass;
use ReflectionNamedType;

/**
 * Which classes the DI container can build on its own (app/system/di.php).
 *
 * The container registers every class under app/classes and, in production,
 * compiles them all up front. One whose constructor needs a value it cannot
 * guess -- a string, a number, an array, an untyped parameter, with no default --
 * failed that compilation, and every page and API call with it. Such a class is
 * made with `new` by its callers and is left out of the container.
 */
final class AutowireCheck
{
    /** @param ReflectionClass<object> $reflection */
    public static function needsUnguessableArgument(ReflectionClass $reflection): bool
    {
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->isDefaultValueAvailable() || $parameter->isVariadic()) {
                continue;
            }
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                return true;
            }
        }
        return false;
    }
}
