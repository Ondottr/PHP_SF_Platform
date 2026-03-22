<?php echo "<?php declare(strict_types=1);\n"; ?>

namespace <?= $namespace; ?>;

use Doctrine\ORM\Mapping as ORM;
use <?= $repositoryNamespace; ?>\<?= $class_name; ?>Repository;

#[ORM\Entity(repositoryClass: <?= $class_name; ?>Repository::class)]
#[ORM\Table(name: '<?= $table_name; ?>', schema: '<?= $schema; ?>')]
class <?= $class_name; ?>
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column(type: 'integer')]
private int $id;

public function getId(): int
{
return $this->id;
}
}
