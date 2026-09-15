<?php

declare(strict_types=1);

namespace App\Utilities;

/**
 * Turns the result-PDF reference a mail form posts back into a file that may be
 * attached, or null.
 *
 * The result mail screens generate a PDF, keep what the generator returned in a
 * hidden field, and post it back to be attached (at once, or through the
 * temp_mail queue that bin/send-email.php works through). That value arrives in
 * three shapes, depending on the screen and on when the row was queued:
 *
 * - a download grant (dl1.…), which the PDF generators echo today;
 * - base64 of an absolute path, which they echoed before grants, and which is
 *   still the format stored in temp_mail.attachment;
 * - a bare file name inside the temporary folder, which the confirm screens
 *   write their summary PDF under.
 *
 * Whatever the shape, the file has to exist inside the temporary or upload
 * folders. A path pointing anywhere else is refused, so a posted value cannot
 * mail out a file such as the database configuration.
 */
final class MailAttachmentUtility
{
    public static function resolve(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);

        if (DownloadTokenUtility::looksLikeToken($value)) {
            $path = DownloadTokenUtility::resolve($value);
        } else {
            $decoded = base64_decode($value, true);
            $candidate = ($decoded !== false && str_contains($decoded, DIRECTORY_SEPARATOR))
                ? urldecode($decoded)
                : TEMP_PATH . DIRECTORY_SEPARATOR . basename($value);
            $path = realpath($candidate) ?: null;
        }

        return $path !== null && is_file($path) && self::isInsideAllowedFolder($path) ? $path : null;
    }

    /**
     * The value to store in temp_mail.attachment for bin/send-email.php, or null
     * when there is nothing that may be attached.
     */
    public static function queueValue(mixed $value): ?string
    {
        $path = self::resolve($value);
        return $path === null ? null : base64_encode($path);
    }

    private static function isInsideAllowedFolder(string $path): bool
    {
        foreach (['TEMP_PATH', 'VAR_TEMP_PATH', 'UPLOAD_PATH'] as $folder) {
            $root = defined($folder) ? realpath((string) constant($folder)) : false;
            if ($root !== false && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return false;
    }
}
