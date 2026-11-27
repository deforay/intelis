<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ActivityLogService;
use PHPUnit\Framework\TestCase;

/**
 * ActivityLogService::changes() decides what the User Activity Log shows as
 * changed: field, label, before and after.
 */
final class ActivityLogServiceTest extends TestCase
{
    public function testListsOnlyTheFieldsThatChanged(): void
    {
        $changes = ActivityLogService::changes(
            ['user_name' => 'Jane', 'email' => 'j@lab.org', 'status' => 'active'],
            ['user_name' => 'Jane', 'email' => 'jane@lab.org', 'status' => 'inactive'],
            ['email' => 'Email']
        );

        $this->assertSame([
            ['field' => 'email', 'label' => 'Email', 'from' => 'j@lab.org', 'to' => 'jane@lab.org'],
            ['field' => 'status', 'label' => 'Status', 'from' => 'active', 'to' => 'inactive'],
        ], $changes);
    }

    public function testComparesValuesAsText(): void
    {
        $this->assertSame([], ActivityLogService::changes(
            ['target' => 100, 'flag' => null],
            ['target' => '100', 'flag' => '']
        ));
    }

    public function testANewRecordListsEveryFieldItWasSavedWith(): void
    {
        $changes = ActivityLogService::changes(null, ['result' => 'Target Not Detected', 'status' => 'active']);

        $this->assertSame(['', ''], array_column($changes, 'from'));
        $this->assertSame(['Target Not Detected', 'active'], array_column($changes, 'to'));
    }

    public function testAListRecordsTheItemsAddedAndRemoved(): void
    {
        $changes = ActivityLogService::changes(
            ['facilities' => ['Lab A', 'Lab B', 'Clinic C']],
            ['facilities' => ['Lab B', 'Clinic C', 'Clinic D']],
            ['facilities' => 'Facilities']
        );

        $this->assertCount(1, $changes);
        $this->assertSame('Facilities', $changes[0]['label']);
        $this->assertSame(['Clinic D'], $changes[0]['added']);
        $this->assertSame(['Lab A'], $changes[0]['removed']);
    }

    public function testTheSameItemsInAnotherOrderAreNotAChange(): void
    {
        $this->assertSame([], ActivityLogService::changes(
            ['facilities' => ['Lab A', 'Lab B']],
            ['facilities' => ['Lab B', 'Lab A']]
        ));
    }

    public function testNestedSettingsAreComparedFieldByFieldUnderTheirParentsLabel(): void
    {
        $changes = ActivityLogService::changes(
            ['test_results_config' => ['result_type' => 'quantitative', 'threshold_value' => '1000']],
            ['test_results_config' => ['result_type' => 'quantitative', 'threshold_value' => '200']],
            ['test_results_config' => 'Result settings']
        );

        $this->assertSame([[
            'field' => 'test_results_config.threshold_value',
            'label' => 'Result settings › Threshold value',
            'from' => '1000',
            'to' => '200',
        ]], $changes);
    }

    public function testASecretIsRecordedAsChangedWithoutItsValues(): void
    {
        $changes = ActivityLogService::changes(
            ['password' => 'hash-one', 'api_token' => '', 'smart_connect_enrollment_key' => 'abc', 'key' => 'k1'],
            ['password' => 'hash-two', 'api_token' => 'tok', 'smart_connect_enrollment_key' => 'def', 'key' => 'k2']
        );

        $this->assertSame(
            [
                ['(hidden)', '(hidden)'],
                ['', '(hidden)'],
                ['(hidden)', '(hidden)'],
                ['(hidden)', '(hidden)'],
            ],
            array_map(static fn(array $c): array => [$c['from'], $c['to']], $changes)
        );
    }

    public function testAFieldThatOnlyEndsInAWordLikeKeyIsNotHidden(): void
    {
        $changes = ActivityLogService::changes(['monkey_count' => '1'], ['monkey_count' => '2']);

        $this->assertSame('1', $changes[0]['from']);
    }

    public function testIgnoredFieldsAreSkipped(): void
    {
        $this->assertSame([], ActivityLogService::changes(
            ['updated_datetime' => '2026-01-01 00:00:00', 'data_sync' => 0],
            ['updated_datetime' => '2026-11-27 10:00:00', 'data_sync' => 1],
            [],
            ['updated_datetime', 'data_sync']
        ));
    }

    public function testALongValueIsCut(): void
    {
        $changes = ActivityLogService::changes(['header_text' => ''], ['header_text' => str_repeat('x', 5000)]);

        $this->assertSame(2001, mb_strlen($changes[0]['to']));
    }

    public function testHumanizeReadsFieldNames(): void
    {
        $this->assertSame('Facility type', ActivityLogService::humanize('facility_type'));
        $this->assertSame('Pass percentage', ActivityLogService::humanize('passPercentage'));
        $this->assertSame(
            'Targets › Vl › Monthly target',
            ActivityLogService::humanize('targets.vl.monthly_target')
        );
    }
}
