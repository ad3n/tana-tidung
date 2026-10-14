<?php

declare(strict_types=1);

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use Sibaper\CodingStandard\BlankLineAfterControlBlockFixer;

require_once dirname(__DIR__, 2).'/tools/php-cs-fixer/BlankLineAfterControlBlockFixer.php';

final class BlankLineAfterControlBlockFixerTest extends TestCase
{
    public function testControlBlocksAndLinkedClauses(): void
    {
        $blocks = [
            'if ($x) { one(); } elseif ($y) { two(); } else { three(); }',
            'for ($i = 0; $i < 2; ++$i) { one(); }',
            'foreach ($items as $item) { one(); }',
            'while ($x) { one(); }',
            'do { one(); } while ($x);',
            'switch ($x) { case 1: one(); break; default: two(); }',
            'try { one(); } catch (Exception $e) { two(); } finally { three(); }',
        ];
        foreach ($blocks as $block) {
            $source = "<?php\n".$block."\nnextStatement();\n";
            $expected = "<?php\n".$block."\n\nnextStatement();\n";
            self::assertSame($expected, $this->format($source));
            self::assertSame($expected, $this->format($expected));
        }
    }

    public function testNestedBlocksClosingBracesAndExpressions(): void
    {
        $source = '<?php
function example($x) {
    if ($x) {
        while ($x) { tick(); }
        nextStatement();
    }
}
$closure = function () { return "} if (fake) {"; };
$next = match (true) { true => 1 };
finish();
';
        $expected = str_replace("while (\$x) { tick(); }\n", "while (\$x) { tick(); }\n\n", $source);
        self::assertSame($expected, $this->format($source));
    }

    public function testCommentsAndExcessBlankLines(): void
    {
        $source = "<?php\nif (true) { run(); }\n/** @author Example */\nfinish();\n";
        self::assertSame(str_replace("}\n", "}\n\n", $source), $this->format($source));
        self::assertSame("<?php\nif (true) {}\n\nfinish();\n", $this->format("<?php\nif (true) {}\n\n\nfinish();\n"));
    }

    public function testOnlyAppIsSupported(): void
    {
        $fixer = new BlankLineAfterControlBlockFixer();
        self::assertTrue($fixer->supports(new SplFileInfo(dirname(__DIR__, 2).'/app/Example.php')));
        self::assertFalse($fixer->supports(new SplFileInfo(dirname(__DIR__, 2).'/lib/Example.php')));
    }

    private function format(string $source): string
    {
        $tokens = Tokens::fromCode($source);
        (new BlankLineAfterControlBlockFixer())->fix(new SplFileInfo(dirname(__DIR__, 2).'/app/Example.php'), $tokens);

        return $tokens->generateCode();
    }
}
