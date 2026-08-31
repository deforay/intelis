<?php

namespace App\Exceptions;

/**
 * A sample-code queue failure that retrying cannot cure.
 *
 * The queue's failure handling has exactly two outcomes -- retry later
 * (processed = 2) or park permanently (processed = 3) -- and it used to route
 * everything to retry. For a failure whose cause is frozen into the queue row
 * or the data it points at (duplicate rows sharing a unique_id, a row missing
 * its required fields), that meant the same error on every cron run, forever:
 * one lab produced 296k log lines in half a day this way. Throwing this class
 * routes the item to processed = 3 instead, where the cron skips it but a
 * named run (a manifest re-activation) can still reach it once the underlying
 * data is repaired.
 */
class PermanentQueueFailureException extends SystemException
{
}
