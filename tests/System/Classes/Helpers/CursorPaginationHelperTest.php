<?php declare( strict_types=1 );

namespace PHP_SF\Tests\System\Classes\Helpers;

use PHP_SF\System\Classes\Helpers\CursorPaginationHelper;
use PHPUnit\Framework\TestCase;

final class CursorPaginationHelperTest extends TestCase
{

    public function testDefaultAndMaxPerPageConstants(): void
    {
        self::assertSame( 20, CursorPaginationHelper::DEFAULT_PER_PAGE );
        self::assertSame( 100, CursorPaginationHelper::MAX_PER_PAGE );
        self::assertGreaterThan(
            CursorPaginationHelper::DEFAULT_PER_PAGE,
            CursorPaginationHelper::MAX_PER_PAGE,
        );
    }

}
