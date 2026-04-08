<?php declare(strict_types=1);

use PHP_SF\System\Core\DateTime;

/**
 * Returns a datetime string shifted forward from the current moment.
 *
 * @param int $seconds Number of seconds to add to the current Unix timestamp.
 *
 * @return string Datetime formatted as `Y-m-d H:i:s`.
 */
function time_inc(int $seconds): string
{
    return date('Y-m-d H:i:s', time() + $seconds);
}


/**
 * Returns a datetime string shifted backward from the current moment.
 *
 * @param int $seconds Number of seconds to subtract from the current Unix timestamp.
 *
 * @return string Datetime formatted as `Y-m-d H:i:s`.
 */
function time_dec(int $seconds): string
{
    return date('Y-m-d H:i:s', time() - $seconds);
}


/**
 * Returns the difference in seconds between the given time and now.
 *
 * - Positive value means the given time is in the future.
 * - Negative value means the given time is in the past.
 *
 * @param DateTimeInterface|string $time Target time as a `DateTimeInterface` or parseable datetime string.
 *
 * @return int Difference in seconds relative to the current moment.
 *
 * @throws Exception When `$time` is a string that cannot be parsed.
 */
function get_time_diff_in_seconds(DateTimeInterface|string $time): int
{
    return (is_string($time) ? new DateTime($time) : $time)->getTimestamp() - (new DateTime())->getTimestamp();
}


/**
 * Returns a localized relative-time string for a past offset in seconds.
 *
 * @param int $seconds Number of seconds before now.
 *
 * @return string Human-readable relative time text.
 */
function get_time_diff_from_seconds(int $seconds): string
{
    return get_time_diff(time_dec($seconds));
}


/**
 * Returns a localized, human-readable relative time string for the given date/time.
 *
 * The function compares the provided `$time` against the current moment and builds
 * a compact range using up to two largest non-zero units, for example:
 * - `2 years and 3 months`
 * - `5 days and 4 hours`
 * - `12 minutes and 10 seconds`
 *
 * The result is wrapped into a directional phrase:
 * - past values: `... ago`
 * - future values: `in ...`
 * - near-zero difference: `a moment ago` or `in a moment`
 *
 * If `$time` is a string, it is parsed with `PHP_SF\System\Core\DateTime`.
 * If `$timezone` is provided, it is used for both parsing string input and
 * generating the current time reference. If omitted, the default PHP timezone
 * is used for string parsing.
 *
 * @param DateTimeInterface|string $time     A target date/time as either:
 *                                           - a `DateTimeInterface` instance, or
 *                                           - a parseable date/time string.
 * @param DateTimeZone|null        $timezone Optional timezone context for parsing/comparison.
 *
 * @return string Localized relative time text based on translation keys
 *                from the `common.time.*` namespace.
 *
 * @throws Exception When `$time` is a string that cannot be parsed into a valid date/time.
 */
function get_time_diff(DateTimeInterface|string $time, DateTimeZone $timezone = null): string
{
    $d   = is_string($time)
        ? new DateTime($time, $timezone ?? new DateTimeZone(date_default_timezone_get()))
        : $time;
    $now = new DateTime('now', $timezone);

    $i = $d->diff($now);

    $units = match (true) {
        $i->y > 0 => [
            ['key' => 'common.time.years', 'count' => $i->y],
            ['key' => 'common.time.months', 'count' => $i->m],
        ],
        $i->m > 0 => [
            ['key' => 'common.time.months', 'count' => $i->m],
            ['key' => 'common.time.days', 'count' => $i->d],
        ],
        $i->d > 0 => [
            ['key' => 'common.time.days', 'count' => $i->d],
            ['key' => 'common.time.hours', 'count' => $i->h],
        ],
        $i->h > 0 => [
            ['key' => 'common.time.hours', 'count' => $i->h],
            ['key' => 'common.time.minutes', 'count' => $i->i],
        ],
        $i->i > 0 => [
            ['key' => 'common.time.minutes', 'count' => $i->i],
            ['key' => 'common.time.seconds', 'count' => $i->s],
        ],
        $i->s > 1 => [
            ['key' => 'common.time.seconds', 'count' => $i->s],
        ],
        default   => [],
    };

    if (empty($units)) {
        return _t($i->invert ? 'common.time.moment_from_now' : 'common.time.moment_ago');
    }

    $parts = [];

    foreach ($units as $u) {
        if ($u['count'] > 0) {
            $parts[] = _t($u['key'], ['count' => $u['count']]);
        }
    }

    $timeStr = isset($parts[1])
        ? _t('common.time.range', ['first' => $parts[0], 'second' => $parts[1]])
        : $parts[0];

    $key = $i->invert ? 'common.time.in' : 'common.time.ago';

    return _t($key, ['time' => $timeStr]);
}
