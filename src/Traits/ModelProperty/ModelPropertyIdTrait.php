<?php

namespace PHP_SF\System\Traits\ModelProperty;

use Doctrine\ORM\Mapping as ORM;

trait ModelPropertyIdTrait
{

    /**
     * @ORM\Id
     * @ORM\Cache
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     */
    #[ORM\Id]
    #[ORM\Cache]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    protected int $id;


    final public function getId(): int
    {
        return $this->id;
    }
}
