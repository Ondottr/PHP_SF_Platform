<?php declare( strict_types=1 );

namespace PHP_SF\System\Traits\ModelProperty;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

trait ModelPropertyIdTrait
{

    #[ORM\Id]
    #[ORM\Cache]
    #[ORM\Column( type: 'integer' )]
    #[ORM\GeneratedValue( 'AUTO' )]
    #[Groups( [ 'read' ] )]
    protected int $id;


    final public function getId(): int
    {
        return $this->id;
    }
}
