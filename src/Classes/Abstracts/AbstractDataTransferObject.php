<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use Serializable;

/**
 * Class AbstractDataTransferObject
 *
 * @package PHP_SF\System\Classes\Abstracts
 */
abstract readonly class AbstractDataTransferObject implements Serializable
{

    /**
     * Create an instance of DTO class from array
     *
     * @param array $array
     *`
     * @return static
     */
    public static function fromArray( array $array ): static
    {
        return new static( ...$array );
    }

    public static function fromJSON( string $json ): static
    {
        return static::fromArray( j_decode( $json, true ) );
    }

    /**
     * Convert the DTO to an associative array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars( $this );
    }

    /**
     * Convert the DTO to string.
     *
     * @return object
     */
    public function toString(): string
    {
        return json_encode( $this->toArray() );
    }

    public function serialize(): string
    {
        return serialize($this->toArray());
    }

    public function unserialize(string $data)
    {
        $this->fromArray(unserialize($data));
    }

    public function __serialize(): array
    {
        return $this->toArray();
    }

    public function __unserialize(array $data): void
    {
        $this->fromArray($data);
    }

}
