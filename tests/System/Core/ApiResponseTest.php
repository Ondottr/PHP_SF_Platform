<?php declare( strict_types=1 );

namespace PHP_SF\Tests\System\Core;

use PHP_SF\System\Classes\Helpers\CursorPaginationResult;
use PHP_SF\System\Classes\Helpers\PaginationCursor;
use PHP_SF\System\Core\ApiResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponseTest extends TestCase
{

    private function decode( ApiResponse $response ): array
    {
        return json_decode( $response->getContent(), associative: true, flags: JSON_THROW_ON_ERROR );
    }


    public function testSuccessEnvelopeStructure(): void
    {
        $r    = ApiResponse::success( [ 'id' => 1 ] );
        $body = $this->decode( $r );

        self::assertTrue( $body['success'] );
        self::assertSame( [ 'id' => 1 ], $body['data'] );
        self::assertNull( $body['errors'] );
        self::assertArrayHasKey( 'timestamp', $body['meta'] );
        self::assertArrayHasKey( 'pagination', $body['meta'] );
        self::assertNull( $body['meta']['pagination'] );
        self::assertSame( JsonResponse::HTTP_OK, $r->getStatusCode() );
    }

    public function testErrorEnvelopeStructure(): void
    {
        $r    = ApiResponse::error( 'Something went wrong.' );
        $body = $this->decode( $r );

        self::assertFalse( $body['success'] );
        self::assertNull( $body['data'] );
        self::assertSame( [ 'Something went wrong.' ], $body['errors'] );
        self::assertSame( JsonResponse::HTTP_BAD_REQUEST, $r->getStatusCode() );
    }

    public function testStringErrorWrappedInArray(): void
    {
        $r    = ApiResponse::error( 'Boom' );
        $body = $this->decode( $r );

        self::assertIsArray( $body['errors'] );
        self::assertSame( [ 'Boom' ], $body['errors'] );
    }

    public function testValidationErrorsPassThrough(): void
    {
        $errors = [ 'email' => 'Invalid email.', 'name' => 'Too short.' ];
        $r      = ApiResponse::unprocessableEntity( $errors );
        $body   = $this->decode( $r );

        self::assertFalse( $body['success'] );
        self::assertSame( $errors, $body['errors'] );
        self::assertSame( JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $r->getStatusCode() );
    }

    public function testNullDataReturnsNull(): void
    {
        $body = $this->decode( ApiResponse::success() );

        self::assertNull( $body['data'] );
    }

    public function testScalarDataPassesThrough(): void
    {
        $body = $this->decode( ApiResponse::success( 42 ) );

        self::assertSame( 42, $body['data'] );
    }

    public function testArrayDataPassesThrough(): void
    {
        $body = $this->decode( ApiResponse::success( [ 'foo' => 'bar' ] ) );

        self::assertSame( [ 'foo' => 'bar' ], $body['data'] );
    }

    public function testPaginationAppearsInMeta(): void
    {
        $entity = new class {
            public function getCreatedAt(): int { return 1700000000; }
            public function getId(): int { return 21; }
        };

        $next   = PaginationCursor::after( $entity, 'createdAt' );
        $result = new CursorPaginationResult(
            items:      [ [ 'id' => 1 ] ],
            cursor:     null,
            nextCursor: $next,
            prevCursor: null,
            perPage:    20,
            hasMore:    true,
        );

        $r    = ApiResponse::success( data: [ [ 'id' => 1 ] ], pagination: $result );
        $body = $this->decode( $r );

        self::assertNotNull( $body['meta']['pagination'] );
        self::assertSame( $next->toString(), $body['meta']['pagination']['next_cursor'] );
        self::assertSame( 20, $body['meta']['pagination']['per_page'] );
        self::assertTrue( $body['meta']['pagination']['has_more'] );
    }

    public function testNoPaginationMetaIsNull(): void
    {
        $body = $this->decode( ApiResponse::success( [ 1, 2, 3 ] ) );

        self::assertNull( $body['meta']['pagination'] );
    }

    public function testCreatedReturns201(): void
    {
        $r = ApiResponse::created( [ 'id' => 5 ] );

        self::assertSame( JsonResponse::HTTP_CREATED, $r->getStatusCode() );
        self::assertTrue( $this->decode( $r )['success'] );
    }

    public function testNotFoundReturns404(): void
    {
        $r    = ApiResponse::notFound( 'Custom not found.' );
        $body = $this->decode( $r );

        self::assertSame( JsonResponse::HTTP_NOT_FOUND, $r->getStatusCode() );
        self::assertFalse( $body['success'] );
        self::assertSame( [ 'Custom not found.' ], $body['errors'] );
    }

    public function testForbiddenReturns403(): void
    {
        $r = ApiResponse::forbidden( 'Nope.' );

        self::assertSame( JsonResponse::HTTP_FORBIDDEN, $r->getStatusCode() );
    }

    public function testUnauthorizedReturns401(): void
    {
        $r = ApiResponse::unauthorized( 'Please log in.' );

        self::assertSame( JsonResponse::HTTP_UNAUTHORIZED, $r->getStatusCode() );
    }

    public function testExtendsJsonResponse(): void
    {
        self::assertInstanceOf( JsonResponse::class, ApiResponse::success() );
    }

    public function testMetaTimestampIsRecentInteger(): void
    {
        $before = time();
        $body   = $this->decode( ApiResponse::success() );
        $after  = time();

        self::assertGreaterThanOrEqual( $before, $body['meta']['timestamp'] );
        self::assertLessThanOrEqual( $after, $body['meta']['timestamp'] );
    }

}
