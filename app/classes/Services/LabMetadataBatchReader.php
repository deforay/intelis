<?php

namespace App\Services;

/**
 * The next batch of an append-only table the lab sends the STS with its metadata
 * (app/tasks/remote/lab-metadata-sender.php): instrument activity, daily instrument
 * usage, sample storage history. Each table keeps a watermark of the last row the STS
 * accepted, and a run sends what lies past it, oldest first, capped, so a backlog
 * goes over several runs rather than as one payload held in memory.
 */
final class LabMetadataBatchReader
{
    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * @param string $column the column the watermark is kept on; rows are ordered by it
     * @param mixed $sentUpTo the watermark; empty when nothing has been sent yet
     * @param bool $fromStart a forced sync: start over from the first row
     * @return list<array<string, mixed>>
     */
    public function next(string $table, string $column, mixed $sentUpTo, int $limit, bool $fromStart = false): array
    {
        $this->db->reset();
        if (!$fromStart && !empty($sentUpTo)) {
            $this->db->where($column . ' > ?', [$sentUpTo]);
        }
        $this->db->orderBy($column, 'ASC');
        return $this->db->get($table, $limit) ?: [];
    }
}
