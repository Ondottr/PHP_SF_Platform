<?php
/**
 * @noinspection TypeUnsafeArraySearchInspection
 * @noinspection SpellCheckingInspection
 * @noinspection PhpPossiblePolymorphicInvocationInspection
 * @noinspection DuplicatedCode
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 */

namespace Symfony\Bundle\MakerBundle\Util;

use Exception;
use DateInterval;
use PhpParser\Node;
use LogicException;
use PhpParser\Lexer;
use ReflectionClass;
use PhpParser\Parser;
use RuntimeException;
use PhpParser\Builder;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionException;
use ReflectionParameter;
use PhpParser\Node\Stmt;
use PhpParser\Comment\Doc;
use PhpParser\NodeVisitor;
use PhpParser\NodeTraverser;
use PhpParser\BuilderHelpers;
use Symfony\Bundle\MakerBundle\Str;
use Doctrine\Common\Collections\Collection;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\MakerBundle\Doctrine\BaseRelation;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToMany;
use Symfony\Bundle\MakerBundle\Doctrine\BaseCollectionRelation;
use function count;
use function is_int;
use function is_bool;
use function gettype;
use function is_array;
use function array_key_exists;

final class ClassSourceManipulator
{
    private const CONTEXT_OUTSIDE_CLASS = 'outside_class';
    private const CONTEXT_CLASS         = 'class';
    private const CONTEXT_CLASS_METHOD  = 'class_method';

    private Parser\Php7     $parser;
    private Lexer\Emulative $lexer;
    private PrettyPrinter   $printer;
    /** @var ConsoleStyle|null */
    private ?ConsoleStyle $io;


    private $oldStmts;
    private $oldTokens;
    private $newStmts;

    private array $pendingComments = [];

    public function __construct(
        private string $sourceCode,
        private bool   $overwrite = false,
        private bool   $useAnnotations = true,
        private bool   $fluentMutators = true
    ) {
        $this->lexer   = new Lexer\Emulative(
            [
                'usedAttributes' => [
                    'comments',
                    'startLine', 'endLine',
                    'startTokenPos', 'endTokenPos',
                ],
            ] );
        $this->parser  = new Parser\Php7( $this->lexer );
        $this->printer = new PrettyPrinter();

        $this->setSourceCode( $sourceCode );
    }

    private function setSourceCode( string $sourceCode ): void
    {
        $this->sourceCode = $sourceCode;
        $this->oldStmts   = $this->parser->parse( $sourceCode );
        $this->oldTokens  = $this->lexer->getTokens();

        $traverser = new NodeTraverser();
        $traverser->addVisitor( new NodeVisitor\CloningVisitor() );
        $traverser->addVisitor(
            new NodeVisitor\NameResolver( null, [
                'replaceNodes' => false,
            ] )
        );
        $this->newStmts = $traverser->traverse( $this->oldStmts );
    }

    public function setIo( ConsoleStyle $io ): void
    {
        $this->io = $io;
    }

    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    public function addEntityField( string $propertyName, array $columnOptions, array $comments = [] ): void
    {
        $propertyName = snakeToCamel( $propertyName );
        $typeHint     = $this->getEntityTypeHint( $columnOptions['type'] );
        $nullable     = $columnOptions['nullable'] ?? false;
        $isId         = (bool)( $columnOptions['id'] ?? false );

        $attributes[] = $this->buildAttributeNode( [
                                                       'name' => camel_to_snake(
                                                           "{$columnOptions['entityName']}_{$propertyName}_property"
                                                       ),
                                                   ] );
        unset( $columnOptions['entityName'] );
        $columnOptions['name'] = camel_to_snake( $propertyName );

        $comments[] = $this->buildAnnotationLine( '@ORM\Column', $columnOptions );

        $defaultValue = null;
        if ( $typeHint === 'array' )
            $defaultValue = new Node\Expr\Array_( [], [ 'kind' => Node\Expr\Array_::KIND_SHORT ] );


        $this->addProperty( $propertyName, $comments, $defaultValue, $attributes, $typeHint );

        $this->addGetter(
            $propertyName,
            $typeHint, // getter methods always have nullable return values
                       // because even though these are required in the db, they may not be set yet
            true
        );

        // don't generate setters for id fields
        if ( !$isId )
            $this->addSetter( $propertyName, $typeHint, $nullable );

    }

    private function getEntityTypeHint( $doctrineType ): ?string
    {
        return match ( $doctrineType ) {
            'string', 'text', 'guid', 'bigint', 'decimal'                                    => 'string',
            'array', 'simple_array', 'json', 'json_array'                                    => 'array',
            'boolean'                                                                        => 'bool',
            'integer', 'smallint'                                                            => 'int',
            'float'                                                                          => 'float',
            'datetime', 'datetimetz', 'date', 'time'                                         => '\\' .
                                                                                                DateTimeInterface::class,
            'datetime_immutable', 'datetimetz_immutable', 'date_immutable', 'time_immutable' => '\\' .
                                                                                                DateTimeImmutable::class,
            'dateinterval'                                                                   => '\\' .
                                                                                                DateInterval::class,
            default                                                                          => null,
        };
    }

    /**
     * builds an PHPParser attribute node.
     *
     * @param array $options the named arguments for the attribute ($key = argument name, $value = argument value)
     *
     * @throws \Exception
     */
    private function buildAttributeNode( array $options ): Node\Attribute
    {
        $options = $this->sortOptionsByClassConstructorParameters(
            $options
        );

        $context       = $this;
        $nodeArguments = array_map( static function ( $option, $value ) use ( $context ) {
            return new Node\Arg(
                $context->buildNodeExprByValue( $value ),
                false,
                false,
                [],
                new Node\Identifier( $option )
            );
        }, array_keys( $options ), array_values( $options ) );

        return new Node\Attribute(
            new Node\Name( '\PHP_SF\System\Attributes\Validator\TranslatablePropertyName' ), $nodeArguments
        );
    }

    /**
     * sort the given options based on the constructor parameters for the given $classString
     * this prevents code inspections warnings for IDEs like intellij/phpstorm.
     *
     * option keys that are not found in the constructor will be added at the end of the sorted array
     */
    private function sortOptionsByClassConstructorParameters( array $options ): array
    {
        $classString = '\PHP_SF\System\Attributes\Validator\TranslatablePropertyName';
        if ( str_starts_with( '\PHP_SF\System\Attributes\Validator\TranslatablePropertyName', 'ORM\\' ) )
            $classString = sprintf(
                'Doctrine\\ORM\\Mapping\\%s',
                substr( '\PHP_SF\System\Attributes\Validator\TranslatablePropertyName', 4 )
            );


        $constructorParameterNames = array_map( static function ( ReflectionParameter $reflectionParameter ) {
            return $reflectionParameter->getName();
        }, ( new ReflectionClass( $classString ) )->getConstructor()?->getParameters() );

        $sorted = [];
        foreach ( $constructorParameterNames as $name ) {
            if ( array_key_exists( $name, $options ) ) {
                $sorted[ $name ] = $options[ $name ];
                unset( $options[ $name ] );
            }
        }

        return array_merge( $sorted, $options );
    }

    /**
     * builds a PHPParser Expr Node based on the value given in $value
     * throws an Exception when the given $value is not resolvable by this method.
     *
     * @throws \Exception
     */
    private function buildNodeExprByValue( mixed $value ): Node\Expr
    {
        switch ( gettype( $value ) ) {
            case 'string':
                $nodeValue = new Node\Scalar\String_( $value );
                break;
            case 'integer':
                $nodeValue = new Node\Scalar\LNumber( $value );
                break;
            case 'double':
                $nodeValue = new Node\Scalar\DNumber( $value );
                break;
            case 'boolean':
                $nodeValue = new Node\Expr\ConstFetch( new Node\Name( $value ? 'true' : 'false' ) );
                break;
            case 'array':
                $context    = $this;
                $arrayItems = array_map( static function ( $key, $value ) use ( $context ) {
                    return new Node\Expr\ArrayItem(
                        $context->buildNodeExprByValue( $value ),
                        !is_int( $key ) ? $context->buildNodeExprByValue( $key ) : null
                    );
                }, array_keys( $value ), array_values( $value ) );
                $nodeValue  = new Node\Expr\Array_( $arrayItems, [ 'kind' => Node\Expr\Array_::KIND_SHORT ] );
                break;
            default:
                $nodeValue = null;
        }

        if ( $nodeValue === null ) {
            if ( $value instanceof ClassNameValue ) {
                $nodeValue = new Node\Expr\ConstFetch(
                    new Node\Name(
                        sprintf( '%s::class', $value->isSelf() ? 'self' : $value->getShortName() )
                    )
                );
            }
            else {
                throw new RuntimeException(
                    sprintf( 'Cannot build a node expr for value of type "%s"', gettype( $value ) )
                );
            }
        }

        return $nodeValue;
    }

    /**
     * @param string $annotationClass The annotation: e.g. "@ORM\Column"
     * @param array  $options         Key-value pair of options for the annotation
     *
     * @throws \Exception
     */
    private function buildAnnotationLine( string $annotationClass, array $options = [] ): string
    {
        if ( empty( $options ) )
            return $annotationClass;

        $formattedOptions = array_map( function ( $option, $value ) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            if ( is_array( $value ) ) {
                /** @noinspection OffsetOperationsInspection */
                if ( !isset( $value[0] ) ) {
                    return sprintf(
                        '%s={%s}',
                        $option,
                        implode(
                            ', ',
                            array_map( function ( $val, $key ) {
                                return sprintf( '"%s" = %s', $key, $this->quoteAnnotationValue( $val ) );
                            }, $value, array_keys( $value ) )
                        )
                    );
                }

                return sprintf(
                    '%s={%s}',
                    $option,
                    implode(
                        ', ',
                        array_map( function ( $val ) {
                            return $this->quoteAnnotationValue( $val );
                        }, $value )
                    )
                );
            }

            return sprintf( '%s=%s', $option, $this->quoteAnnotationValue( $value ) );
        }, array_keys( $options ), array_values( $options ) );

        return sprintf( '%s(%s)', $annotationClass, implode( ', ', $formattedOptions ) );
    }

    private function quoteAnnotationValue( $value )
    {
        if ( is_bool( $value ) )
            return $value ? 'true' : 'false';


        if ( $value === null )
            return 'null';


        if ( is_int( $value ) || $value === '0' )
            return $value;


        if ( $value instanceof ClassNameValue )
            return sprintf( '%s::class', $value->getShortName() );


        if ( is_array( $value ) )
            throw new RuntimeException( 'Invalid value: loop before quoting.' );


        return sprintf( '"%s"', $value );
    }

    public function addProperty(
        string $name,
        array  $annotationLines = [],
               $defaultValue = null,
        array  $attributes = [],
               $type = null
    ): void {
        if ( $this->propertyExists( $name ) ) // we never overwrite properties
            return;


        $propertyBuilder = ( new Builder\Property( $name ) )->makeProtected();

        if ( $type )
            $propertyBuilder->setType( $type );

        $newPropertyBuilder = $propertyBuilder;

        if ( $annotationLines && $this->useAnnotations )
            $newPropertyBuilder->setDocComment( $this->createDocBlock( $annotationLines ) );


        foreach ( $attributes as $attribute )
            $newPropertyBuilder->addAttribute( $attribute );


        if ( $defaultValue !== null )
            $newPropertyBuilder->setDefault( $defaultValue );

        $newPropertyNode = $newPropertyBuilder->getNode();

        $this->addNodeAfterProperties( $newPropertyNode );
    }

    private function propertyExists( string $propertyName ): bool
    {
        foreach ( $this->getClassNode()->stmts as $node )
            if ( $node instanceof Node\Stmt\Property && $node->props[0]->name->toString() === $propertyName )
                return true;


        return false;
    }

    private function getClassNode(): Node
    {
        $node = $this->findFirstNode( function ( $node ) {
            return $node instanceof Node\Stmt\Class_;
        } );

        if ( !$node )
            throw new RuntimeException( 'Could not find class node' );


        return $node;
    }

    private function findFirstNode( callable $filterCallback ): ?Node
    {
        $traverser = new NodeTraverser();
        $visitor   = new NodeVisitor\FirstFindingVisitor( $filterCallback );
        $traverser->addVisitor( $visitor );
        $traverser->traverse( $this->newStmts );

        return $visitor->getFoundNode();
    }

    private function createDocBlock( array $commentLines ): string
    {
        $docBlock = "/**\n";
        foreach ( $commentLines as $commentLine ) {
            if ( $commentLine )
                $docBlock .= " * $commentLine\n";

            else // avoid the empty, extra space on blank lines
                $docBlock .= " *\n";

        }
        $docBlock .= "\n */";

        return $docBlock;
    }

    /**
     * Adds this new node where a new property should go.
     *
     * Useful for adding properties, or adding a constructor.
     */
    private function addNodeAfterProperties( Node $newNode ): void
    {
        $classNode = $this->getClassNode();

        // try to add after last property
        $targetNode = $this->findLastNode( function ( $node ) {
            return $node instanceof Node\Stmt\Property;
        }, [ $classNode ] );

        // otherwise, try to add after the last constant
        if ( !$targetNode ) {
            $targetNode = $this->findLastNode( function ( $node ) {
                return $node instanceof Node\Stmt\ClassConst;
            }, [ $classNode ] );
        }

        // otherwise, try to add after the last trait
        if ( !$targetNode ) {
            $targetNode = $this->findLastNode( function ( $node ) {
                return $node instanceof Node\Stmt\TraitUse;
            }, [ $classNode ] );
        }

        // add the new property after this node
        if ( $targetNode ) {
            $index = array_search( $targetNode, $classNode->stmts );

            array_splice(
                $classNode->stmts,
                $index + 1,
                0,
                [ $this->createBlankLineNode( self::CONTEXT_CLASS ), $newNode ]
            );

            $this->updateSourceCodeFromNewStmts();

            return;
        }

        // put right at the beginning of the class
        // add an empty line, unless the class is totally empty
        if ( !empty( $classNode->stmts ) )
            array_unshift( $classNode->stmts, $this->createBlankLineNode( self::CONTEXT_CLASS ) );

        array_unshift( $classNode->stmts, $newNode );
        $this->updateSourceCodeFromNewStmts();
    }

    private function findLastNode( callable $filterCallback, array $ast ): ?Node
    {
        $traverser = new NodeTraverser();
        $visitor   = new NodeVisitor\FindingVisitor( $filterCallback );
        $traverser->addVisitor( $visitor );
        $traverser->traverse( $ast );

        $nodes = $visitor->getFoundNodes();
        $node  = end( $nodes );

        return $node === false ? null : $node;
    }

    private function createBlankLineNode( string $context )
    {
        return match ( $context ) {
            self::CONTEXT_OUTSIDE_CLASS => ( new Builder\Use_( '__EXTRA__LINE', Node\Stmt\Use_::TYPE_NORMAL ) )
                ->getNode(),
            self::CONTEXT_CLASS         => ( new Builder\Property( '__EXTRA__LINE' ) )
                ->makePrivate()
                ->getNode(),
            self::CONTEXT_CLASS_METHOD  => new Node\Expr\Variable( '__EXTRA__LINE' ),
            default                     => throw new Exception( 'Unknown context: ' . $context ),
        };
    }

    /**
     * @noinspection RegExpRedundantEscape
     * @noinspection RegExpSingleCharAlternation
     */
    private function updateSourceCodeFromNewStmts(): void
    {
        $newCode = $this
            ->printer
            ->printFormatPreserving(
                $this->newStmts,
                $this->oldStmts,
                $this->oldTokens
            );

        // replace the 3 "fake" items that may be in the code (allowing for different indentation)
        $newCode = preg_replace( '/(\ |\t)*private\ \$__EXTRA__LINE;/', '', $newCode );
        $newCode = preg_replace( '/use __EXTRA__LINE;/', '', $newCode );
        $newCode = preg_replace( '/(\ |\t)*\$__EXTRA__LINE;/', '', $newCode );

        // process comment lines
        foreach ( $this->pendingComments as $i => $comment ) {
            // sanity check
            $placeholder = sprintf( '$__COMMENT__VAR_%d;', $i );
            if ( !str_contains( $newCode, $placeholder ) ) {
                // this can happen if a comment is createSingleLineCommentNode()
                // is called, but then that generated code is ultimately not added
                continue;
            }

            $newCode = str_replace( $placeholder, '// ' . $comment, $newCode );
        }
        $this->pendingComments = [];

        $this->setSourceCode( $newCode );
    }

    public function addGetter(
        string $propertyName,
               $returnType,
        bool   $isReturnTypeNullable,
        array  $commentLines = []
    ): void {
        $methodName = 'get' . Str::asCamelCase( $propertyName );

        $this->addCustomGetter( $propertyName, $methodName, $returnType, $isReturnTypeNullable, $commentLines );
    }

    /** @noinspection PhpTooManyParametersInspection */
    private function addCustomGetter(
        string $propertyName,
        string $methodName,
               $returnType,
        bool   $isReturnTypeNullable,
        array  $commentLines = [],
               $typeCast = null
    ): void {
        $propertyFetch = new Node\Expr\PropertyFetch( new Node\Expr\Variable( 'this' ), $propertyName );

        if ( $typeCast !== null )
            $propertyFetch = match ( $typeCast ) {
                'string' => new Node\Expr\Cast\String_( $propertyFetch ),
                default  => throw new Exception( 'Not implemented' ),
            };


        $getterNodeBuilder = ( new Builder\Method( $methodName ) )
            ->makePublic()
            ->addStmt(
                new Node\Stmt\Return_( $propertyFetch )
            );

        if ( $returnType !== null )
            $getterNodeBuilder->setReturnType(
                $isReturnTypeNullable ? new Node\NullableType( $returnType ) : $returnType
            );


        if ( $commentLines )
            $getterNodeBuilder->setDocComment( $this->createDocBlock( $commentLines ) );


        $this->addMethod( $getterNodeBuilder->getNode() );
    }

    private function addMethod( Node\Stmt\ClassMethod $methodNode ): void
    {
        $classNode     = $this->getClassNode();
        $methodName    = $methodNode->name;
        $existingIndex = null;
        if ( $this->methodExists( $methodName ) ) {
            if ( !$this->overwrite ) {
                $this->writeNote(
                    sprintf(
                        'Not generating <info>%s::%s()</info>: method already exists',
                        Str::getShortClassName( $this->getThisFullClassName() ),
                        $methodName
                    )
                );

                return;
            }

            // record, so we can overwrite in the same place
            $existingIndex = $this->getMethodIndex( $methodName );
        }

        $newStatements = [];

        // put new method always at the bottom
        if ( !empty( $classNode->stmts ) )
            $newStatements[] = $this->createBlankLineNode( self::CONTEXT_CLASS );


        $newStatements[] = $methodNode;

        if ( $existingIndex === null ) // add them to the end!
            $classNode->stmts = array_merge( $classNode->stmts, $newStatements );

        else {
            array_splice(
                $classNode->stmts,
                $existingIndex,
                1,
                $newStatements
            );
        }

        $this->updateSourceCodeFromNewStmts();
    }

    private function methodExists( string $methodName ): bool
    {
        return $this->getMethodIndex( $methodName ) !== false;
    }

    private function getMethodIndex( string $methodName )
    {
        foreach ( $this->getClassNode()->stmts as $i => $node ) {
            if ( $node instanceof Node\Stmt\ClassMethod &&
                 strtolower( $node->name->toString() ) === strtolower( $methodName ) ) {
                return $i;
            }
        }

        return false;
    }

    private function writeNote( string $note ): void
    {
        $this->io?->text( $note );
    }

    private function getThisFullClassName(): string
    {
        return (string)$this->getClassNode()->namespacedName;
    }

    public function addSetter( string $propertyName, $type, bool $isNullable, array $commentLines = [] ): void
    {
        $builder = $this->createSetterNodeBuilder( $propertyName, $type, $isNullable, $commentLines );
        $builder->addStmt(
            new Node\Stmt\Expression(
                new Node\Expr\Assign(
                    new Node\Expr\PropertyFetch( new Node\Expr\Variable( 'this' ), $propertyName ),
                    new Node\Expr\Variable( $propertyName )
                )
            )
        );
        $this->makeMethodFluent( $builder );
        $this->addMethod( $builder->getNode() );
    }

    private function createSetterNodeBuilder(
        string $propertyName,
               $type,
        bool   $isNullable,
        array  $commentLines = []
    ): Builder\Method {
        $methodName        = 'set' . Str::asCamelCase( $propertyName );
        $setterNodeBuilder = ( new Builder\Method( $methodName ) )->makePublic();

        if ( $commentLines ) {
            $setterNodeBuilder->setDocComment( $this->createDocBlock( $commentLines ) );
        }

        $paramBuilder = new Builder\Param( $propertyName );
        if ( $type !== null )
            $paramBuilder->setType( $isNullable ? new Node\NullableType( $type ) : $type );

        $setterNodeBuilder->addParam( $paramBuilder->getNode() );

        return $setterNodeBuilder;
    }

    private function makeMethodFluent( Builder\Method $methodBuilder ): void
    {
        if ( !$this->fluentMutators )
            return;


        $methodBuilder
            ->addStmt( $this->createBlankLineNode( self::CONTEXT_CLASS_METHOD ) )
            ->addStmt( new Node\Stmt\Return_( new Node\Expr\Variable( 'this' ) ) )
            ->setReturnType( 'self' );
    }

    public function addEmbeddedEntity( string $propertyName, string $className ): void
    {
        $typeHint = $this->addUseStatementIfNecessary( $className );

        $annotations = [
            $this->buildAnnotationLine(
                '@ORM\\Embedded', [
                'class' => new ClassNameValue( $className, $typeHint ),
            ] ),
        ];

        $this->addProperty( $propertyName, $annotations, type: $typeHint );

        // logic to avoid re-adding the same ArrayCollection line
        $addEmbedded = true;

        /**
         * We print the constructor to a string, then
         * look for "$this->propertyName = "
         */
        if ( $this->getConstructorNode() && str_contains(
                $this->printer->prettyPrint( [ $this->getConstructorNode() ] ),
                sprintf( '$this->%s = ', $propertyName )
            ) )
            $addEmbedded = false;


        if ( $addEmbedded ) {
            $this->addStatementToConstructor(
                new Node\Stmt\Expression(
                    new Node\Expr\Assign(
                        new Node\Expr\PropertyFetch( new Node\Expr\Variable( 'this' ), $propertyName ),
                        new Node\Expr\New_( new Node\Name( $typeHint ) )
                    )
                )
            );
        }

        $this->addGetter( $propertyName, $typeHint, false );
        $this->addSetter( $propertyName, $typeHint, false );
    }

    /**
     * @return string The alias to use when referencing this class
     * @throws \Exception
     * @throws \Exception
     */
    public function addUseStatementIfNecessary( string $class ): string
    {
        $shortClassName = Str::getShortClassName( $class );
        if ( $this->isInSameNamespace( $class ) )
            return $shortClassName;

        $namespaceNode = $this->getNamespaceNode();

        $targetIndex      = null;
        $addLineBreak     = false;
        $lastUseStmtIndex = null;

        foreach ( $namespaceNode->stmts as $index => $stmt ) {
            if ( $stmt instanceof Node\Stmt\Use_ ) {
                // I believe this is an array to account for use statements with {}
                foreach ( $stmt->uses as $use ) {
                    $alias = $use->alias->name ?? $use->name->getLast();

                    // the use statement already exists? Don't add it again
                    if ( $class === (string)$use->name )
                        return $alias;

                    /**
                     * we have a conflicting alias!
                     * to be safe, use the fully-qualified class name
                     * everywhere and do not add another use statement
                     */
                    if ( $alias === $shortClassName )
                        return '\\' . $class;

                }

                /**
                 * if $class is alphabetically before this use statement, place it before
                 * only set $targetIndex the first time you find it
                 */
                if ( $targetIndex === null && Str::areClassesAlphabetical( $class, (string)$stmt->uses[0]->name ) )
                    $targetIndex = $index;


                $lastUseStmtIndex = $index;
            }
            elseif ( $stmt instanceof Node\Stmt\Class_ ) {
                if ( $targetIndex !== null ) // we already found where to place the use statement
                    break;

                // we hit the class! If there were any use statements,
                // then put this at the bottom of the use statement list
                if ( $lastUseStmtIndex !== null )
                    $targetIndex = $lastUseStmtIndex + 1;

                else {
                    $targetIndex  = $index;
                    $addLineBreak = true;
                }

                break;
            }
        }

        if ( $targetIndex === null )
            throw new RuntimeException( 'Could not find a class!' );


        $newUseNode = ( new Builder\Use_( $class, Node\Stmt\Use_::TYPE_NORMAL ) )->getNode();
        array_splice(
            $namespaceNode->stmts,
            $targetIndex,
            0,
            $addLineBreak ? [ $newUseNode, $this->createBlankLineNode( self::CONTEXT_OUTSIDE_CLASS ) ] : [ $newUseNode ]
        );

        $this->updateSourceCodeFromNewStmts();

        return $shortClassName;
    }

    private function isInSameNamespace( $class ): bool
    {
        $namespace = substr( $class, 0, strrpos( $class, '\\' ) );

        return $this->getNamespaceNode()->name->toCodeString() === $namespace;
    }

    private function getNamespaceNode(): Node
    {
        $node = $this->findFirstNode( function ( $node ) {
            return $node instanceof Node\Stmt\Namespace_;
        } );

        if ( !$node )
            throw new RuntimeException( 'Could not find namespace node' );

        return $node;
    }

    /**
     * @throws \Exception
     */
    private function getConstructorNode(): ?Node\Stmt\ClassMethod
    {
        foreach ( $this->getClassNode()->stmts as $classNode )
            if ( $classNode instanceof Node\Stmt\ClassMethod && $classNode->name == '__construct' )
                return $classNode;


        return null;
    }

    private function addStatementToConstructor( Stmt $stmt ): void
    {
        if ( !$this->getConstructorNode() ) {
            $constructorNode = ( new Builder\Method( '__construct' ) )->makePublic()->getNode();

            // add call to parent::__construct() if there is a need to
            try {
                $ref = new ReflectionClass( $this->getThisFullClassName() );

                if ( $ref->getParentClass() && $ref->getParentClass()->getConstructor() )
                    $constructorNode->stmts[] = new Node\Stmt\Expression(
                        new Node\Expr\StaticCall( new Node\Name( 'parent' ), new Node\Identifier( '__construct' ) )
                    );

            } catch ( ReflectionException ) {
            }

            $this->addNodeAfterProperties( $constructorNode );
        }

        $constructorNode          = $this->getConstructorNode();
        $constructorNode->stmts[] = $stmt;
        $this->updateSourceCodeFromNewStmts();
    }

    public function addManyToOneRelation( RelationManyToOne $manyToOne ): void
    {
        $this->addSingularRelation( $manyToOne );
    }

    private function addSingularRelation( BaseRelation $relation ): void
    {
        $typeHint = $this->addUseStatementIfNecessary( $relation->getTargetClassName() );
        if ( $relation->getTargetClassName() === $this->getThisFullClassName() )
            $typeHint = 'self';


        $annotationOptions = [
            'targetEntity' => new ClassNameValue( $typeHint, $relation->getTargetClassName() ),
        ];
        if ( $relation->isOwning() )
            // sometimes, we don't map the inverse relation
            if ( $relation->getMapInverseRelation() )
                $annotationOptions['inversedBy'] = $relation->getTargetPropertyName();


            else
                $annotationOptions['mappedBy'] = $relation->getTargetPropertyName();


        if ( $relation instanceof RelationOneToOne )
            $annotationOptions['cascade'] = [ 'persist', 'remove' ];


        $annotations = [
            $this->buildAnnotationLine(
                $relation instanceof RelationManyToOne ? '@ORM\\ManyToOne' : '@ORM\\OneToOne',
                $annotationOptions
            ),
        ];

        if ( !$relation->isNullable() && $relation->isOwning() )
            $annotations[] = $this->buildAnnotationLine( '@ORM\\JoinColumn', [
                'nullable' => false,
            ] );


        $this->addProperty( $relation->getPropertyName(), $annotations, type: $typeHint );

        $this->addGetter(
            $relation->getPropertyName(),
            $relation->getCustomReturnType() ?: $typeHint,
            // getter methods always have nullable return values
            // unless this has been customized explicitly
            !$relation->getCustomReturnType() || $relation->isCustomReturnTypeNullable()
        );

        if ( $relation->shouldAvoidSetter() ) {
            return;
        }

        $setterNodeBuilder = $this->createSetterNodeBuilder(
            $relation->getPropertyName(),
            $typeHint,
            // make the type-hint nullable always for ManyToOne to allow the owning
            // side to be set to null, which is needed for orphanRemoval
            // (specifically: when you set the inverse side, the generated
            // code will *also* set the owning side to null - so it needs to be allowed)
            // e.g. $userAvatarPhoto->setUser(null);
            $relation instanceof RelationOneToOne ? $relation->isNullable() : true
        );

        // set the *owning* side of the relation
        // OneToOne is the only "singular" relation type that
        // may be the inverse side
        if ( $relation instanceof RelationOneToOne && !$relation->isOwning() ) {
            $this->addNodesToSetOtherSideOfOneToOne( $relation, $setterNodeBuilder );
        }

        $setterNodeBuilder->addStmt(
            new Node\Stmt\Expression(
                new Node\Expr\Assign(
                    new Node\Expr\PropertyFetch( new Node\Expr\Variable( 'this' ), $relation->getPropertyName() ),
                    new Node\Expr\Variable( $relation->getPropertyName() )
                )
            )
        );
        $this->makeMethodFluent( $setterNodeBuilder );
        $this->addMethod( $setterNodeBuilder->getNode() );
    }

    private function addNodesToSetOtherSideOfOneToOne(
        RelationOneToOne $relation,
        Builder\Method   $setterNodeBuilder
    ): void {
        if ( !$relation->isNullable() ) {
            $setterNodeBuilder->addStmt(
                $this->createSingleLineCommentNode(
                    'set the owning side of the relation if necessary'
                )
            );

            // if ($user->getUserProfile() !== $this) {
            $ifNode = new Node\Stmt\If_(
                new Node\Expr\BinaryOp\NotIdentical(
                    new Node\Expr\MethodCall(
                        new Node\Expr\Variable( $relation->getPropertyName() ),
                        $relation->getTargetGetterMethodName()
                    ),
                    new Node\Expr\Variable( 'this' )
                )
            );

            // $user->setUserProfile($this);
            $ifNode->stmts = [
                new Node\Stmt\Expression(
                    new Node\Expr\MethodCall(
                        new Node\Expr\Variable( $relation->getPropertyName() ),
                        $relation->getTargetSetterMethodName(),
                        [ new Node\Arg( new Node\Expr\Variable( 'this' ) ) ]
                    )
                ),
            ];
            $setterNodeBuilder->addStmt( $ifNode );
            $setterNodeBuilder->addStmt( $this->createBlankLineNode( self::CONTEXT_CLASS_METHOD ) );

            return;
        }

        // at this point, we know the relation is nullable
        $setterNodeBuilder->addStmt(
            $this->createSingleLineCommentNode(
                'unset the owning side of the relation if necessary'
            )
        );

        // if ($user !== null && $user->getUserProfile() !== $this)
        $ifNode        = new Node\Stmt\If_(
            new Node\Expr\BinaryOp\BooleanAnd(
                new Node\Expr\BinaryOp\Identical(
                    new Node\Expr\Variable( $relation->getPropertyName() ),
                    $this->createNullConstant()
                ),
                new Node\Expr\BinaryOp\NotIdentical(
                    new Node\Expr\PropertyFetch(
                        new Node\Expr\Variable( 'this' ),
                        $relation->getPropertyName()
                    ),
                    $this->createNullConstant()
                )
            )
        );
        $ifNode->stmts = [
            // $this->user->setUserProfile(null)
            new Node\Stmt\Expression(
                new Node\Expr\MethodCall(
                    new Node\Expr\PropertyFetch(
                        new Node\Expr\Variable( 'this' ),
                        $relation->getPropertyName()
                    ),
                    $relation->getTargetSetterMethodName(),
                    [ new Node\Arg( $this->createNullConstant() ) ]
                )
            ),
        ];
        $setterNodeBuilder->addStmt( $ifNode );

        $setterNodeBuilder->addStmt( $this->createBlankLineNode( self::CONTEXT_CLASS_METHOD ) );
        $setterNodeBuilder->addStmt(
            $this->createSingleLineCommentNode(
                'set the owning side of the relation if necessary'
            )
        );

        // if ($user === null && $this->user !== null)
        $ifNode        = new Node\Stmt\If_(
            new Node\Expr\BinaryOp\BooleanAnd(
                new Node\Expr\BinaryOp\NotIdentical(
                    new Node\Expr\Variable( $relation->getPropertyName() ),
                    $this->createNullConstant()
                ),
                new Node\Expr\BinaryOp\NotIdentical(
                    new Node\Expr\MethodCall(
                        new Node\Expr\Variable( $relation->getPropertyName() ),
                        $relation->getTargetGetterMethodName()
                    ),
                    new Node\Expr\Variable( 'this' )
                )
            )
        );
        $ifNode->stmts = [
            new Node\Stmt\Expression(
                new Node\Expr\MethodCall(
                    new Node\Expr\Variable( $relation->getPropertyName() ),
                    $relation->getTargetSetterMethodName(),
                    [ new Node\Arg( new Node\Expr\Variable( 'this' ) ) ]
                )
            ),
        ];
        $setterNodeBuilder->addStmt( $ifNode );

        $setterNodeBuilder->addStmt( $this->createBlankLineNode( self::CONTEXT_CLASS_METHOD ) );
    }

    private function createSingleLineCommentNode( string $comment ): Stmt
    {
        $this->pendingComments[] = $comment;
        switch ( self::CONTEXT_CLASS_METHOD ) {
            case self::CONTEXT_OUTSIDE_CLASS:
                // just not needed yet
                throw new RuntimeException( 'not supported' );
            case self::CONTEXT_CLASS:
                // just not needed yet
                throw new RuntimeException( 'not supported' );
            case self::CONTEXT_CLASS_METHOD:
                return BuilderHelpers::normalizeStmt(
                    new Node\Expr\Variable( sprintf( '__COMMENT__VAR_%d', count( $this->pendingComments ) - 1 ) )
                );
            default:
                throw new RuntimeException( 'Unknown context: ' . self::CONTEXT_CLASS_METHOD );
        }
    }

    private function createNullConstant(): Node\Expr\ConstFetch
    {
        return new Node\Expr\ConstFetch( new Node\Name( 'null' ) );
    }

    public function addOneToOneRelation( RelationOneToOne $oneToOne ): void
    {
        $this->addSingularRelation( $oneToOne );
    }

    public function addOneToManyRelation( RelationOneToMany $oneToMany ): void
    {
        $this->addCollectionRelation( $oneToMany );
    }

    /** @noinspection PhpParamsInspection */
    private function addCollectionRelation( BaseCollectionRelation $relation ): void
    {
        $typeHint = $relation->isSelfReferencing()
            ? 'self'
            : $this->addUseStatementIfNecessary(
                $relation->getTargetClassName()
            );

        $arrayCollectionTypeHint = $this->addUseStatementIfNecessary( ArrayCollection::class );
        $collectionTypeHint      = $this->addUseStatementIfNecessary( Collection::class );

        $annotationOptions = [
            'targetEntity' => new ClassNameValue( $typeHint, $relation->getTargetClassName() ),
        ];
        if ( $relation->isOwning() ) {
            // sometimes, we don't map the inverse relation
            if ( $relation->getMapInverseRelation() ) {
                $annotationOptions['inversedBy'] = $relation->getTargetPropertyName();
            }
        }
        else {
            $annotationOptions['mappedBy'] = $relation->getTargetPropertyName();
        }

        if ( $relation->getOrphanRemoval() ) {
            $annotationOptions['orphanRemoval'] = true;
        }


        /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
        $annotations = [
            $this->buildAnnotationLine(
                $relation instanceof RelationManyToMany ? '@ORM\\ManyToMany' : '@ORM\\OneToMany',
                $annotationOptions
            ),
            $this->buildAnnotationLine( "@var ArrayCollection|{$typeHint}[]", [] ),
        ];

        $this->addProperty( $relation->getPropertyName(), $annotations, type: 'Collection' );

        // logic to avoid re-adding the same ArrayCollection line
        $addArrayCollection = true;
        if ( $this->getConstructorNode() ) {
            // We print the constructor to a string, then
            // look for "$this->propertyName = "

            $constructorString = $this->printer->prettyPrint( [ $this->getConstructorNode() ] );
            if ( str_contains( $constructorString, sprintf( '$this->%s = ', $relation->getPropertyName() ) ) )
                $addArrayCollection = false;

        }

        if ( $addArrayCollection ) {
            $this->addStatementToConstructor(
                new Node\Stmt\Expression(
                    new Node\Expr\Assign(
                        new Node\Expr\PropertyFetch( new Node\Expr\Variable( 'this' ), $relation->getPropertyName() ),
                        new Node\Expr\New_( new Node\Name( $arrayCollectionTypeHint ) )
                    )
                )
            );
        }

        $this->addGetter(
            $relation->getPropertyName(),
            $collectionTypeHint,
            false,
            // add @return that advertises this as a collection of specific objects
            [ sprintf( '@return %s|%s[]', $collectionTypeHint, $typeHint ) ]
        );

        $argName = Str::pluralCamelCaseToSingular( $relation->getPropertyName() );

        // adder method
        $adderNodeBuilder = ( new Builder\Method( $relation->getAdderMethodName() ) )->makePublic();

        $paramBuilder = new Builder\Param( $argName );
        $paramBuilder->setType( $typeHint );
        $adderNodeBuilder->addParam( $paramBuilder->getNode() );

        $containsMethodCallNode = new Node\Expr\MethodCall(
            new Node\Expr\PropertyFetch( new Node\Expr\Variable( 'this' ), $relation->getPropertyName() ),
            'contains',
            [ new Node\Expr\Variable( $argName ) ]
        );
        $ifNotContainsStmt      = new Node\Stmt\If_(
            new Node\Expr\BooleanNot( $containsMethodCallNode )
        );
        $adderNodeBuilder->addStmt( $ifNotContainsStmt );

        // append the item
        $ifNotContainsStmt->stmts[] = new Node\Stmt\Expression(
            new Node\Expr\Assign(
                new Node\Expr\ArrayDimFetch(
                    new Node\Expr\PropertyFetch( new Node\Expr\Variable( 'this' ), $relation->getPropertyName() )
                ),
                new Node\Expr\Variable( $argName )
            )
        );

        // set the owning side of the relationship
        if ( !$relation->isOwning() ) {
            $ifNotContainsStmt->stmts[] = new Node\Stmt\Expression(
                new Node\Expr\MethodCall(
                    new Node\Expr\Variable( $argName ),
                    $relation->getTargetSetterMethodName(),
                    [ new Node\Expr\Variable( 'this' ) ]
                )
            );
        }

        $this->makeMethodFluent( $adderNodeBuilder );
        $this->addMethod( $adderNodeBuilder->getNode() );

        /*
         * Remover
         */
        $removerNodeBuilder = ( new Builder\Method( $relation->getRemoverMethodName() ) )->makePublic();

        $paramBuilder = new Builder\Param( $argName );
        $paramBuilder->setType( $typeHint );
        $removerNodeBuilder->addParam( $paramBuilder->getNode() );

        // $this->avatars->removeElement($avatar)
        $removeElementCall = new Node\Expr\MethodCall(
            new Node\Expr\PropertyFetch( new Node\Expr\Variable( 'this' ), $relation->getPropertyName() ),
            'removeElement',
            [ new Node\Expr\Variable( $argName ) ]
        );

        // set the owning side of the relationship
        if ( $relation->isOwning() ) {
            // $this->avatars->removeElement($avatar);
            $removerNodeBuilder->addStmt( BuilderHelpers::normalizeStmt( $removeElementCall ) );
        }
        else {
            //if ($this->avatars->removeElement($avatar))
            $ifRemoveElementStmt = new Node\Stmt\If_( $removeElementCall );
            $removerNodeBuilder->addStmt( $ifRemoveElementStmt );
            if ( $relation instanceof RelationOneToMany ) {
                // OneToMany: $student->setCourse(null);
                /*
                 * // set the owning side to null (unless already changed)
                 * if ($student->getCourse() === $this) {
                 *     $student->setCourse(null);
                 * }
                 */

                $ifRemoveElementStmt->stmts[] = $this->createSingleLineCommentNode(
                    'set the owning side to null (unless already changed)'
                );

                // if ($student->getCourse() === $this) {
                $ifNode = new Node\Stmt\If_(
                    new Node\Expr\BinaryOp\Identical(
                        new Node\Expr\MethodCall(
                            new Node\Expr\Variable( $argName ),
                            $relation->getTargetGetterMethodName()
                        ),
                        new Node\Expr\Variable( 'this' )
                    )
                );

                // $student->setCourse(null);
                $ifNode->stmts = [
                    new Node\Stmt\Expression(
                        new Node\Expr\MethodCall(
                            new Node\Expr\Variable( $argName ),
                            $relation->getTargetSetterMethodName(),
                            [ new Node\Arg( $this->createNullConstant() ) ]
                        )
                    ),
                ];

                $ifRemoveElementStmt->stmts[] = $ifNode;
            }
            elseif ( $relation instanceof RelationManyToMany ) {
                // $student->removeCourse($this);
                $ifRemoveElementStmt->stmts[] = new Node\Stmt\Expression(
                    new Node\Expr\MethodCall(
                        new Node\Expr\Variable( $argName ),
                        $relation->getTargetRemoverMethodName(),
                        [ new Node\Expr\Variable( 'this' ) ]
                    )
                );
            }
            else {
                throw new RuntimeException( 'Unknown relation type' );
            }
        }

        $this->makeMethodFluent( $removerNodeBuilder );
        $this->addMethod( $removerNodeBuilder->getNode() );
    }

    public function addManyToManyRelation( RelationManyToMany $manyToMany ): void
    {
        $this->addCollectionRelation( $manyToMany );
    }

    public function addInterface( string $interfaceName ): void
    {
        $this->addUseStatementIfNecessary( $interfaceName );

        $this->getClassNode()->implements[] = new Node\Name( Str::getShortClassName( $interfaceName ) );
        $this->updateSourceCodeFromNewStmts();
    }

    /**
     * @param string $trait the fully-qualified trait name
     *
     * @throws \Exception
     * @throws \Exception
     */
    public function addTrait( string $trait ): void
    {
        $importedClassName = $this->addUseStatementIfNecessary( $trait );

        /** @var Node\Stmt\TraitUse[] $traitNodes */
        $traitNodes = $this->findAllNodes( function ( $node ) {
            return $node instanceof Node\Stmt\TraitUse;
        } );

        foreach ( $traitNodes as $node ) {
            if ( $node->traits[0]->toString() === $importedClassName ) {
                return;
            }
        }

        $traitNodes[] = new Node\Stmt\TraitUse( [ new Node\Name( $importedClassName ) ] );

        $classNode = $this->getClassNode();

        if ( !empty( $classNode->stmts ) && count( $traitNodes ) === 1 ) {
            $traitNodes[] = $this->createBlankLineNode( self::CONTEXT_CLASS );
        }

        // avoid all the use traits in class for unshift all the new UseTrait
        // in the right order.
        foreach ( $classNode->stmts as $key => $node ) {
            if ( $node instanceof Node\Stmt\TraitUse ) {
                unset( $classNode->stmts[ $key ] );
            }
        }

        array_unshift( $classNode->stmts, ...$traitNodes );

        $this->updateSourceCodeFromNewStmts();
    }

    /**
     * @return Node[]
     */
    private function findAllNodes( callable $filterCallback ): array
    {
        $traverser = new NodeTraverser();
        $visitor   = new NodeVisitor\FindingVisitor( $filterCallback );
        $traverser->addVisitor( $visitor );
        $traverser->traverse( $this->newStmts );

        return $visitor->getFoundNodes();
    }

    /**
     * @noinspection PhpTooManyParametersInspection
     * @noinspection PhpUnused
     */
    public function addAccessorMethod(
        string $propertyName,
        string $methodName,
               $returnType,
        bool   $isReturnTypeNullable,
        array  $commentLines = [],
               $typeCast = null
    ): void {
        $this->addCustomGetter(
            $propertyName,
            $methodName,
            $returnType,
            $isReturnTypeNullable,
            $commentLines,
            $typeCast
        );
    }

    /**
     * @param Node[] $params
     *
     * @throws \Exception
     * @throws \Exception
     */
    public function addConstructor( array $params, string $methodBody ): void
    {
        if ( $this->getConstructorNode() !== null )
            throw new LogicException( 'Constructor already exists.' );


        $methodBuilder = $this->createMethodBuilder( '__construct', null, false );

        $this->addMethodParams( $methodBuilder, $params );

        $this->addMethodBody( $methodBuilder, $methodBody );

        $this->addNodeAfterProperties( $methodBuilder->getNode() );
        $this->updateSourceCodeFromNewStmts();
    }

    public function createMethodBuilder(
        string $methodName,
               $returnType,
        bool   $isReturnTypeNullable,
        array  $commentLines = []
    ): Builder\Method {
        $methodNodeBuilder = ( new Builder\Method( $methodName ) )
            ->makePublic();

        if ( $returnType !== null ) {
            if ( class_exists( $returnType ) || interface_exists( $returnType ) ) {
                $returnType = $this->addUseStatementIfNecessary( $returnType );
            }
            $methodNodeBuilder->setReturnType(
                $isReturnTypeNullable ? new Node\NullableType( $returnType ) : $returnType
            );
        }

        if ( $commentLines ) {
            $methodNodeBuilder->setDocComment( $this->createDocBlock( $commentLines ) );
        }

        return $methodNodeBuilder;
    }

    private function addMethodParams( Builder\Method $methodBuilder, array $params ): void
    {
        foreach ( $params as $param ) {
            $methodBuilder->addParam( $param );
        }
    }

    public function addMethodBody( Builder\Method $methodBuilder, string $methodBody ): void
    {
        $nodes = $this->parser->parse( $methodBody );
        $methodBuilder->addStmts( $nodes );
    }

    /**
     * @param Node[] $params
     */
    public function addMethodBuilder(
        Builder\Method $methodBuilder,
        array          $params = [],
        string         $methodBody = null
    ): void {
        $this->addMethodParams( $methodBuilder, $params );

        if ( $methodBody ) {
            $this->addMethodBody( $methodBuilder, $methodBody );
        }

        $this->addMethod( $methodBuilder->getNode() );
    }

    /**
     * @noinspection PhpUnused
     */
    public function createMethodLevelCommentNode( string $comment ): Stmt
    {
        return $this->createSingleLineCommentNode( $comment );
    }

    /**
     * @noinspection PhpUnused
     */
    public function createMethodLevelBlankLine()
    {
        return $this->createBlankLineNode( self::CONTEXT_CLASS_METHOD );
    }

    public function addAnnotationToClass( string $annotationClass, array $options ): void
    {
        $annotationClassAlias = $this->addUseStatementIfNecessary( $annotationClass );
        $docComment           = $this->getClassNode()->getDocComment();

        $docLines = $docComment ? explode( "\n", $docComment->getText() ) : [];
        if ( count( $docLines ) === 0 ) {
            $docLines = [ '/**', ' */' ];
        }
        elseif ( count( $docLines ) === 1 ) {
            // /** inline doc syntax */
            // imperfect way to try to find where to split the lines
            $endOfOpening   = strpos( $docLines[0], '* ' );
            $endingPosition = strrpos( $docLines[0], ' *', $endOfOpening );
            $extraComments  = trim( substr( $docLines[0], $endOfOpening + 2, $endingPosition - $endOfOpening - 2 ) );
            $newDocLines    = [
                substr( $docLines[0], 0, $endOfOpening + 1 ),
            ];

            if ( $extraComments ) {
                $newDocLines[] = ' * ' . $extraComments;
            }

            $newDocLines[] = substr( $docLines[0], $endingPosition );
            $docLines      = $newDocLines;
        }

        array_splice(
            $docLines,
            count( $docLines ) - 1,
            0,
            ' * ' . $this->buildAnnotationLine( '@' . $annotationClassAlias, $options )
        );

        $docComment = new Doc( implode( "\n", $docLines ) );
        $this->getClassNode()->setDocComment( $docComment );
        $this->updateSourceCodeFromNewStmts();
    }

}
