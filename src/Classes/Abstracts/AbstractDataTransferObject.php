<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Abstracts;

/**
 * @phpstan-consistent-constructor
 */
abstract readonly class AbstractDataTransferObject
{
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }


    /**
     * Convert the DTO to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Convert the DTO to string.
     */
    public function toString(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Create an instance of DTO class from array.
     *
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): static
    {
        return new static(...$array);
    }

    public static function fromJSON(string $json): static
    {
        return static::fromArray(j_decode($json, true));
    }
}
