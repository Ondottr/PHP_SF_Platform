<?php

declare(strict_types=1);

/**
 * Minimal declarations of the jetbrains/phpstorm-attributes classes.
 *
 * The framework annotates code with these IDE attributes but does not depend on
 * the package at runtime, and the consuming template does not install it either.
 * Declaring them lets PHPStan resolve the attribute usages during analysis.
 */

namespace JetBrains\PhpStorm;

use Attribute;

if (class_exists(ArrayShape::class, false)) {
    return;
}

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class ArrayShape
{
    public function __construct(array $shape = [])
    {
    }
}

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
class ExpectedValues
{
    /**
     * Params are intentionally untyped to mirror the real package, which
     * accepts arrays, class-strings and flag expressions interchangeably.
     */
    public function __construct(mixed $values = [], mixed $valuesFromClass = [], mixed $flagsFromClass = [])
    {
    }
}

#[Attribute(Attribute::TARGET_ALL)]
class Deprecated
{
    public function __construct(string $reason = '', string $replacement = '')
    {
    }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Immutable
{
    public const CONSTRUCTOR_WRITE_SCOPE = 'constructor';

    public const PRIVATE_WRITE_SCOPE = 'private';

    public const PROTECTED_WRITE_SCOPE = 'protected';

    public function __construct(string $allowedWriteScope = self::CONSTRUCTOR_WRITE_SCOPE)
    {
    }
}

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class NoReturn
{
    public const ANY_ARGUMENT = -1;

    public function __construct(int ...$arguments)
    {
    }
}
