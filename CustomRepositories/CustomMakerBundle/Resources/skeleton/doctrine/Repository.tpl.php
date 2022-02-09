<?= "<?php declare(strict_types=1);\n" ?>

namespace <?= $namespace ?>;

use <?= $entity_full_class_name ?>;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHP_SF\System\Classes\Abstracts\AbstractEntityRepository;

/**
* @method <?= $entity_class_name ?>|null find($id, $lockMode = null, $lockVersion = null)
* @method <?= $entity_class_name ?>|null findOneBy(array $criteria, array $orderBy = null)
* @method array|<?= $entity_class_name ?>[] findAll()
* @method array|<?= $entity_class_name ?>[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
*/
class <?= $class_name ?> extends AbstractEntityRepository
{
public function __construct()
{
parent::__construct(em(), new ClassMetadata(<?= $entity_class_name ?>::class));
}

/**
* @deprecated
*/
protected static function getEntityClass(): string
{
return <?= $entity_class_name ?>::class;
}
}
