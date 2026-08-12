<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\Services\InstrumentInstallationResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Covers which installation a row is attributed to. The lookup itself is one IN query;
 * what is worth pinning down is what the answer is used for, so the resolver is built
 * without its constructor and the mapping is exercised against a lookup result supplied
 * here. These run without a database.
 */
final class InstrumentInstallationResolverTest extends TestCase
{
    private const SOURCE = 'interface-aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
    private const INSTALLATION = '11111111-2222-4333-8444-555555555555';

    /**
     * The identifier the tool stamps on its events is 46 characters and belongs to the
     * tool. What is stored is the 36-character identity this server assigned. Getting
     * this the other way round is what the resolver exists to prevent.
     */
    public function testASourceIsExchangedForTheServersOwnIdentity(): void
    {
        $resolved = $this->map(
            [['source_installation_id' => self::SOURCE]],
            [self::SOURCE => self::INSTALLATION]
        );

        self::assertSame([self::INSTALLATION], $resolved);
        self::assertSame(36, strlen((string) $resolved[0]));
    }

    /**
     * An installation this server never activated has no identity here. Falling back to
     * the tool's own identifier would overflow CHAR(36) and match nothing.
     */
    public function testAnUnregisteredSourceIsLeftUnnamed(): void
    {
        self::assertSame(
            [null],
            $this->map([['source_installation_id' => self::SOURCE]], [])
        );
    }

    /**
     * A batch read out of an interfacing database shared by several tools carries
     * several sources, and each row has to keep its own.
     */
    public function testABatchSpanningInstallationsKeepsThemApart(): void
    {
        $otherSource = 'interface-99999999-8888-4777-8666-555555555555';
        $otherInstallation = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

        $resolved = $this->map(
            [
                ['source_installation_id' => self::SOURCE],
                ['source_installation_id' => $otherSource],
                ['source_installation_id' => self::SOURCE],
            ],
            [self::SOURCE => self::INSTALLATION, $otherSource => $otherInstallation]
        );

        self::assertSame(
            [self::INSTALLATION, $otherInstallation, self::INSTALLATION],
            $resolved
        );
    }

    /**
     * A relayed row was resolved by the LIS that first stored it and already carries
     * this server's kind of identifier. The installations table is never relayed, so
     * the choice is between keeping that identifier and losing it.
     */
    public function testARelayedRowKeepsTheIdentityItArrivedWith(): void
    {
        self::assertSame(
            [self::INSTALLATION],
            $this->map([['installation_id' => self::INSTALLATION]], [])
        );
    }

    /**
     * Both columns present means a relayed row that still carries the source it began
     * as. The identity already settled wins; the lookup is not second-guessed.
     */
    public function testAnAlreadyResolvedIdentityIsPreferredToTheSource(): void
    {
        $resolved = $this->map(
            [[
                'installation_id' => self::INSTALLATION,
                'source_installation_id' => self::SOURCE,
            ]],
            [self::SOURCE => 'ffffffff-eeee-4ddd-8ccc-bbbbbbbbbbbb']
        );

        self::assertSame([self::INSTALLATION], $resolved);
    }

    /**
     * An installation_id that is not the shape of one is not treated as already
     * resolved -- otherwise a tool-side identifier landing in that column would be
     * stored verbatim, which is the failure this whole path guards against.
     *
     * @param mixed $value
     */
    #[DataProvider('unusableInstallationIdProvider')]
    public function testSomethingOtherThanAnIdentityIsNotTakenForOne(mixed $value): void
    {
        self::assertSame(
            [null],
            $this->map([['installation_id' => $value]], [])
        );
    }

    /** @return array<string, array{mixed}> */
    public static function unusableInstallationIdProvider(): array
    {
        return [
            'the tool-side source identifier' => [self::SOURCE],
            'empty' => [''],
            'not a uuid' => ['ANALYZER-1'],
            'truncated to the column width' => ['11111111-2222-4333-8444-5555555555'],
            'an array' => [['11111111-2222-4333-8444-555555555555']],
            'null' => [null],
        ];
    }

    /** @param mixed $value */
    #[DataProvider('unusableSourceProvider')]
    public function testAnUnusableSourceIsNotLookedUp(mixed $value): void
    {
        $reflected = new ReflectionMethod(InstrumentInstallationResolver::class, 'sourceOf');

        self::assertNull($reflected->invoke($this->resolver(), ['source_installation_id' => $value]));
    }

    /** @return array<string, array{mixed}> */
    public static function unusableSourceProvider(): array
    {
        return [
            'empty' => [''],
            'too short to be one' => ['abc'],
            'past the column width' => [str_repeat('a', 129)],
            'carries a space' => ['interface id'],
            'carries a quote' => ["interface-'"],
            'an array' => [['interface-aaaaaaaa']],
            'null' => [null],
        ];
    }

    /** A source at the column width is usable; one character more is not. */
    public function testTheSourceLengthBoundIsTheColumnWidth(): void
    {
        $reflected = new ReflectionMethod(InstrumentInstallationResolver::class, 'sourceOf');
        $resolver = $this->resolver();

        self::assertSame(
            str_repeat('a', 128),
            $reflected->invoke($resolver, ['source_installation_id' => str_repeat('a', 128)])
        );
        self::assertNull($reflected->invoke($resolver, ['source_installation_id' => str_repeat('a', 129)]));
    }

    /** Rows keep the keys they were given, so callers can line answers up with input. */
    public function testAnswersAreKeyedAsTheRowsWere(): void
    {
        $resolved = $this->map(
            [5 => ['source_installation_id' => self::SOURCE], 9 => []],
            [self::SOURCE => self::INSTALLATION]
        );

        self::assertSame([5 => self::INSTALLATION, 9 => null], $resolved);
    }

    /**
     * A source already looked up is not looked up again. Proven by the absence of a
     * database rather than by counting queries: the resolver is built without its
     * constructor, so reaching for one would fail outright.
     */
    public function testASourceAlreadyLookedUpDoesNotGoBackToTheDatabase(): void
    {
        $resolver = $this->resolver();
        $this->seed($resolver, [7 . ':' . self::SOURCE => self::INSTALLATION]);

        self::assertSame(
            [self::INSTALLATION],
            $resolver->resolve([['source_installation_id' => self::SOURCE]], 7)
        );
    }

    /**
     * Held misses too, or an unregistered tool -- the case that repeats most, since
     * every one of its summaries misses -- would query on every row.
     */
    public function testASourceKnownToBeUnregisteredDoesNotGoBackToTheDatabase(): void
    {
        $resolver = $this->resolver();
        $this->seed($resolver, [7 . ':' . self::SOURCE => null]);

        self::assertSame(
            [null],
            $resolver->resolve([['source_installation_id' => self::SOURCE]], 7)
        );
    }

    /**
     * The lab is part of what was looked up. The same tool identifier under a different
     * lab is a different question, and answering it from the cache regardless would
     * attribute one lab's events to another lab's installation.
     */
    public function testTheCacheDoesNotCarryAcrossLabs(): void
    {
        $otherInstallation = 'ffffffff-eeee-4ddd-8ccc-bbbbbbbbbbbb';
        $resolver = $this->resolver();
        $this->seed($resolver, [
            7 . ':' . self::SOURCE => self::INSTALLATION,
            8 . ':' . self::SOURCE => $otherInstallation,
        ]);

        $rows = [['source_installation_id' => self::SOURCE]];

        self::assertSame([self::INSTALLATION], $resolver->resolve($rows, 7));
        self::assertSame([$otherInstallation], $resolver->resolve($rows, 8));
    }

    /**
     * A lookup that cannot run leaves rows unattributed instead of throwing. The event
     * is worth more than knowing which installation sent it, and bin/interface.php
     * treats a throw from that block as "activity not imported" -- so propagating would
     * stop telemetry importing over a detail the import can manage without.
     */
    public function testAFailedLookupLeavesRowsUnattributed(): void
    {
        // Built without its constructor, so there is no database to look in.
        self::assertSame(
            [null],
            $this->resolver()->resolve([['source_installation_id' => self::SOURCE]], 7)
        );
    }

    /** @param array<string, string|null> $seen */
    private function seed(InstrumentInstallationResolver $resolver, array $seen): void
    {
        $property = new \ReflectionProperty(InstrumentInstallationResolver::class, 'seen');
        $property->setValue($resolver, $seen);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $known
     * @return array<int, string|null>
     */
    private function map(array $rows, array $known): array
    {
        $reflected = new ReflectionMethod(InstrumentInstallationResolver::class, 'map');

        return $reflected->invoke($this->resolver(), $rows, $known);
    }

    private function resolver(): InstrumentInstallationResolver
    {
        // No constructor: it wants a database, and none of this reaches one.
        return (new ReflectionClass(InstrumentInstallationResolver::class))->newInstanceWithoutConstructor();
    }
}
