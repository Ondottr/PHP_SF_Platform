<?php echo "<?php declare(strict_types=1);\n"; ?>

namespace <?= $namespace; ?>;

use Doctrine\ORM\Mapping as ORM;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use <?= $repositoryNamespace; ?>\<?= $class_name; ?>Repository;

#[ORM\Entity(repositoryClass: <?= $class_name; ?>Repository::class)]
#[ORM\Table(name: '<?= $table_name; ?>', schema: '<?= $schema; ?>')]
class <?= $class_name; ?> extends AbstractEntity
{
}
