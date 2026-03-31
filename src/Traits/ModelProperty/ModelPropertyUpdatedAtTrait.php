<?php declare( strict_types=1 );

namespace PHP_SF\System\Traits\ModelProperty;

use DateTimeInterface;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\ORM\Mapping as ORM;
use PHP_SF\System\Attributes\Validator\Constraints as Validate;
use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;
use PHP_SF\System\Core\DateTime;
use Symfony\Component\Serializer\Attribute\Groups;

use function is_string;

trait ModelPropertyUpdatedAtTrait
{

    #[Validate\DateTime]
    #[TranslatablePropertyName( 'Updated At' )]
    #[ORM\Column( name: 'updated_at', type: 'datetime', nullable: true, options: [ 'default' => CurrentTimestamp::class ] )]
    #[Groups( [ 'read', 'write' ] )]
    protected string|DateTimeInterface|null $updatedAt = null;


    final public function getUpdatedAt(): DateTimeInterface
    {
        if ( is_string( $this->updatedAt ) )
            return ( $this->updatedAt = new DateTime( $this->updatedAt ) );

        return $this->updatedAt;
    }

    final public function setUpdatedAt( DateTimeInterface $updatedAt ): void
    {
        $this->updatedAt = $updatedAt;
    }
}
