<?php declare(strict_types=1);

namespace PHP_SF\System\PHPStan;

use PHP_SF\Cache\Abstracts\AbstractCacheAdapter;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Narrows the return type of ca() based on the adapter class-string argument.
 *
 * ca(RedisCacheAdapter::class)   → RedisCacheAdapter
 * ca(MemcachedCacheAdapter::class) → MemcachedCacheAdapter
 * ca() / ca(null)                → AbstractCacheAdapter
 */
final class CaReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{

    public function getFunctionName(): string
    {
        return 'ca';
    }

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'ca';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $funcCall,
        Scope $scope,
    ): Type {
        $args = $funcCall->getArgs();

        if (empty($args)) {
            return new ObjectType(AbstractCacheAdapter::class);
        }

        $argType = $scope->getType($args[0]->value);
        $strings = $argType->getConstantStrings();

        if (empty($strings)) {
            return new ObjectType(AbstractCacheAdapter::class);
        }

        $types = [];

        foreach ($strings as $str) {
            $value = $str->getValue();

            // The AbstractCacheAdapter::*_CACHE_ADAPTER constants hold the concrete class FQCNs,
            // so a literal string that is a subclass of AbstractCacheAdapter maps directly.
            if (class_exists($value) && is_a($value, AbstractCacheAdapter::class, true)) {
                $types[] = new ObjectType($value);
            } else {
                $types[] = new ObjectType(AbstractCacheAdapter::class);
            }
        }

        return TypeCombinator::union(...$types);
    }

}
