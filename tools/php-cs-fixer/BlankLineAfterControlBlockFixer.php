<?php

declare(strict_types=1);

namespace Sibaper\CodingStandard;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use SplFileInfo;

final class BlankLineAfterControlBlockFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'Sibaper/blank_line_after_control_block';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition('Separate completed control blocks from the next statement with one blank line.', [new CodeSample("<?php\nif (true) {\n    run();\n}\nfinish();\n")]);
    }

    public function getPriority(): int
    {
        return -100;
    }

    public function supports(SplFileInfo $file): bool
    {
        return str_starts_with($file->getRealPath() ?: $file->getPathname(), dirname(__DIR__, 2).'/app/');
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([T_IF, T_FOR, T_FOREACH, T_WHILE, T_DO, T_SWITCH, T_TRY]);
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $source = $tokens->generateCode();
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        $edits = [];
        $this->collectEdits($nodes ?? [], $source, $edits);
        krsort($edits);
        foreach ($edits as $start => [$length, $replacement]) {
            $source = substr_replace($source, $replacement, $start, $length);
        }

        if ($edits) {
            $tokens->setCode($source);
        }
    }

    private function collectEdits(array $nodes, string $source, array &$edits): void
    {
        $nodes = array_values($nodes);
        foreach ($nodes as $index => $node) {
            if (!$node instanceof Node) {
                continue;
            }

            $next = $nodes[$index + 1] ?? null;
            if ($next instanceof Stmt && $this->isControlBlock($node)) {
                $start = $node->getEndFilePos() + 1;
                $end = $next->getStartFilePos();
                foreach ($next->getComments() as $comment) {
                    $end = min($end, $comment->getStartFilePos());
                }

                $gap = substr($source, $start, $end - $start);
                if (preg_match('/^\s*$/D', $gap)) {
                    preg_match('/[^\r\n]*$/', $gap, $indent);
                    $newline = str_contains($gap, "\r\n") ? "\r\n" : "\n";
                    $replacement = $newline.$newline.$indent[0];
                    if ($gap !== $replacement) {
                        $edits[$start] = [$end - $start, $replacement];
                    }
                }
            }

            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->$name;
                $this->collectEdits(is_array($child) ? $child : [$child], $source, $edits);
            }
        }
    }

    private function isControlBlock(Node $node): bool
    {
        return $node instanceof Stmt\If_
            || $node instanceof Stmt\For_
            || $node instanceof Stmt\Foreach_
            || $node instanceof Stmt\While_
            || $node instanceof Stmt\Do_
            || $node instanceof Stmt\Switch_
            || $node instanceof Stmt\TryCatch;
    }
}
