<?php declare( strict_types=1 );

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
    return ( is_string($time) ? new DateTime($time) : $time )->getTimestamp() - ( new DateTime )->getTimestamp();
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

    $d = is_string($time) ? new DateTime($time, $timezone) : $time;

    $interval = $d->diff(new DateTime('now', $timezone));

    if ($interval->y !== 0) {

        if ($interval->y <= 4)
            $res .= sprintf('%s %s ', $interval->y, _t('yr'));

        else
            $res .= sprintf('%s %s ', $interval->y, _t('yrs'));

    }

    if ($interval->m !== 0) {
        if ($interval->m === 1)
            $res .= sprintf('%s %s ', $interval->m, _t('mo'));

        else
            $res .= sprintf('%s %s ', $interval->m, _t('mos'));
    }

    if (( $interval->d !== 0 ) && $interval->y === 0) {
        if ($interval->d === 1)
            $res .= sprintf('%s %s ', $interval->d, _t('d'));

        else
            $res .= sprintf('%s %s ', $interval->d, _t('d (plural)'));
    }

    if ($interval->h !== 0 && $interval->y === 0 && $interval->m === 0) {
        if ($interval->h === 1)
            $res .= sprintf('%s %s ', $interval->h, _t('hr'));

        else
            $res .= sprintf('%s %s ', $interval->h, _t('hr (plural)'));
    }

    if ($interval->i !== 0 && $interval->m === 0 && ( $interval->d === 0 || $interval->h === 0 )) {
        if ($interval->i === 1)
            $res .= sprintf('%s %s ', $interval->i, _t('min'));

        else
            $res .= sprintf('%s %s ', $interval->i, _t('min (plural)'));
    }

    if ($interval->s !== 0 && $interval->d === 0 && ( $interval->h === 0 || $interval->i === 0 )) {
        if ($interval->s === 1)
            $res .= sprintf('%s %s ', $interval->s, _t('sec'));

        else
            $res .= sprintf('%s %s ', $interval->s, _t('sec (plural)'));
    }

    if (empty($res))
        $res = _t('moment_ago');

    return $res;
}

function showTimeDiff(DateTimeInterface|string $time, DateTimeZone $timezone = null): void
{
    echo getTimeDiff($time);
}
