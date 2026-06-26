<?php declare(strict_types=1);

namespace PHP_SF\Tests\System\Classes\Helpers;

use InvalidArgumentException;
use PHP_SF\System\Classes\Helpers\CursorPaginationHelper;
use PHPUnit\Framework\TestCase;

final class CursorPaginationHelperTest extends TestCase
{
    public function testDefaultAndMaxPerPageConstants(): void
    {
        self::assertSame(20, CursorPaginationHelper::DEFAULT_PER_PAGE);
        self::assertSame(100, CursorPaginationHelper::MAX_PER_PAGE);
        self::assertGreaterThan(
            CursorPaginationHelper::DEFAULT_PER_PAGE,
            CursorPaginationHelper::MAX_PER_PAGE,
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidIdentifierProvider')]
    public function testInvalidSortFieldThrows(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sortField/');

        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        CursorPaginationHelper::paginate(qb: $qb, sortField: $value);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidIdentifierProvider')]
    public function testInvalidEntityAliasThrows(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/entityAlias/');

        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        CursorPaginationHelper::paginate(qb: $qb, sortField: 'createdAt', entityAlias: $value);
    }

    public static function invalidIdentifierProvider(): array
    {
        return [
            'space' => ['created At'],
            'dot' => ['e.field'],
            'semicolon' => ['field;DROP TABLE'],
            'leading digit' => ['1field'],
            'empty string' => [''],
        ];
    }
}
