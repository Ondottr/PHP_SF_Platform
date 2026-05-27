<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Helpers;

use InvalidArgumentException;

final class PaginationCursor
{
    private function __construct(
        public readonly mixed $field,
        public readonly int $id,
        public readonly bool $isForward,
        private readonly string $encoded,
    ) {
    }

    public function __toString(): string
    {
        return $this->encoded;
    }

    /**
     * Parse a cursor string received from a client request.
     * Throws InvalidArgumentException when the value is malformed.
     */
    public static function fromString(string $raw): self
    {
        $decoded = base64_decode($raw, strict: true);

        if (false === $decoded) {
            throw new \InvalidArgumentException('Invalid cursor: not valid base64.');
        }

        try {
            $data = json_decode($decoded, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Invalid cursor: malformed JSON payload.', previous: $e);
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid cursor: decoded payload must be a JSON object.');
        }

        if (!array_key_exists('field', $data) || !isset($data['id'])) {
            throw new \InvalidArgumentException('Invalid cursor: missing required keys.');
        }

        return new self(
            field: $data['field'],
            id: (int) $data['id'],
            isForward: ($data['dir'] ?? 'next') === 'next',
            encoded: $raw,
        );
    }

    /**
     * Null-safe wrapper — passes null through, throws on an invalid non-null string.
     */
    public static function tryFromString(?string $raw): ?self
    {
        if (null === $raw) {
            return null;
        }

        return self::fromString($raw);
    }

    /**
     * Create a forward cursor pointing after the given entity.
     */
    public static function after(object $entity, string $sortField): self
    {
        return self::encode($entity, $sortField, isForward: true);
    }

    /**
     * Create a backward cursor pointing before the given entity.
     */
    public static function before(object $entity, string $sortField): self
    {
        return self::encode($entity, $sortField, isForward: false);
    }

    public function toString(): string
    {
        return $this->encoded;
    }

    private static function encode(object $entity, string $sortField, bool $isForward): self
    {
        $getter = 'get' . ucfirst($sortField);

        if (!method_exists($entity, $getter)) {
            throw new \InvalidArgumentException(
                sprintf('Entity %s has no getter %s() for sort field "%s".', $entity::class, $getter, $sortField),
            );
        }

        $fieldValue = $entity->$getter();

        if ($fieldValue instanceof \DateTimeInterface) {
            $fieldValue = $fieldValue->getTimestamp();
        }

        $encoded = base64_encode(json_encode([
            'field' => $fieldValue,
            'id' => $entity->getId(),
            'dir' => $isForward ? 'next' : 'prev',
        ], JSON_THROW_ON_ERROR));

        return new self(
            field: $fieldValue,
            id: $entity->getId(),
            isForward: $isForward,
            encoded: $encoded,
        );
    }
}
