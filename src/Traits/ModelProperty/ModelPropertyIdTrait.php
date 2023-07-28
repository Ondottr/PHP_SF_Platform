<?php declare( strict_types=1 );

namespace PHP_SF\System\Traits\ModelProperty;

use Doctrine\ORM\Mapping as ORM;

trait ModelPropertyIdTrait
{

    #[ORM\Id]
    #[ORM\Cache]
    #[ORM\Column( type: 'integer' )]
    #[ORM\GeneratedValue( 'SEQUENCE' )]
    protected int $id;


    final public function getId(): int
    {
        return $this->id;
    }
}
