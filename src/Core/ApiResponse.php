<?php declare(strict_types=1);

namespace PHP_SF\System\Core;

use PHP_SF\System\Classes\Abstracts\AbstractDataTransferObject;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Classes\Helpers\CursorPaginationResult;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponse extends JsonResponse
{
    private function __construct(
        bool $success,
        mixed $data,
        array|string|null $errors,
        ?CursorPaginationResult $pagination,
        int $status,
        array $headers = [],
    ) {
        parent::__construct(
            data: [
                'success' => $success,
                'data' => self::normalizeData($data),
                'errors' => self::normalizeErrors($errors),
                'meta' => [
                    'timestamp' => time(),
                    'pagination' => $pagination?->getPaginationMeta(),
                ],
            ],
            status: $status,
            headers: $headers,
        );
    }

    public static function success(
        mixed $data = null,
        ?CursorPaginationResult $pagination = null,
        int $status = self::HTTP_OK,
        array $headers = [],
    ): self {
        return new self(
            success: true,
            data: $data,
            errors: null,
            pagination: $pagination,
            status: $status,
            headers: $headers,
        );
    }

    public static function created(
        mixed $data = null,
        array $headers = [],
    ): self {
        return new self(
            success: true,
            data: $data,
            errors: null,
            pagination: null,
            status: self::HTTP_CREATED,
            headers: $headers,
        );
    }

    public static function error(
        string|array $errors,
        int $status = self::HTTP_BAD_REQUEST,
        array $headers = [],
    ): self {
        return new self(
            success: false,
            data: null,
            errors: $errors,
            pagination: null,
            status: $status,
            headers: $headers,
        );
    }

    public static function notFound(
        ?string $error = null,
        array $headers = [],
    ): self {
        return self::error(
            errors: $error ?? _t('common.errors.not_found'),
            status: self::HTTP_NOT_FOUND,
            headers: $headers,
        );
    }

    public static function forbidden(
        ?string $error = null,
        array $headers = [],
    ): self {
        return self::error(
            errors: $error ?? _t('common.errors.access_denied'),
            status: self::HTTP_FORBIDDEN,
            headers: $headers,
        );
    }

    public static function unauthorized(
        ?string $error = null,
        array $headers = [],
    ): self {
        return self::error(
            errors: $error ?? _t('common.errors.unauthorized'),
            status: self::HTTP_UNAUTHORIZED,
            headers: $headers,
        );
    }

    public static function unprocessableEntity(
        array $errors,
        array $headers = [],
    ): self {
        return new self(
            success: false,
            data: null,
            errors: $errors,
            pagination: null,
            status: self::HTTP_UNPROCESSABLE_ENTITY,
            headers: $headers,
        );
    }

    // 204 — no envelope, empty body (HTTP spec prohibits content on 204)
    public static function noContent(array $headers = []): JsonResponse
    {
        return new JsonResponse(status: self::HTTP_NO_CONTENT, headers: $headers);
    }

    private static function normalizeData(mixed $data): mixed
    {
        if (null === $data) {
            return null;
        }

        if ($data instanceof AbstractEntity) {
            return $data->jsonSerialize();
        }

        if ($data instanceof AbstractDataTransferObject) {
            return $data->toArray();
        }

        if ($data instanceof \JsonSerializable) {
            return $data->jsonSerialize();
        }

        if (is_array($data)) {
            return array_map(static fn (mixed $item) => self::normalizeData($item), $data);
        }

        return $data;
    }

    private static function normalizeErrors(array|string|null $errors): ?array
    {
        if (null === $errors) {
            return null;
        }

        if (is_string($errors)) {
            return [$errors];
        }

        return $errors;
    }
}
