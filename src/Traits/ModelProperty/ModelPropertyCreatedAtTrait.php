<?php declare(strict_types=1);

namespace PHP_SF\System\Traits\ModelProperty;

use DateTimeInterface;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\ORM\Mapping as ORM;
use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;
use PHP_SF\System\Core\DateTime;
use Symfony\Component\Serializer\Attribute\Groups;

trait ModelPropertyCreatedAtTrait
{

    #[TranslatablePropertyName('common.fields.created_at')]
    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false, options: ['default' => new CurrentTimestamp()])]
    #[Groups(['read'])]
    protected string|DateTimeInterface|null $createdAt;


    final public function getCreatedAt(): DateTimeInterface
    {
        if (is_string($this->createdAt)) {
            return ($this->createdAt = new DateTime($this->createdAt));
        }

        return $this->createdAt;
    }

    final public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

}
