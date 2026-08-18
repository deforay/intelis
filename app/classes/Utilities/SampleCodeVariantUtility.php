<?php

declare(strict_types=1);

namespace App\Utilities;

/**
 * Distinguishing two samples that arrived carrying the same sample code.
 *
 * A sample code is minted by whichever instance creates the request, from a
 * counter keyed only on (test_type, year, code_type) -- no instance, no lab --
 * and a VL code minted on a LIS carries no lab component either. So two
 * instances that both send work to the same testing lab will eventually mint
 * the same code, each one correctly, neither aware of the other. On one
 * national database 59,821 code/key pairs are claimed by more than one
 * instance, and every large lab there is fed by three to eight instances.
 *
 * The unique index on (sample_code, lab_id) is right and should stay: a lab
 * scanning a code has to get one sample. But it asserts something no code path
 * upholds, so the collision surfaces as a failed write -- and on the result
 * path that meant the whole record was rolled back and the result was lost.
 *
 * Rather than lose it, the arriving sample takes a numbered variant of the
 * code: VL02261176, then VL02261176-1, VL02261176-2. The sample that already
 * holds the bare code keeps it, so nothing printed or filed stops being valid,
 * and the variant is still recognisably the same code to anyone reading it.
 *
 * This is deliberately a smaller idea than it looks. It does not stop
 * collisions, it absorbs them, and it is not a code format -- the suffix never
 * reaches the counter, never appears in sample_code_key, and (because every
 * module excludes sample_code from the updates it sends down) never reaches the
 * lab that minted the original.
 */
final class SampleCodeVariantUtility
{
    /**
     * The separator. '-' matches what stsLabPostfix() already appends, so it is
     * nothing new to read; the suffix here is always digits, which is what
     * tells the two apart.
     */
    private const SEPARATOR = '-';

    /**
     * Is $code the base code itself, or one of its numbered variants?
     *
     * Used to make the assignment stick. The lab keeps sending the bare code on
     * every sync, so without this the record would be renamed back, collide
     * again, and fail again on every run -- the same unbounded loop the sync
     * receiver produced when it re-inserted records it could not match.
     */
    public static function isVariantOf(string $code, string $base): bool
    {
        $code = trim($code);
        $base = trim($base);
        if ($code === '' || $base === '') {
            return false;
        }
        return (bool) preg_match(
            '/^' . preg_quote($base, '/') . '(' . preg_quote(self::SEPARATOR, '/') . '\d+)?$/',
            $code
        );
    }

    /**
     * The lowest numbered variant of $base that nothing in $taken is using.
     *
     * Counts rather than assuming -1 is free: labs here are fed by up to eight
     * instances, so a third and fourth claim on the same code is expected, not
     * exceptional.
     *
     * @param string[] $taken every code already in use at this lab that is the
     *                        base code or a variant of it
     */
    public static function nextVariant(string $base, array $taken): string
    {
        $base = trim($base);
        $highest = 0;
        $baseIsTaken = false;

        foreach ($taken as $code) {
            $code = trim((string) $code);
            if ($code === $base) {
                $baseIsTaken = true;
                continue;
            }
            if (preg_match(
                '/^' . preg_quote($base, '/') . preg_quote(self::SEPARATOR, '/') . '(\d+)$/',
                $code,
                $matches
            )) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        // Nothing holds the bare code, so there is no conflict to resolve and
        // the sample keeps the code its own lab gave it.
        if (!$baseIsTaken && $highest === 0) {
            return $base;
        }

        return $base . self::SEPARATOR . ($highest + 1);
    }

    /** The LIKE escape character, named here so the query and the pattern agree. */
    public const LIKE_ESCAPE = '|';

    /**
     * SQL-LIKE pattern matching the base code and every variant of it.
     *
     * The escape is defensive rather than necessary -- sample codes are
     * alphanumeric in every format in use -- but a code holding a '%' would
     * otherwise match, and quietly renumber, unrelated samples.
     *
     * '|' rather than a backslash because the pattern is written into a
     * double-quoted PHP string that then becomes a MySQL string literal, and a
     * backslash has a meaning to both of them on the way through.
     */
    public static function likePattern(string $base): string
    {
        $escaped = str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'],
            trim($base)
        );
        return $escaped . self::SEPARATOR . '%';
    }
}
