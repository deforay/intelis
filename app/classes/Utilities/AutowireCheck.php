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
    /**
     * Classes left out on purpose. bin/build/check-di.php fails on any other one,
     * so a class callers get from the container that gains such an argument is
     * caught in CI, not on the page that asks for it.
     */
    public const LEFT_OUT = [
        \App\Interop\Dhis2::class,
        \App\Interop\Fhir::class,
        \App\Utilities\ImageResizeUtility::class,
        // Built per sync run with a query callable
        \App\Utilities\ResultSyncBatch::class,
        // Defined by hand in app/system/di.php with the debug flag
        \App\ErrorHandlers\ErrorResponseGenerator::class,
    ];

    /**
     * @param list<string> $leftOut
     * @return list<string> those AutowireCheck::LEFT_OUT does not name
     */
    public static function unexpected(array $leftOut): array
    {
        return array_values(array_diff($leftOut, self::LEFT_OUT));
    }

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
