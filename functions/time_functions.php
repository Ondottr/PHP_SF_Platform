<?php declare( strict_types=1 );

use PHP_SF\System\Core\DateTime;

function timeInc( int $seconds): string
{
    return date('Y-m-d H:i:s', time() + $seconds);
}

function timeDec(int $seconds): string
{
    return date('Y-m-d H:i:s', time() - $seconds);
}

function getTimeDiffInSeconds(DateTimeInterface|string $time): int
{
    return ( is_string($time) ? new DateTime( $time) : $time )->getTimestamp() - ( new DateTime )->getTimestamp();
}

function getTimeFromSeconds(int $seconds): string
{
    return getTimeDiff(timeDec($seconds));
}

function showTimeFromSeconds(int $seconds): void
{
    echo getTimeFromSeconds($seconds);
}

function getTimeDiff(DateTimeInterface|string $time, DateTimeZone $timezone = null): string
{
    $res = '';

    $d = is_string($time) ? new DateTime( $time, $timezone) : $time;

    $interval = $d->diff(new DateTime( 'now', $timezone));

    if ($interval->y !== 0)
        $res .= _t('common.time.years',   ['count' => $interval->y]) . ' ';

    if ($interval->m !== 0)
        $res .= _t('common.time.months',  ['count' => $interval->m]) . ' ';

    if ($interval->d !== 0 && $interval->y === 0)
        $res .= _t('common.time.days',    ['count' => $interval->d]) . ' ';

    if ($interval->h !== 0 && $interval->y === 0 && $interval->m === 0)
        $res .= _t('common.time.hours',   ['count' => $interval->h]) . ' ';

    if ($interval->i !== 0 && $interval->m === 0 && ($interval->d === 0 || $interval->h === 0))
        $res .= _t('common.time.minutes', ['count' => $interval->i]) . ' ';

    if ($interval->s !== 0 && $interval->d === 0 && ($interval->h === 0 || $interval->i === 0))
        $res .= _t('common.time.seconds', ['count' => $interval->s]) . ' ';

    if (empty($res))
        $res = _t('common.time.moment_ago');

    return $res;
}

function showTimeDiff(DateTimeInterface|string $time, DateTimeZone $timezone = null): void
{
    echo getTimeDiff($time, $timezone);
}
