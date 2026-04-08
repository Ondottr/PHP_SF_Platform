<?php declare( strict_types=1 );

namespace PHP_SF\Tests\System\Functions;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeFunctionsTest extends TestCase
{

    // ── timeInc ──────────────────────────────────────────────────────────

    public function testTimeIncAddsSecondsToCurrentTime(): void
    {
        $before = time();
        $result = strtotime( time_inc( 3600 ) );
        $after  = time();

        self::assertGreaterThanOrEqual( $before + 3600, $result );
        self::assertLessThanOrEqual( $after + 3600, $result );
    }

    public function testTimeIncZeroReturnsCurrentTime(): void
    {
        $before = time();
        $result = strtotime( time_inc( 0 ) );
        $after  = time();

        self::assertGreaterThanOrEqual( $before, $result );
        self::assertLessThanOrEqual( $after, $result );
    }

    public function testTimeIncReturnsYmdHisFormat(): void
    {
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            time_inc( 60 )
        );
    }


    // ── timeDec ──────────────────────────────────────────────────────────

    public function testTimeDecSubtractsSecondsFromCurrentTime(): void
    {
        $before = time();
        $result = strtotime( time_dec( 3600 ) );
        $after  = time();

        self::assertGreaterThanOrEqual( $before - 3600, $result );
        self::assertLessThanOrEqual( $after - 3600, $result );
    }

    public function testTimeDecZeroReturnsCurrentTime(): void
    {
        $before = time();
        $result = strtotime( time_dec( 0 ) );
        $after  = time();

        self::assertGreaterThanOrEqual( $before, $result );
        self::assertLessThanOrEqual( $after, $result );
    }

    public function testTimeDecReturnsYmdHisFormat(): void
    {
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            time_dec( 60 )
        );
    }


    // ── getTimeDiff — unit selection and suppression rules ───────────────

    public function testGetTimeDiffNowReturnsMomentAgo(): void
    {
        self::assertSame( 'a moment ago', get_time_diff( new DateTime() ) );
    }

    public function testGetTimeDiffAcceptsDateString(): void
    {
        $time = ( new DateTime( '-5 days -2 hours' ) )->format( 'Y-m-d H:i:s' );

        self::assertSame( '5 d 2 hrs ago', get_time_diff( $time ) );
    }

    public function testGetTimeDiffAcceptsDateTimeImmutable(): void
    {
        self::assertSame( '5 d 2 hrs ago', get_time_diff( new DateTimeImmutable( '-5 days -2 hours' ) ) );
    }

    public function testGetTimeDiffShowsYearsAndMonths(): void
    {
        // d=1 suppresses seconds; days suppressed by y > 0 condition in match
        self::assertSame( '3 yrs 2 mos ago', get_time_diff( new DateTime( '-3 years -2 months -1 day' ) ) );
    }

    public function testGetTimeDiffSuppressesDaysWhenYearsPresent(): void
    {
        self::assertSame( '1 yr 3 mos ago', get_time_diff( new DateTime( '-1 year -3 months -1 day' ) ) );
    }

    public function testGetTimeDiffShowsDaysAndHours(): void
    {
        // d=5 suppresses seconds
        self::assertSame( '5 d 2 hrs ago', get_time_diff( new DateTime( '-5 days -2 hours' ) ) );
    }

    public function testGetTimeDiffShowsHoursAndMinutes(): void
    {
        // h > 0 && i > 0 suppresses seconds
        self::assertSame( '4 hrs 20 min ago', get_time_diff( new DateTime( '-4 hours -20 minutes' ) ) );
    }

    public function testGetTimeDiffSuppressesHoursWhenYearsOrMonthsPresent(): void
    {
        // d=1 suppresses seconds; hours suppressed by y > 0 in match
        self::assertSame( '2 yrs 1 mo ago', get_time_diff( new DateTime( '-2 years -1 month -1 day' ) ) );
    }

    public function testGetTimeDiffShowsOnlyYearsWhenMonthsAreZero(): void
    {
        // Exactly N years → only years in range (second part absent → no range key used)
        self::assertSame( '2 yrs ago', get_time_diff( new DateTime( '-2 years' ) ) );
    }

    public function testGetTimeDiffShowsSeconds(): void
    {
        // Exact count is non-deterministic; assert the unit and wrapper appear
        $result = get_time_diff( new DateTime( '-30 seconds' ) );

        self::assertStringContainsString( 'sec', $result );
        self::assertStringEndsWith( 'ago', $result );
    }


    // ── getTimeDiff — ICU plural forms ───────────────────────────────────

    #[DataProvider( 'pluralYearsProvider' )]
    public function testGetTimeDiffYearPlural( string $date, string $expected ): void
    {
        self::assertSame( $expected, get_time_diff( new DateTime( $date ) ) );
    }

    public static function pluralYearsProvider(): array
    {
        return [
            'singular' => [ '-1 year -1 month -1 day',  '1 yr 1 mo ago' ],
            'plural'   => [ '-5 years -3 months -1 day', '5 yrs 3 mos ago' ],
        ];
    }

    #[DataProvider( 'pluralMonthsProvider' )]
    public function testGetTimeDiffMonthPlural( string $date, string $expected ): void
    {
        self::assertSame( $expected, get_time_diff( new DateTime( $date ) ) );
    }

    public static function pluralMonthsProvider(): array
    {
        return [
            'singular' => [ '-1 month -5 days', '1 mo 5 d ago' ],
            'plural'   => [ '-4 months -5 days', '4 mos 5 d ago' ],
        ];
    }

    #[DataProvider( 'pluralHoursProvider' )]
    public function testGetTimeDiffHourPlural( string $date, string $expected ): void
    {
        // h > 0 && i > 0 suppresses seconds
        self::assertSame( $expected, get_time_diff( new DateTime( $date ) ) );
    }

    public static function pluralHoursProvider(): array
    {
        return [
            'singular' => [ '-1 hour -30 minutes', '1 hr 30 min ago' ],
            'plural'   => [ '-6 hours -30 minutes', '6 hrs 30 min ago' ],
        ];
    }

    public function testGetTimeDiffDoesNotReturnRawIcuPattern(): void
    {
        $result = get_time_diff( new DateTime( '-2 years' ) );

        self::assertStringNotContainsString( '{count, plural,', $result );
        self::assertStringNotContainsString( 'one   {#', $result );
    }


    // ── getTimeFromSeconds ───────────────────────────────────────────────

    public function testGetTimeFromSecondsZeroReturnsMomentAgo(): void
    {
        self::assertSame( 'a moment ago', get_time_diff_from_seconds( 0 ) );
    }

    public function testGetTimeFromSecondsOneHourOneMinute(): void
    {
        // h=1 i=1 → seconds suppressed (h > 0 && i > 0)
        self::assertSame( '1 hr 1 min ago', get_time_diff_from_seconds( 3660 ) );
    }

    public function testGetTimeFromSecondsTwoHoursOneMinute(): void
    {
        self::assertSame( '2 hrs 1 min ago', get_time_diff_from_seconds( 7260 ) );
    }

    public function testGetTimeFromSecondsOneDay(): void
    {
        // d=1 → seconds suppressed
        self::assertSame( '1 d ago', get_time_diff_from_seconds( 86400 ) );
    }

}
