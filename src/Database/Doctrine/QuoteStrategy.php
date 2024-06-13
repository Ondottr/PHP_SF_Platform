<?php /** @noinspection MissingReturnTypeInspection */
declare(strict_types=1);

namespace PHP_SF\System\Database\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use Doctrine\ORM\Mapping\QuoteStrategy as QuoteStrategyInterface;
use Doctrine\ORM\Mapping\Table;
use Override;

final class QuoteStrategy extends DefaultQuoteStrategy implements QuoteStrategyInterface
{

    #[Override]
    public function getColumnName($fieldName, ClassMetadata $class, AbstractPlatform $platform)
    {
        echo '==============================================================1';
        dump($fieldName, $class, $platform);
        echo '==============================================================1';
        exit(die);

        return parent::getColumnName($fieldName, $class, $platform);
    }

    #[Override]
    public function getTableName(ClassMetadata $class, AbstractPlatform $platform)
    {
        foreach ($class->reflClass->getAttributes() as $attr) {
            if ($attr->getName() === Table::class) {
                $args = $attr->getArguments();

                if (array_key_exists('name', $args)) {
                    return $args['name'];
                }
            }
        }

        return parent::getTableName($class, $platform);
    }

    #[Override]
    public function getSequenceName(array $definition, ClassMetadata $class, AbstractPlatform $platform)
    {
        echo '==============================================================3';
        dump($definition, $class, $platform);
        echo '==============================================================3';
        exit(die);

        return parent::getSequenceName($definition, $class, $platform);
    }

    #[Override]
    public function getJoinTableName(array $association, ClassMetadata $class, AbstractPlatform $platform)
    {
        echo '==============================================================4';
        dump($association, $class, $platform);
        echo '==============================================================4';
        exit(die);

        return parent::getJoinTableName($association, $class, $platform);
    }

    #[Override]
    public function getJoinColumnName(array $joinColumn, ClassMetadata $class, AbstractPlatform $platform)
    {
        echo '==============================================================5';
        dump($joinColumn, $class, $platform);
        echo '==============================================================5';
        exit(die);

        return parent::getJoinColumnName($joinColumn, $class, $platform);
    }

    #[Override]
    public function getReferencedJoinColumnName(array $joinColumn, ClassMetadata $class, AbstractPlatform $platform)
    {
        echo '==============================================================6';
        dump($joinColumn, $class, $platform);
        echo '==============================================================6';
        exit(die);

        return parent::getReferencedJoinColumnName($joinColumn, $class, $platform);
    }

    #[Override]
    public function getIdentifierColumnNames(ClassMetadata $class, AbstractPlatform $platform)
    {
        echo '==============================================================7';
        dump($class, $platform);
        echo '==============================================================7';
        exit(die);

        return parent::getIdentifierColumnNames($class, $platform);
    }

    #[Override]
    public function getColumnAlias($columnName, $counter, AbstractPlatform $platform, ?ClassMetadata $class = null)
    {
        echo '==============================================================8';
        dump($columnName, $counter, $platform, $class);
        echo '==============================================================8';
        exit(die);

        return parent::getColumnAlias($columnName, $counter, $platform, $class);
    }

}