<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Services\ManifestMoveService;
use App\Utilities\AutowireCheck;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The DI container compiles every class it registers; one it cannot build broke
 * that compilation in production and took every page down. Such classes are
 * left out of the container.
 */
final class AutowireCheckTest extends TestCase
{
    public function testAValueTheContainerCannotGuessKeepsTheClassOut(): void
    {
        $classes = [
            'a path' => new class ('x') {
                public function __construct(public string $file)
                {
                }
            },
            'a nullable path, no default' => new class (null) {
                public function __construct(public ?string $file)
                {
                }
            },
            'an untyped value' => new class (1) {
                /** @param mixed $value */
                public function __construct(public $value)
                {
                }
            },
            'a list' => new class ([]) {
                public function __construct(public array $items)
                {
                }
            },
        ];
        foreach ($classes as $what => $object) {
            self::assertTrue(AutowireCheck::needsUnguessableArgument(new ReflectionClass($object)), $what);
        }
    }

    public function testClassesItCanBuildAreKept(): void
    {
        $classes = [
            'no constructor' => new class () {
            },
            'a path with a default' => new class () {
                public function __construct(public ?string $file = null)
                {
                }
            },
            'services only' => new ReflectionClass(ManifestMoveService::class),
            'variadic' => new class () {
                public function __construct(string ...$parts)
                {
                }
            },
        ];
        foreach ($classes as $what => $object) {
            $reflection = $object instanceof ReflectionClass ? $object : new ReflectionClass($object);
            self::assertFalse(AutowireCheck::needsUnguessableArgument($reflection), $what);
        }
    }
}
