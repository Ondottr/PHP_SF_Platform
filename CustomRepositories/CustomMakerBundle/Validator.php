<?php declare( strict_types=1 );

/*
 * Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace Symfony\Bundle\MakerBundle;

use InvalidArgumentException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Doctrine\Common\Persistence\ManagerRegistry as LegacyManagerRegistry;
use function in_array;
use const FILTER_VALIDATE_INT;
use const FILTER_VALIDATE_EMAIL;
use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

final class Validator
{
    public static function validateLength( $length )
    {
        if ( !$length )
            return $length;


        $result = filter_var( $length, FILTER_VALIDATE_INT, [
            'options' => [ 'min_range' => 1 ],
        ] );

        if ( $result === false )
            throw new RuntimeCommandException( sprintf( 'Invalid length "%s".', $length ) );


        return $result;
    }

    public static function validatePrecision( $precision )
    {
        if ( !$precision )
            return $precision;


        $result = filter_var( $precision, FILTER_VALIDATE_INT, [
            'options' => [ 'min_range' => 1, 'max_range' => 65 ],
        ] );

        if ( $result === false )
            throw new RuntimeCommandException( sprintf( 'Invalid precision "%s".', $precision ) );


        return $result;
    }

    public static function validateScale( $scale )
    {
        if ( !$scale )
            return $scale;


        $result = filter_var( $scale, FILTER_VALIDATE_INT, [
            'options' => [ 'min_range' => 0, 'max_range' => 30 ],
        ] );

        if ( $result === false )
            throw new RuntimeCommandException( sprintf( 'Invalid scale "%s".', $scale ) );


        return $result;
    }

    public static function validateBoolean( $value )
    {
        if ( $value === 'yes' )
            return true;


        if ( $value === 'no' )
            return false;


        if ( ( $valueAsBool = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ) === null )
            throw new RuntimeCommandException( sprintf( 'Invalid bool value "%s".', $value ) );


        return $valueAsBool;
    }

    public static function validateDoctrineFieldName( string $name, $registry ): string
    {
        if ( !$registry instanceof ManagerRegistry && !$registry instanceof LegacyManagerRegistry ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Argument 2 to %s::validateDoctrineFieldName must be an instance of %s, %s passed.',
                    __CLASS__,
                    ManagerRegistry::class,
                    get_debug_type( $registry )
                )
            );
        }

        // check reserved words
        if ( $registry->getConnection()->getDatabasePlatform()->getReservedKeywordsList()->isKeyword( $name ) )
            throw new InvalidArgumentException( sprintf( 'Name "%s" is a reserved word.', $name ) );


        self::validatePropertyName( $name );

        return $name;
    }

    public static function validatePropertyName( string $name )
    {
        // check for valid PHP variable name
        if ( !Str::isValidPhpVariableName( $name ) )
            throw new InvalidArgumentException( sprintf( '"%s" is not a valid PHP property name.', $name ) );


        return $name;
    }

    public static function validateEmailAddress( ?string $email ): string
    {
        if ( !filter_var( $email, FILTER_VALIDATE_EMAIL ) )
            throw new RuntimeCommandException( sprintf( '"%s" is not a valid email address.', $email ) );


        return $email;
    }

    public static function existsOrNull( string $className = null, array $entities = [] ): ?string
    {
        if ( $className !== null ) {
            self::validateClassName( $className );

            if ( str_starts_with( $className, '\\' ) )
                self::classExists( $className );

            else
                self::entityExists( $className, $entities );

        }

        return $className;
    }

    public static function validateClassName( string $className, string $errorMessage = '' ): string
    {
        // remove potential opening slash so we don't match on it
        $pieces         = explode( '\\', ltrim( $className, '\\' ) );
        $shortClassName = Str::getShortClassName( $className );

        $reservedKeywords = [
            '__halt_compiler', 'abstract', 'and', 'array',
            'as', 'break', 'callable', 'case', 'catch', 'class',
            'clone', 'const', 'continue', 'declare', 'default', 'die', 'do',
            'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
            'endforeach', 'endif', 'endswitch', 'endwhile', 'eval',
            'exit', 'extends', 'final', 'finally', 'for', 'foreach', 'function',
            'global', 'goto', 'if', 'implements', 'include',
            'include_once', 'instanceof', 'insteadof', 'interface', 'isset',
            'list', 'namespace', 'new', 'or', 'print', 'private',
            'protected', 'public', 'require', 'require_once', 'return',
            'static', 'switch', 'throw', 'trait', 'try', 'unset',
            'use', 'var', 'while', 'xor', 'yield',
            'int', 'float', 'bool', 'string', 'true', 'false', 'null', 'void',
            'iterable', 'object', '__file__', '__line__', '__dir__', '__function__', '__class__',
            '__method__', '__namespace__', '__trait__', 'self', 'parent',
        ];

        foreach ( $pieces as $piece ) {
            if ( !mb_check_encoding( $piece, 'UTF-8' ) ) {
                $errorMessage = $errorMessage ?: sprintf( '"%s" is not a UTF-8-encoded string.', $piece );

                throw new RuntimeCommandException( $errorMessage );
            }

            if ( !preg_match( '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $piece ) ) {
                $errorMessage = $errorMessage
                    ?: sprintf(
                        '"%s" is not valid as a PHP class name (it must start with a letter or underscore, followed by any number of letters, numbers, or underscores)',
                        $className
                    );

                throw new RuntimeCommandException( $errorMessage );
            }

            if ( in_array( strtolower( $shortClassName ), $reservedKeywords, true ) )
                throw new RuntimeCommandException(
                    sprintf(
                        '"%s" is a reserved keyword and thus cannot be used as class name in PHP.',
                        $shortClassName
                    )
                );

        }

        // return original class name
        return $className;
    }

    public static function classExists( string $className, string $errorMessage = '' ): string
    {
        self::notBlank( $className );

        if ( !class_exists( $className ) ) {
            $errorMessage = $errorMessage
                ?: sprintf(
                    'Class "%s" doesn\'t exist; please enter an existing full class name.',
                    $className
                );

            throw new RuntimeCommandException( $errorMessage );
        }

        return $className;
    }

    public static function notBlank( string $value = null ): string
    {
        if ( $value === null || $value === '' )
            throw new RuntimeCommandException( 'This value cannot be blank.' );


        return $value;
    }

    public static function entityExists( string $className = null, array $entities = [] ): string
    {
        self::notBlank( $className );

        if ( empty( $entities ) )
            throw new RuntimeCommandException(
                'There are no registered entities; please create an entity before using this command.'
            );


        if ( str_starts_with( $className, '\\' ) )
            self::classExists(
                $className,
                sprintf(
                    'Entity "%s" doesn\'t exist; please enter an existing one or create a new one.',
                    $className
                )
            );


        if ( !in_array( $className, $entities ) )
            throw new RuntimeCommandException(
                sprintf( 'Entity "%s" doesn\'t exist; please enter an existing one or create a new one.', $className )
            );


        return $className;
    }

    public static function classDoesNotExist( $className ): string
    {
        self::notBlank( $className );

        if ( class_exists( $className ) )
            throw new RuntimeCommandException( sprintf( 'Class "%s" already exists.', $className ) );


        return $className;
    }

    public static function classIsUserInterface( $userClassName ): string
    {
        self::classExists( $userClassName );

        if ( !isset( class_implements( $userClassName )[ UserInterface::class ] ) )
            throw new RuntimeCommandException(
                sprintf( 'The class "%s" must implement "%s".', $userClassName, UserInterface::class )
            );


        return $userClassName;
    }
}
