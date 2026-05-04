<?php declare( strict_types=1 );

namespace PHP_SF\Tests\System\Classes\Helpers;

use DateTime;
use InvalidArgumentException;
use PHP_SF\System\Classes\Helpers\CursorPaginationHelper;
use PHPUnit\Framework\TestCase;

final class CursorPaginationHelperTest extends TestCase
{

    public function testEncodeThenDecodeRoundtrip(): void
    {
        $entity = new class {
            public function getCreatedAt(): DateTime { return new DateTime( '@1700000000' ); }
            public function getId(): int { return 42; }
        };

        $cursor  = self::callEncodeCursor( $entity, 'createdAt', isPrev: false );
        $decoded = CursorPaginationHelper::decodeCursor( $cursor );

        self::assertSame( 1700000000, $decoded['field'] );
        self::assertSame( 42, $decoded['id'] );
        self::assertSame( 'next', $decoded['dir'] );
    }

    public function testPrevCursorHasPrevDir(): void
    {
        $entity = new class {
            public function getTitle(): string { return 'hello'; }
            public function getId(): int { return 7; }
        };

        $cursor  = self::callEncodeCursor( $entity, 'title', isPrev: true );
        $decoded = CursorPaginationHelper::decodeCursor( $cursor );

        self::assertSame( 'prev', $decoded['dir'] );
    }

    public function testInvalidBase64Throws(): void
    {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessageMatches( '/not valid base64/' );

        CursorPaginationHelper::decodeCursor( '!!not-base64!!' );
    }

    public function testMissingCursorKeysThrows(): void
    {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessageMatches( '/missing required keys/' );

        CursorPaginationHelper::decodeCursor( base64_encode( json_encode( [ 'only_id' => 1 ] ) ) );
    }

    public function testPerPageCappedAtMax(): void
    {
        // We verify the cap indirectly via a real QB in functional tests.
        // Here we just verify the constant values are sane.
        self::assertSame( 20, CursorPaginationHelper::DEFAULT_PER_PAGE );
        self::assertSame( 100, CursorPaginationHelper::MAX_PER_PAGE );
        self::assertGreaterThan( CursorPaginationHelper::DEFAULT_PER_PAGE, CursorPaginationHelper::MAX_PER_PAGE );
    }

    public function testDateTimeFieldSerializedToTimestamp(): void
    {
        $ts     = 1700000000;
        $entity = new class( $ts ) {
            public function __construct( private readonly int $ts ) {}
            public function getCreatedAt(): DateTime { return new DateTime( "@{$this->ts}" ); }
            public function getId(): int { return 1; }
        };

        $cursor  = self::callEncodeCursor( $entity, 'createdAt', isPrev: false );
        $decoded = CursorPaginationHelper::decodeCursor( $cursor );

        self::assertIsInt( $decoded['field'] );
        self::assertSame( $ts, $decoded['field'] );
    }


    private static function callEncodeCursor( object $entity, string $sortField, bool $isPrev ): string
    {
        $ref    = new \ReflectionClass( CursorPaginationHelper::class );
        $method = $ref->getMethod( 'encodeCursor' );
        $method->setAccessible( true );

        return $method->invoke( null, $entity, $sortField, $isPrev );
    }

}
