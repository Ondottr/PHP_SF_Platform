<?php declare( strict_types=1 );

namespace PHP_SF\System\Doctrine;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass that forbids injection of the default Doctrine entity manager
 * or default DBAL connection into any service.
 *
 * Fails fast at container build time (cache:warmup) rather than at runtime,
 * preventing accidental cross-database data access in a multi-database setup.
 */
final class ForbidDefaultDoctrinePass implements CompilerPassInterface
{

    private const FORBIDDEN_SERVICES = [
        'doctrine.orm.entity_manager' => 'Use a named EM: @doctrine.orm.invoices_uk_entity_manager, etc.',
        'doctrine.dbal.connection'    => 'Use a named connection: @doctrine.dbal.invoices_uk_connection, etc.',
    ];


    public function process( ContainerBuilder $container ): void
    {
        foreach ( $container->getDefinitions() as $serviceId => $definition ) {
            foreach ( $definition->getArguments() as $argument ) {
                $this->assertNotForbidden( $argument, $serviceId );
            }

            foreach ( $definition->getMethodCalls() as [, $methodArguments] ) {
                foreach ( $methodArguments as $argument ) {
                    $this->assertNotForbidden( $argument, $serviceId );
                }
            }
        }
    }


    private function assertNotForbidden( mixed $argument, string $serviceId ): void
    {
        if ( !$argument instanceof Reference ) {
            return;
        }

        $referencedId = (string)$argument;

        if ( isset( self::FORBIDDEN_SERVICES[ $referencedId ] ) ) {
            throw new LogicException( sprintf(
                'Service "%s" injects the forbidden default Doctrine service "%s". %s',
                $serviceId,
                $referencedId,
                self::FORBIDDEN_SERVICES[ $referencedId ],
            ) );
        }
    }

}
