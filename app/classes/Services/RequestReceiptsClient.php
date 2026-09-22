<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;

/**
 * The lab side of request receipts: after saving a pull, tell the STS which
 * requests were saved and which were not (remote/v2/request-receipts.php).
 *
 * Only used when the STS answered the pull with X-Request-Receipts: 1. A receipt
 * that cannot be delivered is kept in request_receipt_outbox and sent on the next
 * run; the requests it covers stay in flight on the STS meanwhile and are sent
 * again, which the lab handles as it always has. Nothing here can fail the pull.
 */
final class RequestReceiptsClient
{
    /** Below the STS's own cap of 5000 per receipt. */
    public const int MAX_IDS_PER_RECEIPT = 2000;
    /** An undelivered receipt this old no longer matters: the STS has resent its requests. */
    public const int OUTBOX_MAX_AGE_DAYS = 7;

    public function __construct(
        private readonly DatabaseService $db,
        private readonly ApiService $apiService
    ) {
    }

    /**
     * @param list<string> $saved unique_ids saved (or already up to date)
     * @param list<array{unique_id: string, reason: ?string}> $failed
     * @return bool Every part was delivered.
     */
    public function send(string $stsUrl, int $labId, string $testType, array $saved, array $failed): bool
    {
        $delivered = true;
        foreach (self::split($labId, $testType, $saved, $failed) as $receipt) {
            $status = $this->post($stsUrl, $receipt);
            if ($status === 200) {
                continue;
            }
            $delivered = false;
            if (!self::isRejected($status)) {
                $this->keep($testType, $receipt);
            }
        }
        return $delivered;
    }

    /**
     * Send what earlier runs could not, oldest first. Stops at the first failure:
     * the STS is still unreachable, and the rest can wait for the next run.
     *
     * @return int Receipts delivered.
     */
    public function flushOutbox(string $stsUrl): int
    {
        try {
            $this->db->rawQuery(
                'DELETE FROM request_receipt_outbox WHERE created_datetime < SUBDATE(?, INTERVAL '
                    . self::OUTBOX_MAX_AGE_DAYS . ' DAY)',
                [DateUtility::getCurrentDateTime()]
            );
            $pending = $this->db->rawQuery('SELECT * FROM request_receipt_outbox ORDER BY receipt_id');
        } catch (Throwable) {
            return 0; // No outbox table yet: nothing was ever kept.
        }

        $delivered = 0;
        foreach ($pending as $row) {
            $receipt = json_decode((string) $row['receipt'], true);
            if (!is_array($receipt)) {
                $this->db->rawQuery('DELETE FROM request_receipt_outbox WHERE receipt_id = ?', [$row['receipt_id']]);
                continue;
            }
            $status = $this->post($stsUrl, $receipt);
            if ($status !== 200 && !self::isRejected($status)) {
                $this->db->rawQuery(
                    'UPDATE request_receipt_outbox SET attempts = attempts + 1, last_attempt_datetime = ?
                        WHERE receipt_id = ?',
                    [DateUtility::getCurrentDateTime(), $row['receipt_id']]
                );
                break;
            }
            $this->db->rawQuery('DELETE FROM request_receipt_outbox WHERE receipt_id = ?', [$row['receipt_id']]);
            $delivered++;
        }
        return $delivered;
    }

    /**
     * @param list<string> $saved
     * @param list<array{unique_id: string, reason: ?string}> $failed
     * @return list<array<string, mixed>>
     */
    public static function split(int $labId, string $testType, array $saved, array $failed): array
    {
        $entries = [...array_map(static fn(string $id): array => ['saved', $id], $saved), ...array_map(
            static fn(array $failure): array => ['failed', $failure],
            $failed
        )];
        $receipts = [];
        foreach (array_chunk($entries, self::MAX_IDS_PER_RECEIPT) as $chunk) {
            $receipt = ['labId' => $labId, 'testType' => $testType, 'saved' => [], 'failed' => []];
            foreach ($chunk as [$kind, $value]) {
                $receipt[$kind][] = $value;
            }
            $receipts[] = $receipt;
        }
        return $receipts;
    }

    /**
     * The STS read the receipt and refused it (400 malformed, 413 too large).
     * Sending it again would get the same answer, so it is not kept.
     */
    private static function isRejected(int $status): bool
    {
        return in_array($status, [400, 413, 422], true);
    }

    /**
     * @param array<string, mixed> $receipt
     * @return int HTTP status, 0 when nothing came back.
     */
    private function post(string $stsUrl, array $receipt): int
    {
        try {
            $response = $this->apiService->post(
                rtrim($stsUrl, '/') . '/remote/v2/request-receipts.php',
                $receipt,
                gzip: true,
                returnWithStatusCode: true
            );
            return is_array($response) ? (int) ($response['httpStatusCode'] ?? 0) : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /** @param array<string, mixed> $receipt */
    private function keep(string $testType, array $receipt): void
    {
        try {
            $this->db->insert('request_receipt_outbox', [
                'test_type' => $testType,
                'receipt' => JsonUtility::encodeUtf8Json($receipt),
                'created_datetime' => DateUtility::getCurrentDateTime(),
            ]);
        } catch (Throwable) {
            // No outbox table yet. The requests stay in flight on the STS and come
            // back in a later pull, so dropping the receipt loses nothing.
        }
    }
}
