<?php declare( strict_types=1 );

namespace PHP_SF\System\Core;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\Helper;

/**
 * Doctrine CLI Connection Helper.
 *
 * @link    www.doctrine-project.org
 */
class EntityManagerHelper extends Helper
{

    /**
     * Doctrine ORM EntityManagerInterface.
     *
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $_em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->_em = $em;
    }

    /**
     * Retrieves Doctrine ORM EntityManager.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->_em;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getName(): string
    {
        return 'entityManager';
    }
}
