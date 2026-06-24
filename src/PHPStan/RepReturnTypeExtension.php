<?php declare(strict_types=1);

namespace PHP_SF\System\PHPStan;

use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Classes\Abstracts\AbstractEntityRepository;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Narrows the return type of Entity::rep() from AbstractEntityRepository<T>
 * to the concrete XxxRepository class, using the App\Entity\X\Foo →
 * App\Repository\X\FooRepository naming convention.
 */
final class RepReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{

    public function getClass(): string
    {
        return AbstractEntity::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'rep';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): Type {
        $entityClass = $this->resolveCalledClass($methodCall, $scope);

        if (null !== $entityClass) {
            $repoClass = str_replace('\\Entity\\', '\\Repository\\', $entityClass) . 'Repository';
            if (class_exists($repoClass)) {
                return new ObjectType($repoClass);
            }
        }

        return new ObjectType(AbstractEntityRepository::class);
    }


    private function resolveCalledClass(StaticCall $methodCall, Scope $scope): ?string
    {
        $class = $methodCall->class;

        if ($class instanceof Name) {
            $name = (string) $class;

            if ($name === 'self' || $name === 'static') {
                return $scope->isInClass() ? $scope->getClassReflection()->getName() : null;
            }

            if ($name === 'parent') {
                return $scope->isInClass()
                    ? ($scope->getClassReflection()->getParentClass()?->getName())
                    : null;
            }

            return ltrim($scope->resolveName($class), '\\');
        }

        return null;
    }

}
