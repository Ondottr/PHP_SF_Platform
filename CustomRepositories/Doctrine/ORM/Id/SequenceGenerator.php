<?php /** @noinspection MethodShouldBeFinalInspection */
declare( strict_types=1 );

namespace Doctrine\ORM\Id;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Exception\CannotGenerateIds;
use Serializable;
use function serialize;
use function unserialize;

/**
 * Represents an ID generator that uses a database sequence.
 */
class SequenceGenerator extends AbstractIdGenerator implements Serializable
{

    /**
     * The allocation size of the sequence.
     */
    private int $_allocationSize;

    /**
     * The name of the sequence.
     */
    private string $_sequenceName;

    private int $_nextValue = 0;

    private int|null $_maxValue = null;


    /**
     * Initializes a new sequence generator.
     */
    public function __construct( string $sequenceName, int $allocationSize )
    {
        $this->_sequenceName = $sequenceName;
        $this->_allocationSize = $allocationSize;
    }


    /**
     * {@inheritDoc}
     */
    public function generateId( EntityManagerInterface $em, $entity ): int
    {
        if ( $this->_maxValue === null || $this->_nextValue === $this->_maxValue ) {
            // Allocate new values
            $connection = $em->getConnection();
            $sql = $connection->getDatabasePlatform()->getSequenceNextValSQL( $this->_sequenceName );

            if ( $connection instanceof PrimaryReadReplicaConnection )
                $connection->ensureConnectedToPrimary();

            $this->_nextValue = (int)$connection->fetchOne( $sql );

            $res = ra()->deleteByKeyPattern( sprintf( "*%s*", sprintf( "nextval:%s:", $this->_sequenceName ) ) );
            if ( $res === false )
                throw new CannotGenerateIds( sprintf( 'Failed to delete cache key for "%s" sequence', $this->_sequenceName ), 500 );

            $this->_maxValue = $this->_nextValue + $this->_allocationSize;
        }

        return $this->_nextValue++;
    }

    /**
     * Gets the maximum value of the currently allocated bag of values.
     */
    public function getCurrentMaxValue(): int|null
    {
        return $this->_maxValue;
    }

    /**
     * Gets the next value that will be returned by generate().
     */
    public function getNextValue(): int
    {
        return $this->_nextValue;
    }


    final public function serialize(): string
    {
        return serialize( $this->__serialize() );
    }

    final public function unserialize( string $data ): void
    {
        /** @noinspection UnserializeExploitsInspection */
        $this->__unserialize( unserialize( $data ) );
    }


    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'allocationSize' => $this->_allocationSize,
            'sequenceName' => $this->_sequenceName,
        ];
    }

    /** @param array<string, mixed> $data */
    public function __unserialize( array $data ): void
    {
        $this->_sequenceName = $data['sequenceName'];
        $this->_allocationSize = $data['allocationSize'];
    }

}
