<?php

namespace PHP_SF\System\Traits\ModelProperty;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PHP_SF\System\Attributes\Validator\Constraints as Validate;

trait ModelPropertyUpdatedAtTrait
{

    /**
     * @ORM\Column(type="datetime", name="updated_at", options={"default": "CURRENT_TIMESTAMP"}, nullable=true)
     */
    #[Validate\DateTime]
    protected ?DateTimeInterface $updatedAt = null;


    /**
     * @return DateTimeInterface
     */
    final public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @param DateTimeInterface $updatedAt
     */
    final public function setUpdatedAt(DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
