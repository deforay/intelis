<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TestRequestsService;
use PHPUnit\Framework\TestCase;

/**
 * The sync receiver may only insert a record it will be able to find again.
 *
 * When it inserted one it could not, the record was inserted a second time on
 * the next sync and a third on the one after: 141 records became 537,074 rows
 * on one instance, at one new copy each every ten minutes, because
 * findMatchingLocalRecord() returned the same empty array for "searched and
 * found nothing" as for "had nothing to search by".
 *
 * These cases are the shapes that decide it. Pure array logic -- the method is
 * static and touches neither the database nor the session.
 */
final class SyncRecordIdentityTest extends TestCase
{
    /** The shape that caused the damage: a sample code and nothing else. */
    public function testSampleCodeAloneIsNotAnIdentity(): void
    {
        $this->assertFalse(TestRequestsService::hasUsableIdentity([
            'sample_code' => 'HAUT19053111417',
            'unique_id' => null,
            'remote_sample_code' => null,
            'lab_id' => null,
            'facility_id' => null,
        ]));
    }

    /**
     * sample_code is not unique -- 47% of rows on one production database share
     * theirs -- so it only becomes an identity paired with somewhere.
     */
    public function testSampleCodeWithLabIsAnIdentity(): void
    {
        $this->assertTrue(TestRequestsService::hasUsableIdentity([
            'sample_code' => 'VL012159161',
            'lab_id' => 14,
        ]));
    }

    public function testSampleCodeWithFacilityIsAnIdentity(): void
    {
        $this->assertTrue(TestRequestsService::hasUsableIdentity([
            'sample_code' => 'VL012159161',
            'facility_id' => 208,
        ]));
    }

    public function testRemoteSampleCodeAloneIsAnIdentity(): void
    {
        $this->assertTrue(TestRequestsService::hasUsableIdentity([
            'remote_sample_code' => 'STS-99114',
        ]));
    }

    public function testUniqueIdAloneIsAnIdentity(): void
    {
        $this->assertTrue(TestRequestsService::hasUsableIdentity([
            'unique_id' => '01J8ZQ0K2M4N6P8R0T2V4X6Z8A',
        ]));
    }

    /** Nothing at all -- the case the empty-candidate branch always covered. */
    public function testEmptyRecordIsNotAnIdentity(): void
    {
        $this->assertFalse(TestRequestsService::hasUsableIdentity([]));
    }

    /**
     * A payload that carries every key but fills them with blanks reads as
     * populated to anything checking with isset().
     */
    public function testBlankAndWhitespaceValuesAreNotIdentities(): void
    {
        $this->assertFalse(TestRequestsService::hasUsableIdentity([
            'sample_code' => '  ',
            'unique_id' => '',
            'remote_sample_code' => '   ',
            'lab_id' => '',
            'facility_id' => null,
        ]));
    }

    /**
     * A lab id of 0 is absence wearing a number. Treating it as a real value
     * would build a lookup that can never match, which ends in the same insert
     * loop as having no key at all.
     */
    public function testZeroLabOrFacilityIdIsNotAnIdentity(): void
    {
        $this->assertFalse(TestRequestsService::hasUsableIdentity([
            'sample_code' => 'VL012159161',
            'lab_id' => 0,
            'facility_id' => '0',
        ]));
    }

    /**
     * A sample code paired with a lab has to stay sufficient on its own: it is
     * the identity most synced records actually arrive with, and it is tried
     * ahead of unique_id because a unique_id has been seen arriving attached to
     * the wrong sample_code.
     */
    public function testLabPairStandsWithoutAnyOtherKey(): void
    {
        $this->assertTrue(TestRequestsService::hasUsableIdentity([
            'sample_code' => 'VL012159161',
            'lab_id' => 14,
            'unique_id' => null,
            'remote_sample_code' => null,
            'facility_id' => null,
        ]));
    }
}
