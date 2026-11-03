<?php

namespace App\Services;

use Sqids\Sqids;
use App\Exceptions\SystemException;

/**
 * The one place that turns a sample id into a link and a link back into a sample.
 *
 * Pages used to pick their own id format: some read base64_decode($_GET['id']),
 * others MiscUtility::desqid(). When a page and the links to it disagreed, the page
 * decoded the wrong number. In #472 the EID result page opened sample 15948 from
 * the link for sample 123. A link built with url() and read with load() always
 * agrees, and load() also checks the user may open the sample.
 *
 * The encoding hides nothing: access is decided by assertCanOpen(), not by the id
 * being hard to guess. The "s." prefix is there because base64 and sqids never
 * produce a ".", so a link in an old format fails to decode instead of opening a
 * different sample.
 */
final class SampleAccessService
{
    private const string PREFIX = 's.';
    private const int MIN_LENGTH = 10;

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $general,
    ) {}

    /** The id, or ids, as they go into a link or a form field. */
    public function encode(int ...$ids): string
    {
        if ($ids === []) {
            throw new SystemException('encode() needs at least one id');
        }
        foreach ($ids as $id) {
            if ($id <= 0) {
                throw new SystemException('A sample id must be a positive number');
            }
        }
        return self::PREFIX . $this->sqids()->encode($ids);
    }

    /**
     * The ids in a value that encode() made, or [] for anything else.
     *
     * @return list<int>
     */
    public function decodeList(?string $value): array
    {
        $value = trim((string) $value);
        if (!str_starts_with($value, self::PREFIX)) {
            return [];
        }
        $payload = substr($value, strlen(self::PREFIX));
        if ($payload === '') {
            return [];
        }
        $ids = $this->sqids()->decode($payload);
        // Several strings decode to the same ids. Only the one encode() writes is
        // accepted, so a hand-edited value cannot pass as a valid link.
        if ($ids === [] || $this->sqids()->encode($ids) !== $payload) {
            return [];
        }
        foreach ($ids as $id) {
            if ($id <= 0) {
                return [];
            }
        }
        return array_values($ids);
    }

    /** The id in a value that encode() made from one id, or null. */
    public function decode(?string $value): ?int
    {
        $ids = $this->decodeList($value);
        return count($ids) === 1 ? $ids[0] : null;
    }

    /** A link to a page that opens one sample, e.g. url('/eid/results/eid-update-result.php', $eidId). */
    public function url(string $page, int $sampleId): string
    {
        return $page . '?id=' . rawurlencode($this->encode($sampleId));
    }

    /**
     * The sample a link points to, after checking the user may open it.
     *
     * @throws SystemException 404 when the link or the sample is not valid,
     *                         403 when the user may not open it
     */
    public function load(string $testType, ?string $value): array
    {
        $id = $this->decode($value);
        if ($id === null) {
            throw new SystemException(_translate('This link is not valid. Open the sample from the list.'), 404);
        }
        $table = TestsService::getTestTableName($testType);
        $primaryKey = TestsService::getPrimaryColumn($testType);
        $sample = $this->db->rawQueryOne("SELECT * FROM $table WHERE $primaryKey = ?", [$id]);
        if (empty($sample)) {
            throw new SystemException(_translate('Sample not found'), 404);
        }
        $this->assertCanOpen($sample);
        return $sample;
    }

    /**
     * Stops when the user may not open this sample. For pages that load the sample
     * with their own query.
     *
     * @throws SystemException 403
     */
    public function assertCanOpen(array $sample): void
    {
        $this->general->assertFacilityAllowed((int) ($sample['facility_id'] ?? 0));
    }

    private function sqids(): Sqids
    {
        return new Sqids('', self::MIN_LENGTH);
    }
}
