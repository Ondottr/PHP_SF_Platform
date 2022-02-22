<?php /** @noinspection ALL */
declare( strict_types=1 );

use ApiPlatform\Metadata\ApiResource;


?>
<?= "<?php declare(strict_types=1);\n" ?>

namespace <?= $namespace ?>;

<?php if ( $api_resource && class_exists( ApiResource::class ) ): ?>use ApiPlatform\Metadata\ApiResource;
<?php elseif ( $api_resource ): ?>use ApiPlatform\Core\Annotation\ApiResource;
<?php endif ?>
use <?= $repository_full_class_name ?>;
use Doctrine\ORM\Mapping as ORM;
use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;

<?php if ( !$use_attributes || !$doctrine_use_attributes ): ?>
    /**
    <?php if ( $api_resource && !$use_attributes ): ?> * @ApiResource()
    <?php endif ?>
    * @ORM\HasLifecycleCallbacks
    * @ORM\Entity(repositoryClass=<?= $repository_class_name ?>::class)
    <?php if ( $should_escape_table_name ): ?> * @ORM\Table(name="`<?= $table_name ?>`")
    <?php endif ?>
    */
<?php endif ?>
<?php if ( $doctrine_use_attributes ): ?>
    #[ORM\Entity(repositoryClass: <?= $repository_class_name ?>::class)]
    <?php if ( $should_escape_table_name ): ?>#[ORM\Table(name: '`<?= $table_name ?>`')]
    <?php endif ?>
<?php endif ?>
<?php if ( $api_resource && $use_attributes ): ?>
    #[ApiResource]
<?php endif ?>
class <?= $class_name ?> extends AbstractEntity
{


public function getLifecycleCallbacks(): array
{
// TODO: Implement getLifecycleCallbacks() method.
return [];
}

}
