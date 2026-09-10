<?php
declare(strict_types=1);
namespace App\Utilities;
/**
* Seconds rendered for a reader. Two units at most, zero parts dropped.
* Pure functions, so the unit suite can drive them without a database.
*/
final class DurationUtility
{
private const MINUTE = 60;
private const HOUR = 3600;
private const DAY = 86400;
/** 0 => '-', 38 => '38 sec', 750 => '12 min 30 sec', 8115 => '2 hr 15 min' */
public static function humanReadable(int $seconds): string
{
if ($seconds <= 0) {
return '-';
}
if ($seconds < self::MINUTE) {
return $seconds . ' ' . _translate('sec');
}
if ($seconds < self::HOUR) {
return self::pair(
intdiv($seconds, self::MINUTE),
_translate('min'),
$seconds % self::MINUTE,
_translate('sec')
);
}
if ($seconds < self::DAY) {
return self::pair(
intdiv($seconds, self::HOUR),
_translate('hr'),
intdiv($seconds % self::HOUR, self::MINUTE),
_translate('min')
);
}
return self::pair(
intdiv($seconds, self::DAY),
_translate('d'),
intdiv($seconds % self::DAY, self::HOUR),
_translate('hr')
);
}
/** 8115 => '02:15:15'. For exports, where a spreadsheet has to add the column up. */
public static function clock(int $seconds): string
{
$seconds = max(0, $seconds);
return sprintf(
'%02d:%02d:%02d',
intdiv($seconds, self::HOUR),
intdiv($seconds % self::HOUR, self::MINUTE),
$seconds % self::MINUTE
);
}
private static function pair(int $big, string $bigUnit, int $small, string $smallUnit):
string
{
$out = $big . ' ' . $bigUnit;
if ($small > 0) {
$out .= ' ' . $small . ' ' . $smallUnit;
}
return $out;
}
}