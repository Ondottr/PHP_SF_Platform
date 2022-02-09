<?php

/** @noinspection PhpMethodNamingConventionInspection */
declare( strict_types=1 );


namespace PHP_SF\System\Database;

use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\ExistsExpression;
use Doctrine\ORM\Query\AST\Functions;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\JoinAssociationPathExpression;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TreeWalker;
use Doctrine\ORM\Query\TreeWalkerChain;
use ReflectionClass;
use function array_intersect;
use function array_search;
use function assert;
use function class_exists;
use function count;
use function explode;
use function implode;
use function in_array;
use function interface_exists;
use function is_string;
use function sprintf;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;

final class Parser
{

    /**
     * READ-ONLY: Maps BUILT-IN string function names to AST class names.
     *
     * @psalm-var array<string, class-string<FunctionNode>>
     */
    private static array $stringFunctions = [
        'concat'    => Functions\ConcatFunction::class,
        'substring' => Functions\SubstringFunction::class,
        'trim'      => Functions\TrimFunction::class,
        'lower'     => Functions\LowerFunction::class,
        'upper'     => Functions\UpperFunction::class,
        'identity'  => Functions\IdentityFunction::class,
    ];

    /**
     * READ-ONLY: Maps BUILT-IN numeric function names to AST class names.
     *
     * @psalm-var array<string, class-string<Functions\FunctionNode>>
     */
    private static array $numericFunctions = [
        'length'    => Functions\LengthFunction::class,
        'locate'    => Functions\LocateFunction::class,
        'abs'       => Functions\AbsFunction::class,
        'sqrt'      => Functions\SqrtFunction::class,
        'mod'       => Functions\ModFunction::class,
        'size'      => Functions\SizeFunction::class,
        'date_diff' => Functions\DateDiffFunction::class,
        'bit_and'   => Functions\BitAndFunction::class,
        'bit_or'    => Functions\BitOrFunction::class,

        // Aggregate functions
        'min'       => Functions\MinFunction::class,
        'max'       => Functions\MaxFunction::class,
        'avg'       => Functions\AvgFunction::class,
        'sum'       => Functions\SumFunction::class,
        'count'     => Functions\CountFunction::class,
    ];

    /**
     * READ-ONLY: Maps BUILT-IN datetime function names to AST class names.
     *
     * @psalm-var array<string, class-string<Functions\FunctionNode>>
     */
    private static array $datetimeFunctions = [
        'current_date'      => Functions\CurrentDateFunction::class,
        'current_time'      => Functions\CurrentTimeFunction::class,
        'current_timestamp' => Functions\CurrentTimestampFunction::class,
        'date_add'          => Functions\DateAddFunction::class,
        'date_sub'          => Functions\DateSubFunction::class,
    ];

    /*
     * Expressions that were encountered during parsing of identifiers and expressions
     * and still need to be validated.
     */

    /** @psalm-var list<array{token: mixed, expression: mixed, nestingLevel: int}> */
    private array $deferredIdentificationVariables = [];

    /** @psalm-var list<array{token: mixed, expression: mixed, nestingLevel: int}> */
    private array $deferredPartialObjectExpressions = [];

    /** @psalm-var list<array{token: mixed, expression: mixed, nestingLevel: int}> */
    private array $deferredPathExpressions = [];

    /** @psalm-var list<array{token: mixed, expression: mixed, nestingLevel: int}> */
    private array $deferredResultVariables = [];

    /** @psalm-var list<array{token: mixed, expression: mixed, nestingLevel: int}> */
    private array $deferredNewObjectExpressions = [];

    /**
     * The lexer.
     *
     * @var Lexer
     */
    private Lexer $lexer;

    /**
     * The parser result.
     *
     * @var ParserResult
     */
    private ParserResult $parserResult;

    /**
     * The EntityManager.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $em;

    /**
     * The Query to parse.
     *
     * @var Query
     */
    private Query $query;

    /**
     * Map of declared query components in the parsed query.
     *
     * @psalm-var array<string, array<string, mixed>>
     */
    private array $queryComponents = [];

    /**
     * Keeps the nesting level of defined ResultVariables.
     *
     * @var int
     */
    private int $nestingLevel = 0;

    /**
     * Any additional custom tree walkers that modify the AST.
     *
     * @psalm-var list<class-string<TreeWalker>>
     */
    private array $customTreeWalkers = [];

    /**
     * The custom last tree walker, if any, that is responsible for producing the output.
     *
     * @var class-string<TreeWalker>|null
     */
    private ?string $customOutputWalker;

    /** @psalm-var list<AST\SelectExpression> */
    private array $identVariableExpressions = [];

    /**
     * Creates a new query parser object.
     *
     * @param Query $query The Query to parse.
     */
    public function __construct(Query $query)
    {
        $this->query = $query;
        $this->em = $query->getEntityManager();
        $this->lexer = new Lexer((string)$query->getDQL());
        $this->parserResult = new ParserResult();
    }

    /**
     * Sets a custom tree walker that produces output.
     * This tree walker will be run last over the AST, after any other walkers.
     *
     * @param string $className
     *
     * @return void
     */
    public function setCustomOutputTreeWalker(string $className): void
    {
        $this->customOutputWalker = $className;
    }

    /**
     * Adds a custom tree walker for modifying the AST.
     *
     * @param string $className
     * @psalm-param class-string $className
     *
     * @return void
     */
    public function addCustomTreeWalker(string $className): void
    {
        $this->customTreeWalkers[] = $className;
    }

    /**
     * Gets the lexer used by the parser.
     *
     * @return Lexer
     */
    public function getLexer(): Lexer
    {
        return $this->lexer;
    }

    /**
     * Gets the ParserResult that is being filled with information during parsing.
     *
     * @return ParserResult
     */
    public function getParserResult(): ParserResult
    {
        return $this->parserResult;
    }

    /**
     * Gets the EntityManager used by the parser.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }

    /**
     * Frees this parser, enabling it to be reused.
     *
     * @param bool $deep     Whether to clean peek and reset errors.
     * @param int  $position Position to reset.
     *
     * @return void
     */
    public function free(bool $deep, int $position = 0): void
    {
        // WARNING! Use this method with care. It resets the scanner!
        $this->lexer->resetPosition($position);

        // Deep = true cleans peek and also any previously defined errors
        if ($deep) {
            $this->lexer->resetPeek();
        }

        $this->lexer->token = null;
        $this->lexer->lookahead = null;
    }

    /**
     * Parses a query string.
     *
     * @return ParserResult
     * @noinspection PhpVariableNamingConventionInspection
     */
    public function parse(): ParserResult
    {
        $AST = $this->getAST();

        $customWalkers = $this->query->getHint(Query::HINT_CUSTOM_TREE_WALKERS);
        if ($customWalkers !== false) {
            $this->customTreeWalkers = $customWalkers;
        }

        $customOutputWalker = $this->query->getHint(Query::HINT_CUSTOM_OUTPUT_WALKER);
        if ($customOutputWalker !== false) {
            $this->customOutputWalker = $customOutputWalker;
        }

        // Run any custom tree walkers over the AST
        if ($this->customTreeWalkers) {
            $treeWalkerChain = new TreeWalkerChain($this->query, $this->parserResult, $this->queryComponents);

            foreach ($this->customTreeWalkers as $walker) {
                $treeWalkerChain->addTreeWalker($walker);
            }

            switch (true) {
                case $AST instanceof AST\UpdateStatement:
                    $treeWalkerChain->walkUpdateStatement($AST);
                    break;

                case $AST instanceof AST\DeleteStatement:
                    $treeWalkerChain->walkDeleteStatement($AST);
                    break;

                case $AST instanceof AST\SelectStatement:
                default:
                    $treeWalkerChain->walkSelectStatement($AST);
            }

            $this->queryComponents = $treeWalkerChain->getQueryComponents();
        }

        $outputWalkerClass = $this->customOutputWalker ?? SqlWalker::class;
        $outputWalker = new $outputWalkerClass($this->query, $this->parserResult, $this->queryComponents);

        // Assign an SQL executor to the parser result
        $this->parserResult->setSqlExecutor($outputWalker->getExecutor($AST));

        return $this->parserResult;
    }

    /**
     * Parses and builds AST for the given Query.
     *
     * @return AST\DeleteStatement|AST\UpdateStatement|AST\SelectStatement|null
     * @throws QueryException
     * @noinspection NullPointerExceptionInspection
     * @noinspection PhpVariableNamingConventionInspection
     */
    public function getAST(): AST\DeleteStatement|AST\UpdateStatement|AST\SelectStatement|null
    {
        // Parse & build AST
        $AST = $this->QueryLanguage();

        // Process any deferred validations of some nodes in the AST.
        // This also allows post-processing of the AST for modification purposes.
        $this->processDeferredIdentificationVariables();

        if ($this->deferredPartialObjectExpressions) {
            $this->processDeferredPartialObjectExpressions();
        }

        if ($this->deferredPathExpressions) {
            $this->processDeferredPathExpressions();
        }

        if ($this->deferredResultVariables) {
            $this->processDeferredResultVariables();
        }

        if ($this->deferredNewObjectExpressions) {
            $this->processDeferredNewObjectExpressions($AST);
        }

        $this->processRootEntityAliasSelected();

        $this->fixIdentificationVariableOrder($AST);

        return $AST;
    }

    /**
     * QueryLanguage ::= SelectStatement | UpdateStatement | DeleteStatement
     *
     * @return AST\DeleteStatement|AST\UpdateStatement|AST\SelectStatement|null
     * @throws QueryException
     */
    public function QueryLanguage(): AST\DeleteStatement|AST\UpdateStatement|AST\SelectStatement|null
    {
        $statement = null;

        $this->lexer->moveNext();

        switch ($this->lexer->lookahead[ 'type' ] ?? null) {
            case Lexer::T_SELECT:
                $statement = $this->SelectStatement();
                break;

            case Lexer::T_UPDATE:
                $statement = $this->UpdateStatement();
                break;

            case Lexer::T_DELETE:
                $statement = $this->DeleteStatement();
                break;

            default:
                $this->syntaxError('SELECT, UPDATE or DELETE');
        }

        // Check for end of string
        if ($this->lexer->lookahead !== null) {
            $this->syntaxError('end of string');
        }

        return $statement;
    }

    /**
     * SelectStatement ::= SelectClause FromClause [WhereClause] [GroupByClause] [HavingClause] [OrderByClause]
     *
     * @return AST\SelectStatement
     */
    public function SelectStatement(): AST\SelectStatement
    {
        $selectStatement = new AST\SelectStatement($this->SelectClause(), $this->FromClause());

        $selectStatement->whereClause = $this->lexer->isNextToken(Lexer::T_WHERE) ? $this->WhereClause() : null;
        $selectStatement->groupByClause = $this->lexer->isNextToken(Lexer::T_GROUP) ? $this->GroupByClause() : null;
        $selectStatement->havingClause = $this->lexer->isNextToken(Lexer::T_HAVING) ? $this->HavingClause() : null;
        $selectStatement->orderByClause = $this->lexer->isNextToken(Lexer::T_ORDER) ? $this->OrderByClause() : null;

        return $selectStatement;
    }

    /**
     * SelectClause ::= "SELECT" ["DISTINCT"] SelectExpression {"," SelectExpression}
     *
     * @return AST\SelectClause
     */
    public function SelectClause(): AST\SelectClause
    {
        $isDistinct = false;
        $this->match(Lexer::T_SELECT);

        // Check for DISTINCT
        if ($this->lexer->isNextToken(Lexer::T_DISTINCT)) {
            $this->match(Lexer::T_DISTINCT);

            $isDistinct = true;
        }

        // Process SelectExpressions (1..N)
        $selectExpressions = [];
        $selectExpressions[] = $this->SelectExpression();

        while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
            $this->match(Lexer::T_COMMA);

            $selectExpressions[] = $this->SelectExpression();
        }

        return new AST\SelectClause($selectExpressions, $isDistinct);
    }

    /**
     * Attempts to match the given token with the current lookahead token.
     *
     * If they match, updates the lookahead token; otherwise raises a syntax
     * error.
     *
     * @param int $token The token type.
     *
     * @return void
     *
     * @throws QueryException If the tokens don't match.
     */
    public function match(int $token): void
    {
        $lookaheadType = $this->lexer->lookahead[ 'type' ] ?? null;

        // Short-circuit on first condition, usually types match
        if ($lookaheadType === $token) {
            $this->lexer->moveNext();

            return;
        }

        // If parameter is not identifier (1-99) must be exact match
        if ($token < Lexer::T_IDENTIFIER) {
            $this->syntaxError($this->lexer->getLiteral($token));
        }

        // If parameter is keyword (200+) must be exact match
        if ($token > Lexer::T_IDENTIFIER) {
            $this->syntaxError($this->lexer->getLiteral($token));
        }

        // If parameter is T_IDENTIFIER, then matches T_IDENTIFIER (100) and keywords (200+)
        if ($token === Lexer::T_IDENTIFIER && $lookaheadType < Lexer::T_IDENTIFIER) {
            $this->syntaxError($this->lexer->getLiteral($token));
        }

        $this->lexer->moveNext();
    }

    /**
     * Generates a new syntax error.
     *
     * @param string     $expected Expected string.
     * @param array|null $token    Got token.
     * @psalm-param  array<string, mixed>|null $token
     *
     * @return void
     * @psalm-return no-return
     *
     * @throws QueryException
     */
    public function syntaxError(string $expected = '', array $token = null): void
    {
        if ($token === null) {
            $token = $this->lexer->lookahead;
        }

        $tokenPos = $token[ 'position' ] ?? '-1';

        $message = sprintf('line 0, col %d: Error: ', $tokenPos);
        $message .= $expected !== '' ? sprintf('Expected %s, got ', $expected) : 'Unexpected ';
        $message .= $this->lexer->lookahead === null ? 'end of string.' : sprintf("'%s'", $token[ 'value' ]);

        throw QueryException::syntaxError($message, QueryException::dqlError($this->query->getDQL() ?? ''));
    }

    /**
     * SelectExpression ::= (
     *     IdentificationVariable | ScalarExpression | AggregateExpression | FunctionDeclaration |
     *     PartialObjectExpression | "(" Subselect ")" | CaseExpression | NewObjectExpression
     * ) [["AS"] ["HIDDEN"] AliasResultVariable]
     *
     * @return AST\SelectExpression
     */
    public function SelectExpression(): AST\SelectExpression
    {
        $expression = null;
        $identVariable = null;
        $peek = $this->lexer->glimpse();
        $lookaheadType = $this->lexer->lookahead[ 'type' ];

        switch (true) {
            // ScalarExpression (u.name)
            case $lookaheadType === Lexer::T_IDENTIFIER && $peek[ 'type' ] === Lexer::T_DOT:
                $expression = $this->ScalarExpression();
                break;

            // IdentificationVariable (u)
            case $lookaheadType === Lexer::T_IDENTIFIER && $peek[ 'type' ] !== Lexer::T_OPEN_PARENTHESIS:
                $expression = ( $identVariable = $this->IdentificationVariable() );
                break;

            // CaseExpression (CASE ... or NULLIF(...) or COALESCE(...))
            case $lookaheadType === Lexer::T_CASE:
            case $lookaheadType === Lexer::T_COALESCE:
            case $lookaheadType === Lexer::T_NULLIF:
                $expression = $this->CaseExpression();
                break;

            // DQL Function (SUM(u.value) or SUM(u.value) + 1)
            case $this->isFunction():
                $this->lexer->peek(); // "("

                $expression = match (true) {
                    $this->isMathOperator($this->peekBeyondClosingParenthesis()) => $this->ScalarExpression(),
                    default => $this->FunctionDeclaration(),
                };

                break;

            // PartialObjectExpression (PARTIAL u.{id, name})
            case $lookaheadType === Lexer::T_PARTIAL:
                $expression = $this->PartialObjectExpression();
                $identVariable = $expression->identificationVariable;
                break;

            // Subselect
            case $lookaheadType === Lexer::T_OPEN_PARENTHESIS && $peek[ 'type' ] === Lexer::T_SELECT:
                $this->match(Lexer::T_OPEN_PARENTHESIS);
                $expression = $this->Subselect();
                $this->match(Lexer::T_CLOSE_PARENTHESIS);
                break;

            // Shortcut: ScalarExpression => SimpleArithmeticExpression
            case $lookaheadType === Lexer::T_OPEN_PARENTHESIS:
            case $lookaheadType === Lexer::T_INTEGER:
            case $lookaheadType === Lexer::T_STRING:
            case $lookaheadType === Lexer::T_FLOAT:
                // SimpleArithmeticExpression : (- u.value ) or ( + u.value )
            case $lookaheadType === Lexer::T_MINUS:
            case $lookaheadType === Lexer::T_PLUS:
                $expression = $this->SimpleArithmeticExpression();
                break;

            // NewObjectExpression (New ClassName(id, name))
            case $lookaheadType === Lexer::T_NEW:
                $expression = $this->NewObjectExpression();
                break;

            default:
                $this->syntaxError(
                    'IdentificationVariable | ScalarExpression | AggregateExpression | FunctionDeclaration | PartialObjectExpression | "(" Subselect ")" | CaseExpression',
                    $this->lexer->lookahead
                );
        }

        // [["AS"] ["HIDDEN"] AliasResultVariable]
        $mustHaveAliasResultVariable = false;

        if ($this->lexer->isNextToken(Lexer::T_AS)) {
            $this->match(Lexer::T_AS);

            $mustHaveAliasResultVariable = true;
        }

        $hiddenAliasResultVariable = false;

        if ($this->lexer->isNextToken(Lexer::T_HIDDEN)) {
            $this->match(Lexer::T_HIDDEN);

            $hiddenAliasResultVariable = true;
        }

        $aliasResultVariable = null;

        if ($mustHaveAliasResultVariable || $this->lexer->isNextToken(Lexer::T_IDENTIFIER)) {
            $token = $this->lexer->lookahead;
            $aliasResultVariable = $this->AliasResultVariable();

            // Include AliasResultVariable in query components.
            $this->queryComponents[ $aliasResultVariable ] = [
                'resultVariable' => $expression,
                'nestingLevel'   => $this->nestingLevel,
                'token'          => $token,
            ];
        }

        // AST

        $expr = new AST\SelectExpression($expression, $aliasResultVariable, $hiddenAliasResultVariable);

        if ($identVariable) {
            $this->identVariableExpressions[ $identVariable ] = $expr;
        }

        return $expr;
    }

    /**
     * ScalarExpression ::= SimpleArithmeticExpression | StringPrimary | DateTimePrimary |
     *                      StateFieldPathExpression | BooleanPrimary | CaseExpression |
     *                      InstanceOfExpression
     *
     * @return AST\AggregateExpression|AST\ArithmeticFactor|AST\ArithmeticTerm|AST\CoalesceExpression|FunctionNode|AST\GeneralCaseExpression|AST\InputParameter|AST\Literal|AST\NullIfExpression|AST\ParenthesisExpression|PathExpression|AST\SimpleArithmeticExpression|AST\SimpleCaseExpression|null|string|void One of the possible expressions or subexpressions.
     */
    public function ScalarExpression()
    {
        $lookahead = $this->lexer->lookahead[ 'type' ];
        $peek = $this->lexer->glimpse();

        switch (true) {
            case $lookahead === Lexer::T_INTEGER:
            case $lookahead === Lexer::T_FLOAT:
                // SimpleArithmeticExpression : (- u.value ) or ( + u.value )  or ( - 1 ) or ( + 1 )
            case $lookahead === Lexer::T_MINUS:
            case $lookahead === Lexer::T_OPEN_PARENTHESIS:
            case $lookahead === Lexer::T_PLUS:
                return $this->SimpleArithmeticExpression();

            case $lookahead === Lexer::T_STRING:
                return $this->StringPrimary();

            case $lookahead === Lexer::T_TRUE:
            case $lookahead === Lexer::T_FALSE:
                $this->match($lookahead);

                return new AST\Literal(AST\Literal::BOOLEAN, $this->lexer->token[ 'value' ]);

            case $lookahead === Lexer::T_INPUT_PARAMETER:
                return match (true) {
                    $this->isMathOperator($peek) => $this->SimpleArithmeticExpression(),
                    default => $this->InputParameter(),
                };
                case $lookahead === Lexer::T_CASE:
                case $lookahead === Lexer::T_COALESCE:
                case $lookahead === Lexer::T_NULLIF:
                    // Since NULLIF and COALESCE can be identified as a function,
                    // we need to check these before checking for FunctionDeclaration
                    return $this->CaseExpression();

            // this check must be done before checking for a filed path expression
                case $this->isFunction():
                    $this->lexer->peek(); // "("

                    return match (true) {
                    $this->isMathOperator($this->peekBeyondClosingParenthesis()) => $this->SimpleArithmeticExpression(),
                    default => $this->FunctionDeclaration(),
                    };

            // it is no function, so it must be a field path
                    case $lookahead === Lexer::T_IDENTIFIER:
                        $this->lexer->peek();         // lookahead => '.'
                        $this->lexer->peek();         // lookahead => token after '.'
                        $peek = $this->lexer->peek(); // lookahead => token after the token after the '.'
                        $this->lexer->resetPeek();

                        if ($this->isMathOperator($peek)) {
                            return $this->SimpleArithmeticExpression();
                        }

                        return $this->StateFieldPathExpression();

                    default:
                        $this->syntaxError();
        }
    }

    /**
     * SimpleArithmeticExpression ::= ArithmeticTerm {("+" | "-") ArithmeticTerm}*
     *
     * @return AST\CoalesceExpression|AST\SimpleArithmeticExpression|AST\NullIfExpression|PathExpression|FunctionNode|AST\Literal|AST\ArithmeticTerm|string|AST\SimpleCaseExpression|AST\GeneralCaseExpression|Node|AST\InputParameter|AST\ParenthesisExpression|AST\ArithmeticFactor|null
     * @throws QueryException
     */
    public function SimpleArithmeticExpression(): AST\CoalesceExpression|AST\SimpleArithmeticExpression|AST\NullIfExpression|PathExpression|FunctionNode|AST\Literal|AST\ArithmeticTerm|string|AST\SimpleCaseExpression|AST\GeneralCaseExpression|Node|AST\InputParameter|AST\ParenthesisExpression|AST\ArithmeticFactor|null
    {
        $terms = [];
        $terms[] = $this->ArithmeticTerm();

        while (( $isPlus = $this->lexer->isNextToken(Lexer::T_PLUS) ) || $this->lexer->isNextToken(Lexer::T_MINUS)) {
            $this->match($isPlus ? Lexer::T_PLUS : Lexer::T_MINUS);

            $terms[] = $this->lexer->token[ 'value' ];
            $terms[] = $this->ArithmeticTerm();
        }

        // Phase 1 AST optimization: Prevent AST\SimpleArithmeticExpression
        // if only one AST\ArithmeticTerm is defined
        if (count($terms) === 1) {
            return $terms[ 0 ];
        }

        return new AST\SimpleArithmeticExpression($terms);
    }

    /**
     * ArithmeticTerm ::= ArithmeticFactor {("*" | "/") ArithmeticFactor}*
     *
     * @return AST\CoalesceExpression|AST\NullIfExpression|PathExpression|FunctionNode|AST\Literal|AST\ArithmeticTerm|string|AST\SimpleCaseExpression|AST\GeneralCaseExpression|AST\ParenthesisExpression|AST\InputParameter|Node|AST\ArithmeticFactor|null
     * @throws QueryException
     */
    public function ArithmeticTerm(): AST\CoalesceExpression|AST\NullIfExpression|PathExpression|FunctionNode|AST\Literal|AST\ArithmeticTerm|string|AST\SimpleCaseExpression|AST\GeneralCaseExpression|AST\ParenthesisExpression|AST\InputParameter|Node|AST\ArithmeticFactor|null
    {
        $factors = [];
        $factors[] = $this->ArithmeticFactor();

        while (( $isMult = $this->lexer->isNextToken(Lexer::T_MULTIPLY) ) || $this->lexer->isNextToken(Lexer::T_DIVIDE)) {
            $this->match($isMult ? Lexer::T_MULTIPLY : Lexer::T_DIVIDE);

            $factors[] = $this->lexer->token[ 'value' ];
            $factors[] = $this->ArithmeticFactor();
        }

        // Phase 1 AST optimization: Prevent AST\ArithmeticTerm
        // if only one AST\ArithmeticFactor is defined
        if (count($factors) === 1) {
            return $factors[ 0 ];
        }

        return new AST\ArithmeticTerm($factors);
    }

    /**
     * ArithmeticFactor ::= [("+" | "-")] ArithmeticPrimary
     *
     * @return AST\CoalesceExpression|AST\NullIfExpression|PathExpression|FunctionNode|AST\Literal|string|AST\SimpleCaseExpression|AST\GeneralCaseExpression|Node|AST\InputParameter|AST\ParenthesisExpression|AST\ArithmeticFactor|null
     * @throws QueryException
     */
    public function ArithmeticFactor(): AST\CoalesceExpression|AST\NullIfExpression|PathExpression|FunctionNode|AST\Literal|string|AST\SimpleCaseExpression|AST\GeneralCaseExpression|Node|AST\InputParameter|AST\ParenthesisExpression|AST\ArithmeticFactor|null
    {
        $sign = null;

        $isPlus = $this->lexer->isNextToken(Lexer::T_PLUS);
        if ($isPlus || $this->lexer->isNextToken(Lexer::T_MINUS)) {
            $this->match($isPlus ? Lexer::T_PLUS : Lexer::T_MINUS);
            $sign = $isPlus;
        }

        $primary = $this->ArithmeticPrimary();

        // Phase 1 AST optimization: Prevent AST\ArithmeticFactor
        // if only one AST\ArithmeticPrimary is defined
        if ($sign === null) {
            return $primary;
        }


        return new AST\ArithmeticFactor($primary, $sign);
    }

    /**
     * ArithmeticPrimary ::= SingleValuedPathExpression | Literal | ParenthesisExpression
     *          | FunctionsReturningNumerics | AggregateExpression | FunctionsReturningStrings
     *          | FunctionsReturningDatetime | IdentificationVariable | ResultVariable
     *          | InputParameter | CaseExpression
     *
     * @return AST\CoalesceExpression|AST\NullIfExpression|PathExpression|FunctionNode|AST\Literal|string|AST\SimpleCaseExpression|AST\GeneralCaseExpression|Node|AST\InputParameter|AST\ParenthesisExpression|null
     * @throws QueryException
     */
    public function ArithmeticPrimary(): AST\CoalesceExpression|AST\NullIfExpression|PathExpression|FunctionNode|AST\Literal|string|AST\SimpleCaseExpression|AST\GeneralCaseExpression|Node|AST\InputParameter|AST\ParenthesisExpression|null
    {
        if ($this->lexer->isNextToken(Lexer::T_OPEN_PARENTHESIS)) {
            $this->match(Lexer::T_OPEN_PARENTHESIS);

            $expr = $this->SimpleArithmeticExpression();

            $this->match(Lexer::T_CLOSE_PARENTHESIS);

            return new AST\ParenthesisExpression($expr);
        }

        switch ($this->lexer->lookahead[ 'type' ]) {
            case Lexer::T_COALESCE:
            case Lexer::T_NULLIF:
            case Lexer::T_CASE:
                return $this->CaseExpression();

            case Lexer::T_IDENTIFIER:
                $peek = $this->lexer->glimpse();

                if ($peek !== null && $peek[ 'value' ] === '(') {
                    return $this->FunctionDeclaration();
                }

                if ($peek !== null && $peek[ 'value' ] === '.') {
                    return $this->SingleValuedPathExpression();
                }

                if (isset($this->queryComponents[ $this->lexer->lookahead[ 'value' ] ][ 'resultVariable' ])) {
                    return $this->ResultVariable();
                }

                return $this->StateFieldPathExpression();

            case Lexer::T_INPUT_PARAMETER:
                return $this->InputParameter();

            default:
                $peek = $this->lexer->glimpse();

                if ($peek !== null && $peek[ 'value' ] === '(') {
                    return $this->FunctionDeclaration();
                }

                return $this->Literal();
        }
    }

    /**
     * CaseExpression ::= GeneralCaseExpression | SimpleCaseExpression | CoalesceExpression | NullifExpression
     * GeneralCaseExpression ::= "CASE" WhenClause {WhenClause}* "ELSE" ScalarExpression "END"
     * WhenClause ::= "WHEN" ConditionalExpression "THEN" ScalarExpression
     * SimpleCaseExpression ::= "CASE" CaseOperand SimpleWhenClause {SimpleWhenClause}* "ELSE" ScalarExpression "END"
     * CaseOperand ::= StateFieldPathExpression | TypeDiscriminator
     * SimpleWhenClause ::= "WHEN" ScalarExpression "THEN" ScalarExpression
     * CoalesceExpression ::= "COALESCE" "(" ScalarExpression {"," ScalarExpression}* ")"
     * NullifExpression ::= "NULLIF" "(" ScalarExpression "," ScalarExpression ")"
     *
     * @return AST\CoalesceExpression|AST\GeneralCaseExpression|AST\NullIfExpression|AST\SimpleCaseExpression|void One of the possible expressions or subexpressions.
     */
    public function CaseExpression()
    {
        $lookahead = $this->lexer->lookahead[ 'type' ];

        switch ($lookahead) {
            case Lexer::T_NULLIF:
                return $this->NullIfExpression();

            case Lexer::T_COALESCE:
                return $this->CoalesceExpression();

            case Lexer::T_CASE:
                $this->lexer->resetPeek();
                $peek = $this->lexer->peek();

                if ($peek[ 'type' ] === Lexer::T_WHEN) {
                    return $this->GeneralCaseExpression();
                }

                return $this->SimpleCaseExpression();

            default:
                // Do nothing
                break;
        }

        $this->syntaxError();
    }

    /**
     * NullIfExpression ::= "NULLIF" "(" ScalarExpression "," ScalarExpression ")"
     *
     * @return AST\NullIfExpression
     */
    public function NullIfExpression(): AST\NullIfExpression
    {
        $this->match(Lexer::T_NULLIF);
        $this->match(Lexer::T_OPEN_PARENTHESIS);

        $firstExpression = $this->ScalarExpression();
        $this->match(Lexer::T_COMMA);
        $secondExpression = $this->ScalarExpression();

        $this->match(Lexer::T_CLOSE_PARENTHESIS);

        return new AST\NullIfExpression($firstExpression, $secondExpression);
    }

    /**
     * CoalesceExpression ::= "COALESCE" "(" ScalarExpression {"," ScalarExpression}* ")"
     *
     * @return AST\CoalesceExpression
     */
    public function CoalesceExpression(): AST\CoalesceExpression
    {
        $this->match(Lexer::T_COALESCE);
        $this->match(Lexer::T_OPEN_PARENTHESIS);

        // Process ScalarExpressions (1..N)
        $scalarExpressions = [];
        $scalarExpressions[] = $this->ScalarExpression();

        while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
            $this->match(Lexer::T_COMMA);

            $scalarExpressions[] = $this->ScalarExpression();
        }

        $this->match(Lexer::T_CLOSE_PARENTHESIS);

        return new AST\CoalesceExpression($scalarExpressions);
    }

    /**
     * GeneralCaseExpression ::= "CASE" WhenClause {WhenClause}* "ELSE" ScalarExpression "END"
     *
     * @return AST\GeneralCaseExpression
     */
    public function GeneralCaseExpression(): AST\GeneralCaseExpression
    {
        $this->match(Lexer::T_CASE);

        // Process WhenClause (1..N)
        $whenClauses = [];

        do {
            $whenClauses[] = $this->WhenClause();
        } while ($this->lexer->isNextToken(Lexer::T_WHEN));

        $this->match(Lexer::T_ELSE);
        $scalarExpression = $this->ScalarExpression();
        $this->match(Lexer::T_END);

        return new AST\GeneralCaseExpression($whenClauses, $scalarExpression);
    }

    /**
     * WhenClause ::= "WHEN" ConditionalExpression "THEN" ScalarExpression
     *
     * @return AST\WhenClause
     */
    public function WhenClause(): AST\WhenClause
    {
        $this->match(Lexer::T_WHEN);
        $conditionalExpression = $this->ConditionalExpression();
        $this->match(Lexer::T_THEN);

        return new AST\WhenClause($conditionalExpression, $this->ScalarExpression());
    }

    /**
     * ConditionalExpression ::= ConditionalTerm {"OR" ConditionalTerm}*
     *
     * @return AST\ConditionalExpression|AST\ConditionalFactor|AST\ConditionalPrimary|AST\ConditionalTerm
     */
    public function ConditionalExpression(): AST\ConditionalExpression|AST\ConditionalFactor|AST\ConditionalTerm|AST\ConditionalPrimary
    {
        $conditionalTerms = [];
        $conditionalTerms[] = $this->ConditionalTerm();

        while ($this->lexer->isNextToken(Lexer::T_OR)) {
            $this->match(Lexer::T_OR);

            $conditionalTerms[] = $this->ConditionalTerm();
        }

        // Phase 1 AST optimization: Prevent AST\ConditionalExpression
        // if only one AST\ConditionalTerm is defined
        if (count($conditionalTerms) === 1) {
            return $conditionalTerms[ 0 ];
        }

        return new AST\ConditionalExpression($conditionalTerms);
    }

    /**
     * ConditionalTerm ::= ConditionalFactor {"AND" ConditionalFactor}*
     *
     * @return AST\ConditionalFactor|AST\ConditionalPrimary|AST\ConditionalTerm
     */
    public function ConditionalTerm(): AST\ConditionalFactor|AST\ConditionalTerm|AST\ConditionalPrimary
    {
        $conditionalFactors = [];
        $conditionalFactors[] = $this->ConditionalFactor();

        while ($this->lexer->isNextToken(Lexer::T_AND)) {
            $this->match(Lexer::T_AND);

            $conditionalFactors[] = $this->ConditionalFactor();
        }

        // Phase 1 AST optimization: Prevent AST\ConditionalTerm
        // if only one AST\ConditionalFactor is defined
        if (count($conditionalFactors) === 1) {
            return $conditionalFactors[ 0 ];
        }

        return new AST\ConditionalTerm($conditionalFactors);
    }

    /**
     * ConditionalFactor ::= ["NOT"] ConditionalPrimary
     *
     * @return AST\ConditionalFactor|AST\ConditionalPrimary
     */
    public function ConditionalFactor(): AST\ConditionalFactor|AST\ConditionalPrimary
    {
        $not = false;

        if ($this->lexer->isNextToken(Lexer::T_NOT)) {
            $this->match(Lexer::T_NOT);

            $not = true;
        }

        $conditionalPrimary = $this->ConditionalPrimary();

        // Phase 1 AST optimization: Prevent AST\ConditionalFactor
        // if only one AST\ConditionalPrimary is defined
        if (!$not) {
            return $conditionalPrimary;
        }

        $conditionalFactor = new AST\ConditionalFactor($conditionalPrimary);
        $conditionalFactor->not = true;

        return $conditionalFactor;
    }

    /**
     * ConditionalPrimary ::= SimpleConditionalExpression | "(" ConditionalExpression ")"
     *
     * @return AST\ConditionalPrimary
     */
    public function ConditionalPrimary(): AST\ConditionalPrimary
    {
        $condPrimary = new AST\ConditionalPrimary();

        if (!$this->lexer->isNextToken(Lexer::T_OPEN_PARENTHESIS)) {
            $condPrimary->simpleConditionalExpression = $this->SimpleConditionalExpression();

            return $condPrimary;
        }

        // Peek beyond the matching closing parenthesis ')'
        $peek = $this->peekBeyondClosingParenthesis();

        if ($peek !== null && (
                in_array($peek[ 'value' ], [ '=', '<', '<=', '<>', '>', '>=', '!=' ]) ||
                in_array($peek[ 'type' ], [ Lexer::T_NOT, Lexer::T_BETWEEN, Lexer::T_LIKE, Lexer::T_IN, Lexer::T_IS, Lexer::T_EXISTS ], true) ||
                $this->isMathOperator($peek)
            )
        ) {
            $condPrimary->simpleConditionalExpression = $this->SimpleConditionalExpression();

            return $condPrimary;
        }

        $this->match(Lexer::T_OPEN_PARENTHESIS);
        $condPrimary->conditionalExpression = $this->ConditionalExpression();
        $this->match(Lexer::T_CLOSE_PARENTHESIS);

        return $condPrimary;
    }

    /**
     * SimpleConditionalExpression ::=
     *      ComparisonExpression | BetweenExpression | LikeExpression |
     *      InExpression | NullComparisonExpression | ExistsExpression |
     *      EmptyCollectionComparisonExpression | CollectionMemberExpression |
     *      InstanceOfExpression
     *
     * @return AST\LikeExpression|AST\ComparisonExpression|AST\NullComparisonExpression|AST\EmptyCollectionComparisonExpression|AST\BetweenExpression|AST\InExpression|AST\CollectionMemberExpression|AST\InstanceOfExpression|ExistsExpression AST\CollectionMemberExpression|
     */
    public function SimpleConditionalExpression(): AST\LikeExpression|AST\ComparisonExpression|AST\NullComparisonExpression|AST\EmptyCollectionComparisonExpression|AST\BetweenExpression|AST\InExpression|AST\CollectionMemberExpression|AST\InstanceOfExpression|ExistsExpression
    {
        if ($this->lexer->isNextToken(Lexer::T_EXISTS)) {
            return $this->ExistsExpression();
        }

        $token = $this->lexer->lookahead;
        $peek = $this->lexer->glimpse();
        $lookahead = $token;

        if ($this->lexer->isNextToken(Lexer::T_NOT)) {
            $token = $this->lexer->glimpse();
        }

        if ($token[ 'type' ] === Lexer::T_IDENTIFIER || $token[ 'type' ] === Lexer::T_INPUT_PARAMETER || $this->isFunction()) {
            // Peek beyond the matching closing parenthesis.
            $beyond = $this->lexer->peek();

            if ($peek[ 'value' ] === '(') {
                // Peeks beyond the matched closing parenthesis.
                $token = $this->peekBeyondClosingParenthesis(false);

                if ($token[ 'type' ] === Lexer::T_NOT) {
                    $token = $this->lexer->peek();
                }

                if ($token[ 'type' ] === Lexer::T_IS) {
                    $lookahead = $this->lexer->peek();
                }
            } else {
                // Peek beyond the PathExpression or InputParameter.
                $token = $beyond;

                while ($token[ 'value' ] === '.') {
                    $this->lexer->peek();

                    $token = $this->lexer->peek();
                }

                // Also peek beyond a NOT if there is one.
                if ($token[ 'type' ] === Lexer::T_NOT) {
                    $token = $this->lexer->peek();
                }

                // We need to go even further in case of IS (differentiate between NULL and EMPTY)
                $lookahead = $this->lexer->peek();
            }

            // Also peek beyond a NOT if there is one.
            if ($lookahead[ 'type' ] === Lexer::T_NOT) {
                $lookahead = $this->lexer->peek();
            }

            $this->lexer->resetPeek();
        }

        if ($token[ 'type' ] === Lexer::T_BETWEEN) {
            return $this->BetweenExpression();
        }

        if ($token[ 'type' ] === Lexer::T_LIKE) {
            return $this->LikeExpression();
        }

        if ($token[ 'type' ] === Lexer::T_IN) {
            return $this->InExpression();
        }

        if ($token[ 'type' ] === Lexer::T_INSTANCE) {
            return $this->InstanceOfExpression();
        }

        if ($token[ 'type' ] === Lexer::T_MEMBER) {
            return $this->CollectionMemberExpression();
        }

        if ($token[ 'type' ] === Lexer::T_IS && $lookahead[ 'type' ] === Lexer::T_NULL) {
            return $this->NullComparisonExpression();
        }

        if ($token[ 'type' ] === Lexer::T_IS && $lookahead[ 'type' ] === Lexer::T_EMPTY) {
            return $this->EmptyCollectionComparisonExpression();
        }

        return $this->ComparisonExpression();
    }

    /**
     * ExistsExpression ::= ["NOT"] "EXISTS" "(" Subselect ")"
     *
     * @return ExistsExpression
     */
    public function ExistsExpression(): ExistsExpression
    {
        $not = false;

        if ($this->lexer->isNextToken(Lexer::T_NOT)) {
            $this->match(Lexer::T_NOT);
            $not = true;
        }

        $this->match(Lexer::T_EXISTS);
        $this->match(Lexer::T_OPEN_PARENTHESIS);

        $existsExpression = new AST\ExistsExpression($this->Subselect());
        $existsExpression->not = $not;

        $this->match(Lexer::T_CLOSE_PARENTHESIS);

        return $existsExpression;
    }

    /**
     * Subselect ::= SimpleSelectClause SubselectFromClause [WhereClause] [GroupByClause] [HavingClause] [OrderByClause]
     *
     * @return AST\Subselect
     */
    public function Subselect(): AST\Subselect
    {
        // Increase query nesting level
        $this->nestingLevel++;

        $subselect = new AST\Subselect($this->SimpleSelectClause(), $this->SubselectFromClause());

        $subselect->whereClause = $this->lexer->isNextToken(Lexer::T_WHERE) ? $this->WhereClause() : null;
        $subselect->groupByClause = $this->lexer->isNextToken(Lexer::T_GROUP) ? $this->GroupByClause() : null;
        $subselect->havingClause = $this->lexer->isNextToken(Lexer::T_HAVING) ? $this->HavingClause() : null;
        $subselect->orderByClause = $this->lexer->isNextToken(Lexer::T_ORDER) ? $this->OrderByClause() : null;

        // Decrease query nesting level
        $this->nestingLevel--;

        return $subselect;
    }

    /**
     * SimpleSelectClause ::= "SELECT" ["DISTINCT"] SimpleSelectExpression
     *
     * @return AST\SimpleSelectClause
     */
    public function SimpleSelectClause(): AST\SimpleSelectClause
    {
        $isDistinct = false;
        $this->match(Lexer::T_SELECT);

        if ($this->lexer->isNextToken(Lexer::T_DISTINCT)) {
            $this->match(Lexer::T_DISTINCT);

            $isDistinct = true;
        }

        return new AST\SimpleSelectClause($this->SimpleSelectExpression(), $isDistinct);
    }

    /**
     * SimpleSelectExpression ::= (
     *      StateFieldPathExpression | IdentificationVariable | FunctionDeclaration |
     *      AggregateExpression | "(" Subselect ")" | ScalarExpression
     * ) [["AS"] AliasResultVariable]
     *
     * @return AST\SimpleSelectExpression
     */
    public function SimpleSelectExpression(): AST\SimpleSelectExpression
    {
        $peek = $this->lexer->glimpse();

        switch ($this->lexer->lookahead[ 'type' ]) {
            case Lexer::T_IDENTIFIER:
                switch (true) {
                    case $peek[ 'type' ] === Lexer::T_DOT:
                        $expression = $this->StateFieldPathExpression();

                        return new AST\SimpleSelectExpression($expression);

                    case $peek[ 'type' ] !== Lexer::T_OPEN_PARENTHESIS:
                        $expression = $this->IdentificationVariable();

                        return new AST\SimpleSelectExpression($expression);

                    case $this->isFunction():
                        // SUM(u.id) + COUNT(u.id)
                        if ($this->isMathOperator($this->peekBeyondClosingParenthesis())) {
                            return new AST\SimpleSelectExpression($this->ScalarExpression());
                        }

                        // COUNT(u.id)
                        if ($this->isAggregateFunction($this->lexer->lookahead[ 'type' ])) {
                            return new AST\SimpleSelectExpression($this->AggregateExpression());
                        }

                        // IDENTITY(u)
                        return new AST\SimpleSelectExpression($this->FunctionDeclaration());

                    default:
                        // Do nothing
                }

                break;

            case Lexer::T_OPEN_PARENTHESIS:
                if ($peek[ 'type' ] !== Lexer::T_SELECT) {
                    // Shortcut: ScalarExpression => SimpleArithmeticExpression
                    $expression = $this->SimpleArithmeticExpression();

                    return new AST\SimpleSelectExpression($expression);
                }

                // Subselect
                $this->match(Lexer::T_OPEN_PARENTHESIS);
                $expression = $this->Subselect();
                $this->match(Lexer::T_CLOSE_PARENTHESIS);

                return new AST\SimpleSelectExpression($expression);

            default:
                // Do nothing
        }

        $this->lexer->peek();

        $expression = $this->ScalarExpression();
        $expr = new AST\SimpleSelectExpression($expression);

        if ($this->lexer->isNextToken(Lexer::T_AS)) {
            $this->match(Lexer::T_AS);
        }

        if ($this->lexer->isNextToken(Lexer::T_IDENTIFIER)) {
            $token = $this->lexer->lookahead;
            $resultVariable = $this->AliasResultVariable();
            $expr->fieldIdentificationVariable = $resultVariable;

            // Include AliasResultVariable in query components.
            $this->queryComponents[ $resultVariable ] = [
                'resultvariable' => $expr,
                'nestingLevel'   => $this->nestingLevel,
                'token'          => $token,
            ];
        }

        return $expr;
    }

    /**
     * StateFieldPathExpression ::= IdentificationVariable "." StateField
     *
     * @return PathExpression
     */
    public function StateFieldPathExpression(): PathExpression
    {
        return $this->PathExpression(AST\PathExpression::TYPE_STATE_FIELD);
    }

    /**
     * Parses an arbitrary path expression and defers semantical validation
     * based on expected types.
     *
     * PathExpression ::= IdentificationVariable {"." identifier}*
     *
     * @param int $expectedTypes
     *
     * @return PathExpression
     */
    public function PathExpression(int $expectedTypes): PathExpression
    {
        $identVariable = $this->IdentificationVariable();
        $field = null;

        if ($this->lexer->isNextToken(Lexer::T_DOT)) {
            $this->match(Lexer::T_DOT);
            $this->match(Lexer::T_IDENTIFIER);

            $field = $this->lexer->token[ 'value' ];

            while ($this->lexer->isNextToken(Lexer::T_DOT)) {
                $this->match(Lexer::T_DOT);
                $this->match(Lexer::T_IDENTIFIER);
                $field .= '.' . $this->lexer->token[ 'value' ];
            }
        }

        // Creating AST node
        $pathExpr = new AST\PathExpression($expectedTypes, $identVariable, $field);

        // Defer PathExpression validation if requested to be deferred
        $this->deferredPathExpressions[] = [
            'expression'   => $pathExpr,
            'nestingLevel' => $this->nestingLevel,
            'token'        => $this->lexer->token,
        ];

        return $pathExpr;
    }

    /**
     * IdentificationVariable ::= identifier
     *
     * @return string
     */
    public function IdentificationVariable(): string
    {
        $this->match(Lexer::T_IDENTIFIER);

        $identVariable = $this->lexer->token[ 'value' ];

        $this->deferredIdentificationVariables[] = [
            'expression'   => $identVariable,
            'nestingLevel' => $this->nestingLevel,
            'token'        => $this->lexer->token,
        ];

        return $identVariable;
    }

    /**
     * Checks if the next-next (after lookahead) token starts a function.
     *
     * @return bool TRUE if the next-next tokens start a function, FALSE otherwise.
     */
    private function isFunction(): bool
    {
        $lookaheadType = $this->lexer->lookahead[ 'type' ];
        $peek = $this->lexer->peek();

        $this->lexer->resetPeek();

        return $lookaheadType >= Lexer::T_IDENTIFIER && $peek !== null && $peek[ 'type' ] === Lexer::T_OPEN_PARENTHESIS;
    }

    /**
     * Checks if the given token indicates a mathematical operator.
     *
     * @psalm-param array<string, mixed>|null $token
     */
    private function isMathOperator(?array $token): bool
    {
        return $token !== null && in_array($token[ 'type' ], [ Lexer::T_PLUS, Lexer::T_MINUS, Lexer::T_DIVIDE, Lexer::T_MULTIPLY ], true);
    }

    /**
     * Peeks beyond the matched closing parenthesis and returns the first token after that one.
     *
     * @param bool $resetPeek Reset peek after finding the closing parenthesis.
     *
     * @return array|null
     */
    private function peekBeyondClosingParenthesis(bool $resetPeek = true): ?array
    {
        $token = $this->lexer->peek();
        $numUnmatched = 1;

        while ($numUnmatched > 0 && $token !== null) {
            switch ($token[ 'type' ]) {
                case Lexer::T_OPEN_PARENTHESIS:
                    ++$numUnmatched;
                    break;

                case Lexer::T_CLOSE_PARENTHESIS:
                    --$numUnmatched;
                    break;

                default:
                    // Do nothing
            }

            $token = $this->lexer->peek();
        }

        if ($resetPeek) {
            $this->lexer->resetPeek();
        }

        return $token;
    }

    /**
     * Checks whether the given token type indicates an aggregate function.
     *
     * @psalm-param Lexer::T_* $tokenType
     *
     * @return bool TRUE if the token type is an aggregate function, FALSE otherwise.
     */
    private function isAggregateFunction(int $tokenType): bool
    {
        return in_array($tokenType, [ Lexer::T_AVG, Lexer::T_MIN, Lexer::T_MAX, Lexer::T_SUM, Lexer::T_COUNT ], true);
    }

    /**
     * AggregateExpression ::=
     *  ("AVG" | "MAX" | "MIN" | "SUM" | "COUNT") "(" ["DISTINCT"] SimpleArithmeticExpression ")"
     *
     * @return AST\AggregateExpression
     */
    public function AggregateExpression(): AST\AggregateExpression
    {
        $lookaheadType = $this->lexer->lookahead[ 'type' ];
        $isDistinct = false;

        if (!in_array($lookaheadType, [ Lexer::T_COUNT, Lexer::T_AVG, Lexer::T_MAX, Lexer::T_MIN, Lexer::T_SUM ], true)) {
            $this->syntaxError('One of: MAX, MIN, AVG, SUM, COUNT');
        }

        $this->match($lookaheadType);
        $functionName = $this->lexer->token[ 'value' ];
        $this->match(Lexer::T_OPEN_PARENTHESIS);

        if ($this->lexer->isNextToken(Lexer::T_DISTINCT)) {
            $this->match(Lexer::T_DISTINCT);
            $isDistinct = true;
        }

        $pathExp = $this->SimpleArithmeticExpression();

        $this->match(Lexer::T_CLOSE_PARENTHESIS);

        return new AST\AggregateExpression($functionName, $pathExp, $isDistinct);
    }

    /**
     * FunctionDeclaration ::= FunctionsReturningStrings | FunctionsReturningNumerics | FunctionsReturningDatetime
     *
     * @return FunctionNode|null
     * @throws QueryException
     */
    public function FunctionDeclaration(): ?FunctionNode
    {
        $token = $this->lexer->lookahead;
        $funcName = strtolower($token[ 'value' ]);

        $customFunctionDeclaration = $this->CustomFunctionDeclaration();

        // Check for custom functions functions first!
        switch (true) {
            case $customFunctionDeclaration !== null:
                return $customFunctionDeclaration;

            case isset(self::$stringFunctions[ $funcName ]):
                return $this->FunctionsReturningStrings();

            case isset(self::$numericFunctions[ $funcName ]):
                return $this->FunctionsReturningNumerics();

            case isset(self::$datetimeFunctions[ $funcName ]):
                return $this->FunctionsReturningDatetime();

            default:
                $this->syntaxError('known function', $token);
        }
    }

    /**
     * Helper function for FunctionDeclaration grammar rule.
     */
    private function CustomFunctionDeclaration(): ?FunctionNode
    {
        $token = $this->lexer->lookahead;
        $funcName = strtolower($token[ 'value' ]);

        // Check for custom functions afterwards
        $config = $this->em->getConfiguration();

        return match (true) {
            $config->getCustomStringFunction($funcName) !== null => $this->CustomFunctionsReturningStrings(),
            $config->getCustomNumericFunction($funcName) !== null => $this->CustomFunctionsReturningNumerics(),
            $config->getCustomDatetimeFunction($funcName) !== null => $this->CustomFunctionsReturningDatetime(),
            default => null,
        };
    }

    /**
     * @return FunctionNode
     */
    public function CustomFunctionsReturningStrings(): FunctionNode
    {
        // getCustomStringFunction is case-insensitive
        $functionName = $this->lexer->lookahead[ 'value' ];
        $functionClass = $this->em->getConfiguration()->getCustomStringFunction($functionName);

        assert($functionClass !== null);

        $function = is_string($functionClass)
            ? new $functionClass($functionName)
            : $functionClass($functionName);

        $function->parse($this);

        return $function;
    }

    /**
     * @return FunctionNode
     */
    public function CustomFunctionsReturningNumerics(): FunctionNode
    {
        // getCustomNumericFunction is case-insensitive
        $functionName = strtolower($this->lexer->lookahead[ 'value' ]);
        $functionClass = $this->em->getConfiguration()->getCustomNumericFunction($functionName);

        assert($functionClass !== null);

        $function = is_string($functionClass)
            ? new $functionClass($functionName)
            : $functionClass($functionName);

        $function->parse($this);

        return $function;
    }

    /**
     * @return FunctionNode
     */
    public function CustomFunctionsReturningDatetime(): FunctionNode
    {
        // getCustomDatetimeFunction is case-insensitive
        $functionName = $this->lexer->lookahead[ 'value' ];
        $functionClass = $this->em->getConfiguration()->getCustomDatetimeFunction($functionName);

        assert($functionClass !== null);

        $function = is_string($functionClass)
            ? new $functionClass($functionName)
            : $functionClass($functionName);

        $function->parse($this);

        return $function;
    }

    /**
     * FunctionsReturningStrings ::=
     *   "CONCAT" "(" StringPrimary "," StringPrimary {"," StringPrimary}* ")" |
     *   "SUBSTRING" "(" StringPrimary "," SimpleArithmeticExpression "," SimpleArithmeticExpression ")" |
     *   "TRIM" "(" [["LEADING" | "TRAILING" | "BOTH"] [char] "FROM"] StringPrimary ")" |
     *   "LOWER" "(" StringPrimary ")" |
     *   "UPPER" "(" StringPrimary ")" |
     *   "IDENTITY" "(" SingleValuedAssociationPathExpression {"," string} ")"
     *
     * @return FunctionNode
     */
    public function FunctionsReturningStrings(): FunctionNode
    {
        $funcNameLower = strtolower($this->lexer->lookahead[ 'value' ]);
        $funcClass = self::$stringFunctions[ $funcNameLower ];

        $function = new $funcClass($funcNameLower);
        $function->parse($this);

        return $function;
    }

    /**
     * FunctionsReturningNumerics ::=
     *      "LENGTH" "(" StringPrimary ")" |
     *      "LOCATE" "(" StringPrimary "," StringPrimary ["," SimpleArithmeticExpression]")" |
     *      "ABS" "(" SimpleArithmeticExpression ")" |
     *      "SQRT" "(" SimpleArithmeticExpression ")" |
     *      "MOD" "(" SimpleArithmeticExpression "," SimpleArithmeticExpression ")" |
     *      "SIZE" "(" CollectionValuedPathExpression ")" |
     *      "DATE_DIFF" "(" ArithmeticPrimary "," ArithmeticPrimary ")" |
     *      "BIT_AND" "(" ArithmeticPrimary "," ArithmeticPrimary ")" |
     *      "BIT_OR" "(" ArithmeticPrimary "," ArithmeticPrimary ")"
     *
     * @return FunctionNode
     */
    public function FunctionsReturningNumerics(): FunctionNode
    {
        $funcNameLower = strtolower($this->lexer->lookahead[ 'value' ]);
        $funcClass = self::$numericFunctions[ $funcNameLower ];

        $function = new $funcClass($funcNameLower);
        $function->parse($this);

        return $function;
    }

    /**
     * FunctionsReturningDateTime ::=
     *     "CURRENT_DATE" |
     *     "CURRENT_TIME" |
     *     "CURRENT_TIMESTAMP" |
     *     "DATE_ADD" "(" ArithmeticPrimary "," ArithmeticPrimary "," StringPrimary ")" |
     *     "DATE_SUB" "(" ArithmeticPrimary "," ArithmeticPrimary "," StringPrimary ")"
     *
     * @return FunctionNode
     */
    public function FunctionsReturningDatetime(): FunctionNode
    {
        $funcNameLower = strtolower($this->lexer->lookahead[ 'value' ]);
        $funcClass = self::$datetimeFunctions[ $funcNameLower ];

        $function = new $funcClass($funcNameLower);
        $function->parse($this);

        return $function;
    }

    /**
     * AliasResultVariable ::= identifier
     *
     * @return string
     */
    public function AliasResultVariable(): string
    {
        $this->match(Lexer::T_IDENTIFIER);

        $resultVariable = $this->lexer->token[ 'value' ];
        $exists = isset($this->queryComponents[ $resultVariable ]);

        if ($exists) {
            $this->semanticalError(
                sprintf("'%s' is already defined.", $resultVariable),
                $this->lexer->token
            );
        }

        return $resultVariable;
    }

    /**
     * Generates a new semantical error.
     *
     * @param string     $message Optional message.
     * @param array|null $token   Optional token.
     * @psalm-param array<string, mixed>|null $token
     *
     * @return void
     *
     * @throws QueryException
     */
    public function semanticalError(string $message = '', array $token = null): void
    {
        if ($token === null) {
            $token = $this->lexer->lookahead ?? [ 'position' => 0 ];
        }

        // Minimum exposed chars ahead of token
        $distance = 12;

        // Find a position of a final word to display in error string
        $dql = $this->query->getDQL();
        $length = strlen($dql);
        $pos = $token[ 'position' ] + $distance;
        $pos = strpos($dql, ' ', $length > $pos ? $pos : $length);
        $length = $pos !== false ? $pos - $token[ 'position' ] : $distance;

        $tokenPos = $token[ 'position' ] > 0 ? $token[ 'position' ] : '-1';
        $tokenStr = substr($dql, $token[ 'position' ], $length);

        // Building informative message
        $message = 'line 0, col ' . $tokenPos . " near '" . $tokenStr . "': Error: " . $message;

        throw QueryException::semanticalError($message, QueryException::dqlError($this->query->getDQL()));
    }

    /**
     * SubselectFromClause ::= "FROM" SubselectIdentificationVariableDeclaration {"," SubselectIdentificationVariableDeclaration}*
     *
     * @return AST\SubselectFromClause
     */
    public function SubselectFromClause(): AST\SubselectFromClause
    {
        $this->match(Lexer::T_FROM);

        $identificationVariables = [];
        $identificationVariables[] = $this->SubselectIdentificationVariableDeclaration();

        while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
            $this->match(Lexer::T_COMMA);

            $identificationVariables[] = $this->SubselectIdentificationVariableDeclaration();
        }

        return new AST\SubselectFromClause($identificationVariables);
    }

    /**
     * SubselectIdentificationVariableDeclaration ::= IdentificationVariableDeclaration
     *
     * {Internal note: WARNING: Solution is harder than a bare implementation.
     * Desired EBNF support:
     *
     * SubselectIdentificationVariableDeclaration ::= IdentificationVariableDeclaration | (AssociationPathExpression ["AS"] AliasIdentificationVariable)
     *
     * It demands that entire SQL generation to become programmatical. This is
     * needed because association based subselect requires "WHERE" conditional
     * expressions to be injected, but there is no scope to do that. Only scope
     * accessible is "FROM", prohibiting an easy implementation without larger
     * changes.}
     *
     * @return AST\IdentificationVariableDeclaration
     */
    public function SubselectIdentificationVariableDeclaration(): AST\IdentificationVariableDeclaration
    {
        /*
        NOT YET IMPLEMENTED!

        $glimpse = $this->lexer->glimpse();

        if ($glimpse['type'] == Lexer::T_DOT) {
            $associationPathExpression = $this->AssociationPathExpression();

            if ($this->lexer->isNextToken(Lexer::T_AS)) {
                $this->match(Lexer::T_AS);
            }

            $aliasIdentificationVariable = $this->AliasIdentificationVariable();
            $identificationVariable      = $associationPathExpression->identificationVariable;
            $field                       = $associationPathExpression->associationField;

            $class       = $this->queryComponents[$identificationVariable]['metadata'];
            $targetClass = $this->em->getClassMetadata($class->associationMappings[$field]['targetEntity']);

            // Building queryComponent
            $joinQueryComponent = array(
                'metadata'     => $targetClass,
                'parent'       => $identificationVariable,
                'relation'     => $class->getAssociationMapping($field),
                'map'          => null,
                'nestingLevel' => $this->nestingLevel,
                'token'        => $this->lexer->lookahead
            );

            $this->queryComponents[$aliasIdentificationVariable] = $joinQueryComponent;

            return new AST\SubselectIdentificationVariableDeclaration(
                $associationPathExpression, $aliasIdentificationVariable
            );
        }
        */

        return $this->IdentificationVariableDeclaration();
    }

    /**
     * IdentificationVariableDeclaration ::= RangeVariableDeclaration [IndexBy] {Join}*
     *
     * @return AST\IdentificationVariableDeclaration
     */
    public function IdentificationVariableDeclaration(): AST\IdentificationVariableDeclaration
    {
        $joins = [];
        $rangeVariableDeclaration = $this->RangeVariableDeclaration();
        $indexBy = $this->lexer->isNextToken(Lexer::T_INDEX)
            ? $this->IndexBy()
            : null;

        $rangeVariableDeclaration->isRoot = true;

        while ($this->lexer->isNextToken(Lexer::T_LEFT) ||
            $this->lexer->isNextToken(Lexer::T_INNER) ||
            $this->lexer->isNextToken(Lexer::T_JOIN)
        ) {
            $joins[] = $this->Join();
        }

        return new AST\IdentificationVariableDeclaration(
            $rangeVariableDeclaration,
            $indexBy,
            $joins
        );
    }

    /**
     * RangeVariableDeclaration ::= AbstractSchemaName ["AS"] AliasIdentificationVariable
     *
     * @return AST\RangeVariableDeclaration
     *
     * @throws QueryException
     */
    public function RangeVariableDeclaration(): AST\RangeVariableDeclaration
    {
        if ($this->lexer->isNextToken(Lexer::T_OPEN_PARENTHESIS) && $this->lexer->glimpse()[ 'type' ] === Lexer::T_SELECT) {
            $this->semanticalError('Subquery is not supported here', $this->lexer->token);
        }

        $abstractSchemaName = $this->AbstractSchemaName();

        $this->validateAbstractSchemaName($abstractSchemaName);

        if ($this->lexer->isNextToken(Lexer::T_AS)) {
            $this->match(Lexer::T_AS);
        }

        $token = $this->lexer->lookahead;
        $aliasIdentificationVariable = $this->AliasIdentificationVariable();
        $classMetadata = $this->em->getClassMetadata($abstractSchemaName);

        // Building queryComponent
        $queryComponent = [
            'metadata'     => $classMetadata,
            'parent'       => null,
            'relation'     => null,
            'map'          => null,
            'nestingLevel' => $this->nestingLevel,
            'token'        => $token,
        ];

        $this->queryComponents[ $aliasIdentificationVariable ] = $queryComponent;

        return new AST\RangeVariableDeclaration($abstractSchemaName, $aliasIdentificationVariable);
    }

    /**
     * AbstractSchemaName ::= fully_qualified_name | aliased_name | identifier
     *
     * @return string
     */
    public function AbstractSchemaName(): string
    {
        if ($this->lexer->isNextToken(Lexer::T_FULLY_QUALIFIED_NAME)) {
            $this->match(Lexer::T_FULLY_QUALIFIED_NAME);

            return $this->lexer->token[ 'value' ];
        }

        if ($this->lexer->isNextToken(Lexer::T_IDENTIFIER)) {
            $this->match(Lexer::T_IDENTIFIER);

            return $this->lexer->token[ 'value' ];
        }

        $this->match(Lexer::T_ALIASED_NAME);

        [ $namespaceAlias, $simpleClassName ] = explode(':', $this->lexer->token[ 'value' ]);

        return $this->em->getConfiguration()->getEntityNamespace($namespaceAlias) . '\\' . $simpleClassName;
    }

    /**
     * Validates an AbstractSchemaName, making sure the class exists.
     *
     * @param string $schemaName The name to validate.
     *
     * @throws QueryException if the name does not exist.
     */
    private function validateAbstractSchemaName(string $schemaName): void
    {
        if (!( class_exists($schemaName) || interface_exists($schemaName) )) {
            $this->semanticalError(
                sprintf("Class '%s' is not defined.", $schemaName),
                $this->lexer->token
            );
        }
    }

    /**
     * AliasIdentificationVariable = identifier
     *
     * @return string
     */
    public function AliasIdentificationVariable(): string
    {
        $this->match(Lexer::T_IDENTIFIER);

        $aliasIdentVariable = $this->lexer->token[ 'value' ];
        $exists = isset($this->queryComponents[ $aliasIdentVariable ]);

        if ($exists) {
            $this->semanticalError(
                sprintf("'%s' is already defined.", $aliasIdentVariable),
                $this->lexer->token
            );
        }

        return $aliasIdentVariable;
    }

    /**
     * IndexBy ::= "INDEX" "BY" SingleValuedPathExpression
     *
     * @return AST\IndexBy
     */
    public function IndexBy(): AST\IndexBy
    {
        $this->match(Lexer::T_INDEX);
        $this->match(Lexer::T_BY);
        $pathExpr = $this->SingleValuedPathExpression();

        // Add the INDEX BY info to the query component
        $this->queryComponents[ $pathExpr->identificationVariable ][ 'map' ] = $pathExpr->field;

        return new AST\IndexBy($pathExpr);
    }

    /**
     * SingleValuedPathExpression ::= StateFieldPathExpression | SingleValuedAssociationPathExpression
     *
     * @return PathExpression
     */
    public function SingleValuedPathExpression(): PathExpression
    {
        return $this->PathExpression(
            AST\PathExpression::TYPE_STATE_FIELD |
            AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION
        );
    }

    /**
     * Join ::= ["LEFT" ["OUTER"] | "INNER"] "JOIN"
     *          (JoinAssociationDeclaration | RangeVariableDeclaration)
     *          ["WITH" ConditionalExpression]
     *
     * @return AST\Join
     */
    public function Join(): AST\Join
    {
        // Check Join type
        $joinType = AST\Join::JOIN_TYPE_INNER;

        switch (true) {
            case $this->lexer->isNextToken(Lexer::T_LEFT):
                $this->match(Lexer::T_LEFT);

                $joinType = AST\Join::JOIN_TYPE_LEFT;

                // Possible LEFT OUTER join
                if ($this->lexer->isNextToken(Lexer::T_OUTER)) {
                    $this->match(Lexer::T_OUTER);

                    $joinType = AST\Join::JOIN_TYPE_LEFTOUTER;
                }

                break;

            case $this->lexer->isNextToken(Lexer::T_INNER):
                $this->match(Lexer::T_INNER);
                break;

            default:
                // Do nothing
        }

        $this->match(Lexer::T_JOIN);

        $next = $this->lexer->glimpse();
        $joinDeclaration = $next[ 'type' ] === Lexer::T_DOT ? $this->JoinAssociationDeclaration() : $this->RangeVariableDeclaration();
        $adhocConditions = $this->lexer->isNextToken(Lexer::T_WITH);
        $join = new AST\Join($joinType, $joinDeclaration);

        // Describe non-root join declaration
        if ($joinDeclaration instanceof AST\RangeVariableDeclaration) {
            $joinDeclaration->isRoot = false;
        }

        // Check for ad-hoc Join conditions
        if ($adhocConditions) {
            $this->match(Lexer::T_WITH);

            $join->conditionalExpression = $this->ConditionalExpression();
        }

        return $join;
    }

    /**
     * JoinAssociationDeclaration ::= JoinAssociationPathExpression ["AS"] AliasIdentificationVariable [IndexBy]
     *
     * @return AST\JoinAssociationDeclaration
     */
    public function JoinAssociationDeclaration(): AST\JoinAssociationDeclaration
    {
        $joinAssociationPathExpression = $this->JoinAssociationPathExpression();

        if ($this->lexer->isNextToken(Lexer::T_AS)) {
            $this->match(Lexer::T_AS);
        }

        $aliasIdentificationVariable = $this->AliasIdentificationVariable();
        $indexBy = $this->lexer->isNextToken(Lexer::T_INDEX) ? $this->IndexBy() : null;

        $identificationVariable = $joinAssociationPathExpression->identificationVariable;
        $field = $joinAssociationPathExpression->associationField;

        $class = $this->queryComponents[ $identificationVariable ][ 'metadata' ];
        $targetClass = $this->em->getClassMetadata($class->associationMappings[ $field ][ 'targetEntity' ]);

        // Building queryComponent
        $joinQueryComponent = [
            'metadata'     => $targetClass,
            'parent'       => $joinAssociationPathExpression->identificationVariable,
            'relation'     => $class->getAssociationMapping($field),
            'map'          => null,
            'nestingLevel' => $this->nestingLevel,
            'token'        => $this->lexer->lookahead,
        ];

        $this->queryComponents[ $aliasIdentificationVariable ] = $joinQueryComponent;

        return new AST\JoinAssociationDeclaration($joinAssociationPathExpression, $aliasIdentificationVariable, $indexBy);
    }

    /**
     * JoinAssociationPathExpression ::= IdentificationVariable "." (CollectionValuedAssociationField | SingleValuedAssociationField)
     *
     * @return JoinAssociationPathExpression
     */
    public function JoinAssociationPathExpression(): JoinAssociationPathExpression
    {
        $identVariable = $this->IdentificationVariable();

        if (!isset($this->queryComponents[ $identVariable ])) {
            $this->semanticalError(
                'Identification Variable ' . $identVariable . ' used in join path expression but was not defined before.'
            );
        }

        $this->match(Lexer::T_DOT);
        $this->match(Lexer::T_IDENTIFIER);

        $field = $this->lexer->token[ 'value' ];

        // Validate association field
        $qComp = $this->queryComponents[ $identVariable ];
        $class = $qComp[ 'metadata' ];

        if (!$class->hasAssociation($field)) {
            $this->semanticalError('Class ' . $class->name . ' has no association named ' . $field);
        }

        return new AST\JoinAssociationPathExpression($identVariable, $field);
    }

    /**
     * WhereClause ::= "WHERE" ConditionalExpression
     *
     * @return AST\WhereClause
     */
    public function WhereClause(): AST\WhereClause
    {
        $this->match(Lexer::T_WHERE);

        return new AST\WhereClause($this->ConditionalExpression());
    }

    /**
     * GroupByClause ::= "GROUP" "BY" GroupByItem {"," GroupByItem}*
     *
     * @return AST\GroupByClause
     */
    public function GroupByClause(): AST\GroupByClause
    {
        $this->match(Lexer::T_GROUP);
        $this->match(Lexer::T_BY);

        $groupByItems = [ $this->GroupByItem() ];

        while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
            $this->match(Lexer::T_COMMA);

            $groupByItems[] = $this->GroupByItem();
        }

        return new AST\GroupByClause($groupByItems);
    }

    /**
     * GroupByItem ::= IdentificationVariable | ResultVariable | SingleValuedPathExpression
     *
     * @return string|PathExpression
     */
    public function GroupByItem(): PathExpression|string
    {
        // We need to check if we are in a IdentificationVariable or SingleValuedPathExpression
        $glimpse = $this->lexer->glimpse();

        if ($glimpse !== null && $glimpse[ 'type' ] === Lexer::T_DOT) {
            return $this->SingleValuedPathExpression();
        }

        // Still need to decide between IdentificationVariable or ResultVariable
        $lookaheadValue = $this->lexer->lookahead[ 'value' ];

        if (!isset($this->queryComponents[ $lookaheadValue ])) {
            $this->semanticalError('Cannot group by undefined identification or result variable.');
        }

        return isset($this->queryComponents[ $lookaheadValue ][ 'metadata' ])
            ? $this->IdentificationVariable()
            : $this->ResultVariable();
    }

    /**
     * ResultVariable ::= identifier
     *
     * @return string
     */
    public function ResultVariable(): string
    {
        $this->match(Lexer::T_IDENTIFIER);

        $resultVariable = $this->lexer->token[ 'value' ];

        // Defer ResultVariable validation
        $this->deferredResultVariables[] = [
            'expression'   => $resultVariable,
            'nestingLevel' => $this->nestingLevel,
            'token'        => $this->lexer->token,
        ];

        return $resultVariable;
    }

    /**
     * HavingClause ::= "HAVING" ConditionalExpression
     *
     * @return AST\HavingClause
     */
    public function HavingClause(): AST\HavingClause
    {
        $this->match(Lexer::T_HAVING);

        return new AST\HavingClause($this->ConditionalExpression());
    }

    /**
     * OrderByClause ::= "ORDER" "BY" OrderByItem {"," OrderByItem}*
     *
     * @return AST\OrderByClause
     */
    public function OrderByClause(): AST\OrderByClause
    {
        $this->match(Lexer::T_ORDER);
        $this->match(Lexer::T_BY);

        $orderByItems = [];
        $orderByItems[] = $this->OrderByItem();

        while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
            $this->match(Lexer::T_COMMA);

            $orderByItems[] = $this->OrderByItem();
        }

        return new AST\OrderByClause($orderByItems);
    }

    /**
     * OrderByItem ::= (
     *      SimpleArithmeticExpression | SingleValuedPathExpression | CaseExpression |
     *      ScalarExpression | ResultVariable | FunctionDeclaration
     * ) ["ASC" | "DESC"]
     *
     * @return AST\OrderByItem
     */
    public function OrderByItem(): AST\OrderByItem
    {
        $this->lexer->peek(); // lookahead => '.'
        $this->lexer->peek(); // lookahead => token after '.'

        $peek = $this->lexer->peek(); // lookahead => token after the token after the '.'

        $this->lexer->resetPeek();

        $glimpse = $this->lexer->glimpse();

        $expr = match (true) {
            $this->isMathOperator($peek) => $this->SimpleArithmeticExpression(),
            $glimpse !== null && $glimpse[ 'type' ] === Lexer::T_DOT => $this->SingleValuedPathExpression(),
            $this->lexer->peek() && $this->isMathOperator(
                $this->peekBeyondClosingParenthesis()
            ) => $this->ScalarExpression(),
            $this->lexer->lookahead[ 'type' ] === Lexer::T_CASE => $this->CaseExpression(),
            $this->isFunction() => $this->FunctionDeclaration(),
            default => $this->ResultVariable(),
        };

        $type = 'ASC';
        $item = new AST\OrderByItem($expr);

        switch (true) {
            case $this->lexer->isNextToken(Lexer::T_DESC):
                $this->match(Lexer::T_DESC);
                $type = 'DESC';
                break;

            case $this->lexer->isNextToken(Lexer::T_ASC):
                $this->match(Lexer::T_ASC);
                break;

            default:
                // Do nothing
        }

        $item->type = $type;

        return $item;
    }

    /**
     * BetweenExpression ::= ArithmeticExpression ["NOT"] "BETWEEN" ArithmeticExpression "AND" ArithmeticExpression
     *
     * @return AST\BetweenExpression
     */
    public function BetweenExpression(): AST\BetweenExpression
    {
        $not = false;
        $arithExpr1 = $this->ArithmeticExpression();

        if ($this->lexer->isNextToken(Lexer::T_NOT)) {
            $this->match(Lexer::T_NOT);
            $not = true;
        }

        $this->match(Lexer::T_BETWEEN);
        $arithExpr2 = $this->ArithmeticExpression();
        $this->match(Lexer::T_AND);
        $arithExpr3 = $this->ArithmeticExpression();

        $betweenExpr = new AST\BetweenExpression($arithExpr1, $arithExpr2, $arithExpr3);
        $betweenExpr->not = $not;

        return $betweenExpr;
    }

    /**
     * ArithmeticExpression ::= SimpleArithmeticExpression | "(" Subselect ")"
     *
     * @return AST\ArithmeticExpression
     */
    public function ArithmeticExpression(): AST\ArithmeticExpression
    {
        $expr = new AST\ArithmeticExpression();

        if ($this->lexer->isNextToken(Lexer::T_OPEN_PARENTHESIS)) {
            $peek = $this->lexer->glimpse();

            if ($peek[ 'type' ] === Lexer::T_SELECT) {
                $this->match(Lexer::T_OPEN_PARENTHESIS);
                $expr->subselect = $this->Subselect();
                $this->match(Lexer::T_CLOSE_PARENTHESIS);

                return $expr;
            }
        }

        $expr->simpleArithmeticExpression = $this->SimpleArithmeticExpression();

        return $expr;
    }

    /**
     * LikeExpression ::= StringExpression ["NOT"] "LIKE" StringPrimary ["ESCAPE" char]
     *
     * @return AST\LikeExpression
     */
    public function LikeExpression(): AST\LikeExpression
    {
        $stringExpr = $this->StringExpression();
        $not = false;

        if ($this->lexer->isNextToken(Lexer::T_NOT)) {
            $this->match(Lexer::T_NOT);
            $not = true;
        }

        $this->match(Lexer::T_LIKE);

        if ($this->lexer->isNextToken(Lexer::T_INPUT_PARAMETER)) {
            $this->match(Lexer::T_INPUT_PARAMETER);
            $stringPattern = new AST\InputParameter($this->lexer->token[ 'value' ]);
        } else {
            $stringPattern = $this->StringPrimary();
        }

        $escapeChar = null;

        if ($this->lexer->lookahead !== null && $this->lexer->lookahead[ 'type' ] === Lexer::T_ESCAPE) {
            $this->match(Lexer::T_ESCAPE);
            $this->match(Lexer::T_STRING);

            $escapeChar = new AST\Literal(AST\Literal::STRING, $this->lexer->token[ 'value' ]);
        }

        $likeExpr = new AST\LikeExpression($stringExpr, $stringPattern, $escapeChar);
        $likeExpr->not = $not;

        return $likeExpr;
    }

    /**
     * StringExpression ::= StringPrimary | ResultVariable | "(" Subselect ")"
     *
     * @return AST\CoalesceExpression|AST\NullIfExpression|AST\Subselect|PathExpression|FunctionNode|AST\Literal|string|AST\SimpleCaseExpression|AST\GeneralCaseExpression|AST\InputParameter|Node|AST\AggregateExpression|null
     * @throws QueryException
     */
    public function StringExpression(): AST\CoalesceExpression|AST\NullIfExpression|AST\Subselect|PathExpression|FunctionNode|AST\Literal|string|AST\SimpleCaseExpression|AST\GeneralCaseExpression|AST\InputParameter|Node|AST\AggregateExpression|null
    {
        $peek = $this->lexer->glimpse();

        // Subselect
        if ($peek[ 'type' ] === Lexer::T_SELECT && $this->lexer->isNextToken(Lexer::T_OPEN_PARENTHESIS)) {
            $this->match(Lexer::T_OPEN_PARENTHESIS);
            $expr = $this->Subselect();
            $this->match(Lexer::T_CLOSE_PARENTHESIS);

            return $expr;
        }

        // ResultVariable (string)
        if (isset($this->queryComponents[ $this->lexer->lookahead[ 'value' ] ][ 'resultVariable' ]) &&
            $this->lexer->isNextToken(Lexer::T_IDENTIFIER)
        ) {
            return $this->ResultVariable();
        }

        return $this->StringPrimary();
    }

    /**
     * StringPrimary ::= StateFieldPathExpression | string | InputParameter | FunctionsReturningStrings | AggregateExpression | CaseExpression
     *
     * @return AST\CoalesceExpression|AST\NullIfExpression|PathExpression|FunctionNode|AST\Literal|AST\SimpleCaseExpression|AST\GeneralCaseExpression|AST\InputParameter|Node|AST\AggregateExpression|null
     * @throws QueryException
     */
    public function StringPrimary(): AST\CoalesceExpression|AST\NullIfExpression|PathExpression|FunctionNode|AST\Literal|AST\SimpleCaseExpression|AST\GeneralCaseExpression|AST\InputParameter|Node|AST\AggregateExpression|null
    {
        $lookaheadType = $this->lexer->lookahead[ 'type' ];

        switch ($lookaheadType) {
            case Lexer::T_IDENTIFIER:
                $peek = $this->lexer->glimpse();

                if ($peek[ 'value' ] === '.') {
                    return $this->StateFieldPathExpression();
                }

                if ($peek[ 'value' ] === '(') {
                    // do NOT directly go to FunctionsReturningString() because it doesn't check for custom functions.
                    return $this->FunctionDeclaration();
                }

                $this->syntaxError("'.' or '('");

            case Lexer::T_STRING:
                $this->match(Lexer::T_STRING);

                return new AST\Literal(AST\Literal::STRING, $this->lexer->token[ 'value' ]);

            case Lexer::T_INPUT_PARAMETER:
                return $this->InputParameter();

            case Lexer::T_CASE:
            case Lexer::T_COALESCE:
            case Lexer::T_NULLIF:
                return $this->CaseExpression();

            default:
                if ($this->isAggregateFunction($lookaheadType)) {
                    return $this->AggregateExpression();
                }
        }

        $this->syntaxError(
            'StateFieldPathExpression | string | InputParameter | FunctionsReturningStrings | AggregateExpression'
        );
    }

    /**
     * InputParameter ::= PositionalParameter | NamedParameter
     *
     * @return AST\InputParameter
     */
    public function InputParameter(): AST\InputParameter
    {
        $this->match(Lexer::T_INPUT_PARAMETER);

        return new AST\InputParameter($this->lexer->token[ 'value' ]);
    }

    /**
     * InExpression ::= SingleValuedPathExpression ["NOT"] "IN" "(" (InParameter {"," InParameter}* | Subselect) ")"
     *
     * @return AST\InExpression
     */
    public function InExpression(): AST\InExpression
    {
        $inExpression = new AST\InExpression($this->ArithmeticExpression());

        if ($this->lexer->isNextToken(Lexer::T_NOT)) {
            $this->match(Lexer::T_NOT);
            $inExpression->not = true;
        }

        $this->match(Lexer::T_IN);
        $this->match(Lexer::T_OPEN_PARENTHESIS);

        if ($this->lexer->isNextToken(Lexer::T_SELECT)) {
            $inExpression->subselect = $this->Subselect();
        } else {
            $literals = [];
            $literals[] = $this->InParameter();

            while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
                $this->match(Lexer::T_COMMA);
                $literals[] = $this->InParameter();
            }

            $inExpression->literals = $literals;
        }

        $this->match(Lexer::T_CLOSE_PARENTHESIS);

        return $inExpression;
    }

    /**
     * InParameter ::= Literal | InputParameter
     *
     * @return AST\InputParameter|AST\Literal
     */
    public function InParameter(): AST\Literal|AST\InputParameter
    {
        if ($this->lexer->lookahead[ 'type' ] === Lexer::T_INPUT_PARAMETER) {
            return $this->InputParameter();
        }

        return $this->Literal();
    }

    /**
     * Literal ::= string | char | integer | float | boolean
     *
     * @return AST\Literal
     */
    public function Literal(): AST\Literal
    {
        switch ($this->lexer->lookahead[ 'type' ]) {
            case Lexer::T_STRING:
                $this->match(Lexer::T_STRING);

                return new AST\Literal(AST\Literal::STRING, $this->lexer->token[ 'value' ]);

            case Lexer::T_INTEGER:
            case Lexer::T_FLOAT:
                $this->match(
                    $this->lexer->isNextToken(Lexer::T_INTEGER) ? Lexer::T_INTEGER : Lexer::T_FLOAT
                );

                return new AST\Literal(AST\Literal::NUMERIC, $this->lexer->token[ 'value' ]);

            case Lexer::T_TRUE:
            case Lexer::T_FALSE:
                $this->match(
                    $this->lexer->isNextToken(Lexer::T_TRUE) ? Lexer::T_TRUE : Lexer::T_FALSE
                );

                return new AST\Literal(AST\Literal::BOOLEAN, $this->lexer->token[ 'value' ]);

            default:
                $this->syntaxError('Literal');
        }
    }

    /**
     * InstanceOfExpression ::= IdentificationVariable ["NOT"] "INSTANCE" ["OF"] (InstanceOfParameter | "(" InstanceOfParameter {"," InstanceOfParameter}* ")")
     *
     * @return AST\InstanceOfExpression
     */
    public function InstanceOfExpression(): AST\InstanceOfExpression
    {
        $instanceOfExpression = new AST\InstanceOfExpression($this->IdentificationVariable());

        if ($this->lexer->isNextToken(Lexer::T_NOT)) {
            $this->match(Lexer::T_NOT);
            $instanceOfExpression->not = true;
        }

        $this->match(Lexer::T_INSTANCE);
        $this->match(Lexer::T_OF);

        $exprValues = [];

        if ($this->lexer->isNextToken(Lexer::T_OPEN_PARENTHESIS)) {
            $this->match(Lexer::T_OPEN_PARENTHESIS);

            $exprValues[] = $this->InstanceOfParameter();

            while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
                $this->match(Lexer::T_COMMA);

                $exprValues[] = $this->InstanceOfParameter();
            }

            $this->match(Lexer::T_CLOSE_PARENTHESIS);

            $instanceOfExpression->value = $exprValues;

            return $instanceOfExpression;
        }

        $exprValues[] = $this->InstanceOfParameter();

        $instanceOfExpression->value = $exprValues;

        return $instanceOfExpression;
    }

    /**
     * InstanceOfParameter ::= AbstractSchemaName | InputParameter
     *
     * @return AST\InputParameter|string
     */
    public function InstanceOfParameter(): string|AST\InputParameter
    {
        if ($this->lexer->isNextToken(Lexer::T_INPUT_PARAMETER)) {
            $this->match(Lexer::T_INPUT_PARAMETER);

            return new AST\InputParameter($this->lexer->token[ 'value' ]);
        }

        $abstractSchemaName = $this->AbstractSchemaName();

        $this->validateAbstractSchemaName($abstractSchemaName);

        return $abstractSchemaName;
    }

    /**
     * CollectionMemberExpression ::= EntityExpression ["NOT"] "MEMBER" ["OF"] CollectionValuedPathExpression
     *
     * EntityExpression ::= SingleValuedAssociationPathExpression | SimpleEntityExpression
     * SimpleEntityExpression ::= IdentificationVariable | InputParameter
     *
     * @return AST\CollectionMemberExpression
     */
    public function CollectionMemberExpression(): AST\CollectionMemberExpression
    {
        $not = false;
        $entityExpr = $this->EntityExpression();

        if ($this->lexer->isNextToken(Lexer::T_NOT)) {
            $this->match(Lexer::T_NOT);

            $not = true;
        }

        $this->match(Lexer::T_MEMBER);

        if ($this->lexer->isNextToken(Lexer::T_OF)) {
            $this->match(Lexer::T_OF);
        }

        $collMemberExpr = new AST\CollectionMemberExpression(
            $entityExpr,
            $this->CollectionValuedPathExpression()
        );
        $collMemberExpr->not = $not;

        return $collMemberExpr;
    }

    /**
     * EntityExpression ::= SingleValuedAssociationPathExpression | SimpleEntityExpression
     *
     * @return AST\InputParameter|PathExpression
     */
    public function EntityExpression(): PathExpression|AST\InputParameter
    {
        $glimpse = $this->lexer->glimpse();

        if ($glimpse[ 'value' ] === '.' && $this->lexer->isNextToken(Lexer::T_IDENTIFIER)) {
            return $this->SingleValuedAssociationPathExpression();
        }

        return $this->SimpleEntityExpression();
    }

    /**
     * SingleValuedAssociationPathExpression ::= IdentificationVariable "." SingleValuedAssociationField
     *
     * @return PathExpression
     */
    public function SingleValuedAssociationPathExpression(): PathExpression
    {
        return $this->PathExpression(AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION);
    }

    /**
     * SimpleEntityExpression ::= IdentificationVariable | InputParameter
     *
     * @return AST\InputParameter|AST\PathExpression
     */
    public function SimpleEntityExpression(): PathExpression|AST\InputParameter
    {
        if ($this->lexer->isNextToken(Lexer::T_INPUT_PARAMETER)) {
            return $this->InputParameter();
        }

        return $this->StateFieldPathExpression();
    }

    /**
     * CollectionValuedPathExpression ::= IdentificationVariable "." CollectionValuedAssociationField
     *
     * @return PathExpression
     */
    public function CollectionValuedPathExpression(): PathExpression
    {
        return $this->PathExpression(AST\PathExpression::TYPE_COLLECTION_VALUED_ASSOCIATION);
    }

    /**
     * NullComparisonExpression ::= (InputParameter | NullIfExpression | CoalesceExpression | AggregateExpression | FunctionDeclaration | IdentificationVariable | SingleValuedPathExpression | ResultVariable) "IS" ["NOT"] "NULL"
     *
     * @return AST\NullComparisonExpression
     */
    public function NullComparisonExpression(): AST\NullComparisonExpression
    {
        switch (true) {
            case $this->lexer->isNextToken(Lexer::T_INPUT_PARAMETER):
                $this->match(Lexer::T_INPUT_PARAMETER);

                $expr = new AST\InputParameter($this->lexer->token[ 'value' ]);
                break;

            case $this->lexer->isNextToken(Lexer::T_NULLIF):
                $expr = $this->NullIfExpression();
                break;

            case $this->lexer->isNextToken(Lexer::T_COALESCE):
                $expr = $this->CoalesceExpression();
                break;

            case $this->isFunction():
                $expr = $this->FunctionDeclaration();
                break;

            default:
                // We need to check if we are in a IdentificationVariable or SingleValuedPathExpression
                $glimpse = $this->lexer->glimpse();

                if ($glimpse[ 'type' ] === Lexer::T_DOT) {
                    $expr = $this->SingleValuedPathExpression();

                    // Leave switch statement
                    break;
                }

                $lookaheadValue = $this->lexer->lookahead[ 'value' ];

                // Validate existing component
                if (!isset($this->queryComponents[ $lookaheadValue ])) {
                    $this->semanticalError('Cannot add having condition on undefined result variable.');
                }

                // Validate SingleValuedPathExpression (ie.: "product")
                if (isset($this->queryComponents[ $lookaheadValue ][ 'metadata' ])) {
                    $expr = $this->SingleValuedPathExpression();
                    break;
                }

                // Validating ResultVariable
                if (!isset($this->queryComponents[ $lookaheadValue ][ 'resultVariable' ])) {
                    $this->semanticalError('Cannot add having condition on a non result variable.');
                }

                $expr = $this->ResultVariable();
                break;
        }

        $nullCompExpr = new AST\NullComparisonExpression($expr);

        $this->match(Lexer::T_IS);

        if ($this->lexer->isNextToken(Lexer::T_NOT)) {
            $this->match(Lexer::T_NOT);

            $nullCompExpr->not = true;
        }

        $this->match(Lexer::T_NULL);

        return $nullCompExpr;
    }

    /**
     * EmptyCollectionComparisonExpression ::= CollectionValuedPathExpression "IS" ["NOT"] "EMPTY"
     *
     * @return AST\EmptyCollectionComparisonExpression
     */
    public function EmptyCollectionComparisonExpression(): AST\EmptyCollectionComparisonExpression
    {
        $emptyCollectionCompExpr = new AST\EmptyCollectionComparisonExpression(
            $this->CollectionValuedPathExpression()
        );
        $this->match(Lexer::T_IS);

        if ($this->lexer->isNextToken(Lexer::T_NOT)) {
            $this->match(Lexer::T_NOT);
            $emptyCollectionCompExpr->not = true;
        }

        $this->match(Lexer::T_EMPTY);

        return $emptyCollectionCompExpr;
    }

    /**
     * ComparisonExpression ::= ArithmeticExpression ComparisonOperator ( QuantifiedExpression | ArithmeticExpression )
     *
     * @return AST\ComparisonExpression
     */
    public function ComparisonExpression(): AST\ComparisonExpression
    {
        $this->lexer->glimpse();

        $leftExpr = $this->ArithmeticExpression();
        $operator = $this->ComparisonOperator();
        $rightExpr = $this->isNextAllAnySome()
            ? $this->QuantifiedExpression()
            : $this->ArithmeticExpression();

        return new AST\ComparisonExpression($leftExpr, $operator, $rightExpr);
    }

    /**
     * ComparisonOperator ::= "=" | "<" | "<=" | "<>" | ">" | ">=" | "!="
     *
     * @return string
     */
    public function ComparisonOperator(): string
    {
        switch ($this->lexer->lookahead[ 'value' ]) {
            case '=':
                $this->match(Lexer::T_EQUALS);

                return '=';

            case '<':
                $this->match(Lexer::T_LOWER_THAN);
                $operator = '<';

                if ($this->lexer->isNextToken(Lexer::T_EQUALS)) {
                    $this->match(Lexer::T_EQUALS);
                    $operator .= '=';
                } elseif ($this->lexer->isNextToken(Lexer::T_GREATER_THAN)) {
                    $this->match(Lexer::T_GREATER_THAN);
                    $operator .= '>';
                }

                return $operator;

            case '>':
                $this->match(Lexer::T_GREATER_THAN);
                $operator = '>';

                if ($this->lexer->isNextToken(Lexer::T_EQUALS)) {
                    $this->match(Lexer::T_EQUALS);
                    $operator .= '=';
                }

                return $operator;

            case '!':
                $this->match(Lexer::T_NEGATE);
                $this->match(Lexer::T_EQUALS);

                return '<>';

            default:
                $this->syntaxError('=, <, <=, <>, >, >=, !=');
        }
    }

    /**
     * Checks whether the current lookahead token of the lexer has the type T_ALL, T_ANY or T_SOME.
     */
    private function isNextAllAnySome(): bool
    {
        return in_array($this->lexer->lookahead[ 'type' ], [ Lexer::T_ALL, Lexer::T_ANY, Lexer::T_SOME ], true);
    }

    /**
     * QuantifiedExpression ::= ("ALL" | "ANY" | "SOME") "(" Subselect ")"
     *
     * @return AST\QuantifiedExpression
     */
    public function QuantifiedExpression(): AST\QuantifiedExpression
    {
        $lookaheadType = $this->lexer->lookahead[ 'type' ];
        $value = $this->lexer->lookahead[ 'value' ];

        if (!in_array($lookaheadType, [ Lexer::T_ALL, Lexer::T_ANY, Lexer::T_SOME ], true)) {
            $this->syntaxError('ALL, ANY or SOME');
        }

        $this->match($lookaheadType);
        $this->match(Lexer::T_OPEN_PARENTHESIS);

        $qExpr = new AST\QuantifiedExpression($this->Subselect());
        $qExpr->type = $value;

        $this->match(Lexer::T_CLOSE_PARENTHESIS);

        return $qExpr;
    }

    /**
     * SimpleCaseExpression ::= "CASE" CaseOperand SimpleWhenClause {SimpleWhenClause}* "ELSE" ScalarExpression "END"
     * CaseOperand ::= StateFieldPathExpression | TypeDiscriminator
     *
     * @return AST\SimpleCaseExpression
     */
    public function SimpleCaseExpression(): AST\SimpleCaseExpression
    {
        $this->match(Lexer::T_CASE);
        $caseOperand = $this->StateFieldPathExpression();

        // Process SimpleWhenClause (1..N)
        $simpleWhenClauses = [];

        do {
            $simpleWhenClauses[] = $this->SimpleWhenClause();
        } while ($this->lexer->isNextToken(Lexer::T_WHEN));

        $this->match(Lexer::T_ELSE);
        $scalarExpression = $this->ScalarExpression();
        $this->match(Lexer::T_END);

        return new AST\SimpleCaseExpression($caseOperand, $simpleWhenClauses, $scalarExpression);
    }

    /**
     * SimpleWhenClause ::= "WHEN" ScalarExpression "THEN" ScalarExpression
     *
     * @return AST\SimpleWhenClause
     */
    public function SimpleWhenClause(): AST\SimpleWhenClause
    {
        $this->match(Lexer::T_WHEN);
        $conditionalExpression = $this->ScalarExpression();
        $this->match(Lexer::T_THEN);

        return new AST\SimpleWhenClause($conditionalExpression, $this->ScalarExpression());
    }

    /**
     * PartialObjectExpression ::= "PARTIAL" IdentificationVariable "." PartialFieldSet
     * PartialFieldSet ::= "{" SimpleStateField {"," SimpleStateField}* "}"
     *
     * @return AST\PartialObjectExpression
     */
    public function PartialObjectExpression(): AST\PartialObjectExpression
    {
        Deprecation::trigger(
            'doctrine/orm',
            'https://github.com/doctrine/orm/issues/8471',
            'PARTIAL syntax in DQL is deprecated.'
        );

        $this->match(Lexer::T_PARTIAL);

        $partialFieldSet = [];

        $identificationVariable = $this->IdentificationVariable();

        $this->match(Lexer::T_DOT);
        $this->match(Lexer::T_OPEN_CURLY_BRACE);
        $this->match(Lexer::T_IDENTIFIER);

        $field = $this->lexer->token[ 'value' ];

        // First field in partial expression might be embeddable property
        while ($this->lexer->isNextToken(Lexer::T_DOT)) {
            $this->match(Lexer::T_DOT);
            $this->match(Lexer::T_IDENTIFIER);
            $field .= '.' . $this->lexer->token[ 'value' ];
        }

        $partialFieldSet[] = $field;

        while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
            $this->match(Lexer::T_COMMA);
            $this->match(Lexer::T_IDENTIFIER);

            $field = $this->lexer->token[ 'value' ];

            while ($this->lexer->isNextToken(Lexer::T_DOT)) {
                $this->match(Lexer::T_DOT);
                $this->match(Lexer::T_IDENTIFIER);
                $field .= '.' . $this->lexer->token[ 'value' ];
            }

            $partialFieldSet[] = $field;
        }

        $this->match(Lexer::T_CLOSE_CURLY_BRACE);

        $partialObjectExpression = new AST\PartialObjectExpression($identificationVariable, $partialFieldSet);

        // Defer PartialObjectExpression validation
        $this->deferredPartialObjectExpressions[] = [
            'expression'   => $partialObjectExpression,
            'nestingLevel' => $this->nestingLevel,
            'token'        => $this->lexer->token,
        ];

        return $partialObjectExpression;
    }

    /**
     * NewObjectExpression ::= "NEW" AbstractSchemaName "(" NewObjectArg {"," NewObjectArg}* ")"
     *
     * @return AST\NewObjectExpression
     */
    public function NewObjectExpression(): AST\NewObjectExpression
    {
        $this->match(Lexer::T_NEW);

        $className = $this->AbstractSchemaName(); // note that this is not yet validated
        $token = $this->lexer->token;

        $this->match(Lexer::T_OPEN_PARENTHESIS);

        $args[] = $this->NewObjectArg();

        while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
            $this->match(Lexer::T_COMMA);

            $args[] = $this->NewObjectArg();
        }

        $this->match(Lexer::T_CLOSE_PARENTHESIS);

        $expression = new AST\NewObjectExpression($className, $args);

        // Defer NewObjectExpression validation
        $this->deferredNewObjectExpressions[] = [
            'token'        => $token,
            'expression'   => $expression,
            'nestingLevel' => $this->nestingLevel,
        ];

        return $expression;
    }

    /**
     * NewObjectArg ::= ScalarExpression | "(" Subselect ")"
     *
     * @return AST\AggregateExpression|AST\ArithmeticFactor|AST\ArithmeticTerm|AST\CoalesceExpression|FunctionNode|AST\GeneralCaseExpression|AST\InputParameter|AST\Literal|Node|AST\NullIfExpression|AST\ParenthesisExpression|PathExpression|AST\SimpleArithmeticExpression|AST\SimpleCaseExpression|AST\Subselect|null|string|void
     */
    public function NewObjectArg()
    {
        $token = $this->lexer->lookahead;
        $peek = $this->lexer->glimpse();

        if ($token[ 'type' ] === Lexer::T_OPEN_PARENTHESIS && $peek[ 'type' ] === Lexer::T_SELECT) {
            $this->match(Lexer::T_OPEN_PARENTHESIS);
            $expression = $this->Subselect();
            $this->match(Lexer::T_CLOSE_PARENTHESIS);

            return $expression;
        }

        return $this->ScalarExpression();
    }

    /**
     * FromClause ::= "FROM" IdentificationVariableDeclaration {"," IdentificationVariableDeclaration}*
     *
     * @return AST\FromClause
     */
    public function FromClause(): AST\FromClause
    {
        $this->match(Lexer::T_FROM);

        $identificationVariableDeclarations = [];
        $identificationVariableDeclarations[] = $this->IdentificationVariableDeclaration();

        while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
            $this->match(Lexer::T_COMMA);

            $identificationVariableDeclarations[] = $this->IdentificationVariableDeclaration();
        }

        return new AST\FromClause($identificationVariableDeclarations);
    }

    /**
     * UpdateStatement ::= UpdateClause [WhereClause]
     *
     * @return AST\UpdateStatement
     */
    public function UpdateStatement(): AST\UpdateStatement
    {
        $updateStatement = new AST\UpdateStatement($this->UpdateClause());

        $updateStatement->whereClause = $this->lexer->isNextToken(Lexer::T_WHERE) ? $this->WhereClause() : null;

        return $updateStatement;
    }

    /**
     * UpdateClause ::= "UPDATE" AbstractSchemaName ["AS"] AliasIdentificationVariable "SET" UpdateItem {"," UpdateItem}*
     *
     * @return AST\UpdateClause
     */
    public function UpdateClause(): AST\UpdateClause
    {
        $this->match(Lexer::T_UPDATE);

        $token = $this->lexer->lookahead;
        $abstractSchemaName = $this->AbstractSchemaName();

        $this->validateAbstractSchemaName($abstractSchemaName);

        if ($this->lexer->isNextToken(Lexer::T_AS)) {
            $this->match(Lexer::T_AS);
        }

        $aliasIdentificationVariable = $this->AliasIdentificationVariable();

        $class = $this->em->getClassMetadata($abstractSchemaName);

        // Building queryComponent
        $queryComponent = [
            'metadata'     => $class,
            'parent'       => null,
            'relation'     => null,
            'map'          => null,
            'nestingLevel' => $this->nestingLevel,
            'token'        => $token,
        ];

        $this->queryComponents[ $aliasIdentificationVariable ] = $queryComponent;

        $this->match(Lexer::T_SET);

        $updateItems = [];
        $updateItems[] = $this->UpdateItem();

        while ($this->lexer->isNextToken(Lexer::T_COMMA)) {
            $this->match(Lexer::T_COMMA);

            $updateItems[] = $this->UpdateItem();
        }

        $updateClause = new AST\UpdateClause($abstractSchemaName, $updateItems);
        $updateClause->aliasIdentificationVariable = $aliasIdentificationVariable;

        return $updateClause;
    }

    /**
     * UpdateItem ::= SingleValuedPathExpression "=" NewValue
     *
     * @return AST\UpdateItem
     */
    public function UpdateItem(): AST\UpdateItem
    {
        $pathExpr = $this->SingleValuedPathExpression();

        $this->match(Lexer::T_EQUALS);

        return new AST\UpdateItem($pathExpr, $this->NewValue());
    }

    /**
     * NewValue ::= SimpleArithmeticExpression | StringPrimary | DatetimePrimary | BooleanPrimary |
     *      EnumPrimary | SimpleEntityExpression | "NULL"
     *
     * NOTE: Since it is not possible to correctly recognize individual types, here is the full
     * grammar that needs to be supported:
     *
     * NewValue ::= SimpleArithmeticExpression | "NULL"
     *
     * SimpleArithmeticExpression covers all *Primary grammar rules and also SimpleEntityExpression
     *
     * @return AST\ArithmeticExpression|AST\InputParameter|null
     */
    public function NewValue(): AST\ArithmeticExpression|AST\InputParameter|null
    {
        if ($this->lexer->isNextToken(Lexer::T_NULL)) {
            $this->match(Lexer::T_NULL);

            return null;
        }

        if ($this->lexer->isNextToken(Lexer::T_INPUT_PARAMETER)) {
            $this->match(Lexer::T_INPUT_PARAMETER);

            return new AST\InputParameter($this->lexer->token[ 'value' ]);
        }

        return $this->ArithmeticExpression();
    }

    /**
     * DeleteStatement ::= DeleteClause [WhereClause]
     *
     * @return AST\DeleteStatement
     */
    public function DeleteStatement(): AST\DeleteStatement
    {
        $deleteStatement = new AST\DeleteStatement($this->DeleteClause());

        $deleteStatement->whereClause = $this->lexer->isNextToken(Lexer::T_WHERE) ? $this->WhereClause() : null;

        return $deleteStatement;
    }

    /**
     * DeleteClause ::= "DELETE" ["FROM"] AbstractSchemaName ["AS"] AliasIdentificationVariable
     *
     * @return AST\DeleteClause
     */
    public function DeleteClause(): AST\DeleteClause
    {
        $this->match(Lexer::T_DELETE);

        if ($this->lexer->isNextToken(Lexer::T_FROM)) {
            $this->match(Lexer::T_FROM);
        }

        $token = $this->lexer->lookahead;
        $abstractSchemaName = $this->AbstractSchemaName();

        $this->validateAbstractSchemaName($abstractSchemaName);

        $deleteClause = new AST\DeleteClause($abstractSchemaName);

        if ($this->lexer->isNextToken(Lexer::T_AS)) {
            $this->match(Lexer::T_AS);
        }

        $aliasIdentificationVariable = $this->lexer->isNextToken(Lexer::T_IDENTIFIER)
            ? $this->AliasIdentificationVariable()
            : 'alias_should_have_been_set';

        $deleteClause->aliasIdentificationVariable = $aliasIdentificationVariable;
        $class = $this->em->getClassMetadata($deleteClause->abstractSchemaName);

        // Building queryComponent
        $queryComponent = [
            'metadata'     => $class,
            'parent'       => null,
            'relation'     => null,
            'map'          => null,
            'nestingLevel' => $this->nestingLevel,
            'token'        => $token,
        ];

        $this->queryComponents[ $aliasIdentificationVariable ] = $queryComponent;

        return $deleteClause;
    }

    /**
     * Validates that the given <tt>IdentificationVariable</tt> is semantically correct.
     * It must exist in query components list.
     */
    private function processDeferredIdentificationVariables(): void
    {
        foreach ($this->deferredIdentificationVariables as $deferredItem) {
            $identVariable = $deferredItem[ 'expression' ];

            // Check if IdentificationVariable exists in queryComponents
            if (!isset($this->queryComponents[ $identVariable ])) {
                $this->semanticalError(
                    sprintf("'%s' is not defined.", $identVariable),
                    $deferredItem[ 'token' ]
                );
            }

            $qComp = $this->queryComponents[ $identVariable ];

            // Check if queryComponent points to an AbstractSchemaName or a ResultVariable
            if (!isset($qComp[ 'metadata' ])) {
                $this->semanticalError(
                    sprintf("'%s' does not point to a Class.", $identVariable),
                    $deferredItem[ 'token' ]
                );
            }

            // Validate if identification variable nesting level is lower or equal than the current one
            if ($qComp[ 'nestingLevel' ] > $deferredItem[ 'nestingLevel' ]) {
                $this->semanticalError(
                    sprintf("'%s' is used outside the scope of its declaration.", $identVariable),
                    $deferredItem[ 'token' ]
                );
            }
        }
    }

    /**
     * Validates that the given <tt>PartialObjectExpression</tt> is semantically correct.
     * It must exist in query components list.
     */
    private function processDeferredPartialObjectExpressions(): void
    {
        foreach ($this->deferredPartialObjectExpressions as $deferredItem) {
            $expr = $deferredItem[ 'expression' ];
            $class = $this->queryComponents[ $expr->identificationVariable ][ 'metadata' ];

            foreach ($expr->partialFieldSet as $field) {
                if (isset($class->fieldMappings[ $field ])) {
                    continue;
                }

                if (isset($class->associationMappings[ $field ]) &&
                    $class->associationMappings[ $field ][ 'isOwningSide' ] &&
                    $class->associationMappings[ $field ][ 'type' ] & ClassMetadataInfo::TO_ONE
                ) {
                    continue;
                }

                $this->semanticalError(sprintf(
                    "There is no mapped field named '%s' on class %s.",
                    $field,
                    $class->name
                ), $deferredItem[ 'token' ]);
            }

            if (array_intersect($class->identifier, $expr->partialFieldSet) !== $class->identifier) {
                $this->semanticalError(
                    'The partial field selection of class ' . $class->name . ' must contain the identifier.',
                    $deferredItem[ 'token' ]
                );
            }
        }
    }

    /**
     * Validates that the given <tt>PathExpression</tt> is semantically correct for grammar rules:
     *
     * AssociationPathExpression             ::= CollectionValuedPathExpression | SingleValuedAssociationPathExpression
     * SingleValuedPathExpression            ::= StateFieldPathExpression | SingleValuedAssociationPathExpression
     * StateFieldPathExpression              ::= IdentificationVariable "." StateField
     * SingleValuedAssociationPathExpression ::= IdentificationVariable "." SingleValuedAssociationField
     * CollectionValuedPathExpression        ::= IdentificationVariable "." CollectionValuedAssociationField
     */
    private function processDeferredPathExpressions(): void
    {
        foreach ($this->deferredPathExpressions as $deferredItem) {
            $pathExpression = $deferredItem[ 'expression' ];

            $qComp = $this->queryComponents[ $pathExpression->identificationVariable ];
            $class = $qComp[ 'metadata' ];

            $field = $pathExpression->field;
            if ($field === null) {
                $field = ( $pathExpression->field = $class->identifier[ 0 ] );
            }


            // Check if field or association exists
            if (!isset($class->associationMappings[ $field ]) && !isset($class->fieldMappings[ $field ])) {
                $this->semanticalError(
                    'Class ' . $class->name . ' has no field or association named ' . $field,
                    $deferredItem[ 'token' ]
                );
            }

            $fieldType = AST\PathExpression::TYPE_STATE_FIELD;

            if (isset($class->associationMappings[ $field ])) {
                $assoc = $class->associationMappings[ $field ];

                $fieldType = $assoc[ 'type' ] & ClassMetadataInfo::TO_ONE
                    ? AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION
                    : AST\PathExpression::TYPE_COLLECTION_VALUED_ASSOCIATION;
            }

            // Validate if PathExpression is one of the expected types
            $expectedType = $pathExpression->expectedType;

            if (!( $expectedType & $fieldType )) {
                // We need to recognize which was expected type(s)
                $expectedStringTypes = [];

                // Validate state field type
                if ($expectedType & AST\PathExpression::TYPE_STATE_FIELD) {
                    $expectedStringTypes[] = 'StateFieldPathExpression';
                }

                // Validate single valued association (*-to-one)
                if ($expectedType & AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION) {
                    $expectedStringTypes[] = 'SingleValuedAssociationField';
                }

                // Validate single valued association (*-to-many)
                if ($expectedType & AST\PathExpression::TYPE_COLLECTION_VALUED_ASSOCIATION) {
                    $expectedStringTypes[] = 'CollectionValuedAssociationField';
                }

                // Build the error message
                $semanticalError = 'Invalid PathExpression. ';
                $semanticalError .= count($expectedStringTypes) === 1
                    ? 'Must be a ' . $expectedStringTypes[ 0 ] . '.'
                    : implode(' or ', $expectedStringTypes) . ' expected.';

                $this->semanticalError($semanticalError, $deferredItem[ 'token' ]);
            }

            // We need to force the type in PathExpression
            $pathExpression->type = $fieldType;
        }
    }

    /**
     * Validates that the given <tt>ResultVariable</tt> is semantically correct.
     * It must exist in query components list.
     */
    private function processDeferredResultVariables(): void
    {
        foreach ($this->deferredResultVariables as $deferredItem) {
            $resultVariable = $deferredItem[ 'expression' ];

            // Check if ResultVariable exists in queryComponents
            if (!isset($this->queryComponents[ $resultVariable ])) {
                $this->semanticalError(
                    sprintf("'%s' is not defined.", $resultVariable),
                    $deferredItem[ 'token' ]
                );
            }

            $qComp = $this->queryComponents[ $resultVariable ];

            // Check if queryComponent points to an AbstractSchemaName or a ResultVariable
            if (!isset($qComp[ 'resultVariable' ])) {
                $this->semanticalError(
                    sprintf("'%s' does not point to a ResultVariable.", $resultVariable),
                    $deferredItem[ 'token' ]
                );
            }

            // Validate if identification variable nesting level is lower or equal than the current one
            if ($qComp[ 'nestingLevel' ] > $deferredItem[ 'nestingLevel' ]) {
                $this->semanticalError(
                    sprintf("'%s' is used outside the scope of its declaration.", $resultVariable),
                    $deferredItem[ 'token' ]
                );
            }
        }
    }

    /**
     * Validates that the given <tt>NewObjectExpression</tt>.
     */
    private function processDeferredNewObjectExpressions(AST\SelectStatement $AST): void
    {
        foreach ($this->deferredNewObjectExpressions as $deferredItem) {
            $expression = $deferredItem[ 'expression' ];
            $token = $deferredItem[ 'token' ];
            $className = $expression->className;
            $args = $expression->args;
            $fromClassName = $AST->fromClause->identificationVariableDeclarations[ 0 ]->rangeVariableDeclaration->abstractSchemaName ?? null;

            // If the namespace is not given then assumes the first FROM entity namespace
            if (!str_contains($className, '\\') && !class_exists($className) && str_contains($fromClassName, '\\')) {
                /** @noinspection PhpStrictTypeCheckingInspection */
                $namespace = substr($fromClassName, 0, strrpos($fromClassName, '\\'));
                $fqcn = $namespace . '\\' . $className;

                if (class_exists($fqcn)) {
                    $expression->className = $fqcn;
                    $className = $fqcn;
                }
            }

            if (!class_exists($className)) {
                $this->semanticalError(sprintf('Class "%s" is not defined.', $className), $token);
            }

            $class = new ReflectionClass($className);

            if (!$class->isInstantiable()) {
                $this->semanticalError(sprintf('Class "%s" can not be instantiated.', $className), $token);
            }

            if ($class->getConstructor() === null) {
                $this->semanticalError(sprintf('Class "%s" has not a valid constructor.', $className), $token);
            }

            if ($class->getConstructor()->getNumberOfRequiredParameters() > count($args)) {
                $this->semanticalError(sprintf('Number of arguments does not match with "%s" constructor declaration.', $className), $token);
            }
        }
    }

    private function processRootEntityAliasSelected(): void
    {
        if (!count($this->identVariableExpressions)) {
            return;
        }

        foreach ($this->identVariableExpressions as $dqlAlias => $expr) {
            if (isset($this->queryComponents[ $dqlAlias ]) && $this->queryComponents[ $dqlAlias ][ 'parent' ] === null) {
                return;
            }
        }

        $this->semanticalError('Cannot select entity through identification variables without choosing at least one root entity alias.');
    }

    /**
     * Fixes order of identification variables.
     *
     * They have to appear in the select clause in the same order as the
     * declarations (from ... x join ... y join ... z ...) appear in the query
     * as the hydration process relies on that order for proper operation.
     *
     * @param Node $AST
     */
    private function fixIdentificationVariableOrder(Node $AST): void
    {
        if (count($this->identVariableExpressions) <= 1) {
            return;
        }

        assert($AST instanceof AST\SelectStatement);

        foreach ($this->queryComponents as $dqlAlias => $qComp) {
            if (!isset($this->identVariableExpressions[ $dqlAlias ])) {
                continue;
            }

            $expr = $this->identVariableExpressions[ $dqlAlias ];
            $key = array_search($expr, $AST->selectClause->selectExpressions, true);

            unset($AST->selectClause->selectExpressions[ $key ]);

            $AST->selectClause->selectExpressions[] = $expr;
        }
    }

    /**
     * AssociationPathExpression ::= CollectionValuedPathExpression | SingleValuedAssociationPathExpression
     *
     * @return PathExpression
     */
    public function AssociationPathExpression(): PathExpression
    {
        return $this->PathExpression(
            AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION |
            AST\PathExpression::TYPE_COLLECTION_VALUED_ASSOCIATION
        );
    }
}
