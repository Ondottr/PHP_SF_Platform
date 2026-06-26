<?php declare(strict_types=1);

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Ensures every PHP file has declare(strict_types=1) and normalizes its position:
 *   - No file-level comments → one-liner:  <?php declare(strict_types=1);
 *   - File-level comments    → two-liner:  <?php /** … * /\ndeclare(strict_types=1);
 *
 * Also inserts declare(strict_types=1) when the file is missing it entirely.
 * Runs at priority -100 so it fires after declare_strict_types and
 * blank_line_after_opening_tag have already done their work.
 */
final class DeclareStrictTypesOneLineFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'App/declare_strict_types_one_line';
    }

    public function getPriority(): int
    {
        return -100;
    }

    public function isRisky(): bool
    {
        return true;
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Ensures declare(strict_types=1) exists and keeps it on one line with <?php (two lines when file-level comments are present).',
            [
                new CodeSample("<?php\n\ndeclare(strict_types=1);\n"),
                new CodeSample("<?php\ndeclare(strict_types=1);\n"),
                new CodeSample("<?php /** @noinspection Foo */\n\ndeclare(strict_types=1);\n"),
                new CodeSample("<?php\n\nnamespace App;\n"),
            ],
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_OPEN_TAG);
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $openTagIndex = $this->findOpenTagIndex($tokens);
        if (null === $openTagIndex) {
            return;
        }

        if (!$this->hasDeclareStrictTypes($tokens)) {
            $this->insertDeclareStrictTypes($tokens, $openTagIndex);
        }

        // Re-scan after potential insertion so indices are fresh
        [$commentIndices, $whitespaceIndices, $declareIndex] = $this->scanToDeclaration($tokens, $openTagIndex);
        if (null === $declareIndex || !$this->isDeclareStrictTypes($tokens, $declareIndex)) {
            return;
        }

        foreach ($whitespaceIndices as $idx) {
            $tokens->clearAt($idx);
        }

        $tokens[$openTagIndex] = new Token([T_OPEN_TAG, '<?php ']);

        if (empty($commentIndices)) {
            return;
        }

        // Two-liner: <?php {comments}\ndeclare(...)
        $tokens->insertAt($declareIndex, new Token([T_WHITESPACE, "\n"]));

        for ($i = count($commentIndices) - 1; $i > 0; --$i) {
            $tokens->insertAt($commentIndices[$i], new Token([T_WHITESPACE, ' ']));
        }
    }

    /**
     * Inserts declare(strict_types=1);\n just before the first non-whitespace,
     * non-comment token after <?php, so any existing file-level comments stay
     * between <?php and declare for the formatting phase to normalize.
     */
    private function insertDeclareStrictTypes(Tokens $tokens, int $openTagIndex): void
    {
        $insertAt = $openTagIndex + 1;
        $total = count($tokens);

        for ($i = $openTagIndex + 1; $i < $total; ++$i) {
            $token = $tokens[$i];
            if ($token->isWhitespace() || $token->isGivenKind([T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }

            $insertAt = $i;
            break;
        }

        $tokens->insertAt($insertAt, [
            new Token([T_DECLARE, 'declare']),
            new Token('('),
            new Token([T_STRING, 'strict_types']),
            new Token('='),
            new Token([T_LNUMBER, '1']),
            new Token(')'),
            new Token(';'),
            new Token([T_WHITESPACE, "\n"]),
        ]);
    }

    private function hasDeclareStrictTypes(Tokens $tokens): bool
    {
        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind(T_DECLARE) && $this->isDeclareStrictTypes($tokens, $index)) {
                return true;
            }
        }

        return false;
    }

    private function findOpenTagIndex(Tokens $tokens): ?int
    {
        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind(T_OPEN_TAG)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Scans from $start+1 until a non-whitespace/non-comment token is found.
     * Returns [commentIndices[], whitespaceIndices[], declareIndex|null].
     *
     * @return array{0: int[], 1: int[], 2: int|null}
     */
    private function scanToDeclaration(Tokens $tokens, int $start): array
    {
        $commentIndices = [];
        $whitespaceIndices = [];
        $declareIndex = null;

        for ($i = $start + 1, $total = count($tokens); $i < $total; ++$i) {
            $token = $tokens[$i];

            if ($token->isWhitespace()) {
                $whitespaceIndices[] = $i;
                continue;
            }

            if ($token->isGivenKind([T_COMMENT, T_DOC_COMMENT])) {
                $commentIndices[] = $i;
                continue;
            }

            if ($token->isGivenKind(T_DECLARE)) {
                $declareIndex = $i;
            }

            break;
        }

        return [$commentIndices, $whitespaceIndices, $declareIndex];
    }

    private function isDeclareStrictTypes(Tokens $tokens, int $declareIndex): bool
    {
        $total = count($tokens);

        $i = $declareIndex + 1;
        while ($i < $total && $tokens[$i]->isWhitespace()) {
            ++$i;
        }
        if ($i >= $total || '(' !== $tokens[$i]->getContent()) {
            return false;
        }
        ++$i;

        while ($i < $total && $tokens[$i]->isWhitespace()) {
            ++$i;
        }

        return $i < $total && 'strict_types' === $tokens[$i]->getContent();
    }
}
