<?php

namespace PHP_SF\System\Traits\ModelProperty;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PHP_SF\System\Attributes\Validator\Constraints as Validate;

trait ModelPropertyCreatedAtTrait
{

    /**
     * @ORM\Column(type="datetime", name="created_at", options={"default": "CURRENT_TIMESTAMP"}, nullable=false)
     */
    #[Validate\DateTime]
    protected DateTimeInterface $createdAt;


    /**
     * @return DateTimeInterface
     */
    final public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @param DateTimeInterface $createdAt
     */
    final public function setCreatedAt(DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
