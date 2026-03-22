<?php echo "<?php declare(strict_types=1);\n"; ?>

namespace <?= $namespace; ?>;

use Doctrine\Persistence\ManagerRegistry;
use PHP_SF\System\Classes\Abstracts\AbstractEntityRepository;
use <?= $entityFqcn; ?>;

/**
* @method <?= $entity_class; ?>|null find($id, $lockMode = null, $lockVersion = null)
* @method <?= $entity_class; ?>|null findOneBy(array $criteria, array $orderBy = null)
* @method <?= $entity_class; ?>[]    findAll()
* @method <?= $entity_class; ?>[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
*/
class <?= $class_name; ?>Repository extends AbstractEntityRepository
{
public function __construct(ManagerRegistry $registry)
{
parent::__construct($registry, <?= $entity_class; ?>::class);
}
}
