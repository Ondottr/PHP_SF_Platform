<?php declare(strict_types=1);

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;

/**
 * Enforces blank-line spacing between class/trait/interface/enum members:
 *   - 2 blank lines between members from different sections
 *   - 1 blank line between members within the same section
 *
 * Sections (matching ordered_class_elements in .php-cs-fixer.dist.php):
 *   0  use_trait / enum case
 *   1  constants      (public → protected → private)
 *   2  static props   (public → protected → private)
 *   3  instance props (public → protected → private)
 *   4  construct + destruct
 *   5  magic methods  (__toString etc.)
 *   6  abstract methods
 *   7  public methods  (+ public static)
 *   8  protected methods (+ protected static)
 *   9  private methods  (+ private static)
 */
final class ClassSectionSeparationFixer extends AbstractFixer
{

    private const array SECTION_MAP = [
        'use_trait'                 => 0,
        'constant_public'           => 1,
        'constant_protected'        => 1,
        'constant_private'          => 1,
        'property_static_public'    => 2,
        'property_static_protected' => 2,
        'property_static_private'   => 2,
        'property_public'           => 3,
        'property_protected'        => 3,
        'property_private'          => 3,
        'construct'                 => 4,
        'destruct'                  => 4,
        'magic'                     => 5,
        'method_abstract'           => 6,
        'method_public'             => 7,
        'method_public_static'      => 7,
        'method_protected'          => 8,
        'method_protected_static'   => 8,
        'method_private'            => 9,
        'method_private_static'     => 9,
    ];


    public function getName(): string
    {
        return 'App/class_section_separation';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            '2 blank lines between class sections, 1 blank line within a section.',
            [new CodeSample("<?php\nclass Foo\n{\n    public const A = 1;\n    private string \$x;\n}\n")],
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound(Token::getClassyTokenKinds());
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $analyzer    = new TokensAnalyzer($tokens);
        $allElements = $analyzer->getClassyElements();

        for ($i = 0, $count = count($tokens); $i < $count; ++$i) {
            if (!$tokens[$i]->isClassy()) {
                continue;
            }

            $classIdx  = $i;
            $openBrace = $tokens->getNextTokenOfKind($i, ['{']);

            if (null === $openBrace) {
                continue;
            }

            $closeBrace = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $openBrace);

            $elements = array_filter(
                $allElements,
                static fn(array $el): bool => ($el['classIndex'] ?? -1) === $classIdx,
            );

            if (empty($elements)) {
                $i = $closeBrace;

                continue;
            }

            $keys = array_keys($elements);

            for ($j = 1, $total = count($keys); $j < $total; ++$j) {
                $prev   = $keys[$j - 1];
                $curr   = $keys[$j];
                $blanks = $this->sectionOf($elements[$prev], $tokens, $prev) === $this->sectionOf($elements[$curr], $tokens, $curr) ? 1 : 2;

                $this->setGapBefore($tokens, $curr, $blanks);
            }

            $i = $closeBrace;
        }
    }


    private function sectionOf(array $element, Tokens $tokens, int $idx): int
    {
        $type = $element['type'];

        if ('use_trait' === $type || 'case' === $type) {
            return 0;
        }

        $vis    = $element['visibility'] ?? 'public';
        $static = $element['static'] ?? false;

        if ('const' === $type) {
            return self::SECTION_MAP["constant_{$vis}"] ?? 1;
        }

        if ('property' === $type) {
            $key = ($static ? 'property_static_' : 'property_') . $vis;

            return self::SECTION_MAP[$key] ?? 3;
        }

        if ('method' === $type) {
            $name = $this->methodName($tokens, $idx);

            return match (true) {
                '__construct' === $name              => self::SECTION_MAP['construct'],
                '__destruct' === $name               => self::SECTION_MAP['destruct'],
                str_starts_with($name, '__')         => self::SECTION_MAP['magic'],
                ($element['abstract'] ?? false)      => self::SECTION_MAP['method_abstract'],
                $static                              => self::SECTION_MAP["method_{$vis}_static"] ?? 7,
                default                              => self::SECTION_MAP["method_{$vis}"] ?? 7,
            };
        }

        return 99;
    }

    private function methodName(Tokens $tokens, int $fromIdx): string
    {
        // Handle the case where fromIdx already points at `function`
        $funcIdx = $tokens[$fromIdx]->isGivenKind(T_FUNCTION)
            ? $fromIdx
            : $tokens->getNextTokenOfKind($fromIdx, [[T_FUNCTION]]);

        if (null === $funcIdx) {
            return '';
        }

        $nameIdx = $tokens->getNextTokenOfKind($funcIdx, [[T_STRING]]);

        if (null === $nameIdx) {
            return '';
        }

        return $tokens[$nameIdx]->getContent();
    }

    /**
     * Adjusts the whitespace gap before $elementIdx so it contains exactly $blanks blank lines.
     * Walks backward past any docblock, inline comments, and PHP attributes to find the gap.
     */
    private function setGapBefore(Tokens $tokens, int $elementIdx, int $blanks): void
    {
        $blockStart = $this->blockStart($tokens, $elementIdx);
        $gapIdx     = $blockStart - 1;

        if ($gapIdx < 1 || !$tokens[$gapIdx]->isWhitespace()) {
            return;
        }

        $current = $tokens[$gapIdx]->getContent();

        // Only modify line-separating whitespace (must cross a line boundary)
        if (!str_contains($current, "\n")) {
            return;
        }

        // Preserve the indentation on the last line of the gap
        $indent = '';
        if (preg_match('/\n([ \t]*)$/', $current, $m)) {
            $indent = $m[1];
        }

        // N blank lines = N+1 newlines (first \n ends the previous line, rest are blank lines)
        $desired = str_repeat("\n", $blanks + 1) . $indent;

        if ($current !== $desired) {
            $tokens[$gapIdx] = new Token([T_WHITESPACE, $desired]);
        }
    }

    /**
     * Returns the index of the first token in the element's "block"
     * (walks backward past docblocks, comments, PHP attributes, visibility/modifier
     * keywords, and type-hint tokens to reach the true start of the declaration).
     *
     * Stops at `;`, `{`, or `}` — terminators of the previous element.
     */
    private function blockStart(Tokens $tokens, int $elementIdx): int
    {
        $start = $elementIdx;

        for ($i = $elementIdx - 1; $i > 0; --$i) {
            $tok = $tokens[$i];

            // Previous-element terminators or class opening brace — stop here
            if ($tok->equalsAny([';', '{', '}'])) {
                break;
            }

            if ($tok->isWhitespace()) {
                continue;
            }

            if ($tok->isGivenKind([T_DOC_COMMENT, T_COMMENT])) {
                $start = $i;
                continue;
            }

            if ($tok->isGivenKind(CT::T_ATTRIBUTE_CLOSE)) {
                $open  = $tokens->findBlockStart(Tokens::BLOCK_TYPE_ATTRIBUTE, $i);
                $start = $open;
                $i     = $open;
                continue;
            }

            // getClassyElements() returns the T_FUNCTION / T_CONST / T_VARIABLE index,
            // not the first visibility modifier. Walk past all modifier keywords so the
            // gap ends up before the entire declaration (e.g. before `public`, not
            // between `public` and `function`).
            if ($tok->isGivenKind([T_PUBLIC, T_PROTECTED, T_PRIVATE, T_ABSTRACT, T_FINAL, T_STATIC, T_READONLY])) {
                $start = $i;
                continue;
            }

            // Type-hint tokens that may sit between modifiers and the main keyword
            // (e.g. `string`, `?Foo`, class names). Skip silently without updating $start.
            if ($tok->isGivenKind([T_STRING, T_ARRAY, T_CALLABLE, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                continue;
            }

            // Nullable `?`, union `|`, intersection `&` type operators
            if ($tok->equalsAny(['?', '|', '&'])) {
                continue;
            }

            break;
        }

        return $start;
    }

}
