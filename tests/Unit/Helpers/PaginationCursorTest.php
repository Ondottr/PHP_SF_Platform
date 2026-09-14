<?php declare(strict_types=1);

namespace PHP_SF\Tests\Unit\Helpers;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PHP_SF\System\Classes\Helpers\PaginationCursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that {@see PaginationCursor} preserves the runtime type of the id
 * field — both auto-increment integers (default AbstractEntity PK) and string
 * UUIDs (custom PK entities). Regression coverage for the v4.2.0 widening of
 * `PaginationCursor::$id` and removal of the `(int) $data['id']` cast in
 * `fromString()`.
 */
#[CoversClass(PaginationCursor::class)]
final class PaginationCursorTest extends TestCase
{
    public function testFieldIdHoldsIntAsProvided(): void
    {
        $cursor = PaginationCursor::after($this->makeEntityWithId(42), 'createdAt');
        $this->assertSame(42, $cursor->id);
    }

    public function testFieldIdHoldsStringUuidAsProvided(): void
    {
        $uuid = '0190e7a5-7c3a-7c2a-9d3e-1f1a2b3c4d5e';

        $cursor = PaginationCursor::after($this->makeEntityWithId($uuid), 'createdAt');
        $this->assertSame($uuid, $cursor->id);
    }

    public function testStringIdRoundTripsThroughEncodedCursor(): void
    {
        $uuid = '0190e7a5-7c3a-7c2a-9d3e-1f1a2b3c4d5e';
        $original = PaginationCursor::after($this->makeEntityWithId($uuid), 'createdAt');

        $reconstructed = PaginationCursor::tryFromString((string) $original);

        $this->assertNotNull($reconstructed);
        $this->assertSame($uuid, $reconstructed->id);
    }

    public function testIntIdRoundTripsThroughEncodedCursor(): void
    {
        $original = PaginationCursor::after($this->makeEntityWithId(12345), 'createdAt');

        $reconstructed = PaginationCursor::tryFromString((string) $original);

        $this->assertNotNull($reconstructed);
        $this->assertSame(12345, $reconstructed->id);
    }

    public function testMalformedIdTypeRejectedOnParse(): void
    {
        $encoded = base64_encode(json_encode([
            'field' => 'foo',
            'id' => ['nested' => 'array'],
            'dir' => 'next',
        ], JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        PaginationCursor::tryFromString($encoded);
    }

    /**
     * Lightweight stand-in: only `getId()` and `getCreatedAt()` are needed for
     * `PaginationCursor::after/before` — the latter is the sort field in the
     * test fixtures.
     */
    private function makeEntityWithId(int|string $id): object
    {
        return new class($id) {
            public function __construct(private readonly int|string $id) {}

            public function getId(): int|string
            {
                return $this->id;
            }

            public function getCreatedAt(): DateTimeInterface
            {
                return new DateTimeImmutable('2026-01-01T00:00:00Z');
            }
        };
    }
}
