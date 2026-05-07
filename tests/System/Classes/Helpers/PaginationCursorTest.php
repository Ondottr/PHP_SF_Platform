<?php declare( strict_types=1 );

namespace PHP_SF\Tests\System\Classes\Helpers;

use DateTime;
use InvalidArgumentException;
use PHP_SF\System\Classes\Helpers\PaginationCursor;
use PHPUnit\Framework\TestCase;

final class PaginationCursorTest extends TestCase
{

    private function makeEntity( mixed $fieldValue, int $id ): object
    {
        return new class( $fieldValue, $id ) {
            public function __construct(
                private readonly mixed $val,
                private readonly int   $id,
            ) {}
            public function getCreatedAt(): mixed { return $this->val; }
            public function getId(): int { return $this->id; }
        };
    }


    public function testAfterRoundtrip(): void
    {
        $entity = $this->makeEntity( 1700000000, 42 );
        $cursor = PaginationCursor::after( $entity, 'createdAt' );

        self::assertTrue( $cursor->isForward );
        self::assertSame( 1700000000, $cursor->field );
        self::assertSame( 42, $cursor->id );

        $decoded = PaginationCursor::fromString( $cursor->toString() );
        self::assertSame( $cursor->field, $decoded->field );
        self::assertSame( $cursor->id, $decoded->id );
        self::assertTrue( $decoded->isForward );
    }

    public function testBeforeRoundtrip(): void
    {
        $entity = $this->makeEntity( 1700000000, 7 );
        $cursor = PaginationCursor::before( $entity, 'createdAt' );

        self::assertFalse( $cursor->isForward );

        $decoded = PaginationCursor::fromString( $cursor->toString() );
        self::assertFalse( $decoded->isForward );
    }

    public function testDateTimeSerializedToTimestamp(): void
    {
        $ts     = 1700000000;
        $entity = $this->makeEntity( new DateTime( "@{$ts}" ), 1 );
        $cursor = PaginationCursor::after( $entity, 'createdAt' );

        self::assertIsInt( $cursor->field );
        self::assertSame( $ts, $cursor->field );
    }

    public function testToStringAndMagicStringCast(): void
    {
        $entity = $this->makeEntity( 99, 3 );
        $cursor = PaginationCursor::after( $entity, 'createdAt' );

        self::assertSame( $cursor->toString(), (string) $cursor );
        self::assertNotEmpty( $cursor->toString() );
    }

    public function testFromStringThrowsOnInvalidBase64(): void
    {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessageMatches( '/not valid base64/' );

        PaginationCursor::fromString( '!!not-base64!!' );
    }

    public function testFromStringThrowsOnMissingKeys(): void
    {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessageMatches( '/missing required keys/' );

        PaginationCursor::fromString( base64_encode( json_encode( [ 'only_id' => 1 ] ) ) );
    }

    public function testTryFromStringPassesNullThrough(): void
    {
        self::assertNull( PaginationCursor::tryFromString( null ) );
    }

    public function testTryFromStringThrowsOnInvalidNonNullString(): void
    {
        $this->expectException( InvalidArgumentException::class );

        PaginationCursor::tryFromString( 'garbage' );
    }

    public function testTryFromStringReturnsValidCursor(): void
    {
        $entity = $this->makeEntity( 5, 10 );
        $raw    = PaginationCursor::after( $entity, 'createdAt' )->toString();

        $cursor = PaginationCursor::tryFromString( $raw );

        self::assertInstanceOf( PaginationCursor::class, $cursor );
        self::assertSame( 10, $cursor->id );
    }

    public function testMissingGetterThrows(): void
    {
        $entity = new class {
            public function getId(): int { return 1; }
            // no getCreatedAt()
        };

        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessageMatches( '/getter getCreatedAt/' );

        PaginationCursor::after( $entity, 'createdAt' );
    }

}
